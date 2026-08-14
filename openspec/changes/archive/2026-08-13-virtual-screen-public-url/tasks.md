## 1. Backend — VirtualScreenController

- [x] 1.1 En `VirtualScreenController::update()`, agregar al array de validación: `'public_hls_url' => ['nullable', 'string', 'max:500', 'url']`
- [x] 1.2 Después de `$screen = VirtualScreen::updateOrCreate(...)`, agregar: `if (isset($data['public_hls_url'])) { $channel->update(['public_hls_url' => $data['public_hls_url']]); }`
- [x] 1.3 Verificar que `enrichScreen()` ya retorna `channel_public_hls_url` (línea 167) — no requiere cambios

## 2. Frontend — VirtualScreenEditorModal

### 2.1 Alpine data: agregar estado

- [x] 2.1.1 Agregar `publicHlsUrl: ''` a la sección de estado inicial del objeto Alpine (junto a `outputUrl`, `outputProtocol`, etc.)

### 2.2 UI: agregar campo al formulario

- [x] 2.2.1 Después del bloque `<div>` de "URL de salida" (línea 77-82), agregar un nuevo bloque con el campo URL pública HLS

### 2.3 load(): precargar valor

- [x] 2.3.1 En la función `load()`, después de asignar `this.outputUrl = d.output_url || ''` (línea 463), agregar: `this.publicHlsUrl = d.channel_public_hls_url || ''`

### 2.4 save(): enviar en el body

- [x] 2.4.1 En la función `save()`, agregar `public_hls_url: this.publicHlsUrl,` al objeto `body` (después de `output_url`)

## 3. Verificación

- [x] 3.1 Probar: abrir modal de pantalla de un canal con `public_hls_url` ya configurado → el campo debe aparecer precargado
- [x] 3.2 Probar: guardar con valor nuevo → verificar en la DB que `channels.public_hls_url` se actualizó
- [x] 3.3 Probar: abrir el live-viewer del mismo canal → el modal debe reproducir usando la URL actualizada
- [x] 3.4 Probar: `php artisan view:cache` y `view:clear` después del deploy
