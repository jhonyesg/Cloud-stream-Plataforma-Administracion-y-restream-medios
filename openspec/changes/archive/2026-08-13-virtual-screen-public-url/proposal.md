## Why

El modal de "Pantalla Virtual" (VirtualScreenEditor) permite configurar la resolución, codecs, logo y URL de salida RTMP/SRT/HLS del canal, pero **no tiene campo para la URL pública M3U8** que usan los reproductores externos como Replanet. Esa URL (`channels.public_hls_url`) solo es editable desde el formulario de edición del canal, lo cual es inconsistente — el usuario espera configurar todo lo relacionado a "salida de stream" desde el mismo lugar.

## What Changes

- Se agrega un campo "URL pública HLS (player)" al modal de VirtualScreenEditor, junto al campo de URL de salida
- El modal envía `public_hls_url` al guardar, y el backend lo persiste en `channels.public_hls_url`
- El modal precarga el valor actual de `channel_public_hls_url` al abrir (ya viene en la respuesta del API `show`)
- Se unifica la experiencia: todo lo de output del canal en un solo lugar

## Capabilities

### New Capabilities
- `virtual-screen-public-url` (MODIFIED): El modal de pantalla virtual ahora permite editar la URL pública HLS del canal asociado, escribiéndola en `channels.public_hls_url`. No se crea nueva columna; se reutiliza la existente.

### Modified Capabilities
- `virtual-screen`: Se extiende el requisito "Editor de pantalla virtual accesible desde Canales" para incluir la edición de `public_hls_url` del canal vinculado, manteniendo idempotencia con el formulario de edición del canal.

## Impact

- **Backend**: `VirtualScreenController::update()` — acepta y persiste `public_hls_url` en el modelo `Channel`
- **Frontend**: `virtual-screen-editor-modal.blade.php` — agrega campo en la UI, estado Alpine, carga y guardado
- **Esquema DB**: Sin cambios (columna `channels.public_hls_url` ya existe desde migración `2026_08_12_172504_add_public_hls_url_to_channels`)
- **Live-viewer modal**: No se modifica; ya consume `channel_public_hls_url` desde `GET /api/virtual-screens/{channelId}`
