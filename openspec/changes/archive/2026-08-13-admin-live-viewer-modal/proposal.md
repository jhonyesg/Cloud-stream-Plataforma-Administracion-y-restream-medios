## Why

Hoy los administradores no pueden previsualizar la emisión de un canal desde dentro
del panel. El `VirtualScreen` ya guarda `output_url` (HLS/RTMP) pero la única forma
de "ver" un canal es abrir el `.m3u8` en una pestaña externa (`https://canal.mediaserver.com.co/live/{slug}.m3u8`)
sin saber si hay emisión activa ni a qué resolución/bitrate. En `admin/channels`
las acciones son texto plano con color (Editar / Pantalla / Archivar) y rompen el
lenguaje visual del resto del admin (chips circulares con icono del calendario del
scheduler). En `admin/scheduler` el bloque "Iniciar / Detener / Log" tampoco
expone un atajo para confirmar visualmente que la emisión está al aire.

## What Changes

- Nuevo componente `<x-live-viewer-modal>` que reproduce la salida HLS del canal
  dentro de un modal del admin (HLS.js desde CDN con fallback a `<video>` nativo
  para Safari/iOS). Muestra la URL pública, el estado de emisión, y permite
  abrir en pestaña nueva.
- Reemplazar los 3 botones de acción en `admin/channels/index.blade.php`
  (Editar / Pantalla / Archivar, hoy texto con color) por iconos circulares
  consistentes con el calendario del scheduler, y añadir un cuarto icono
  "Ver en vivo" que abre el nuevo modal.
- Añadir un botón "Ver en vivo" en el bloque de control de emisión de
  `admin/scheduler/index.blade.php`, al final de la fila Iniciar/Detener/Log.
  Visible siempre; deshabilitado si `emission_state.status === 'offline'`.
- La URL HLS se resuelve así: si el canal tiene `VirtualScreen.output_protocol = 'hls'`
  y `output_url` definida, se usa esa URL; si no, se muestra un aviso
  "El canal aún no tiene salida HLS configurada" con CTA hacia el editor virtual.
- Sin cambios en BD, sin migraciones, sin nuevas rutas Laravel
  (el `.m3u8` lo sigue sirviendo nginx-rtmp fuera de la app).

## Alcance del cliente (ampliado tras feedback)

El mismo problema existe en el lado cliente: `client/channels/index.blade.php`
tiene los mismos botones de acción como texto+icono pequeño (líneas 109-128)
y `client/scheduler/index.blade.php` tiene el bloque de control de emisión
(Iniciar/Detener/Log, líneas 100-140) sin botón "Ver vivo". Por consistencia
visual y de funcionalidad, esta propuesta también cubre:

- `client/channels/index.blade.php`: añadir botón "Ver vivo" (icono circular
  sky) en la columna Acciones para canales donde el usuario tiene acceso
  (owner o asignado), e incluir `<x-live-viewer-modal />` en el layout.
- `client/scheduler/index.blade.php`: añadir el mismo botón "Ver vivo" en el
  bloque de control de emisión, con el mismo binding de estado deshabilitado.
- `Client/ScheduleController`: exponer `currentChannelSlug` y
  `currentChannelName` igual que el admin.

## URL pública HLS por canal (ampliado tras feedback 2)

El primer prototipo asumía que `VirtualScreen.output_url` era la URL pública
del player. **No lo es**: ese campo es el destino donde FFmpeg publica
(stream push), mientras que la URL pública de playback es servida por
nginx-rtmp leyendo el slug del canal desde el path `/live/{slug}.m3u8`.
Ningún modelo guardaba esa URL hasta ahora. Decisión:

- Agregar columna `public_hls_url` (string 500, nullable) a `channels` mediante
  migración aditiva (sin `WithDataSafetySnapshot` — no es destructiva).
- Exponerla en el formulario de creación/edición de canal del admin
  (campo opcional con texto de ayuda explicando que es la URL `.m3u8` que
  ven los espectadores finales).
- Denormalizarla en la respuesta de `GET /api/virtual-screens/{channelId}`
  para que el live-viewer modal la consuma sin pedir un endpoint adicional.
- Actualizar el modal live-viewer para preferir `channel.public_hls_url` y
  caer al comportamiento previo (`VirtualScreen.output_url` cuando
  `output_protocol === 'hls'`) solo como fallback.
- La validación es `nullable|string|max:500|url` — URL completa, opcional.

## Capabilities

### New Capabilities
- `live-viewer`: capacidad para que un admin previsualice la salida HLS de un canal
  desde el panel, con detección de "hay emisión" / "sin emisión" y fallback
  graceful cuando el navegador no soporta HLS.js.

### Modified Capabilities
- (ninguna — no se modifican requirements de specs existentes; sólo se añaden
  dos botones a vistas que no tienen spec propia).

## Impact

- Vistas editadas:
  - `resources/views/admin/channels/index.blade.php` (columna Acciones)
  - `resources/views/admin/scheduler/index.blade.php` (bloque emission control)
- Vista nueva:
  - `resources/views/components/live-viewer-modal.blade.php`
- Dependencias externas:
  - `https://cdn.jsdelivr.net/npm/hls.js@1.5.13` (cargado on-demand sólo cuando
    se abre el modal; sin build pipeline, sin `package.json`)
- APIs consumidas (ya existen):
  - `GET /api/virtual-screens/{channelId}` → para resolver `output_url`
  - `GET /api/channels/{channelId}/emission/status` → para saber si hay emisión
- Sin migraciones, sin cambios en modelos, sin nuevas rutas Laravel.
- Compatible con Safari/iOS (HLS nativo) y Chrome/Firefox/Edge (HLS.js).