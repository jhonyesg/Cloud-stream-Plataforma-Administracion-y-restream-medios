## ADDED Requirements

### Requirement: Modal submit reflects the actual checkbox state for `enabled`

The `<x-restream-target-modal>` SHALL submit the `enabled` field to the server as `"1"` when the "Activar al guardar (consume un slot)" checkbox is checked, and `"0"` when it is unchecked, in both `create` and `update` modes. The submit handler SHALL NOT rely on the literal string `"on"` to detect checked state, because the checkbox declares `value="1"`. The submit handler SHALL derive the boolean state either from the live DOM property (`form.querySelector('[name="enabled"]').checked`) or from a `FormData.has('enabled')` / `data.get('enabled') === '1'` check.

#### Scenario: Checked checkbox submits `enabled=1` on create

- **WHEN** the user opens the modal in `create` mode, fills required fields, leaves the "Activar al guardar" checkbox checked, and clicks "Guardar"
- **THEN** the `POST /client/channels/{c}/restream-targets` request body contains `enabled=1`, the controller treats it as enabled, `RestreamQuotaGuard::assertCanEnable()` is invoked, and the new target row is persisted with `enabled = true`

#### Scenario: Unchecked checkbox submits `enabled=0` on create

- **WHEN** the user opens the modal in `create` mode and submits the form with the "Activar al guardar" checkbox unchecked
- **THEN** the request body contains `enabled=0`, the controller does NOT invoke `assertCanEnable()`, and the new target row is persisted with `enabled = false`

#### Scenario: Editing an active target keeps `enabled` when checkbox is unchanged

- **WHEN** the user opens the modal in `update` mode on a target that already has `enabled = true` and submits the form without touching the checkbox
- **THEN** the `PATCH` request body contains `enabled=1`, the controller leaves `enabled` unchanged, and `assertCanEnable()` is NOT invoked (no transition)

#### Scenario: Editing an active target and unchecking releases the slot

- **WHEN** the user opens the modal in `update` mode on a target that has `enabled = true` and unchecks "Activar al guardar" before saving
- **THEN** the `PATCH` request body contains `enabled=0`, the controller persists `enabled = false`, `restreamUsedOutputsFor(C)` decreases by 1, and `restreamRemainingSlotsFor(C)` increases by 1

#### Scenario: Checkbox state survives Alpine rebinding

- **WHEN** Alpine re-renders the form (e.g., after switching platforms or toggling the OAuth switch) and the user submits without touching the checkbox
- **THEN** the value sent to the server still reflects the visible checkbox state, not a stale FormData snapshot from before the re-render

### Requirement: Cap pre-flight prevents wasted round-trips when at quota

When `used_outputs >= max_outputs` for the channel of context, the modal SHALL render the "Activar al guardar" checkbox as `disabled`, show an inline helper text indicating that the cap has been reached, and SHALL NOT allow the user to toggle it on. The cap check SHALL be recomputed on modal open and on every refresh of the target list (driven by `restream-targets-changed`).

#### Scenario: At cap, checkbox is disabled on open

- **WHEN** the user opens the modal in `create` mode for a channel where `used_outputs === max_outputs`
- **THEN** the "Activar al guardar" checkbox renders as `disabled`, an inline message "Has alcanzado el límite de destinos activos (X/X) para este canal" is visible, and the checkbox state cannot be toggled by clicks

#### Scenario: Below cap, checkbox is enabled

- **WHEN** the user opens the modal in `create` mode for a channel where `used_outputs < max_outputs`
- **THEN** the "Activar al guardar" checkbox is enabled and toggleable

#### Scenario: Crossing the cap reactively re-disables the checkbox

- **WHEN** the user disables another target from the table (reducing `used_outputs`), the `restream-targets-changed` event refreshes the modal's payload, and `used_outputs` drops below `max_outputs`
- **THEN** the checkbox becomes enabled again without requiring the user to close and reopen the modal
