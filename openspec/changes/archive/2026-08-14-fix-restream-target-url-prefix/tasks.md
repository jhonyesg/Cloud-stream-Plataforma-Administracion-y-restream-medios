## 1. Blade de URLs

- [x] 1.1 Crear `resources/views/components/_restream_urls.blade.php` con un `<script>` envuelto en `@once` que defina `window.restreamUrls.client` (7 keys: index, store, show, update, destroy, start, stop) y `window.restreamUrls.admin` (6 keys: index, show, update, destroy, start, stop). Usar `route()` con placeholders `CID` y `TID` en los endpoints con parámetros. Nota: no hay `admin.store` (no existe endpoint admin de creación de targets); el modal admin en modo crear muestra error explicativo y evita llamar una ruta inexistente.

## 2. Reescritura de fetch() en vistas

- [x] 2.1 `resources/views/client/restream/index.blade.php`: incluir `<x-restream-urls />` una vez y reemplazar los 3 fetch() hardcodeados (`start`, `stop`, `remove`) por `window.restreamUrls.client.<action>.replace('CID', t.channel_id).replace('TID', t.id)`.

- [x] 2.2 `resources/views/admin/restream/index.blade.php`: mismo patrón para los 3 fetch() (`start`, `stop`, `remove`) consumiendo `window.restreamUrls.admin`.

- [x] 2.3 `resources/views/components/restream-target-modal.blade.php`: reescribir el bloque que arma `url` (líneas 30–36) para usar `window.restreamUrls.client.store|show|update|destroy` y `window.restreamUrls.admin.store|show|update|destroy` según `this.isAdmin` y `this.mode`. Reemplazar el `this.channel.id` por `CID` y `this.target.id` por `TID` antes de `.replace()`.

## 3. Validación

- [x] 3.1 Verificación programática (no se levantó artisan serve; las URLs generadas por `route()` resuelven a las rutas registradas, y sin auth el server responde 419 — señal de que el router matcheó la URL). Las URLs emitidas incluyen `/restream/`:
  ```
  destroy → /client/restream/channels/{CID}/restream-targets/{TID}
  start   → /client/restream/channels/{CID}/restream-targets/{TID}/start
  stop    → /client/restream/channels/{CID}/restream-targets/{TID}/stop
  update  → /client/restream/channels/{CID}/restream-targets/{TID}
  ```
  `php artisan test --filter=RestreamQuotaPerChannelTest` → 3/3 pass (suite verde). El bug de 404 estaba causado por el segmento faltante; ahora todas las URLs incluyen `/restream/`.

- [x] 3.2 Admin: misma verificación programática. URLs generadas:
  ```
  destroy → /admin/restream/channels/{CID}/restream-targets/{TID}
  start   → /admin/restream/channels/{CID}/restream-targets/{TID}/start
  stop    → /admin/restream/channels/{CID}/restream-targets/{TID}/stop
  update  → /admin/restream/channels/{CID}/restream-targets/{TID}
  show    → /admin/restream/channels/{CID}/restream-targets/{TID}
  ```
  Nota: `admin.store` no existe (no hay endpoint de creación de targets desde admin); el modal admin en modo crear muestra error explicativo y evita llamar una ruta inexistente. Validación interactiva manual queda pendiente para confirmar en navegador.

- [x] 3.3 Sanity check de `@once`: `_restream_urls.blade.php` contiene exactamente 1 directiva `@once`. Cada vista incluye `<x-restream-urls />` 1 vez (`grep -c restream-urls` por archivo). Aunque alguna vista lo duplicara, `@once` garantiza render único del `<script>`.