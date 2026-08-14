## Context

El dashboard actual de admin (`resources/views/admin/dashboard.blade.php`) y de client (`resources/views/client/dashboard.blade.php`) muestra solo estadísticas internas de la DB (usuarios, canales, medios, playlists) y NO tiene ninguna métrica de emisión porque no hay conexión configurada con la plataforma MediaServer. La plataforma MediaServer (SRS) es la que permite administrar usuarios y emitir, y expone una API REST documentada en `API.md` (`http://15.204.8.139:1985`) con todas las métricas en vivo por stream. La fuente de datos para el dashboard es esa API: hay que determinar qué expone y qué se puede integrar, congruente con los medios asignados a cada cliente (el admin ve todos los streams configurados).

Ya existe `App\Services\MediaserverApiService` (resoluble por el container vía `AppServiceProvider`, config en `config/mediaserver.php`) con `getStreams()`, `getClients()` y `kickClient()`, pero no se consume en ningún controller actualmente y no expone los endpoints de reglas/diagnóstico. Las credenciales (`MEDIASERVER_API_URL`, `MEDIASERVER_API_USER`, `MEDIASERVER_API_PASSWORD`) no están en `.env` hoy; sin ellas el dashboard debe degradar a "MediaServer no disponible".

La API fue probada en vivo: `/api/streams/` devuelve 5 streams con `name` (slug), `clients`, `kbps_recv`, `kbps_send`, `health_state`, `video`, `audio`, `srt_port`, `urls`; `/api/streams/{name}/clients` devuelve viewers con `ip`, `type` (hls-play, rtmp-play, fmle-publish) y `name` (agente/player); `/api/rules/blacklist`, `/api/rules/geoblock`, `/api/rules/clients` y `/api/srt/status` devuelven las reglas y puertos. Un token de prueba read-only (ver `API.md`, excluido del repo) funciona para todos los endpoints de lectura.

## Goals / Non-Goals

**Goals:**
- Determinar qué métricas expone la API de MediaServer (`API.md`) y mostrar las relevantes en el dashboard de admin (todos los streams + selector) y de client (solo canales asignados).
- Mapear streams a canales por `slug` para que las métricas sean congruentes con los medios que tiene asignado cada cliente.
- Mostrar por stream: viewers, agentes/players, bitrate, resolución/codecs, health, puerto SRT, y reglas de bloqueo aplicables.
- Degradación elegante cuando la API no está configurada o no responde.
- Aplicar el patrón dual admin/client con scoping por `effectiveChannelIds()`.

**Non-Goals:**
- No se agregan acciones de escritura (kick, blacklist, geoblock) desde el dashboard — solo lectura. Las acciones de escritura quedan para un cambio futuro.
- No se agrega polling automático ni WebSockets; los datos se cargan en el render del dashboard (y se pueden refrescar manualmente).
- No se toca el módulo de emisión (dismounted).
- No se cambia la config de credenciales de la API.

## Decisions

### D1: Extender `MediaserverApiService` con métodos de lectura
Se agregan métodos: `getStreamClients(string $name)`, `getDiagnostics()`, `getBlacklist()`, `getGeoblock()`, `getClientRules()`, `getSrtStatus()`, `getHealth()`. Todos reutilizan el `request()` privado existente (JWT cacheado + retry en 401). `getStreams()` y `getClients()` ya existen.
- **Alternativa considerada**: crear un service nuevo `DashboardMetricsService`. Se descarta: duplicaría la lógica de auth/retry ya resuelta en `MediaserverApiService` y el binding del container ya existe.

### D2: Agregación de métricas en un DTO/array por stream
Se crea un método de agregación (en el controller o en un pequeño helper) que por cada stream de la API construye un array normalizado:
- `stream_name`, `channel` (Channel|null por slug), `active`, `health_state`, `clients`, `kbps_recv`, `kbps_send`, `video` (codec/resolución), `audio`, `srt_port`, `uptime_ms`.
- Para el stream seleccionado (admin) o cada canal (client), se consulta además `/api/streams/{name}/clients` para viewers (total, IPs únicas, agentes con conteo) y se cruzan las reglas (blacklist, geoblock, client rules con `stream_filter` vacío o que incluya el stream).
- **Alternativa considerada**: hacer N llamadas HTTP por stream en el render. Se mitiga con un timeout corto (5s config) y capturando excepciones por stream para que un stream fallido no rompa el resto.

### D3: Mapeo stream↔canal por stream name (no por slug)
Validación con datos reales: los slugs de canal (`cine-dios`, `red-planet`) NO coinciden con los stream names de la API (`cinedios`, `Redplanet_TV`). El mapeo real se extrae del `public_hls_url` del canal (basename del path, sin `.m3u8`) y, si está vacío, de `virtualScreen.output_url` (patrón `live/<stream>`), con fallback al `slug`. En admin se muestran todos los streams (con o sin canal). En client se filtran los streams cuyo stream name esté en los canales del usuario (`effectiveChannelIds()`).

### D4: Selector de stream en admin
Dropdown Alpine en `admin/dashboard.blade.php` que filtra la sección de métricas por stream seleccionado (patrón de selector usado en scheduler/channels). Sin selección → resumen agregado (streams activos, total viewers, streams degradados). El client no tiene selector: muestra sus canales en tarjetas.

### D5: Degradación elegante
Los controllers envuelven todas las llamadas a la API en try/catch (`MediaserverApiException` + `Throwable`), loguean el fallo y pasan `mediaserver_available: false` con arrays vacíos. La vista muestra un banner "MediaServer no disponible" y el resto del dashboard se renderiza normal.

### D6: Vista compartida para el bloque de métricas
Se crea un partial `resources/views/partials/mediaserver-metrics.blade.php` incluido por ambos dashboards, con una variable `$isAdmin` (o `$showSelector`) para el selector. Esto evita divergencia entre admin y client (regla dual del proyecto).

## Risks / Trade-offs

- [Latencia: N+1 llamadas a la API por stream (clients + reglas)] → Mitigación: solo se consulta el detalle de clients para el stream seleccionado (admin) o los canales del client (pocos); reglas se consultan una vez y se cruzan en memoria; timeout corto por request.
- [API caída o lenta degrada el render] → Mitigación: try/catch por llamada, `isReachable()` previo, banner de degradación, nunca error 500.
- [Streams sin canal asociado en admin] → Mitigación: se muestran con etiqueta "sin canal asociado"; en client se excluyen.
- [Token de prueba read-only en docs] → Mitigación: la app usa credenciales de `config('mediaserver.*')` (env), no el token de prueba; el token de prueba solo sirve para desarrollo/verificación.
- [Divergencia admin/client en la vista] → Mitigación: partial compartido + selector condicionado por rol.

## Migration Plan

- No hay migraciones de DB ni cambios de schema.
- Deploy: agregar métodos al service, actualizar controllers, agregar partial, actualizar vistas. Sin pasos destructivos.
- Rollback: revertir los cambios de controllers/vistas; el service puede quedarse (aditivo).
- Config: verificar que `MEDIASERVER_API_URL`, `MEDIASERVER_API_USER`, `MEDIASERVER_API_PASSWORD` estén en `.env` (hoy no están; sin ellas el dashboard muestra el estado degradado, que es el comportamiento esperado).

## Open Questions

- ¿Se desea un botón de "refrescar" manual en la sección de métricas? (Se asume sí, vía recarga de página, por simplicidad.)
- ¿Se desea mostrar el detalle de viewers/agentes de TODOS los streams en admin o solo del seleccionado? (Se asume solo del seleccionado para limitar llamadas.)
