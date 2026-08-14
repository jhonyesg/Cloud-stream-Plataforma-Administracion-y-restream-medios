## 1. Extender MediaserverApiService

- [x] 1.1 Agregar método `getStreamClients(string $name): array` que llama `GET /api/streams/{name}/clients` y devuelve `$response['clients'] ?? []`
- [x] 1.2 Agregar método `getDiagnostics(): array` que llama `GET /api/diagnostics/streams` y devuelve `$response['streams'] ?? []`
- [x] 1.3 Agregar método `getBlacklist(): array` que llama `GET /api/rules/blacklist` y devuelve `$response['blocked'] ?? []`
- [x] 1.4 Agregar método `getGeoblock(): array` que llama `GET /api/rules/geoblock` y devuelve `$response['rules'] ?? []`
- [x] 1.5 Agregar método `getClientRules(): array` que llama `GET /api/rules/clients` y devuelve `$response['rules'] ?? []`
- [x] 1.6 Agregar método `getSrtStatus(): array` que llama `GET /api/srt/status` y devuelve el payload completo
- [x] 1.7 Agregar método `getHealth(): array` que llama `GET /api/health` (público) y devuelve el payload completo
- [x] 1.8 Verificar que todos los métodos nuevos reutilizan `request()` (JWT cacheado + retry 401) y lanzan `MediaserverApiException` cuando la API no está configurada

## 2. Agregación de métricas en el controller admin

- [x] 2.1 En `Admin\DashboardController::index()`, inyectar `MediaserverApiService` y consultar `isReachable()`; si es false pasar `mediaserver_available: false` con datos vacíos
- [x] 2.2 Si la API responde, obtener streams (`getStreams()`) y mapearlos a canales por slug (`Channel::whereIn('slug', $names)->get()->keyBy('slug')`)
- [x] 2.3 Construir array normalizado por stream: `stream_name`, `channel`, `active`, `health_state`, `clients`, `kbps_recv`, `kbps_send`, `video`, `audio`, `srt_port`, `uptime_ms`
- [x] 2.4 Consultar reglas una sola vez (`getBlacklist()`, `getGeoblock()`, `getClientRules()`) y cruzarlas en memoria por stream (blacklist, geoblock, client rules con `stream_filter` vacío o que incluya el stream)
- [x] 2.5 Para el stream seleccionado (query param `?stream=`), consultar `getStreamClients($name)` y agregar total de viewers, IPs únicas y agentes (user_agent) con conteo
- [x] 2.6 Envolver todas las llamadas en try/catch (`MediaserverApiException` + `Throwable`), loguear el fallo y pasar `mediaserver_available: false` sin propagar el error
- [x] 2.7 Pasar a la vista: `mediaserver_available`, `mediaserver_streams` (agregados), `mediaserver_selected` (detalle del stream elegido), `mediaserver_rules` (resumen de reglas)

## 3. Agregación de métricas en el controller client

- [x] 3.1 En `Client\DashboardController::index()`, inyectar `MediaserverApiService` y consultar `isReachable()`; si es false pasar `mediaserver_available: false` con datos vacíos
- [x] 3.2 Obtener los slugs de los canales del usuario (`Channel::whereIn('id', $user->effectiveChannelIds())->pluck('slug')`)
- [x] 3.3 Obtener streams de la API y filtrar SOLO los cuyo `name` esté en los slugs del usuario (nunca streams ajenos ni sin canal)
- [x] 3.4 Construir el mismo array normalizado por stream que en admin (reutilizar la misma lógica de agregación)
- [x] 3.5 Consultar `getStreamClients($name)` para cada canal del client y agregar viewers/IPs únicas/agentes
- [x] 3.6 Envolver todas las llamadas en try/catch y pasar `mediaserver_available: false` sin propagar el error
- [x] 3.7 Pasar a la vista: `mediaserver_available`, `mediaserver_streams` (solo canales del usuario), `mediaserver_rules`

## 4. Vista compartida de métricas

- [x] 4.1 Crear `resources/views/partials/mediaserver-metrics.blade.php` con el bloque de métricas: banner de degradación si `!$mediaserver_available`, resumen agregado, y tarjetas por stream
- [x] 4.2 Mostrar por stream: badge de health (healthy/degraded/paused/zombie), viewers, kbps_recv/kbps_send, resolución y codecs, puerto SRT (o "SRT no asignado"), y resumen de reglas de bloqueo
- [x] 4.3 Mostrar detalle de viewers/agentes (total, IPs únicas, lista de user_agent con conteo) para el stream seleccionado
- [x] 4.4 Mostrar "sin canal asociado" para streams sin canal en admin; el partial recibe `$showSelector` para el dropdown de admin
- [x] 4.5 Incluir el partial en `admin/dashboard.blade.php` con selector Alpine (dropdown) que filtra por stream; sin selección muestra resumen agregado
- [x] 4.6 Incluir el partial en `client/dashboard.blade.php` sin selector, mostrando solo los canales del usuario
- [x] 4.7 Verificar que el partial usa las mismas clases/estilos Tailwind que el resto del dashboard y no rompe el layout

## 5. Verificación

- [x] 5.1 Verificar que `php artisan view:clear && php artisan config:clear && php artisan route:clear` corre sin errores
- [x] 5.2 Smoke test admin: render de `/admin` verificado vía tinker con datos REALES de la API (selector, resumen, detalle de viewers/agentes/reglas, degradación); login browser bloqueado por credenciales de producción desconocidas
- [x] 5.3 Smoke test client: render de `/client` verificado vía tinker con datos REALES (solo canales asignados, sin selector, detalle de viewers/agentes)
- [x] 5.4 Smoke test degradación: con `MEDIASERVER_API_URL` vacío (o API apagada) el dashboard muestra "MediaServer no disponible" y el resto del panel funciona
- [x] 5.5 Verificar que ningún controller client nuevo accede a canales fuera de `effectiveChannelIds()` (grep `effectiveChannelIds`/`canAccessChannel` en `app/Http/Controllers/Client/`)
- [x] 5.6 Validar la API con datos reales ANTES de cerrar: `/api/health` ok, `/api/streams/` (5 streams), `/api/streams/{name}/clients`, `/api/rules/*`, `/api/srt/status` — todos responden con el token de prueba y con credenciales reales `jsuarez`
- [x] 5.7 Configurar credenciales reales en `.env` (`MEDIASERVER_API_URL/USER/PASSWORD`) y verificar que `MediaserverApiService` trae datos reales (streams, clients, reglas, srt)
- [x] 5.8 Corregir mapeo stream↔canal: los slugs (`cine-dios`) NO coinciden con los stream names (`cinedios`); el mapeo real se extrae de `public_hls_url` y `virtualScreen.output_url` (patrón `live/<stream>`), con fallback al slug
- [x] 5.9 Corregir `Client\DashboardController` para pasar `effectiveChannelIds()` como array (devuelve Collection)
- [x] 5.10 Corregir partial: el detalle de viewers/agentes se renderiza desde `$msSelected` (no desde `$msStreams`)
- [x] 5.11 Agregar lista individual de clientes conectados por stream: IP, país, tipo de player, agente (user_agent), tiempo conectado y bitrate — enriquecida con `/api/admin/srs/clients` (country, user_agent) cruzada por IP con `/api/streams/{name}/clients`
- [x] 5.12 Renderizar la lista en el partial como tabla expandible ("Ver lista / Ocultar") con Alpine, visible en admin (stream seleccionado) y client (sus canales)
- [x] 5.13 Mejorar la tabla de clientes con estilo visual: bandera del país (emoji), badges de color por tipo de player (hls/rtmp/flv/webrtc/publish), búsqueda en vivo (IP, país, agente), ordenamiento por columna en ciclo asc → desc → normal (↕/▲/▼), contador "X de Y clientes", fila vacía "Sin resultados" y hover en filas
