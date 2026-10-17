# restream-targets delta

## ADDED Requirements

### Requirement: Form sections are visually delimited

The `<x-restream-target-modal>` SHALL render its inputs grouped into visually-delimited panels (Identificación, Conexión, Detalles de la transmisión, Activación). Each panel SHALL have a distinct background tint or border so the user can scan the form section by section, and SHALL NOT require the user to read every field to discover the OAuth-vs-manual mode toggle.

#### Scenario: OAuth section is visually separate from manual RTMP section
- **WHEN** the user opens the modal with a connected YouTube or Facebook account
- **THEN** the OAuth switch card is visually distinct from the manual RTMP inputs (URL de destino / Stream Key); selecting one mode hides the other's fields via Alpine and the visual emphasis changes (active card has a stronger border or tinted background)

#### Scenario: Información del canal is in its own header pill
- **WHEN** the modal opens with a `channel` payload
- **THEN** the "Canal destino: X" line appears in a small pill at the top of the form, NOT as a regular label

### Requirement: Inputs share one consistent visual recipe

Every input (text, select, textarea, datetime-local, file) SHALL use the same recipe: `block w-full rounded-xl border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm py-2.5`. The select SHALL have a visible chevron icon. The checkbox SHALL be a larger rounded checkbox (`w-4 h-4`) with the rose brand color.

#### Scenario: All inputs share the same border-radius
- **WHEN** the form is rendered
- **THEN** `getComputedStyle(input).borderRadius` is the same value (`0.75rem` = `12px`) for every text/select/textarea/datetime-local/file input

#### Scenario: Select has a chevron icon
- **WHEN** the Plataforma select is rendered
- **THEN** an SVG chevron-down icon is visible on the right side of the field, indicating it opens a dropdown

### Requirement: OAuth use-account switch is a prominent card

The "Usar mi cuenta de X conectada" option SHALL render as a card with: the platform badge (YouTube/Facebook), the connected account display name, a green dot indicating healthy OAuth status, and the explanatory subtitle "crea la transmisión automáticamente". Clicking the card SHALL toggle the checkbox.

#### Scenario: OAuth card shows the platform badge and account name
- **WHEN** the modal opens with `platform = 'youtube'` and a connected YouTube account
- **THEN** the OAuth card displays "YouTube" badge, the connected account display name (e.g., "Efrain Suarez (Jhon Suarez)"), a green dot, and the subtitle

#### Scenario: Clicking the card toggles the underlying checkbox
- **WHEN** the user clicks anywhere inside the OAuth card body
- **THEN** the hidden `<input type="checkbox">` toggles, and the visual state of the card updates (border + bg tint) to reflect the new state

### Requirement: Programar-inicio has an info banner explaining auto-enable

The `Programar inicio` `<input type="datetime-local">` SHALL be accompanied by a visible info banner that reads (verbatim, in Spanish): "Si programas el inicio, el destino se activará automáticamente a la hora indicada. Puedes dejarlo desactivado en el formulario y se encenderá solo. La hora es local del navegador." The banner SHALL be rendered with `bg-sky-50 border-sky-200 text-sky-900` and a clock icon.

#### Scenario: Banner appears under the field
- **WHEN** the modal is rendered with `useConnectedAccount === true`
- **THEN** a sky-blue banner with the literal copy is visible immediately under the Programar-inicio input

#### Scenario: Past times are not selectable
- **WHEN** the user opens the datetime picker
- **THEN** the `min` attribute is set to the current local ISO-8601 string so past dates/times are not selectable

### Requirement: Consolidated error banner replaces per-field red rows

The form SHALL NOT render a separate `<template x-if="errors.X">` row per field. Instead, when the server returns 422, the modal SHALL render a single error banner at the top of the form listing `errors.general` plus one row per field error, with anchor links that focus the offending input.

#### Scenario: Server 422 shows a single banner with all errors
- **WHEN** the user submits the form with invalid data and the server responds 422 with `errors.destination_url = ['The destination url field is required.']`
- **THEN** a single red banner at the top of the form lists "destination_url: The destination url field is required." and focuses the `destination_url` input on click

### Requirement: Per-row countdown chip for scheduled targets

The target list row SHALL render a countdown chip `Inicia en Xh Ym` (or `Inicia en Xd Xh` for ≥ 1 day) when the target has `scheduled_start_at` in the future and `enabled = false` or `status === 'idle'`. The chip SHALL recompute every minute via `setInterval` scoped to the row, and SHALL disappear once the target's status transitions to `starting` or `live`.

#### Scenario: Chip appears for a future scheduled target
- **WHEN** a target row has `scheduled_start_at = now() + 2h` and `enabled = false`
- **THEN** the row contains a sky-blue rounded chip with a clock icon and the text "Inicia en 2h 0m"

#### Scenario: Chip disappears once the cron flips enabled
- **WHEN** the cron `restream:launch-scheduled` runs and sets `enabled = true`
- **THEN** on the next page refresh / poll, the chip is no longer rendered for that target

#### Scenario: Chip disappears for past scheduled times
- **WHEN** a target's `scheduled_start_at < now()` and `enabled = false` (cron hasn't run yet, or the user manually disabled again)
- **THEN** the chip is not rendered (no countdown, since the time has passed)

### Requirement: Cron auto-enables scheduled targets

The `restream:launch-scheduled` Artisan command SHALL run every minute (via `routes/console.php`) and SHALL flip `enabled` from `false` to `true` for every target whose `scheduled_start_at <= now() AND enabled = false`. Once flipped, the existing orchestrator picks the target up on its normal cadence.

#### Scenario: Scheduled target auto-enables within 60s of its time
- **WHEN** a target has `scheduled_start_at = now() - 5min` and `enabled = false`
- **AND** the user runs `php artisan restream:launch-scheduled` (or the cron fires within the minute)
- **THEN** the target's `enabled` column is `true` and the existing orchestrator / daemon starts pushing via the normal enable→start path

#### Scenario: Already-enabled targets are not touched
- **WHEN** the command runs and a target has `enabled = true`
- **THEN** its `enabled` column is NOT modified (no-op, idempotent)