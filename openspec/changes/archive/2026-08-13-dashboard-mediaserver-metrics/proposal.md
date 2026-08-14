## Why

El panel de inicio (dashboard) de Cloudstream hoy NO muestra ninguna métrica de emisión porque no tiene conexión configurada con la plataforma MediaServer. La plataforma MediaServer (SRS) es la que permite administrar usuarios y emitir, y expone una API REST documentada en `API.md` (`http://15.204.8.139:1985`) con TODAS las métricas en tiempo real por stream: viewers conectados, agente/player usado, bitrate de entrada y salida, health, resolución, puertos SRT, y las reglas de bloqueo (blacklist, geoblock, reglas por IP/país) que aplican a cada stream. El objetivo es integrar esas métricas de la API en el dashboard, congruentes con los medios que tiene asignado cada cliente (y el administrador ve todos los streams configurados). No se trata de investigar por qué el dashboard no las tiene: la API es la fuente de datos y hay que determinar qué expone para integrarlo.

## What Changes

- Agregar un panel de **métricas en vivo de MediaServer** en el dashboard de admin y de client, alimentado por la API de MediaServer Platform documentada en `API.md`.
- **Admin**: ve métricas de TODOS los streams configurados con un selector (dropdown) para elegir un canal/stream puntual, igual que el patrón de selector usado en otros módulos.
- **Client**: ve métricas solo de los canales que le pertenecen o le fueron asignados (`effectiveChannelIds()`), congruentes con los medios que tiene asignado, sin selector global.
- Por cada stream mostrar: estado (alive/health), viewers conectados, agentes/players usados (user_agent), bitrate recibido/enviado (kbps), resolución y codecs, uptime, puerto SRT, y las reglas de bloqueo que aplican a ese stream (blacklist, geoblock, reglas por IP/país).
- El mapeo stream↔canal se hace por el nombre del stream (RTMP `live/<slug>`) contra el `slug` del canal.
- Degradación elegante: si la API no está configurada o no responde, el dashboard muestra un estado "MediaServer no disponible" sin romper el resto del panel.
- **BREAKING**: ninguno. Cambio aditivo sobre el dashboard existente.

## Capabilities

### New Capabilities
- `dashboard-mediaserver-metrics`: Panel de métricas en vivo de MediaServer Platform integrado en el dashboard de admin y client, con mapeo stream↔canal por slug, selector de stream para admin, scoping por `effectiveChannelIds()` para client, y degradación elegante cuando la API no responde.

### Modified Capabilities
- `mediaserver-api-injection`: el servicio `MediaserverApiService` se extiende con métodos para consumir los endpoints de métricas (streams, clients, diagnostics, rules) que hoy no expone.

## Impact

- `app/Services/MediaserverApiService.php` — nuevos métodos de lectura (streams, clients por stream, diagnostics, blacklist, geoblock, client rules, srt status).
- `app/Http/Controllers/Admin/DashboardController.php` — `index()` pasa métricas en vivo al dashboard.
- `app/Http/Controllers/Client/DashboardController.php` — `index()` pasa métricas en vivo scoped a los canales del usuario.
- `resources/views/admin/dashboard.blade.php` — nueva sección de métricas en vivo con selector de stream.
- `resources/views/client/dashboard.blade.php` — nueva sección de métricas en vivo (solo canales asignados).
- `config/mediaserver.php` — sin cambios estructurales; se reutiliza la config existente.
- Dependencia: la API de MediaServer Platform en la LAN (ya documentada en `API.md`).
