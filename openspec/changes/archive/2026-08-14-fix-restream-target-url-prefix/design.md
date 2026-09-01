## Context

El módulo Restream (introducido antes del OpenSpec `restream-quota-por-canal`) tiene 6 endpoints agrupados bajo `Route::middleware('restream.enabled')->prefix('restream')->group(...)` en `routes/web.php` para el cliente, y otro grupo `prefix('restream')` para admin. Los nombres de ruta ya están bien nombrados (`client.restream.channels.{index,store,show,update,destroy,start,stop}` y `admin.restream.{show,update,destroy,start,stop}`).

El problema está en los blades: las URLs se arman con template strings JS (`/client/channels/${c}/restream-targets/${t}`) que omiten el segmento `/restream/` del prefijo. Resultado: el router no encuentra match → 404 antes del controller. **Todas** las mutaciones de destinos (start, stop, edit, delete) están rotas en cliente y admin. La creación funciona solo en el modal cliente (porque ese blade sí incluye `/client/restream/…`) y la creación admin está rota (el modal usa `/admin/channels/...` sin `/restream/`).

Patrón actual del proyecto (revisado en `scheduler/index.blade.php`, `media/index.blade.php`):
- Rutas estáticas: `fetch('{{ route('media.thumbnails.generate') }}', …)`.
- Rutas con parámetros dinámicos: hardcoded en JS (mismo antipatrón).
- Ziggy: no instalado.

El bug no se introdujo en `restream-quota-por-canal`; estaba desde la primera versión del módulo. El cambio OpenSpec previo no tocó URLs pero sí tocó los blades del listado (start/stop/delete), por lo que el bug quedó preservado.

## Goals / Non-Goals

**Goals:**
- Restaurar el CRUD completo de `restream_targets` en cliente y admin.
- Eliminar la clase de bug "URL hardcodeada sin segmento de prefijo" en este módulo.
- Mantener `prefix('restream')` y `middleware('restream.enabled')` intactos (no reorganizar rutas).
- No agregar dependencias externas.

**Non-Goals:**
- No se instalan Ziggy u otros helpers JS de routing.
- No se renombran rutas ni se reorganiza `routes/web.php`.
- No se cambia el comportamiento HTTP de los endpoints; solo cómo el cliente arma la URL.

## Decisions

### D1. `window.restreamUrls` como contrato único de URLs

Un nuevo blade `@once` `resources/views/components/_restream_urls.blade.php` expone:

```js
window.restreamUrls = {
  client: {
    index:   …,
    store:   …,
    show:    …,
    update:  …,
    destroy: …,
    start:   …,
    stop:    …,
  },
  admin: {
    index:   …,
    show:    …,
    update:  …,
    destroy: …,
    start:   …,
    stop:    …,
  },
};
```

Las URLs con parámetros dinámicos usan placeholders `CID` y `TID` (camelCase, improbables de aparecer en un UUID real). Las URLs sin parámetros (`index`) se generan tal cual.

**Alternativas consideradas:**
- Hardcodear `/restream/` literal en los 8 fetch(): **descartado** — sigue siendo frágil.
- Quitar `prefix('restream')` y mover el segmento a cada ruta: **descartado** — pierde el agrupamiento del middleware `restream.enabled`.
- Instalar Ziggy: **descartado** — cambio de arquitectura desproporcionado para un fix de bug.

### D2. `route()` como única fuente de verdad

Cada URL se genera con el helper `route()` de Laravel: `route('client.restream.channels.destroy', ['channel' => 'CID', 'target' => 'TID'])`. Laravel produce la URL exacta que el router matcheará; el JS solo hace `.replace('CID', uuid).replace('TID', uuid)` para obtener la URL final.

**Beneficio**: si en el futuro alguien renombra `channels.destroy` a `targets.destroy`, o cambia `prefix('restream')` por `prefix('streaming')`, **cero cambios en JS**. Solo se reescribe el blade que expone `window.restreamUrls` (un solo archivo).

### D3. Inclusión con `@once` desde el blade que abre el modal / listado

Cada vista que hace fetch incluye `<x-restream-urls />` (alias de `_restream_urls.blade.php`) una sola vez. `@once` garantiza que el `<script>` no se duplica si la vista incluye varios modales que lo consuman.

### D4. Placeholders `CID` y `TID` (no `__CID__`/`__TID__`)

Se prefieren identificadores cortos para mantener las URLs generadas legibles en el HTML. Probabilidad de colisión con un UUID real: prácticamente cero (UUIDs son hexadecimales con guiones; `CID` no aparece).

## Risks / Trade-offs

- **[Riesgo] Colisión de placeholder con UUID real** → Mitigación: probabilidad ~0; si pasara, cambiar a `__CID__`/`__TID__` es un cambio de una línea.
- **[Riesgo] `window.restreamUrls` no definido cuando un blade carga antes que otro** → Mitigación: cada vista que consume lo incluye explícitamente con `<x-restream-urls />`. `@once` previene duplicación.
- **[Riesgo] Si alguien olvida el `<x-restream-urls />`** → Mitigación: el bug revierte al patrón actual (URLs hardcodeadas rotas). Aceptable: la regresión es idéntica al estado pre-fix, no peor.
- **[Trade-off] Acoplamiento frontend↔nombres de ruta** → Mitigación: el acoplamiento ya existe (los nombres de ruta se usan en redirects); este cambio solo lo hace explícito y durable.

## Migration Plan

1. Crear `resources/views/components/_restream_urls.blade.php` que expone `window.restreamUrls`.
2. Reescribir los fetch() en los 3 blades afectados.
3. Probar manualmente con un usuario client + admin que el CRUD completo funciona (crear, editar, eliminar, start, stop).
4. No requiere migraciones, deployments especiales, ni rollback plan (cambio puramente cliente/JS).

## Open Questions

(Ninguna — el fix es directo.)