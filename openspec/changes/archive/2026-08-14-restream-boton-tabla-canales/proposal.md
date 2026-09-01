## Why

La vista de canales del admin tiene dos modos: tarjetas y tabla. El botón "Configurar Restream" (que abre `<x-restream-modal>` para habilitar la cuota por canal) solo existe en la vista de tarjetas; en la vista tabla el acceso a Restream no aparece, obligando al admin a cambiar de modo o navegar a `/admin/restream`. El cambio `restream-quota-por-canal` dejó la habilitación anclada al canal, pero el punto de entrada quedó incompleto en la tabla.

## What Changes

- Añadir el botón de icono 📡 "Configurar Restream" a la fila de acciones de la **vista tabla** de `admin/channels/index.blade.php`, idéntico al de la vista tarjetas (mismo `@click="$store.modals.open('restream', ...)"`, mismo payload `channel_id` + `channel_name`, mismo estilo rose).
- El modal `<x-restream-modal />` ya está montado en la vista (línea 203), por lo que no se requiere montaje adicional.
- Sin cambios de backend: las rutas `/admin/channels/{channel}/restream` (GET/POST/PATCH/DELETE) ya existen y el controlador `RestreamQuotaController` ya recibe `Channel $channel`.

## Capabilities

### New Capabilities

- `restream-channel-table-access`: acceso a la configuración de Restream desde la vista tabla del módulo de canales del admin.

### Modified Capabilities

- `admin-channel-crud`: el requisito de acciones por fila se amplía para incluir el botón "Configurar Restream" en la vista tabla (hoy solo existe en tarjetas).

## Impact

- **Vista**: `resources/views/admin/channels/index.blade.php` — añadir un `<button>` en el bloque de acciones de la tabla (líneas ~168-187), replicando el botón de la vista tarjetas (línea 77).
- **Sin impacto en**: backend, rutas, modelos, migraciones, motor FFmpeg, ni en la vista cliente (el cliente gestiona destinos desde `/client/restream` con su selector de canal; no se toca).
