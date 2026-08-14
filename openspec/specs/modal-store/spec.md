## ADDED Requirements

### Requirement: Modal stack is a list of entries each with its own payload and errors

The Alpine store `modals` SHALL maintain a `stack` field whose entries are objects of the form `{ name: string, payload: object, errors: object }`. The `payload` field of each entry SHALL be set when the entry is pushed onto the stack via `open()` and SHALL be preserved unchanged for the lifetime of that entry — including after other entries above it are popped via `close()`. The `errors` field SHALL likewise be owned by the entry, not shared across entries. Each entry SHALL be created with `errors: {}` on push.

#### Scenario: Push a single modal with payload
- **WHEN** `$store.modals.open('edit-channel', { id: 'abc' })` is called on an empty stack
- **THEN** the stack SHALL contain exactly one entry `{ name: 'edit-channel', payload: { id: 'abc' }, errors: {} }`

#### Scenario: Two modals stacked each retain their own payload
- **WHEN** `$store.modals.open('virtual-screen-editor', { channel_id: 'C1' })` is called and then `$store.modals.open('upload-media', { channel_id: 'C1', onUploaded: 'virtual-screen-logo' })` is called
- **THEN** the stack SHALL contain two entries; the bottom entry's `payload.channel_id` SHALL be `'C1'` and the top entry's `payload.onUploaded` SHALL be `'virtual-screen-logo'`

### Requirement: Closing the top modal restores the previous modal's payload and errors

`close()` SHALL pop only the top entry and SHALL project the new top entry's `payload` and `errors` onto the exposed mirror fields `payload` and `errors`. Closing a modal SHALL NOT clear or reset the payload or errors of any entry that remains on the stack.

#### Scenario: Close preserves the previous modal's payload
- **WHEN** `'edit-channel'` is on the bottom of the stack with `payload: { id: 'abc' }`, `'upload-media'` is pushed on top, and then `$store.modals.close()` is called
- **THEN** `$store.modals.payload.id` SHALL be `'abc'` and the bottom entry's stored `payload.id` SHALL still be `'abc'`

#### Scenario: Close preserves the previous modal's validation errors
- **WHEN** `'edit-channel'` was open and a server response set its errors to `{ name: ['required'] }`, then `'upload-media'` was pushed on top, then `$store.modals.close()` is called
- **THEN** `$store.modals.errors.name` SHALL be `['required']`

#### Scenario: Close with only one entry clears the mirrors
- **WHEN** exactly one modal is open with `payload: { id: 'abc' }` and `$store.modals.close()` is called
- **THEN** `$store.modals.current` SHALL be `null` and `$store.modals.payload` SHALL be `{}`

#### Scenario: Close on empty stack is a no-op
- **WHEN** `$store.modals.close()` is called with no entries on the stack
- **THEN** the store SHALL be unchanged and no error SHALL be thrown

### Requirement: Current fields are mirrors of the top entry

The store SHALL expose `current`, `payload`, and `errors` as flat mirror fields derived from the top entry of the stack. Mutations to `stack` from outside the store SHALL NOT be supported; all stack mutations SHALL go through `open()`, `close()`, or `closeAll()`, each of which re-projects the mirrors via an internal `_syncTop()` helper.

#### Scenario: Mirror reads reflect the top entry
- **WHEN** the stack top entry is `{ name: 'rename-media', payload: { id: 'M7', filename: 'a.mp4' }, errors: {} }`
- **THEN** `$store.modals.current` SHALL be `'rename-media'`, `$store.modals.payload.id` SHALL be `'M7'`, and `$store.modals.payload.filename` SHALL be `'a.mp4'`

#### Scenario: After pop, mirrors reflect the new top
- **WHEN** `'rename-media'` is on top of `'edit-channel'` and `$store.modals.close()` runs
- **THEN** `$store.modals.current` SHALL be `'edit-channel'` and `$store.modals.payload` SHALL be the stored payload of that bottom entry

### Requirement: Opening a modal that is already in the stack re-uses it with the new payload

`open(name, payload)` SHALL, if an entry with the same `name` already exists anywhere in the stack, remove that entry and push a new entry with the supplied payload at the top. After this operation the stack SHALL contain the modal exactly once and at the top.

#### Scenario: Reopen the top modal moves nothing and replaces the payload
- **WHEN** `'edit-channel'` is the sole entry on the stack with payload `{ id: 'abc' }` and `$store.modals.open('edit-channel', { id: 'xyz' })` is called
- **THEN** the stack SHALL contain exactly one entry, its `payload.id` SHALL be `'xyz'`, and `$store.modals.payload.id` SHALL be `'xyz'`

#### Scenario: Reopen a modal that is buried under others
- **WHEN** `'upload-media'` is on top of `'edit-channel'` and `$store.modals.open('edit-channel', { id: 'xyz' })` is called
- **THEN** the stack SHALL be `[{ name: 'upload-media', payload: ..., errors: ... }, { name: 'edit-channel', payload: { id: 'xyz' }, errors: {} }]` and `$store.modals.payload.id` SHALL be `'xyz'`

### Requirement: Closing a modal that is buried leaves the top unchanged

If a modal `name` that is NOT on top is targeted (where supported by future close variants), it SHALL NOT mutate the top entry. The base `close()` API SHALL always pop only the top entry; removing non-top entries by name SHALL be achieved via `closeAll()` followed by re-opening the desired modals. Today this contract documents the top-only behavior of `close()`.

#### Scenario: close() only pops the top
- **WHEN** the stack is `['edit-channel', 'upload-media']` and `$store.modals.close()` runs
- **THEN** the stack SHALL be `['edit-channel']` and `$store.modals.current` SHALL be `'edit-channel'`

### Requirement: closeAll empties the stack and clears all mirrors

`closeAll()` SHALL empty the stack, set `current` to `null`, set `payload` to `{}`, and set `errors` to `{}`. No entry SHALL remain with non-default values afterward.

#### Scenario: closeAll from a multi-modal stack
- **WHEN** the stack contains three entries and `$store.modals.closeAll()` is called
- **THEN** the stack SHALL be empty, `$store.modals.current` SHALL be `null`, `$store.modals.payload` SHALL be `{}`, and `$store.modals.errors` SHALL be `{}`

### Requirement: has(name) reports whether a modal is currently on the stack

`has(name)` SHALL return `true` iff at least one entry's `name` equals `name`, and `false` otherwise. It SHALL NOT mutate the stack or the mirrors.

#### Scenario: has returns true while a modal is open
- **WHEN** `'virtual-screen-editor'` is in the stack (at any position)
- **THEN** `$store.modals.has('virtual-screen-editor')` SHALL be `true`

#### Scenario: has returns false after the modal is closed
- **WHEN** `'virtual-screen-editor'` is not in the stack
- **THEN** `$store.modals.has('virtual-screen-editor')` SHALL be `false`

### Requirement: Stack entries are created with empty errors

Every entry pushed via `open()` SHALL have `errors: {}` at the time of push. A consumer that needs to render server-side validation errors SHALL mutate `entry.errors` through a documented setter (e.g. `setErrors(name, errors)`) or write to `$store.modals.errors` while that entry is on top, knowing the latter is shared with the mirror.

#### Scenario: Fresh entry has empty errors
- **WHEN** `$store.modals.open('foo')` is called
- **THEN** the new top entry's `errors` SHALL be `{}` and `$store.modals.errors` SHALL be `{}`

#### Scenario: Errors set while a modal is top persist if no other modal is opened above it
- **WHEN** `'foo'` is the sole entry on the stack and its errors are set to `{ name: ['required'] }`, with no other open() or close() calls
- **THEN** `$store.modals.errors.name` SHALL remain `['required']`

### Requirement: Modal store is registered exactly once per layout

The Alpine stores (`modals`, `mediaSelection`, `schedulerDialog`, and any other shared store) SHALL be defined in a single Blade partial (`resources/views/partials/_alpine-stores.blade.php`) wrapped in `@once`. Both `<x-admin-layout>` and `<x-client-layout>` SHALL include this partial, and no other layout or view SHALL register these stores. This guarantees a single source of truth.

#### Scenario: Both layouts expose identical store shapes
- **WHEN** a page rendered through `<x-admin-layout>` is loaded and a page rendered through `<x-client-layout>` is loaded
- **THEN** `window.Alpine.store('modals')` SHALL exist in both and SHALL expose the API (`open`, `close`, `closeAll`, `has`, `current`, `payload`, `errors`, `stack`) with identical semantics

#### Scenario: No other layout duplicates the store registration
- **WHEN** the codebase is searched for `window.Alpine.store('modals',`
- **THEN** exactly one occurrence SHALL exist and it SHALL be inside the shared partial
