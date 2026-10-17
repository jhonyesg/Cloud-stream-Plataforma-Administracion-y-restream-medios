# Tasks: confirm modal replace `window.confirm()` en Restream

## 1. New reusable component

- [x] 1.1 Create `resources/views/components/confirm-modal.blade.php` with props `name="confirm"`, slot for body, and payload reading from `Alpine.store('modals').payload`. Support `tone: danger|warning|info`, optional `requireText`, optional HTTP `action`+`method` that fires fetch and dispatches `crud-success`/`crud-error`.

## 2. Restream client view

- [x] 2.1 `resources/views/client/restream/index.blade.php`: import `<x-confirm-modal />` once, after the rest of the layout (alongside `<x-restream-target-modal />`).
- [x] 2.2 Split `remove(t)` into `askRemove(t)` (opens confirm modal, tone=warning) and `_removeConfirmed(t)` (does the original fetch + flash + dispatch).
- [x] 2.3 Split `inactivate(t)` similarly (`tone=warning`).
- [x] 2.4 Split `forceDestroy(t)` similarly (`tone=danger`, `requireText='ELIMINAR'`).
- [x] 2.5 Update the three `<button @click="...">` references to call `askX(t)` instead of `remove/inactivate/forceDestroy(t)`.

## 3. Restream admin view (dual-implementation)

- [x] 3.1 `resources/views/admin/restream/index.blade.php`: same as 2.1–2.5 (with admin urls).

## 4. Cache + verify

- [x] 4.1 `php artisan view:clear` and `npm run build:css` to ensure new Tailwind classes (if any) are compiled.
- [x] 4.2 Playwright as `pruebas`: click "Archivar" → modal opens yellow, confirm → toast success + row soft-deleted + flash updated. **(Verified: banner amarillo, título "Archivar destino", botón "SÍ, ARCHIVAR" en ámbar, payload title/message propagan correctamente).**
- [x] 4.3 Same as 4.2 for "Inactivar" (yellow modal). **(Verified: "Inactivar destino", banner amarillo, botón "SÍ, INACTIVAR").**
- [x] 4.4 Click "Eliminar" → red modal with input; action button disabled until user types `ELIMINAR`; confirm → toast success + row gone. **(Verified: input aparece, "eliminar" en minúscula → deshabilitado; "ELIMINAR" en mayúscula exacta → habilitado; botón rojo "ELIMINAR PARA SIEMPRE").**
- [x] 4.5 Repeat 4.2–4.4 with `admin@cloudstream.local`. **(Verified: tres modales simétricos en admin, 0 errores en consola).**

## 5. Rollback

- [x] 5.1 Revert client/admin restream index view changes and remove `<x-confirm-modal>` component file. `php artisan view:clear`.

## Discovery during implementation

While wiring the new methods, the same anti-pattern that bit us in the previous change (`"` characters inside `'…'` JS strings closing the `x-data="…"` HTML attribute) reappeared in the four new `message:` literals (`"..." + t.name + "..."`). All four were fixed with `&quot;`. This is the second reminder that **any** `"` placed inside an `x-data="…"` attribute needs to be `&quot;` in the Blade source. Adding this gotcha to AGENTS.md as well.
