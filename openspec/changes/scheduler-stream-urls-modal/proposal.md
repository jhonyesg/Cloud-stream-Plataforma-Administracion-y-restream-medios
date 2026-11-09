# Proposal

## Why

MediaServer Platform calcula, para cada stream, el juego completo de URLs de **publicación** (RTMP `:1935`, SRT `:10080`) y de **reproducción** (HLS, HTTP-FLV, SRT de lectura `:7386`, WebRTC y MPEG-TS, cada una en variante directa por IP y en variante HTTPS por el dominio público). Esa matriz hoy vive únicamente dentro del panel de MediaServer Platform: el operador de Cloudstream tiene que cambiar de aplicación, buscarse el canal a mano y copiar URLs que luego pega —a mano también— en OBS, vMix o el decodificador del cliente.

En el módulo de **Programación** el usuario ya está mirando el canal sobre el que va a trabajar (la plantilla, la playlist del día, la pantalla virtual). Es el lugar natural para pedirle las URLs de ese canal: un botón que abre un modal con todas las opciones, cada una con su botón de copiar, sin salir de la pantalla y sin depender de que el canal esté emitiendo.

El problema operativo concreto: **copiar una URL larga y con query string a mano desde otra pestaña es la fuente de error más común** — una `streamid` mal escrita en SRT o una URL HLS con el sufijo `.m3u8` duplicado rompen la emisión sin aviso.

## What Changes

- **Nuevo endpoint** `GET /api/channels/{channelId}/mediaserver/urls` que resuelve el canal → nombre de stream, consulta MediaServer Platform y devuelve las URLs **normalizadas en filas** (grupo, etiqueta, URL) listas para pintar.
- **Nuevo método de servicio** `MediaserverApiService::getStream()`/`getStreamUrls()` que consume `GET /api/streams/{name}` del panel (el endpoint que ya devuelve el bloque `urls`).
- **Nuevo resolver compartido** `App\Services\MediaserverStreamResolver` con el mapeo canal → nombre de stream (el mismo que ya usa el agregador de métricas del dashboard, extraído para no duplicar la regla).
- **Nuevo componente Blade** `resources/views/components/stream-urls-modal.blade.php`: modal con los tres grupos (`PUBLICAR`, `REPRODUCIR directo`, `REPRODUCIR vía dominio HTTPS`), botón **Copiar** por fila (Clipboard API con respaldo `textarea` + `execCommand`) y botón **Abrir** en las filas de reproducción.
- **Botón "URLs"** en la barra de herramientas del módulo Programación, en **admin y client**, que abre el modal con el canal seleccionado.
- **Degradación explicita**: si el canal todavía no existe como stream en MediaServer Platform, el modal lo dice y ofrece la URL pública que Cloudstream ya tiene configurada para ese canal, en vez de un error genérico.

**Non-goals**: no se cambian las URLs que el panel genera (la matriz es la del panel, no una copia); no se editan ni se persisten URLs desde el modal — es de lectura y copia; no se agrega el botón a los listados de canales (solo a Programación, que es lo pedido); no se publica ninguna de estas URLs en el sitio público.

## Capabilities

### New Capabilities
- `scheduler-stream-urls`: modal de URLs de un canal en el módulo de Programación — origen de verdad (MediaServer Platform), normalización en filas, copiar por fila, degradación cuando el canal no tiene stream.

### Modified Capabilities
- `mediaserver-api-injection`: `MediaserverApiService` expone la lectura del detalle de un stream y de su bloque `urls`.

## Impact

- **Servicios**: `app/Services/MediaserverApiService.php` (nuevos métodos `getStream()`, `getStreamUrls()`), `app/Services/MediaserverStreamResolver.php` (nuevo), `app/Services/MediaserverMetricsAggregator.php` (usa el resolver compartido en vez de su copia privada).
- **Controller API**: `app/Http/Controllers/Api/MediaserverStreamController.php` (nuevo) — scoping por rol: admin ve cualquier canal, client solo los de `effectiveChannelIds()`.
- **Rutas**: `routes/api.php` — `GET /api/channels/{channelId}/mediaserver/urls` dentro del grupo `auth:sanctum` (mismo grupo que `/api/virtual-screens/{channelId}`, que el modal "Ver vivo" ya consume con la cookie de sesión).
- **Vistas**: `resources/views/components/stream-urls-modal.blade.php` (nuevo), `resources/views/client/scheduler/index.blade.php` y `resources/views/admin/scheduler/index.blade.php` (botón + montaje del modal).
- **Tests**: `tests/Feature/MediaserverStreamUrlsTest.php` (nuevo) — normalización de filas, scoping client/admin, degradación sin stream, y presencia del botón y del modal en las dos vistas de Programación.
- **Assets**: `public/css/tailwind.min.css` se regenera con `npm run build:css` (bundle JIT estático; las clases nuevas se descartan hasta entonces).
- Sin cambios de base de datos, esquema ni de plataforma.
