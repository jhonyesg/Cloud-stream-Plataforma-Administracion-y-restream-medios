# confirm-modal Specification

## Purpose
TBD - created by archiving change restream-confirm-modals. Update Purpose after archive.
## Requirements
### Requirement: Modal opens via the existing Alpine modals store

The `<x-confirm-modal>` component MUST be opened by calling `Alpine.store('modals').open('confirm', payload)` with a payload that supplies, at minimum: `title` (string), `message` (string). The modal then renders inside the existing `<x-app-modal>` shell and inherits the same overlay, focus management, escape-to-close, and stack behavior as every other modal in the product.

#### Scenario: Opening with title + message
- **WHEN** a caller invokes `Alpine.store('modals').open('confirm', { title: 'Eliminar archivo', message: '¿Estás seguro?' })`
- **THEN** the modal renders centered with that title and message visible, and the action button is enabled

#### Scenario: Opening with optional action buttons
- **WHEN** the payload also includes `cancelLabel` and `confirmLabel`
- **THEN** both buttons render with exactly those labels; the action button uses the tone-appropriate color

#### Scenario: Close via Cancel or Escape
- **WHEN** the user clicks the Cancel button or presses Escape
- **THEN** `Alpine.store('modals').close()` is called and the modal disappears without firing any HTTP request

### Requirement: Tone controls the visual severity of the dialog

The payload SHALL accept a `tone` field with one of `danger` (default), `warning`, or `info`. Each tone maps deterministically to a header icon background, body banner color, and confirm button color:

- `danger` → red (`bg-red-100 / text-red-600` header, `bg-red-50 border-red-100` banner, `bg-red-600 hover:bg-red-500` action button).
- `warning` → amber/yellow (`bg-amber-100 / text-amber-600` header, `bg-amber-50 border-amber-100` banner, `bg-amber-500 hover:bg-amber-400` action button with dark text for contrast).
- `info` → indigo (`bg-indigo-100 / text-indigo-600` header, `bg-indigo-50 border-indigo-100` banner, `bg-indigo-600 hover:bg-indigo-500` action button).

#### Scenario: Default tone is danger
- **WHEN** the payload omits `tone`
- **THEN** the modal renders the red/danger palette

#### Scenario: Warning tone is used for reversible destructive actions
- **WHEN** the payload sets `tone: 'warning'` (e.g. for "Archivar / soft-delete" actions)
- **THEN** the modal renders the amber palette and the action button label uses a darker text color so the yellow background remains legible

### Requirement: HTTP action support

If the payload includes `action` (URL) and `method` (default `DELETE`), the modal SHALL `fetch(action, { method, headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })` on confirm. On `r.ok` it SHALL:
- Parse `await r.json().catch(() => ({}))` and extract a friendly message if present.
- Dispatch `crud-success` (or `crud-error` for non-2xx) on `window` so the global toast bar can react.
- Close the modal.

If `action` is omitted, the modal SHALL still render and close on confirm without firing any request (the caller handles the action in their own way).

#### Scenario: Successful confirm fires the request and the global toast
- **WHEN** the user confirms and the payload's `action` is `'/api/widgets/42'` with `method: 'DELETE'`
- **THEN** the modal fetches that URL with DELETE and, on 2xx, dispatches `window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'OK' } }))` and closes itself

#### Scenario: 422 validation failure surfaces the error toast
- **WHEN** the request returns 422
- **THEN** the modal dispatches `crud-error` with the first validation message, and closes itself

#### Scenario: No network request when action is omitted
- **WHEN** the payload has no `action`
- **THEN** pressing the confirm button only closes the modal; no request is sent

### Requirement: Optional `requireText` enables a typed-confirmation gate

If the payload includes `requireText`, the modal SHALL render an input where the user must type the exact `requireText` value (case-sensitive) for the confirm button to become enabled. This is used for irreversible actions like "Eliminar DEFINITIVAMENTE".

#### Scenario: Input gate hides the action behind a typo
- **WHEN** the payload sets `requireText: 'ELIMINAR'` and the input is empty
- **THEN** the confirm button is `:disabled` and clicking does nothing

#### Scenario: Exact match enables the action
- **WHEN** the user types `ELIMINAR` exactly (case-sensitive) into the input
- **THEN** the confirm button becomes enabled and clicking fires the action

### Requirement: Stacking and escape follow the existing modal store semantics

The modal MUST respect the existing `$store.modals.isTop` / `isOpen` semantics from `<x-app-modal>`: while another modal is on top, this one stays mounted but its `show` is `false`; pressing Escape closes only the topmost modal.

#### Scenario: Confirm behind another modal stays inert
- **WHEN** a confirm modal is open and a second modal (e.g. restream-target-modal) is opened on top
- **THEN** Escape closes the topmost modal; the confirm modal remains open underneath

