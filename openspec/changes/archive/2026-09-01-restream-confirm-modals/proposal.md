# Proposal: Reemplazar `window.confirm()` por modales Alpine en acciones destructivas de Restream

## Why

Las acciones destructivas del módulo Restream (`remove`, `inactivate`, `forceDestroy`) usan `window.confirm()` del navegador para pedir confirmación. Estos diálogos nativos:

- Son visualmente inconsistentes con el resto del módulo, que ya usa modales Alpine (`<x-app-modal>` / `<x-restream-target-modal>`) y modales de confirmación (`<x-confirm-delete-modal>`) para medios.
- No se pueden personalizar ni tipográficamente ni cromáticamente (mismo aspecto en Chrome, Firefox, Edge, Brave).
- En el caso de `forceDestroy` obligan a encadenar dos `confirm()` seguidos, lo que es percibido como agresivo.
- Bloquean el hilo JS del navegador: si el usuario hace clic en cualquier parte del DOM mientras el diálogo está abierto, ese clic no se procesa hasta que se descarta.

La modularización de la confirmación destructiva ya existe en `<x-confirm-delete-modal>` (ver `resources/views/components/confirm-delete-modal.blade.php`). Vamos a generalizarla en un componente reusable `<x-confirm-modal>` y conectarla en el módulo Restream para `remove`, `inactivate` y `forceDestroy`.

## What Changes

- **New component `resources/views/components/confirm-modal.blade.php`**: confirmación Alpine configurable (título, mensaje, icono, color del icono, etiqueta del botón, color del botón, acción HTTP opcional). Si se le pasa `action`+`method`, hace `fetch` y despacha `crud-success`/`crud-error`. Si se le pasa `requireText` (texto a tipear para confirmar), sólo habilita el botón cuando el input coincide. Sin `action`, el caller provee un callback por evento global.
- **Refactor `resources/views/components/confirm-delete-modal.blade.php`** (opcional) para usar `<x-confirm-modal>` por debajo, evitando duplicación.
- **Client Restream index** (`resources/views/client/restream/index.blade.php`):
  - Reemplazar las 4 llamadas a `window.confirm()` (1 en `remove`, 1 en `inactivate`, 2 en `forceDestroy`) por `<x-confirm-modal>` + apertura vía `Alpine.store('modals').open('confirm', payload)`.
  - `forceDestroy` usa `requireText` para forzar al usuario a tipear `ELIMINAR` antes de habilitar el botón.
- **Admin Restream index** (`resources/views/admin/restream/index.blade.php`): mismo cambio, simétrico (regla dual-implementation).
- **No cambios de controlador, ruta, API, ni base de datos.**

## Capabilities

### New Capabilities
- `confirm-modal`: nuevo spec para el componente reusable de confirmación.

### Modified Capabilities
- `restream-targets`: añadir requisito de que las acciones destructivas del UI pasen por el modal de confirmación y no por `window.confirm()`.
- `restream-channel-context`: añadir requisito de que ninguna vista de restream-channel-context use `window.confirm()` como guard para acciones destructivas.

## Impact

- **Templates (4 files)**: nuevo componente, refactor opcional, y reemplazo de 4 + 4 llamadas a `confirm()` en client/admin.
- **Controller / API**: ninguno.
- **DB**: ninguno.
- **Visibilidad**: el usuario ve un modal en el mismo lenguaje visual que `<x-restream-target-modal>` y `<x-confirm-delete-modal>`, con icono, título, mensaje, y botón de acción rojo (peligroso) / amarillo (advertencia).
- **Riesgo**: si el componente tiene un bug, las acciones destructivas podrían ejecutarse sin confirmación → el safety net es que `<x-confirm-modal>` se monta en la misma página y abre vía `Alpine.store('modals').open('confirm', ...)`, igual que los modales existentes.
