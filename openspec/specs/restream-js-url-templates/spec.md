# restream-js-url-templates Specification

## Purpose

Convención para que los blades que consumen endpoints agrupados bajo un `Route::prefix()` no hardcodeen URLs en JS. El backend genera las URLs vía `route()` y las expone como objeto global; el JS las consume con `.replace()` para los parámetros dinámicos. Elimina la clase de bug "se olvidó el segmento del prefijo".

## ADDED Requirements

### Requirement: Blade expone URLs generadas por route() en window.*Urls

El sistema SHALL proveer un blade componente (convención `_xxx_urls.blade.php`) que, al incluirse vía `<x-xxx-urls />` y envolver su `<script>` con `@once`, defina `window.xxxUrls` como un objeto cuyas propiedades son las URLs de los endpoints del módulo, generadas con el helper `route()` de Laravel. Para endpoints con parámetros dinámicos (channel, target, etc.), la URL SHALL contener placeholders tipo `CID` / `TID` en la posición de cada parámetro.

#### Scenario: URL con parámetro dinámico incluye placeholder
- **WHEN** el blade genera la URL para `route('client.restream.channels.destroy', ['channel' => 'CID', 'target' => 'TID'])`
- **THEN** el valor publicado en `window.restreamUrls.client.destroy` contiene los placeholders `CID` y `TID` exactamente donde el router espera los UUIDs

#### Scenario: URL sin parámetro dinámico no incluye placeholder
- **WHEN** el blade genera la URL para `route('client.restream.channels.index')`
- **THEN** el valor publicado en `window.restreamUrls.client.index` es la URL completa sin placeholders

#### Scenario: Inclusión múltiple no duplica el script
- **WHEN** una vista incluye `<x-restream-urls />` dos o más veces
- **THEN** el `<script>` que define `window.restreamUrls` se renderiza una sola vez en el HTML

### Requirement: JS consume URLs vía window.*Urls con replace()

Las vistas SHALL consumir las URLs de endpoints con parámetros dinámicos leyendo `window.xxxUrls.<scope>.<action>` y aplicando `.replace('<PLACEHOLDER>', '<valor>')` por cada parámetro. Las URLs SHALL NO armarse concatenando strings hardcodeados en JS.

#### Scenario: Fetch de delete usa window.restreamUrls
- **WHEN** el usuario hace clic en "Eliminar destino" en el listado cliente
- **THEN** el JS arma la URL como `window.restreamUrls.client.destroy.replace('CID', t.channel_id).replace('TID', t.id)` y la usa en `fetch(url, { method: 'DELETE', … })`
- **AND** el servidor responde 2xx (no 404)

#### Scenario: Fetch de start/stop usa window.restreamUrls
- **WHEN** el usuario hace clic en "Iniciar" o "Detener" destino
- **THEN** el JS arma la URL leyendo `window.restreamUrls.<scope>.start` o `.stop` con `.replace()` para `CID` y `TID`
- **AND** el servidor responde 2xx

#### Scenario: Modal de creación/edición usa window.restreamUrls
- **WHEN** el modal de destino envía el form en modo crear o editar (scope cliente o admin)
- **THEN** el JS arma la URL leyendo `window.restreamUrls.<scope>.store` o `.update` con `.replace()` correspondiente
- **AND** el servidor responde 2xx

### Requirement: Cambio de prefijo o nombre de ruta no requiere tocar JS

Si el equipo reorganiza el grupo de rutas (cambia `prefix('restream')` por otro, renombra `channels.destroy` por `targets.destroy`, agrega segmentos al path, etc.), los blades JS SHALL seguir funcionando sin modificación. Solo se reescribe el blade `_xxx_urls.blade.php` que expone `window.*Urls` con el nuevo `route()` correspondiente.

#### Scenario: Cambio de prefix() no rompe URLs del JS
- **WHEN** `routes/web.php` cambia `prefix('restream')` por `prefix('streaming')` y los nombres de ruta se mantienen
- **THEN** las URLs en `window.restreamUrls` se regeneran automáticamente al renderizar la vista
- **AND** los fetch() JS siguen funcionando sin cambios

#### Scenario: Renombre de nombre de ruta no rompe URLs del JS
- **WHEN** el nombre `client.restream.channels.destroy` se renombra a `client.restream.targets.destroy`
- **THEN** el blade `_restream_urls.blade.php` se actualiza con el nuevo `route('client.restream.targets.destroy', …)` 
- **AND** los fetch() JS siguen llamando `window.restreamUrls.client.destroy` sin cambios