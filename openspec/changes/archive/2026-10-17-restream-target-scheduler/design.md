## Context

`restream_targets` actualmente expone dos columnas `scheduled_start_at`/`scheduled_stop_at` consumidas por `restream:launch-scheduled` cada minuto. Es un scheduler de **una ventana por target** con `scheduled_stop_at` obligatorio para que el motor encienda (la condición actual es `scheduled_stop_at <= now()`). No soporta más de una ventana, no soporta fin abierto, no tiene visualizador calendario, y cuando algo falla solo hay un `last_error` que se sobreescribe.

Diagnóstico de hoy tras una caída:

```
restream_targets.last_error        → "Daemon exited immediately with code 1."
audit_logs (búsqueda por target)   → evento por acción (manual/auto start/stop)
FFmpeg log                         → stderr en storage/logs/restream/<id>.log
```

Operador no sabe distinguir "red caída" vs "YouTube revocó" vs "OAuth expirado".

Hoy la infrafraestructura operativa ya tiene el watchdog `restream:reap-dead-broadcasts` (recién agregado) que detecta broadcasts consumidos en YouTube. La nueva pieza debe **enriquecer** ese watchdog con `kind` clasificable y darle al operador una UI histórica.

## Goals / Non-Goals

**Goals:**
- N ventanas de programación por destino, independientes y ordenables.
- `ends_at` opcional → broadcast indefinido (con parada solo manual).
- Timezone IANA por ventana para que el cliente programe "7pm hora Bogotá" y la UI muestre local.
- Motor por minuto idempotente que reconcilia ventana ↔ estado del daemon con cero duplicación.
- Historial causal (`restream_target_events`) con `kind` tipado y retención 90d.
- Calendario visual por target (mes/semana) en cliente y admin.
- Diagnóstico a 3 meses: "falló porque YouTube lo bloqueó" sale explícito de un icono + tooltip.
- Migración limpia de los campos legacy con snapshot de seguridad (`WithDataSafetySnapshot`).
- Coexistencia operativa con el `restream:reap-dead-broadcasts` ya en producción.

**Non-Goals:**
- Recurrencia ("cada lunes") en esta entrega. Las ventanas son one-shot por target; si el cliente quiere rutina diaria, crea N ventanas manualmente. La recurrencia sería un cambio posterior que añadiría un campo `recurrence_rule` (iCalendar RRULE).
- Webhooks/notificaciones al cliente cuando un evento arranca/falla. Esos son cambios separados; el historial ya queda accesible vía UI.
- Programación multi-target coordinada (Diseño B descartado explícitamente). Si el cliente quiere que dos targets arranquen a la vez, crea la misma ventana en cada uno. Pragmático y simétrico.
- Edición bulk. Cada ventana se CRUD individualmente.
- Deshacer/rehacer cambios. El historial es append-only.

## Decisions

**D1. Tabla `restream_target_schedules` con N filas por target (no JSON).**
- Por qué: queries directos (`WHERE target_id=? AND starts_at<=now() AND (ends_at IS NULL OR ends_at>now())`), historial claro, constraint FK simple.
- Alternativa: JSON column. Descartada por la quota guard (lockForUpdate), auditoría (entity_id por fila) y por la migración limpia desde la columna legacy.

**D2. `ends_at` nullable, sin default.**
- Por qué: el caso "broadcast indefinido" es explícito en el modelo. Si quieres fin, lo pones; si no, el motor enciende y no se detiene por sí solo.
- Alternativa: `ends_at` con default `+24h`. Descartada porque significaría "siempre me apaga en 24h" sin elección, contradice el requirement explícito.

**D3. `timezone` IANA por ventana, no por canal ni global.**
- Por qué: el cliente puede tener canales para clientes en distintos países (un cliente internacional con oficinas en Colombia y España). Cada ventana en su hora local.
- Almacenamiento: en BD como string IANA; al guardar convertimos `starts_at`/`ends_at` a UTC; al mostrar, formato local con `Carbon::setTimezone($event->timezone)`.
- Validación: `in_array($tz, DateTimeZone::listIdentifiers())`.

**D4. Estado derivable, sin columnas extra.**
- `pending`: `starts_at > now`
- `running`: `starts_at <= now AND (ends_at IS NULL OR ends_at > now)`
- `ended`: `ends_at <= now` (cuando no es null)
- `disabled`: `enabled = false`
- La UI calcula en cada render. Cero columnas calculadas, cero estados derivados a sincronizar.

**D5. Reutilizar `RestreamOrchestrator::start()` / `stop()` ya idempotentes.**
- El motor no llama a FFmpeg directamente. Llama al orquestador, que ya tiene: idempotencia (chequea `/proc/{pid}/cmdline`), recovery de broadcasts YouTube (`refreshDeadBroadcast()`), respeto de quota. Reutilizar minimiza código nuevo.
- Diferencia con el flujo manual: el motor llama `start()` cuando la ventana applicable es la actual; el orquestador ya noop-ea si el daemon está vivo. Cero cambio al orquestador.

**D6. `restream_target_events` append-only, no tabla de logs tradicional.**
- Append-only = no `updated_at`, índice por `occurred_at` para orden natural.
- `kind` enum-like (string validado contra whitelist).
- `metadata` jsonb nullable para datos específicos de la causa (e.g. `{"pid": 3470136, "broadcast_id": "5sqnSiV...", "youtube_lifecycle": "revoked"}`).
- Retention: comando `restream:prune-target-events` diario borra filas con `occurred_at < now() - 90 days`. Configurable por env (`TARGET_EVENTS_RETENTION_DAYS`).

**D7. Kinds cerrados para distinguir causalidad.**
- Whitelist inicial (se amplía en Open Question):
  - `manual_started`, `manual_stopped` (UI cliente/admin)
  - `auto_started`, `auto_stopped` (motor de ventanas)
  - `youtube_revoked` (broadcast canceled por YouTube, lifeCycleStatus=revoked)
  - `youtube_consumed_remotely` (broadcast ended normal por stream disconnect, lifeCycleStatus=complete)
  - `youtube_health_lost` (streamStatus=inactive o healthStatus=noData mientras daemon vivo)
  - `ffmpeg_died` (FFmpeg child exited sin razón clara)
  - `network_unreachable` (no se pudo conectar al source o destination)
  - `oauth_expired` (refresh token rejected)
  - `quota_exceeded` (slot lleno al intentar start)
  - `watchdog_recovery_succeeded` (reenlace automático funcionó)
  - `watchdog_recovery_exhausted` (N intentos fallaron; emisión queda DOWN con causa)
- El reap-dead-broadcasts ya detecta `revoked` vs `complete` por `lifeCycleStatus`; solo cambia la `kind` que escribe.

**D8. Calendario con Alpine.js + CSS grid (sin FullCalendar).**
- Por qué: el proyecto ya usa Alpine.js + Tailwind; introducir FullCalendar.js suma ~50KB y un modelo mental nuevo (callbacks, vistas predefinidas).
- Implementación: grid de 7×N (mes) o 7×H (semana) donde cada celda es un `<div>` posicionado por `top/left` calculado en `x-data` con `$nextTick`. Click → modal con la ventana.
- Trade-off: el código del calendario es nuestro (más superficie de bugs) pero cero dependencias y consistente con el stack existente.

**D9. Migración destructiva con `WithDataSafetySnapshot`.**
- Por qué: protocolo AGENTS.md exige que cualquier migración que dropee columnas o trunque datos use el trait. La migración: (1) crea tabla `restream_target_schedules`, (2) INSERT-SELECT desde `restream_targets` para cada fila con `scheduled_start_at IS NOT NULL OR scheduled_stop_at IS NOT NULL`, (3) DROP COLUMN las dos columnas. El snapshot pre-DDL garantiza rollback.

**D10. Calendario modal-first, no inline-editable.**
- Click en celda vacía → abre modal con `starts_at` prellenado según la celda clickeada (fecha + hora típica según granularidad).
- Click en bloque existente → abre modal en modo edición con valores prellenados.
- Drag & drop de bloques queda para una iteración futura; no en esta entrega.

## Risks / Trade-offs

- **R1. Ventanas sin fin pueden olvidarse y dejar el daemon corriendo indefinidamente.** → UI muestra banner "Estás emitiendo sin fin programado (desde hace X tiempo). ¿Detener?" en `client.restream.index`; admin ve lista de "indefinite broadcasts currently running". Documentado en `tasks.md` (alertas operacionales).
- **R2. Timezone DST produce ventanas 1h fuera.** → Almacenar TZ IANA; al guardar, normalizar a UTC con la transición DST ya resuelta. La UI muestra la hora local usando `Carbon::setTimezone($event->timezone)`. La validación rechaza TZ inválidas.
- **R3. Migración legacy pierde datos si una fila tiene `scheduled_start_at` pero no `scheduled_stop_at` (infinito).** → La nueva fila insertada tendrá `ends_at = NULL`. Es exactamente lo que queremos; sin pérdida de información.
- **R4. Ventanas solapadas en el mismo target.** → Si dos ventanas cubren el mismo intervalo, el motor enciende UNA vez y apaga a la `ends_at` más tardía. Comportamiento idempotente y documentado.
- **R5. `restream_target_events` crece rápido con retención 90 días.** → Estimación: ~5 eventos/día por target × 10 targets × 90 días = 4500 filas. Trivial. Si creciera, índice por `(target_id, occurred_at)` mantiene queries rápidas.
- **R6. Coexistencia con `restream:launch-scheduled` legacy causa confusión.** → Una vez migrados todos los targets y tras un período de gracia de 1 sprint (ver Open Question), se puede borrar el comando legacy. Mientras coexista: el comando legacy solo procesará `restream_target_schedules` con campos equivalentes (`starts_at`/`ends_at`) que ya no existen, así que será un no-op silencioso.
- **R7. `restream:run-target-schedules` y `restream:reap-dead-broadcasts` corren ambos cada minuto.** → Sin colisión funcional: uno enciende/apaga según ventanas, el otro detecta YouTube consumido y recrea. El reap incluso llama al orquestador que respeta ventanas. Orden recomendado: `run-target-schedules` primero, luego `reap-dead-broadcasts` (cascade natural).
- **R8. El `last_error` legacy se queda sin uso.** → Mantener la columna vacía por compatibilidad. `last_failed_at` y `last_error` ya no se escriben automáticamente; se escriben solo vía `restream_target_events`. Migración de datos: tomar el último `last_error` y crear un evento `kind=legacy_failure_archived` en `restream_target_events`.

## Migration Plan

1. Pre-deploy: `php artisan db:backup` (protocolo AGENTS.md).
2. Deploy del código + nueva migración con `WithDataSafetySnapshot`:
   - Crea `restream_target_schedules`.
   - Mueve filas desde las columnas legacy.
   - DROP COLUMN las 2 columnas legacy.
   - Crea `restream_target_events`.
3. Verificar `php artisan check:destructive-migrations` (debe seguir limpio antes del merge).
4. `php artisan migrate:safe` ejecuta backup + migración + verify.
5. Post-deploy: `php artisan restream:run-target-schedules --once` manual para validar el comando antes del cron.
6. Verificar `php artisan schedule:list` muestra los 3 comandos: `restream-run-target-schedules`, `restream-launch-scheduled` (legacy, ahora no-op), `restream-reap-dead-broadcasts`.
7. Cliente prueba: crear ventana "hoy 14:00 → hoy 16:00 hora Bogotá" en cualquier target, esperar al motor, confirmar que arranca y aparece en historial.
8. Rollback: si la migración falla, `migrate:rollback` restaura desde el snapshot las 2 columnas + las filas. Si un comando tiene bug, basta comentar la línea en `routes/console.php`.

## Open Questions

- **Q1. ¿Cuándo borrar `restream:launch-scheduled`?** Tentativo: 1 sprint después de que todos los clientes hayan migrado. Hasta entonces coexiste como no-op para no romper pipelines de operadores que aún usan los campos legacy.
- **Q2. ¿Ventana recurrente (RRULE) en un próximo change?** Si el cliente lo pide, agrega `recurrence_rule` (string iCalendar RRULE) y `recurrence_until` (datetime). El motor expandiría las ocurrencias bajo demanda. Out of scope explícito de esta entrega.
- **Q3. ¿Webhooks al cliente cuando arranca/falla?** Out of scope. El historial es accesible vía UI; si el cliente quiere notificaciones push, sería un change separado.
- **Q4. ¿Alertas en UI para "llevas N horas emitiendo sin fin"?** Banner propuesto en cliente/admin. Si el cliente prefiere no verlo, hay un toggle para ocultar. Por defecto ON.
