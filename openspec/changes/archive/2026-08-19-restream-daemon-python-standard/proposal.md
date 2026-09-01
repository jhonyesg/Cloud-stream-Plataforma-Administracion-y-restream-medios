## Why

El motor de restream está implementado a medias en PHP: `RestreamLauncher` spawnea ffmpeg con un comando roto (`-rtmp_live live=1`), el `RestreamSupervisor` PHP no está corriendo en producción, la UI muestra "Activo" con un PID fantasma y nadie lee el stderr del proceso. Mientras tanto, el emisor de programación ya tiene un estándar probado en producción: un daemon Python por canal con supervisor loop, heartbeat, stats, watchdog y log rotado. Unificar todo lo que "emite" bajo ese mismo estándar Python elimina la confusión de dos arquitecturas paralelas y hace que mantenimiento y actualizaciones toquen una sola estructura.

## What Changes

- **Nuevo daemon Python de restream** (`restream_daemon/`) que replica el estándar del emisor: un proceso Python por target, con supervisor loop (restart con backoff), heartbeat hacia Laravel cada 5s, línea `[STATS]` cada 5s, watchdog de pipeline atascado, log rotado en `storage/logs/restream/{target_id}.log` y unit systemd por target.
- **El daemon construye el comando ffmpeg correctamente** (corrige `-rtmp_live live=1` → `-rtmp_live live`), leyendo source/dest/stream_key desde un config JSON escrito por Laravel antes del spawn.
- **Nuevo orquestador PHP** (`RestreamOrchestrator`) al estilo `EmissionOrchestrator`: valida estado/cuota, escribe config JSON, actualiza `restream_targets.status = starting`, spawnea el daemon con `proc_open`, health-check a los ~500ms y responde 422 con la causa real si el daemon muere al arrancar.
- **Nuevos endpoints internos** (localhost-only): `POST /api/internal/restream/{target}/heartbeat` para actualizar `restream_targets` (status, `last_heartbeat_at`), y reutilización del log existente para exponer el tail del proceso.
- **La UI de restream (admin y cliente) adopta el patrón del scheduler**: polling de estado cada 10s (estado real por heartbeat/PID) y panel "Log de restream" por target con polling cada 5s, reutilizando la plantilla visual del emisor.
- **BREAKING — Se elimina la implementación PHP de spawn/supervisión**: `RestreamLauncher` (spawn directo, comando, tail), `RestreamSupervisor`, los comandos `restream:supervise`, `restream:start`, `restream:stop` (la parte de spawn) y la detección de huérfanos. El `pipeline_pid` pasa a ser gestionado por el daemon Python vía heartbeat, igual que `emission_state`.
- **`restream_targets` recibe columnas nuevas** al estilo `emission_state`: `last_heartbeat_at`, `loops_completed` (opcional) — con migración protegida por `WithDataSafetySnapshot`.

## Capabilities

### New Capabilities
- `restream-daemon`: Daemon Python por target con el estándar del emisor (supervisor loop, heartbeat cada 5s, [STATS], watchdog, log rotado, systemd), aislamiento total entre targets (los N cupos no se afectan entre sí).

### Modified Capabilities
- `restream-launcher`: sus requirements cambian de "spawn PHP con Symfony Process" a "el orquestador PHP escribe config JSON y spawnea el daemon Python; el comando ffmpeg lo construye el daemon con la opción `-rtmp_live live` corregida".
- `restream-engine`: la reconciliación deja de ser el supervisor PHP (tick cada 5s) y pasa a ser el auto-supervisión del daemon Python por target (restart con backoff + cooldown, reportando error vía heartbeat).
- `restream-targets`: el ciclo de vida del status pasa a ser manejado por el daemon vía heartbeat (starting/live/error/offline), con `last_heartbeat_at` como fuente de frescura; los endpoints start/stop del controlador pasan a delegar en el orquestador.
- `emission-daemon`: se documenta el paquete Python como el estándar compartido (los módulos `utils/` y `pipeline/` son reutilizados por el restream daemon).

## Impact

- **Código nuevo Python**: `restream_daemon/` (main.py, daemon/target_daemon.py) reutilizando `emisor_python/utils/log_rotator.py`, `emisor_python/utils/laravel_client.py` (extendido) y `emisor_python/pipeline/ffmpeg_pipeline.py` (refactorizado a compartido).
- **Código PHP**: `RestreamOrchestrator` nuevo; `RestreamLauncher`/`RestreamSupervisor` y comandos asociados eliminados; `RestreamTargetController` (client y admin) delegando en el orquestador; nuevo `RestreamHeartbeatController` interno.
- **Rutas**: nuevos endpoints internos `/api/internal/restream/*` (localhost-only, como `/api/internal/channels/*/emission/*`).
- **BD**: migración con `WithDataSafetySnapshot` para añadir `last_heartbeat_at` (+ `loops_completed`) a `restream_targets`.
- **UI**: vistas `client/restream/index.blade.php` y `admin/restream/index.blade.php` con polling y panel de log (patrón del scheduler); `restream-urls` gana URLs de log/status.
- **Deploy**: unit systemd `cloudstream-restream@.service` (mismo estilo que `cloudstream-emission@.service`), venv compartido con el emisor.
- **Dependencias**: ninguna nueva (python3, requests, ya presentes en `emisor_python/venv`).
