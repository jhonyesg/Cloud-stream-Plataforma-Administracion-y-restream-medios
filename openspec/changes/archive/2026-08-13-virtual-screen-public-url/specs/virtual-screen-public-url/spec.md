# virtual-screen-public-url Delta Spec

## ADDED Requirements

### Requirement: Edición de URL pública HLS desde el modal de pantalla

El modal de pantalla virtual SHALL permitir editar y persistir el campo `public_hls_url` del `Channel` asociado, de forma que el usuario pueda configurar la URL de playback M3U8 en el mismo lugar donde configura la URL de salida RTMP/SRT/HLS.

#### Scenario: Admin edita public_hls_url desde modal de pantalla
- **GIVEN** un admin tiene un canal con `public_hls_url` vacía
- **WHEN** abre el modal de pantalla virtual y llena el campo "URL pública HLS (player)"
- **AND** guarda los cambios
- **THEN** `channels.public_hls_url` SHALL ser actualizado con el valor ingresado
- **AND** la próxima lectura de `GET /api/virtual-screens/{channelId}` retornará ese valor en `channel_public_hls_url`

#### Scenario: Precarga de public_hls_url al abrir modal
- **GIVEN** un canal tiene `public_hls_url = "https://cdn.example.com/live/canal.m3u8"`
- **WHEN** un usuario abre el modal de pantalla virtual para ese canal
- **THEN** el campo "URL pública HLS (player)" SHALL mostrar ese valor precargado

## MODIFIED Requirements

### virtual-screen: Editor de pantalla virtual accesible desde Canales

Se extiende para incluir la edición de `public_hls_url` del canal vinculado.

#### Scenario: Admin edita public_hls_url desde el modal de pantalla
- **WHEN** un admin hace clic en "Pantalla" en la fila de un canal
- **AND** llena el campo "URL pública HLS (player)"
- **AND** guarda
- **THEN** `channels.public_hls_url` SHALL ser actualizado

## Notes

- No se crea columna nueva en `virtual_screens`; el valor persiste en `channels.public_hls_url`
- La validación reutiliza las reglas existentes: `nullable|string|max:500|url`
- El live-viewer modal no requiere cambios; ya consume `channel_public_hls_url`
