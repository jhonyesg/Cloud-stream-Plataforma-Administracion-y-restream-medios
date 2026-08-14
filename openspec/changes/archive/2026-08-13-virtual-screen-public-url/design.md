## Context

El modal `VirtualScreenEditor` vive en `resources/views/components/virtual-screen-editor-modal.blade.php`. Se abre desde la vista de canales (admin y client) con el botón "Pantalla". Envía un `PUT /api/virtual-screens/{channelId}` al guardar.

El API `VirtualScreenController::show()` ya retorna `channel_public_hls_url` (denormalizado desde `Channel`) en la línea 167 de `app/Http/Controllers/Api/VirtualScreenController.php`. El campo es de solo lectura en la UI actual.

El backend ya tiene validado `public_hls_url` en los requests de canal (`StoreChannelRequest`, `UpdateChannelRequest`, `Client/ChannelController`).

## Goals / Non-Goals

**Goals:**
- Agregar campo `public_hls_url` al modal de VirtualScreenEditor
- Guardar el valor en `channels.public_hls_url` al hacer PUT en virtual-screens
- Mantener coherencia: el mismo valor visible/editado desde el formulario de canal y desde el modal de pantalla

**Non-Goals:**
- No crear columna nueva en `virtual_screens` (el valor vive en `channels`)
- No modificar el live-viewer modal (ya funciona con `channel_public_hls_url`)
- No agregar lógica de validación adicional más allá de lo que ya existe en requests de canal

## Decisions

### Decisión 1: Puente — el modal de VirtualScreen actualiza `channels.public_hls_url`

**Alternativa descartada:** Crear columna `public_hls_url` en `virtual_screens` (duplicación de datos).

**Elección:** Reutilizar `channels.public_hls_url` existente. El modal actúa como puente: recibe `channelId` → busca el canal → actualiza su `public_hls_url`. Evita duplicación y mantiene una sola fuente de verdad.

### Decisión 2: API — un solo PUT, dos destinos de escritura

**Alternativa descartada:** Crear un endpoint separado `PATCH /api/channels/{id}/public-url`.

**Elección:** Enviar `public_hls_url` dentro del body de `PUT /api/virtual-screens/{channelId}`. El controller hace `updateOrCreate` en `virtual_screens` y luego `$channel->update(['public_hls_url' => ...])`. Un solo request desde la UI.

### Decisión 3: Validación — reutilizar las reglas existentes

Se usan las reglas ya definidas en `StoreChannelRequest`/`UpdateChannelRequest`:
```
'public_hls_url' => ['nullable', 'string', 'max:500', 'url']
```

Se copian directamente en `VirtualScreenController::update()`.

## Risks / Trade-offs

- **[Riesgo]** Si el usuario edita `public_hls_url` desde el formulario de canal Y desde el modal de pantalla simultáneamente, gana el último guardado (condición de carrera). → **Mitigación:** Aceptable; es el mismo comportamiento que otros campos compartidos. No se introduce problema nuevo.
- **[Riesgo]** El `output_url` (RTMP push) y `public_hls_url` (playback M3U8) podrían confundirse conceptualmente. → **Mitigación:** Se separan visualmente con labels claros: "URL de salida" (push) y "URL pública HLS (player)" (playback).

## Migration Plan

1. Sin migración de DB (columna ya existe)
2. Despliegue estándar: archivos PHP + Blade
3. Rollback: revertir los cambios de código

## Open Questions

Ninguna. La columna existe, el API endpoint existe, la validación existe.
