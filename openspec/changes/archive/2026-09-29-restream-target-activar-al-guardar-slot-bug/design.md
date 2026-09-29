## Context

El modal de creación/edición de destinos de Restream (`<x-restream-target-modal>`, `resources/views/components/restream-target-modal.blade.php`) tiene un checkbox "Activar al guardar (consume un slot)" que es el ÚNICO mecanismo visible para que el cliente entienda que activar consume su cupo comercial. El backend ya hace lo correcto: `App\Http\Controllers\Client\RestreamTargetController::store()` (líneas 84-87) llama a `RestreamQuotaGuard::assertCanEnable()` cuando `$enabled === true`, y `User::restreamUsedOutputsFor()` cuenta solo `restream_targets WHERE enabled = true`. El guard está bien, el modelo está bien, la migración está bien.

El único punto de fallo es el submit handler JS del modal (líneas 174 y 183):

```js
data.set('enabled', data.get('enabled') === 'on' ? '1' : '0');
payload.enabled = data.get('enabled') === 'on' ? '1' : '0';
```

El input HTML declara `value="1"` explícitamente (línea 397), así que FormData envía `"1"` cuando está marcado y omite la clave cuando no. La comparación `'1' === 'on'` siempre es `false` → siempre se manda `"0"`. Resultado: el backend nunca recibe `enabled=true` desde este flujo, el guard nunca corre, y un cliente puede crear destinos inactivos ilimitados.

Stakeholders: equipo de plataforma (billing/quota), equipo de frontend (UX del modal), equipo de seguridad (bypass de cap comercial).

## Goals / Non-Goals

**Goals:**
- Que el valor de `enabled` enviado al backend refleje fielmente el estado del checkbox del modal, en `create` y `update`.
- Que el cap comercial (1/2/3/4 destinos activos) se aplique de forma ineludible cuando el usuario marca "Activar al guardar".
- Reducir fricción visual: si ya está al cap, el checkbox se deshabilita con un mensaje claro en lugar de dejar que el usuario llene el formulario y descubra el 422 al guardar.

**Non-Goals:**
- Cambiar el modelo de datos, migraciones, controller o `RestreamQuotaGuard` (ya están bien).
- Cambiar el contrato HTTP del backend (`enabled` sigue siendo `boolean`, validado con `'sometimes', 'boolean'`).
- Añadir telemetría/analytics del evento de bypass (se puede hacer en otra propuesta).
- Limpieza retroactiva de filas inactivas existentes — eso queda fuera de scope (defensa en profundidad opcional, decisión del operador).

## Decisions

### Decision 1: Derivar el estado desde el DOM, no desde FormData

**Elegido:** usar `form.querySelector('[name="enabled"]').checked` para obtener el estado real del checkbox en el momento del submit.

**Por qué:**
- Robusto ante cambios futuros del atributo `value` (si alguien lo cambia a `"on"`, `"true"`, lo omite, etc., el código sigue funcionando porque lee la propiedad DOM, no el valor serializado).
- Refleja el estado visual del usuario en el momento exacto del click — no un snapshot anterior.
- Es 1 línea, trivial de auditar, sin regex ni comparaciones frágiles.

**Alternativas consideradas:**
- `data.has('enabled') ? '1' : '0'` — funciona, pero requiere que el `<input>` esté DENTRO del `<form>` referenciado por `FormData(form)`. Funciona aquí, pero añade un acoplamiento implícito.
- `data.get('enabled') === '1' ? '1' : '0'` — mínimo diff vs el código actual (cambiar `'on'` por `'1'`), pero sigue siendo frágil ante un futuro cambio del atributo `value`. NO recomendado.
- Refactor a un helper `boolFromCheckbox(form, name)` compartido entre `create` y `update` — más limpio arquitecturalmente, pero overkill para 2 líneas. Reservar para si aparecen más checkboxes con el mismo bug.

### Decision 2: Pre-flight del cap antes de habilitar el checkbox

**Elegido:** deshabilitar visualmente el checkbox "Activar al guardar" cuando `used_outputs >= max_outputs`, con un mensaje inline.

**Por qué:**
- UX: el usuario ve inmediatamente que ya alcanzó el cap, sin tener que llenar el formulario y recibir un 422.
- Coherencia: ya existe el badge `Slots (X): N/M` arriba a la derecha del canal context — el checkbox debe contar la misma historia.
- Defense in depth: aunque el guard del backend sigue siendo la fuente de verdad, reducir los intentos fallidos reduce logs de error y soporte.

**Cómo:**
- Reutilizar el payload de Alpine que ya llega al modal (`{channel, max_outputs, used_outputs, remaining_slots}` que viene de `GET /client/channels/{c}/restream-targets`).
- En el `<template>` del checkbox, agregar `:disabled="mode === 'create' && used_outputs >= max_outputs"`.
- Debajo del label, agregar un `<p x-show="...">` con el mensaje, usando el mismo color (`text-amber-700` / `bg-amber-50`) que ya usa el módulo para advertencias de cap.
- En modo `update`, el checkbox NO se deshabilita por cap: el target ya existe y solo se está editando; un usuario podría querer desactivarlo (`enabled: true → false`) sin quejarse del cap.

**Alternativas consideradas:**
- No tocar UI y solo arreglar el bug del submit — funciona técnicamente, pero deja la mala UX (422 al guardar). El usuario ya reportó la fricción implícitamente ("no consume el slot y es grave").
- Mostrar un modal de "ya alcanzaste el cap" al hacer click en el checkbox — más disruptivo, no aporta info nueva vs el helper text inline.

### Decision 3: Test del submit con los dos modos (create + update)

**Elegido:** un test feature Playwright/Dusk o un test feature HTTP puro que verifique el payload y el efecto en BD.

**Por qué:**
- Si solo arreglamos el JS sin test, el bug puede regresar la próxima vez que alguien toque el submit handler (de hecho, así es como entró).
- Un test HTTP puro (`$this->postJson(...)`) no captura el bug porque este vive en el cliente. La opción correcta es Playwright/Dusk que ejerza el checkbox y valide que `restream_targets.enabled` queda en `true`.

**Trade-off aceptado:** Playwright/Dusk es más lento y requiere entorno browser. Si el repo ya tiene Dusk configurado (verificar `tests/Browser/` y `php artisan dusk`), usarlo; si no, un test HTTP + un test manual checklist documentado en `tasks.md` es aceptable para este fix de 2 líneas.

## Risks / Trade-offs

- **Riesgo: cambio de comportamiento en modo `update` para un target ya activo** → si un usuario abre editar sobre un target con `enabled=true` y guarda sin tocar el checkbox, el comportamiento debe ser idempotente. La spec cubre esto explícitamente ("Editing an active target keeps enabled when checkbox is unchanged"). El submit lee el estado DOM actual, así que será `true` si Alpine no lo cambió.
- **Riesgo: el partial-unique `(user_id, channel_id, platform) WHERE deleted_at IS NULL` puede bloquear re-creación de un target desactivado** → esto ya estaba así antes; no es introducido por este fix. El controller ya hace `assertNoDuplicateTriple` que incluye soft-deleted.
- **Riesgo: el helper text inline no se ve si el modal se abre en `update` y el target ya está activo** → intencional; en `update` el cap no aplica (target ya existe). La spec lo deja explícito.
- **Trade-off: NO limpiar filas inactivas existentes** → si el operador quiere defensa en profundidad, puede correr manualmente `DELETE FROM restream_targets WHERE enabled = false AND created_at < now() - interval '7 days'` antes o después del deploy. Decisión fuera de scope del fix.

## Migration Plan

1. Pre-deploy: el fix es 100% cliente (Blade + Alpine). No requiere migración, no toca BD, no toca API.
2. Deploy: merge → build CSS (no aplica, no hay clases nuevas) → cache clear estándar → release.
3. Post-deploy: smoke test manual siguiendo el checklist de `tasks.md` (4 escenarios: crear con checkbox marcado, crear sin marcar, editar manteniendo, editando desactivando).
4. Rollback: revertir el commit. El bug regresa, pero no se introduce ningún estado inconsistente (los targets existentes no se ven afectados; solo los nuevos envíos vuelven a fallar silenciosamente).
5. Limpieza opcional de filas históricas (decisión del operador, no incluida en este change).

## Open Questions

- ¿Hay tests Dusk configurados en este repo? Verificar `tests/Browser/` y `php artisan dusk --help` antes de elegir entre test E2E vs test HTTP + checklist manual.
- ¿El mismo modal lo usa el panel admin? Revisar `Admin/RestreamTargetController` y las vistas admin para confirmar que el bug no está duplicado allí. (Sospecha inicial: NO — el admin usa `restream-modal.blade.php`, que es un modal distinto para habilitaciones, no para targets.)
