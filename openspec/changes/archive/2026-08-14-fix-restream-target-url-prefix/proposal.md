## Why

El módulo Restream está completamente roto en el front: cada `fetch()` JS contra los endpoints de `restream_targets` (start / stop / edit / delete) envía URLs sin el segmento `/restream/` del `prefix('restream')` del grupo de rutas. El servidor responde 404 antes de llegar al controller, así que **ninguna** mutación de un destino funciona desde el cliente ni desde el admin. El bug es de patrón: las URLs están hardcodeadas en JS, y cualquier futuro endpoint agrupado bajo un `prefix()` arriesga el mismo fallo.

## What Changes

- Centralizar las URLs de los endpoints de `restream_targets` (cliente y admin) en un único objeto JS `window.restreamUrls`, generado server-side con el helper `route()` de Laravel usando placeholders para los parámetros dinámicos.
- Reescribir los `fetch()` JS en `client/restream/index.blade.php`, `admin/restream/index.blade.php` y `components/restream-target-modal.blade.php` para consumir `window.restreamUrls.*` con `.replace('CID', …).replace('TID', …)`.
- No se cambian rutas, nombres de rutas, ni el agrupamiento por `prefix('restream')`/`middleware('restream.enabled')`.

## Capabilities

### New Capabilities
- `restream-js-url-templates`: convención para que las vistas JS consuman URLs generadas por `route()` desde un objeto `window.*Urls` expuesto por un blade `@once`, eliminando URLs hardcodeadas propensas a olvidar segmentos de prefijo.

### Modified Capabilities
(ninguna — el comportamiento HTTP de los endpoints no cambia, solo se corrige cómo el cliente arma la URL)

## Impact

- **Vistas afectadas**:
  - `resources/views/components/_restream_urls.blade.php` (nuevo, expone `window.restreamUrls`)
  - `resources/views/client/restream/index.blade.php` (3 fetch: start, stop, remove)
  - `resources/views/admin/restream/index.blade.php` (3 fetch: start, stop, remove)
  - `resources/views/components/restream-target-modal.blade.php` (2 URLs admin: create, update)
- **No afecta**: `routes/web.php`, los controllers, los tests, los datos, las migraciones, los nombres de ruta.
- **Sin nuevos paquetes**: no se instala Ziggy; se aprovecha el helper `route()` de Blade que ya usa el proyecto (p. ej. `media.thumbnails.generate`).
- **Mitigación de regresiones futuras**: si más adelante se renombra un endpoint o se mueve un grupo de rutas, el JS no necesita cambios — solo el blade que expone los templates.