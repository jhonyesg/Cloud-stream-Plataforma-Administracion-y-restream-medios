# Design: confirm modal replace `window.confirm()` en Restream

## Context

El módulo Restream tiene tres acciones destructivas (`remove`, `inactivate`, `forceDestroy`) que hoy abren diálogos `window.confirm()` nativos del navegador. La vista usa `<x-app-modal>` y `<x-restream-target-modal>` para todo lo demás. El componente `<x-confirm-delete-modal>` ya existe para el caso de borrar archivos de medios — usa el mismo `Alpine.store('modals')` y la envoltura `<x-app-modal>`, y hace su propio `fetch` con un payload `{ action, method, title, message, confirmLabel, busyLabel }`. Es 90% de lo que necesitamos para Restream; falta generalizar la severidad (no siempre es "danger rojo", a veces es "warning amarillo") y el "requireText" para las acciones más destructivas.

Vamos a:

1. Crear `<x-confirm-modal>` con la misma base pero con props configurables (`tone: 'danger'|'warning'|'info'`, `requireText?: string` opcional).
2. Refactorizar `<x-confirm-delete-modal>` para que use `<x-confirm-modal>` por debajo (DRY).
3. Usar `<x-confirm-modal>` en las acciones destructivas del módulo Restream (admin + client).

## Goals / Non-Goals

**Goals**
- Reemplazar los 4 `confirm()` por un modal visualmente agradable.
- Reusable: cualquier futura acción destructiva del producto puede usar `<x-confirm-modal>` con sólo pasarle el payload.
- Conservar la lógica post-fetch del módulo Restream (`flash`, `flashKind`, dispatch de `restream-targets-changed`).
- Para `forceDestroy` exigir tipear `ELIMINAR` antes de habilitar el botón.

**Non-Goals**
- No cambiar API, ruta, ni modelo de datos.
- No introducir un sistema de toasts nuevo — se sigue usando el `flash` actual sobre el componente `x-data` raíz del Restream.
- No traducir el componente a otros idiomas — los labels siguen viniendo del payload, en español como el resto del módulo.

## Decisions

### Decision 1 — El modal hace el `fetch`, NO se le pasa un callback desde el payload

`Alpine.store('modals').payload` es JSON-serializable; pasar un closure o callback desde el `<x-data>` raíz al modal no funciona. En cambio, el modal acepta `action`+`method`+`body` (opcional) y hace el `fetch` por su cuenta. Tras `r.ok`:
- Despacha `window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: ... } }))` para que el toast global aparezca.
- Cierra el modal.

El Restream sigue refrescando vía el listener `restream-targets-changed` que ya existía — lo cambiamos a que el modal también despache ese evento tras éxito, para mantener la tabla al día sin nuevos listeners en el x-data raíz.

**Alternativa considerada**: refactorizar el `x-data` raíz del Restream para exponer `remove(t)`, `inactivate(t)`, `forceDestroy(t)` sin `confirm()` y usar el modal como un simple "sí/no". Problema: no hay forma limpia de comunicar el callback del page al modal sin cambiar el API del store. Descartado.

### Decision 2 — Tonos del botón (`tone: 'danger'|'warning'|'info'`) via class map

Tres tonos visuales:
- `danger` → botón rojo `bg-red-600`, banner rojo. Usado por `forceDestroy`.
- `warning` → botón amarillo `bg-amber-500`, banner ámbar. Usado por `remove` (Archivar — amarillo como su botón en la tabla).
- `info` → botón gris/indigo, banner neutro. Reservado para casos como "Inactivar" si se prefiere look suave; default va a `warning`.

El iconto del header viene por defecto (trash, archive, power) pero puede sobreescribirse por payload.

### Decision 3 — `requireText` opcional para acciones irreversibles

`forceDestroy` actualmente exige dos `confirm()` encadenados. Vamos a substituir eso por un input que pide tipear `ELIMINAR` (literal del payload `requireText`). El botón de acción está deshabilitado hasta que `inputValue === requireText`. UX más clara que un doble OK.

### Decision 4 — Reusar el patrón `Alpine.store('modals')` y `<x-app-modal>`

No introducimos un segundo sistema de modales. `<x-confirm-modal>` envuelve `<x-app-modal>` igual que `<x-confirm-delete-modal>` y `<x-restream-target-modal>`.

## Risks / Trade-offs

- **[Risk] Bug en el componente deja acciones ejecutándose sin confirmación** → Mitigación: el modal llama `fetch(action, ...)` sólo si el usuario hace clic en el botón del modal — exactamente el mismo patrón que `<x-confirm-delete-modal>`. Sin confirmación no hay `fetch`.
- **[Risk] Doble request si el usuario hace doble clic en "Eliminar"** → Mitigación: el botón tiene `:disabled="busy"` durante el `fetch`, igual que `<x-confirm-delete-modal>`.
- **[Risk] CSS nuevo de `confirm-modal` no aparece en pantalla** → Mitigación: al final del cambio correr `npm run build:css` para regenerar el JIT bundle (mismo riesgo que tuvimos con el botón amarillo; ya está documentado en AGENTS.md).
- **[Risk] Cambio de UX en `forceDestroy` (antes 2 clicks, ahora tipear)** → Mitigación: el input sólo aparece para `forceDestroy`. Es estrictamente más explícito.
- **[Risk] `<x-confirm-delete-modal>` roto al refactorizar** → Mitigación: validación visual con Playwright post-cambio.

## Migration Plan

1. Crear `<x-confirm-modal>` (componente nuevo).
2. Refactorizar `<x-confirm-delete-modal>` para usar `<x-confirm-modal>` por debajo (verificar visualmente que medios sigue funcionando).
3. En `client/restream/index.blade.php`: importar `<x-confirm-modal>` al final del layout (igual que `<x-restream-target-modal>`). En el x-data raíz, partir cada acción destructiva en `askX(t)` (abre el modal) + `*Confirmed(t)` (fetch + flash + dispatch).
4. Mismo cambio en `admin/restream/index.blade.php`.
5. `npm run build:css` para registrar las nuevas clases.
6. Playwright con `pruebas` y con `admin`: probar Archivar / Inactivar / Eliminar y verificar que (a) aparece el modal, (b) se ejecuta la acción sólo tras confirmación, (c) el toast/flash se actualiza y la tabla se refresca.
7. Rollback: revertir los dos archivos de vista + borrar el componente nuevo. Sin DB/API/cola para deshacer.

## Open Questions

- (ninguna)
