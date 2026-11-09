# Design

## Origen de verdad: la API de MediaServer Platform

La matriz de URLs **no se reimplementa** en Cloudstream. Se toma tal cual de MediaServer Platform, que es el componente que conoce los puertos, el dominio público, el modo de deployment (strict/flexible) y las reglas de nombre de stream (recorte de sufijos `.m3u8`/`.flv`).

Endpoint usado: `GET /api/streams/{stream_name}` del panel (autenticado con el JWT que ya cachea `MediaserverApiService`). Devuelve el objeto del stream con el bloque:

```json
{
  "urls": {
    "stream": "cinedios",
    "publish": { "rtmp": "...", "srt": "...", "srt_full": "..." },
    "play": {
      "hls": "...", "flv": "...", "srt": "...", "webrtc_play": "...", "mpegts": "...",
      "via_proxy": {
        "https": { "hls": "...", "flv": "...", "webrtc_play": "...", "mpegts": "..." },
        "http":  { "hls": "...", "flv": "...", "webrtc_play": "...", "mpegts": "..." },
        "srt":   "..."
      }
    }
  }
}
```

El bloque llega anidado y con claves técnicas (`via_proxy.https.webrtc_play`); pintarlo directamente en el modal sería frágil. Por eso el controller **normaliza** el payload en una lista plana de filas:

```php
['group' => 'publish'|'play'|'proxy', 'label' => 'HLS (HTTPS)', 'url' => 'https://…', 'open' => true]
```

El frontend solo itera filas: agregar un protocolo nuevo en el panel es agregar una etiqueta en el mapa del controller, sin tocar el Blade.

## Mapeo canal → stream

El nombre de stream en MediaServer **no es el slug del canal**: es el basename de `public_hls_url` (`https://canal.mediaserver.com.co/live/cinedios.m3u8` → `cinedios`), con fallback al patrón `live/<stream>` de `virtualScreen.output_url` y, en último caso, al slug. Esa regla ya existe, privada, en `MediaserverMetricsAggregator::streamNameForChannel()`.

Se **extrae** a `App\Services\MediaserverStreamResolver` (una clase, un método) y el agregador pasa a usarla. Motivo: el dashboard y el modal deben mapear el canal al mismo stream; dos copias de la regla divergen en silencio cuando un canal cambia de nombre de stream.

## Un solo endpoint para las dos superficies (admin y client)

La regla del proyecto es que admin y client difieren **solo en el alcance de datos**. Aquí el alcance se resuelve en el controller, no en la vista:

- `admin` → cualquier canal.
- `client` → solo los canales de `effectiveChannelIds()` (`canAccessChannel`), con `abort(403)` si no.

Es el mismo patrón de `Api\VirtualScreenController::authorizeAccess()` que ya usa el modal "Ver vivo" (`GET /api/virtual-screens/{channelId}`), que se consume desde las dos superficies con la cookie de sesión (`auth:sanctum` acepta sesión para el mismo origen). Así hay **un** endpoint, **un** componente Blade y **un** contrato de payload; las dos vistas de Programación montan el mismo componente.

Esto evita la alternativa de dos endpoints (`/client/...` y `/admin/...`) con dos controllers y dos payloads que se desincronizan.

## Cuándo el canal no tiene stream en la plataforma

`GET /api/streams/{name}` responde **404** si el stream no existe en SRS, es decir cuando el canal no está publicando. Es un estado normal (un canal recién creado, o entre emisiones), no un error de la API.

En ese caso el modal:

1. Lo dice explícitamente ("este canal todavía no tiene stream en MediaServer Platform").
2. Ofrece la **URL pública que Cloudstream ya tiene configurada** (`channel.public_hls_url`) como única fila, etiquetada como tal.

No se recalculan en Cloudstream las URLs de publicación: para eso la plataforma es la fuente de verdad, y el `output_url` de la pantalla virtual puede apuntar a `127.0.0.1` (destino interno del daemon de emisión), que no sirve para un encoder externo. Reimplementar el builder del panel en Cloudstream duplicaría reglas que ya cambian (recorte de sufijos, dominio público, puertos SRT) — exactamente la deriva que el guardarraíl de `landing-delivery-urls-section` evita.

## Interacción del modal

- Se abre con `$store.modals.open('stream-urls', { channel_id, channel_name })`.
- El componente Alpine `streamUrls()` observa `$store.modals.current` y carga los datos **al abrir** (no en el render del Programador): la pantalla de Programación no paga el costo de una llamada al panel si el operador no abre el modal.
- Cada fila: etiqueta, URL en monoespaciado (truncada con `title` completo), botón **Copiar** (Clipboard API con respaldo `textarea` + `execCommand`) y, en las filas de reproducción, botón **Abrir** (`target="_blank"`).
- Estados obligatorios: cargando, error (API caída → mensaje con el motivo), sin stream, y tabla vacía.

## Consideración de Tailwind

`public/css/tailwind.min.css` es un bundle JIT estático: las clases nuevas que no estaban en el fuente del último build **se descartan en silencio**. Tras tocar el Blade hay que correr `npm run build:css` y `php artisan view:clear`, o el modal se renderiza sin fondos ni bordes.
