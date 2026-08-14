## ADDED Requirements

### Requirement: Codec de video configurable por canal
El sistema SHALL persistir y exponer un campo `codec_video` en `virtual_screens` de tipo `varchar(30)`, nullable, con default `'libx264'`. El playout engine SHALL usar este valor como argumento `-c:v` en el comando FFmpeg. Si el valor es NULL, el engine SHALL usar `libx264` como fallback.

#### Scenario: Codec de video configurado a libx265
- **WHEN** admin guarda `codec_video='libx265'` en la pantalla virtual de un canal
- **THEN** el comando FFmpeg generado SHALL contener `-c:v libx265`

#### Scenario: Codec de video no configurado (NULL)
- **WHEN** un canal existente no tiene `codec_video` (NULL, previo a la migración)
- **THEN** el comando FFmpeg generado SHALL contener `-c:v libx264` (default)

#### Scenario: Codec de video inválido
- **WHEN** admin intenta guardar `codec_video='invalid-codec'`
- **THEN** el sistema SHALL rechazar con error de validación 422

### Requirement: Codec de audio configurable por canal
El sistema SHALL persistir y exponer un campo `codec_audio` en `virtual_screens` de tipo `varchar(30)`, nullable, con default `'aac'`. El playout engine SHALL usar este valor como argumento `-c:a` en el comando FFmpeg. Si el valor es NULL, el engine SHALL usar `aac` como fallback.

#### Scenario: Codec de audio configurado a libmp3lame
- **WHEN** admin guarda `codec_audio='libmp3lame'` en la pantalla virtual de un canal
- **THEN** el comando FFmpeg generado SHALL contener `-c:a libmp3lame`

#### Scenario: Codec de audio no configurado (NULL)
- **WHEN** un canal existente no tiene `codec_audio` (NULL, previo a la migración)
- **THEN** el comando FFmpeg generado SHALL contener `-c:a aac` (default)

### Requirement: Editor de pantalla virtual ofrece dropdowns para codec
El editor de pantalla virtual SHALL renderizar controles `<select>` para `codec_video` y `codec_audio` con las siguientes opciones:

- **Video**: `libx264`, `libx265`, `libvpx-vp9`
- **Audio**: `aac`, `libmp3lame`, `libopus`, `libvorbis`, `copy`

El valor por defecto al crear una nueva configuración SHALL ser `libx264` para video y `aac` para audio.

#### Scenario: Dropdown de codec de video muestra opciones
- **WHEN** admin abre el editor de pantalla virtual
- **THEN** el control de codec de video SHALL ser un `<select>` con las opciones `libx264`, `libx265`, `libvpx-vp9`

#### Scenario: Dropdown de codec de audio muestra opciones
- **WHEN** admin abre el editor de pantalla virtual
- **THEN** el control de codec de audio SHALL ser un `<select>` con las opciones `aac`, `libmp3lame`, `libopus`, `libvorbis`, `copy`

#### Scenario: Codecs se persisten al guardar
- **WHEN** admin selecciona `codec_video='libx265'` y `codec_audio='libopus'` y guarda
- **THEN** la API SHALL persistir ambos valores y el comando FFmpeg preview SHALL reflejar `-c:v libx265` y `-c:a libopus`
