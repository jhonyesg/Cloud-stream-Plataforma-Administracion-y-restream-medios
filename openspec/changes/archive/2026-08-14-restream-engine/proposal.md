## Why

El módulo Restream ya tiene su plano de datos y UI (cambio `add-restream-module` archivado): `restream_quotas` decide qué usuario puede re-transmitir, `restream_targets` define los destinos (plataforma + URL + stream_key). Falta la pieza que **realmente envía la señal**: hoy un target con `enabled=true` no hace nada — el campo `pipeline_pid` existe pero ningún proceso lo escribe. Sin motor, los clientes habilitados ven sus tarjetas pero ninguna emite al exterior.

Necesitamos el **motor de reemisión**: un daemon PHP (`restream:supervise`) que vigile los targets activos y mantenga **un proceso FFmpeg por target**, leyendo la señal fuente del canal (la salida RTMP del motor de emisión cuando esté reconstruido, o el HLS público del canal como puente mientras tanto) y empujándola al `destination_url + stream_key` del target con `-c:v copy -c:a aac -f flv`. El estado de cada target (`idle` / `active` / `error`) y los timestamps `last_started_at` / `last_stopped_at` / `last_error` se actualizan en vivo, igual que el antiguo sistema de emisión. El cliente ve en su panel qué destinos están realmente transmitiendo.

## What Changes

- Nueva columna `restream_targets.source_url` (text nullable) — URL de entrada del FFmpeg. Si es NULL, el motor usa `channels.public_hls_url` como puente hasta que el motor de emisión exponga su RTMP interno.
- Nueva columna `restream_targets.pipeline_pid` (integer nullable) — PID vivo del proceso FFmpeg (espejo de `pipeline_pid` que ya existía conceptualmente; aquí se materializa).
- Nuevo servicio `App\Services\Restream\RestreamLauncher` responsable de construir el comando FFmpeg, arrancarlo como proceso hijo, capturar stderr en `storage/logs/restream/{target_id}.log` y parsear `[restream]` markers para detectar errores.
- Nuevo supervisor `App\Console\Commands\RestreamSuperviseCommand` (`php artisan restream:supervise`) — loop infinito que cada N segundos: (a) arranca FFmpeg para targets con `enabled=true && pipeline_pid IS NULL`; (b) verifica `kill -0` para targets con PID y reintenta si murió; (c) transiciona estados.
- Comandos one-shot: `restream:start {target}` / `restream:stop {target}` / `restream:status` (tabla de PIDs + estado + uptime + últimos errores).
- Endpoints nuevos (admin y client): `POST /client/channels/{channel}/restream-targets/{target}/start` y `.../stop` para que el cliente dispare arranque/parada sin esperar al supervisor.
- `RestreamTarget::status` se actualiza automáticamente: `idle` al crear; `active` cuando el supervisor confirma PID vivo; `error` cuando FFmpeg muere < 30s tras arrancar o el log contiene `[restream]` con error; `idle` al detener.
- Plantilla FFmpeg canónica:
  ```
  ffmpeg -hide_banner -loglevel info -re \
    -i "{source_url}" \
    -c:v copy -c:a aac -ar 44100 -ac 2 -b:a 128k \
    -f flv -rtmp_live live=1 \
    "{destination_url}/{stream_key}"
  ```
  (El supervisor es responsable de inyectar las credenciales reales leyendo `restream_targets.stream_key` descifrado.)
- El panel del cliente muestra el estado vivo (`active` con punto verde pulsante, `error` con tooltip de `last_error`) y refresca cada 5s con la misma cadencia que el sistema de emisión tenía.

## Capabilities

### New Capabilities

- `restream-engine`: define el supervisor daemon, el ciclo de vida del proceso FFmpeg por target, la actualización de estado, los endpoints de start/stop y los comandos Artisan.
- `restream-launcher`: define el `RestreamLauncher` que materializa el comando FFmpeg, captura stderr y lo expone como servicio reutilizable por el supervisor y los endpoints.

### Modified Capabilities

- `restream-targets`: añade `source_url` (nullable) y `pipeline_pid` (nullable integer) a la tabla; los métodos del controller `update` aceptan `source_url`; la regla "habilitar un target" ahora dispara arranque del proceso (vía el supervisor) además de la validación de cap.
- `restream-habilitation`: sin cambios de requisitos — el motor respeta los tiers existentes (`max_outputs`) y no expone nuevos.

## Impact

- **Migración**: `2026_08_14_100000_add_engine_columns_to_restream_targets.php` (up-only, agrega `source_url text NULL` + `pipeline_pid integer NULL`; no requiere `WithDataSafetySnapshot`).
- **Servicios nuevos**: `App\Services\Restream\RestreamLauncher`, `App\Services\Restream\RestreamSupervisor` (clase aparte para que el comando sea fino).
- **Comandos nuevos** (`app/Console/Commands/`): `RestreamSuperviseCommand`, `RestreamStartCommand`, `RestreamStopCommand`, `RestreamStatusCommand`.
- **Endpoints nuevos**: `POST .../start` y `POST .../stop` en `Client\RestreamTargetController` (con `canAccessChannel` y `EnsureRestreamEnabled`).
- **UI**: el panel `_restream_panel.blade.php` ya muestra `status`; agregar badge visual `Activo` con dot pulsante cuando `status === 'active'`, tooltip con `last_error` cuando `status === 'error'`. Refresco cada 5s vía `setInterval` (mismo patrón que el antiguo visor de emisión).
- **Procesos del sistema**: cuando el supervisor arranca FFmpeg, el PID se persiste en `pipeline_pid` y el stderr se redirige a `storage/logs/restream/{target_id}.log` (gitignored).
- **Sin impacto** en emisión (que sigue desmontada) ni en `VirtualScreen` / `program_timeline_items`. El campo `source_url` acepta cualquier URL reproducible; cuando el motor de emisión se reconstruya y publique un RTMP local, el admin del sistema (no del panel) actualizará el `source_url` por defecto.
- **No requiere** `FFmpeg` instalado en desarrollo: los comandos `--dry-run` permiten validar la línea de comando sin ejecutar.