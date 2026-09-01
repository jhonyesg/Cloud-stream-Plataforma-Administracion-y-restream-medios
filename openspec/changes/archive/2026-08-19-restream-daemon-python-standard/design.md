## Context

El restream actual está implementado en PHP con dos piezas: `RestreamLauncher` (spawn de ffmpeg vía Symfony Process) y `RestreamSupervisor` (loop de reconciliación vía `restream:supervise`). En producción el supervisor no corre, el comando ffmpeg generado es inválido (`-rtmp_live live=1` rompe en ffmpeg 4.4 con `Invalid argument`), la UI muestra `status='active'` con un PID que ya no existe, y el stderr del proceso queda en `storage/logs/restream/{id}.log` sin que nadie lo lea.

En paralelo, el emisor de programación tiene un estándar probado en producción: `emisor_python/` — un daemon Python por canal con supervisor loop (restart con backoff, cooldown), heartbeat a Laravel cada 5s, línea `[STATS]` cada 5s, watchdog de pipeline atascado, log rotado y unit systemd por instancia. El objetivo es que **todo lo que emite** use ese mismo estándar, y eliminar la implementación PHP a medio hacer para evitar confusión.

## Goals / Non-Goals

**Goals:**
- Un solo estándar de emisión: daemon Python por proceso (emisor y restream), compartiendo `utils/` y `pipeline/` de `emisor_python/`.
- El restream daemon se auto-supervisa (restart con backoff, cooldown, watchdog) y reporta estado real vía heartbeat — sin supervisor PHP.
- La UI de restream (admin y cliente) adopta el patrón del scheduler: polling de estado cada 10s + panel de log por target cada 5s.
- Los N cupos habilitados operan aislados: cada target = su propio daemon + su propio ffmpeg hijo + su propio log.
- Corregir el comando ffmpeg (`-rtmp_live live` en vez de `live=1`) en el lugar donde se construye.
- Eliminar el código PHP de spawn/supervisión (`RestreamLauncher`, `RestreamSupervisor`, comandos) para que no existan dos arquitecturas.

**Non-Goals:**
- No se toca el emisor de programación (solo se refactoriza para compartir módulos sin cambiar su comportamiento).
- No se implementa billing/pagos de cupos (fuera de alcance del módulo restream).
- No se añaden métricas de bitrate/fps reales del flujo RTMP saliente (los `[STATS]` del restream son aproximados por tiempo, como en el emisor).
- No se migra el emisor a systemd por defecto (ya existe el unit; se mantiene).

## Decisions

### D1: Daemon Python por target, no un daemon multi-target

Cada target corre `restream_daemon/main.py --target-id={id}` (un proceso Python + un ffmpeg hijo). Alternativa considerada: un solo daemon que gestione N targets en hilos — descartada porque rompe el aislamiento (un crash afectaría a todos) y se aleja del estándar del emisor (1 daemon por canal). El costo es más procesos, pero es el modelo ya probado en producción.

### D2: El daemon construye el comando ffmpeg; Laravel solo escribe config JSON

`RestreamOrchestrator::buildConfig()` resuelve `source_url` (o fallback `public_hls_url`), descifra el `stream_key` y escribe `storage/app/restream-daemons/{target_id}-config.json`. El daemon lee el config y arma los args con `-rtmp_live live` (corregido). Alternativa: mantener `buildCommand()` en PHP — descartada porque el estándar es que el proceso Python sea dueño de su pipeline (como `ffmpeg_pipeline.py` en el emisor) y porque el bug del flag se corrige en un solo lugar.

### D3: Heartbeat como fuente de verdad; `posix_kill` solo como respaldo

El daemon reporta `status`/`pipeline_pid`/`error_message` cada 5s a `POST /api/internal/restream/{target}/heartbeat` (localhost-only, mismo guard que el emisor). La frescura (`last_heartbeat_at` < 15s) define "vivo". Alternativa: seguir con `posix_kill(pid,0)` sobre el PID guardado — descartada porque el PID del ffmpeg hijo muere con el daemon y no refleja el estado real (el daemon puede estar reiniciando el hijo).

### D4: El daemon se auto-supervisa (patrón `main.py` del emisor)

El supervisor loop del daemon: si el ffmpeg hijo muere → restart con backoff exponencial (máx 5 en 60s) → cooldown 30s → heartbeat `error`. El `RestreamSupervisor` PHP y `restream:supervise` se eliminan. Alternativa: mantener el supervisor PHP — descartada por duplicación de lógica y porque en producción no corre.

### D5: Reutilizar módulos de `emisor_python/` en vez de copiar

`restream_daemon/` importa `LogRotator`, `LaravelClient` (extendido con métodos de restream) y la gestión de subproceso ffmpeg de `emisor_python/`. Alternativa: paquete espejo con código copiado — descartada porque el objetivo explícito del usuario es un solo estándar para mantenimiento.

### D6: `pipeline_pid` pasa a ser el PID del daemon (no del ffmpeg hijo)

El heartbeat reporta el PID del ffmpeg hijo en el body, pero `restream_targets.pipeline_pid` guarda el PID del daemon (el proceso que Laravel spawnea y puede señalar para stop). El ffmpeg hijo es hijo del daemon y muere con él (kill por grupo de procesos, como `ffmpeg_pipeline.py`). Alternativa: guardar el PID del hijo — descartada porque Laravel no puede señalar a un nieto de forma fiable.

### D7: El stop es señal al daemon + espera del heartbeat `offline`

`RestreamOrchestrator::stop()` envía SIGTERM al daemon (SIGKILL tras 5s), espera hasta 10s el heartbeat `offline` (que limpia `pipeline_pid` y pone `status='idle'`), y fuerza la limpieza si no llega. Alternativa: matar el ffmpeg directamente — descartada porque el daemon debe poder hacer shutdown limpio (matar su grupo de procesos, reportar offline).

### D8: La UI replica el patrón del scheduler (polling + panel de log)

La vista de restream (admin y cliente) gana: polling de estado cada 10s (vía el index existente enriquecido con `last_heartbeat_at`), y un panel "Log de restream" por target que consume un nuevo endpoint de log (tail del archivo `storage/logs/restream/{id}.log`, parseando `[STATS]`/`[DAEMON]`/`[WATCHDOG]` como hace `EmissionController::log`). Alternativa: mantener la tabla estática con refresh manual — descartada porque es exactamente el problema reportado ("le doy iniciar y no sé si emite").

## Risks / Trade-offs

- [El daemon Python nuevo puede tener bugs de arranque en producción] → Mitigación: health-check de 500ms en el orquestador (si muere, 422 con la causa real del log); el daemon es una copia del patrón ya probado del emisor.
- [Eliminar `RestreamLauncher`/`RestreamSupervisor` rompe algo que dependa de ellos] → Mitigación: grep de usos antes de borrar; los comandos `restream:start/stop/status` se reemplazan por el orquestador + systemd; `restream:status` puede quedar como diagnóstico read-only.
- [El `stream_key` descifrado viaja en el config JSON en disco] → Mitigación: el archivo se escribe con permisos 0600 y se elimina tras el arranque del daemon (el daemon lo lee una vez); el key nunca se loguea (requisito de spec).
- [Heartbeat perdido por red interna (nginx 9090 caído) marca targets como error] → Mitigación: el umbral de 15s es generoso (3 heartbeats perdidos); el daemon reintenta el POST y sigue emitiendo aunque Laravel no reciba.
- [Dos daemons por target si se mezcla systemd con el orquestador] → Mitigación: el orquestador verifica el registration file y el estado antes de spawnear; documentar que el arranque es O systemd O orquestador, no ambos.
- [Migración de `status` enum: `active` → `live`] → Mitigación: migración con `WithDataSafetySnapshot` que reasigna `active` → `live` y añade `starting`; la UI y los specs se actualizan en el mismo change.

## Migration Plan

1. **Fase 1 — Daemon y orquestador (paralelo)**: crear `restream_daemon/` reutilizando módulos del emisor; crear `RestreamOrchestrator` y el endpoint interno de heartbeat; migración de columnas (`last_heartbeat_at`, `loops_completed`, reasignación de status). El código PHP viejo sigue funcionando mientras tanto.
2. **Fase 2 — UI**: polling de estado + panel de log en admin y cliente (patrón scheduler).
3. **Fase 3 — Corte**: eliminar `RestreamLauncher`, `RestreamSupervisor`, comandos de spawn y el `RestreamSuperviseCommand`; apuntar los controladores al orquestador; actualizar specs archivadas.
4. **Rollback**: si el daemon falla en producción, revertir a la rama anterior restaura el launcher PHP (los datos de targets no cambian de forma destructiva; la migración es aditiva con snapshot).

## Open Questions

- ¿`restream:status` se conserva como comando read-only de diagnóstico o se elimina del todo? (El proposal asume que puede quedar.)
- ¿El panel de log por target se integra en la tabla existente (acordeón) o como modal separado? (El diseño asume acordeón en la fila, como el scheduler.)
- ¿El daemon debe soportar `--dry-run` para imprimir el comando sin spawnear (útil para depurar el flag de RTMP)?
