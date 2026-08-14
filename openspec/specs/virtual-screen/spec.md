# virtual-screen Specification

## Purpose

La pantalla virtual (`VirtualScreen`) define la configuración de salida de un canal:
resolución, framerate, codecs, bitrates, URL de salida, logo y fallback. Es la
configuración que un futuro motor de emisión consumirá para publicar el stream.

La capacidad está **activa**. El editor de pantalla virtual se gestiona desde la
vista de **Canales** (botón "Pantalla" por canal, admin y client), no desde un
módulo de emisión dedicado (este último fue retirado el 2026-07-20 y se
reconstruirá desde cero).

## Modelo de datos

- tabla PostgreSQL  →  `virtual_screens`
- clase Eloquent    →  `App\Models\VirtualScreen`
- URL API           →  `/api/virtual-screens/{channelId}` (show / update / test-preview)
- modal Alpine      →  `<x-virtual-screen-editor-modal>` (store `virtual-screen-editor`)
- texto UX botón   →  "Pantalla" (en la tabla de canales)

Campos clave: `channel_id, name, width, height, layout, theme, output_protocol,
output_url, fps, video_bitrate_kbps, audio_bitrate_kbps, codec_video, codec_audio,
logo_media_item_id, logo_x/y/w/h, logo_opacity, fallback_type,
fallback_media_item_id`.

## Requirements

### Requirement: Editor de pantalla virtual accesible desde Canales
El sistema SHALL permitir configurar la pantalla virtual de cada canal desde la
vista de gestión de Canales, tanto en el rol admin como en el rol client
(restringido a owners en client).

#### Scenario: Admin abre editor de pantalla
- **WHEN** un admin hace clic en "Pantalla" en la fila de un canal
- **THEN** se abre el modal `virtual-screen-editor` con la configuración actual
  del canal y permite guardar cambios vía `PUT /api/virtual-screens/{channelId}`

#### Scenario: Client owner abre editor de pantalla
- **WHEN** un client propietario del canal hace clic en "Pantalla"
- **THEN** se abre el mismo modal de edición
- **AND** un client no propietario SHALL ver "solo lectura" (sin botón Pantalla)

### Requirement: Independencia del motor de emisión
La pantalla virtual es únicamente configuración persistida. No ejecuta emisión
ni depende de ningún daemon/supervisor. El motor de emisión (cuando se
reimplemente) SHALL consumir `VirtualScreen` como input, no acoplarlo.

#### Scenario: Motor de emisión ausente
- **WHEN** no existe motor de emisión activo
- **THEN** la pantalla virtual SHALL seguir siendo editable y persistible
  sin errores