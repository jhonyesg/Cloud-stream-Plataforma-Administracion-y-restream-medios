## Why

El modal de creación/edición de destinos de Restream (`<x-restream-target-modal>`) tiene un checkbox **"Activar al guardar (consume un slot)"** cuyo estado nunca llega al backend como `enabled=true`. El JS compara `data.get('enabled') === 'on'` (líneas 174 y 183 de `resources/views/components/restream-target-modal.blade.php`), pero el `<input type="checkbox">` declara explícitamente `value="1"`, así que FormData envía `"1"` cuando está marcado y omite la clave cuando no lo está. La comparación `'1' === 'on'` siempre es `false`, por lo que el JS siempre sobreescribe el campo a `"0"`.

**Impacto:** el backend nunca recibe `enabled=true` desde este flujo. `RestreamQuotaGuard::assertCanEnable()` solo se ejecuta cuando `$enabled === true`, así que la protección del cap **no se ejecuta** y el cliente puede crear un número ilimitado de destinos inactivos (el contador `used_outputs` solo cuenta `enabled=true`). Es un bypass directo del límite de slots (1/2/3/4 según tier) que controla la habilitación comercial. Adicionalmente infla `restream_targets` con filas inactivas que el partial-unique `(user_id, channel_id, platform) WHERE deleted_at IS NULL` no bloquea.

## What Changes

- Corregir el submit handler de `<x-restream-target-modal>` para que el valor de `enabled` enviado al backend refleje fielmente el estado del checkbox, **independientemente del atributo `value` del input**.
- Eliminar la comparación `'on'` (asumía el default HTML obsoleto).
- Añadir un guardrail de cliente que deshabilite visualmente el checkbox si `used_outputs >= max_outputs` antes de que el usuario intente crear/actualizar (opcional pero recomendado para reducir fricción y dejar claro que ya alcanzó el cap).
- Garantizar que ambos modos del modal (`create` y `update`) respeten la corrección.

No hay cambios de backend ni de modelo: el controller, `RestreamQuotaGuard` y `User::restreamUsedOutputsFor()` ya están bien. El bug es 100% cliente.

## Capabilities

### New Capabilities
- *(ninguna)*

### Modified Capabilities
- `restream-targets`: añadir un requisito que cubra el contrato cliente↔backend del campo `enabled` enviado desde el modal — específicamente, que el payload HTTP refleje el estado real del checkbox y que el cap se aplique cuando el checkbox esté marcado.

## Impact

- **Código afectado:**
  - `resources/views/components/restream-target-modal.blade.php` (líneas 174 y 183 — submit handler del `x-data` raíz del modal)
- **APIs afectadas:** ninguna (contrato HTTP inalterado; el fix solo hace que el cliente envíe lo que el backend ya espera).
- **Backend:** sin cambios. El controller `App\Http\Controllers\Client\RestreamTargetController::store()` ya valida y aplica el cap cuando `$enabled === true`.
- **Tests:** añadir un test feature que verifique el envío correcto del flag `enabled` desde el cliente y la aplicación del cap cuando se marca el checkbox.
- **Auditoría:** el bug NO requiere limpieza retroactiva de filas existentes (las filas inactivas son inertes salvo por la inflación de filas; se pueden purgar manualmente con `where('enabled', false) WHERE created_at < now() - 7 days` si el operador lo considera).
