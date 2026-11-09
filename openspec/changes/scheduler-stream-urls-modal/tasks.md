# Tasks

## 1. Backend: lectura de URLs en MediaServer Platform

- [x] 1.1 Añadir `MediaserverApiService::getStream(string $name)` (consume `GET /api/streams/{name}`) y `getStreamUrls(string $name)` (devuelve el bloque `urls`), y verificar con `php artisan tinker` que para `cinedios` devuelve el bloque con `publish` y `play.via_proxy`
- [x] 1.2 Crear `App\Services\MediaserverStreamResolver::streamNameForChannel(Channel $channel): string` con la regla basename de `public_hls_url` → patrón `live/<stream>` de `virtualScreen.output_url` → slug, y verificar que para el canal "Cine Dios" devuelve `cinedios`
- [x] 1.3 Refactorizar `MediaserverMetricsAggregator` para usar el resolver compartido (borrar su copia privada) y verificar que el dashboard sigue mapeando los mismos streams (`cinedios`, `oracionconson`)

## 2. Endpoint con scoping

- [x] 2.1 Crear `app/Http/Controllers/Api/MediaserverStreamController::show()` que devuelve `channel`, `stream`, `available`, `live`, `clients`, `rows` (lista normalizada) y `urls` (payload crudo), y verificar con `php artisan tinker` que para el canal "Cine Dios" devuelve las filas de publicación y de reproducción
- [x] 2.2 Implementar `authorizeAccess()` con el patrón de `Api\VirtualScreenController` (admin ve todo; client solo `canAccessChannel`), y verificar que un client sin acceso recibe 403
- [x] 2.3 Normalizar el payload del panel en filas (`group`, `label`, `url`, `open`) con los grupos `publish` (solo RTMP) y `proxy` (HTTPS, HTTP y SRT de lectura); las URLs directas al servidor por IP y el SRT de publicación quedan fuera — y verificar que el modal no expone ni la IP pública de reproducción ni el SRT de publicación
- [x] 2.4 Degradar sin stream (404 del panel): responder 200 con `available: false` y un mensaje explicativo más la fila con `channel.public_hls_url`, y verificar que el modal no muestra un error genérico
- [x] 2.5 Registrar la ruta `GET /api/channels/{channelId}/mediaserver/urls` en `routes/api.php` dentro del grupo `auth:sanctum`, y verificar con `php artisan route:list` que la ruta existe y exige autenticación

## 3. Modal (componente compartido)

- [x] 3.1 Crear `resources/views/components/stream-urls-modal.blade.php` con `<x-app-modal name="stream-urls">` y el componente Alpine `streamUrls()` que carga los datos al abrir el modal
- [x] 3.2 Renderizar los tres grupos de filas con la URL en monoespaciado, botón **Copiar** por fila (Clipboard API con respaldo `textarea` + `execCommand`) y botón **Abrir** en las filas de reproducción
- [x] 3.3 Implementar los estados de carga, error (API no disponible), sin stream en la plataforma y sin filas
- [x] 3.4 Verificar por `grep` que el nombre del modal (`stream-urls`) coincide entre `open('stream-urls', …)` y el atributo `name` del componente

## 4. Integración en Programación (admin y client)

- [x] 4.1 Añadir el botón "URLs" en la barra de herramientas de `resources/views/client/scheduler/index.blade.php`, junto a los demás actions del canal seleccionado, y verificar que abre el modal con el `channel_id` correcto
- [x] 4.2 Replicar el botón en `resources/views/admin/scheduler/index.blade.php` con el mismo patrón visual
- [x] 4.3 Montar `<x-stream-urls-modal />` en **ambas** vistas antes del cierre del layout, y verificar con `grep` que cada `modals.open('stream-urls'` tiene su `<x-stream-urls-modal />` correspondente
- [x] 4.4 Regenerar el bundle de Tailwind (`npm run build:css`) y limpiar vistas compiladas (`php artisan view:clear`), y verificar por `grep` que las clases nuevas del modal aparecen en `public/css/tailwind.min.css`

## 5. Pruebas y guardarraíles

- [x] 5.1 Crear `tests/Feature/MediaserverStreamUrlsTest.php` cubriendo: normalización de filas con un `MediaserverApiService` simulado, ausencia de URLs directas al servidor y del SRT de publicación, 403 para un client sin acceso al canal, respuesta degradada cuando el canal no tiene stream, y presencia del botón y del modal en las dos vistas de Programación
- [x] 5.2 Ejecutar `php artisan test --filter=MediaserverStreamUrlsTest` y la suite completa, y verificar que no hay regresiones
- [ ] 5.3 (pendiente de verificación manual en navegador) Smoke test admin: abrir `/admin?channel_id=…` → "URLs" → el modal lista publicación y reproducción, y "Copiar" deja la URL en el portapapeles
- [ ] 5.4 (pendiente de verificación manual en navegador) Smoke test client: abrir `/client/scheduler?channel_id=…` → "URLs" → mismo comportamiento y el mismo conjunto de filas

## 6. Verificación de las URLs que muestra el modal (2026-11-09)

- [x] 6.1 RTMP de publicación (`rtmp://15.204.8.139:1935/live/cinedios`): el puerto 1935 acepta conexiones y es el mismo destino que usa el daemon de emisión
- [x] 6.2 HLS por dominio (HTTPS y HTTP): HTTP 200 `application/vnd.apple.mpegurl` y decodificación real con ffmpeg (288 frames en 12 s, sin errores de PPS)
- [x] 6.3 HTTP-FLV por dominio (HTTPS y HTTP): HTTP 200 `video/x-flv` y decodificación real con ffmpeg (192 frames en 8 s)
- [x] 6.4 MPEG-TS por dominio (HTTPS y HTTP): HTTP 200 `video/mp2t` y decodificación real con ffmpeg (233 frames en 12 s; un aviso de SPS/PPS al entrar a mitad de GOP, que se recupera)
- [x] 6.5 SRT de lectura por dominio (`srt://canal.mediaserver.com.co:7386?streamid=read:cinedios`): pull real con ffmpeg dentro del container (H264 1280x720 24 fps + AAC, 91 frames en 5 s)
- [x] 6.6 WebRTC por dominio: nginx `/rtc/` → passthrough FastAPI → SRS `:1986`, con `rtc_server enabled on`; el `POST` sin oferta SDP devuelve el JSON de SRS (`code: 400`), que es lo esperado fuera de un navegador

## Workflow follow-up

- Archivar el change cuando la implementación esté revisada y los tests en verde.
