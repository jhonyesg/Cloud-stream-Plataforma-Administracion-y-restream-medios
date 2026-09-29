# restream-targets Specification

## Purpose
TBD - created by archiving change add-restream-module. Update Purpose after archive.
## Requirements
### Requirement: Restream target data model

The system SHALL persist targets in a `restream_targets` table with the following columns:

- `id` (uuid, primary key)
- `user_id` (uuid, FK to `users.id`)
- `channel_id` (uuid, FK to `channels.id`)
- `platform` (enum: `facebook`, `tiktok`, `youtube`, `custom`)
- `name` (string, ≤ 80 chars, required, user-defined label)
- `title` (string, nullable, ≤ 150) — broadcast title used by platform-managed targets
- `description` (text, nullable, ≤ 5000) — broadcast description
- `thumbnail_path` (string, nullable) / `thumbnail_media_id` (uuid, nullable, FK to `media_items.id`)
- `scheduled_start_at` (timestamptz, nullable) — programmed start
- `scheduled_stop_at` (timestamptz, nullable) — programmed stop; MUST be after `scheduled_start_at` when both present
- `destination_url` (string, nullable, validated as RTMP URL — nullable for platform-managed targets)
- `stream_key` (string, nullable, encrypted at rest, never returned in plaintext except on show)
- `source_url` (text, nullable) — input URL that FFmpeg consumes. If NULL, the engine falls back to `channel.public_hls_url` at start time.
- `pipeline_pid` (integer, nullable) — live OS PID of the FFmpeg process managed by the orchestrator/daemon; cleared on stop.
- `platform_account_id` (uuid, nullable, FK to `restream_platform_accounts.id`) — presence marks the target as platform-managed
- `platform_broadcast_id` (string, nullable) — remote broadcast id
- `enabled` (boolean, default `false`)
- `status` (enum: `idle`, `starting`, `live`, `error`, default `idle`)
- `last_error` (text, nullable)
- `last_failed_at` (timestamp, nullable) — set when the target is marked `error` to enforce the 5-minute restart cool-down.
- `last_started_at` (timestamp, nullable)
- `last_stopped_at` (timestamp, nullable)
- `last_heartbeat_at` (timestamp, nullable)
- `loops_completed` (integer, default 0)
- `created_by` (uuid, FK to `users.id`)
- `deleted_at` (timestamp, nullable) — soft deletes
- timestamps (`created_at`, `updated_at`)

A **partial** unique constraint SHALL exist on `(user_id, channel_id, platform) WHERE deleted_at IS NULL`, so soft-deleted targets release the triple and a new target with the same triple can be created. On `store`, the controller SHALL pre-check duplicates (including soft-deleted rows) and respond 422 with an actionable message instead of letting the DB raise a 500.

The scheduler SHALL find candidates via partial indexes: `(scheduled_start_at) WHERE enabled = false` and `(scheduled_stop_at) WHERE scheduled_stop_at IS NOT NULL`.

#### Scenario: Required fields are enforced
- **WHEN** a user submits a manual target without `destination_url`
- **THEN** the server responds 422 with a validation error on `destination_url`

#### Scenario: Platform enum is enforced
- **WHEN** a user submits `platform = "instagram"`
- **THEN** the server responds 422 (only `facebook`, `tiktok`, `youtube`, `custom` are allowed)

#### Scenario: Duplicate triple is rejected
- **WHEN** a user already has a **live** target with `(channel_id=X, platform=facebook)` and submits another with the same triple
- **THEN** the server responds 422 indicating a duplicate target

#### Scenario: Soft-deleted target releases the triple
- **WHEN** a user's target `(channel_id=X, platform=youtube)` is soft-deleted and they create a new one with the same triple
- **THEN** the insert succeeds (no 500, no unique violation)

#### Scenario: source_url is optional and nullable
- **WHEN** a platform-managed or manual target is created without `source_url`
- **THEN** the insert succeeds and `source_url` remains NULL

#### Scenario: Custom source_url is accepted
- **WHEN** a target is created with `source_url = "rtmp://emision.local/app/CHAN"`
- **THEN** the insert succeeds and the value is persisted verbatim

#### Scenario: Scheduled stop must be after start
- **WHEN** a target is submitted with `scheduled_start_at` and `scheduled_stop_at` where stop <= start
- **THEN** the server responds 422 with a validation error on `scheduled_stop_at`

#### Scenario: Legacy status values are normalized
- **WHEN** a row has `status='active'` (legacy value not in the model enum)
- **THEN** the normalization migration rewrites it to `status='live'` and the model never returns an unlisted status

### Requirement: Channel must be accessible by the user

The system SHALL only allow a target to be linked to a channel that is in `auth()->user()->effectiveChannelIds()`. The check MUST happen on both create and update.

#### Scenario: Target on unowned channel is rejected
- **WHEN** a client tries to create a target for a channel that is NOT in `effectiveChannelIds()`
- **THEN** the server responds 403 and no target is created

#### Scenario: Target on owned channel succeeds
- **WHEN** a client creates a target for a channel they own
- **THEN** the server creates the target with that `channel_id`

### Requirement: Active target count respects the user's quota

The system SHALL count only targets where `enabled = true` **for the target's channel** against that channel's `max_outputs` in `restream_quotas`. The check MUST run inside a DB transaction with row locking (`RestreamQuota::lockForUpdate()` on the `(user_id, channel_id)` row) to avoid race conditions when the user clicks "create" twice in quick succession.

#### Scenario: User at cap cannot create another active target
- **WHEN** a habilitated user with `max_outputs = 2` on channel `C` already has 2 active targets on `C` and submits a third on `C` with `enabled = true`
- **THEN** the server responds 422 indicating the cap is reached for that channel and no target is created

#### Scenario: User below cap can create a new active target
- **WHEN** a habilitated user with `max_outputs = 3` on channel `C` has 1 active target on `C` and submits a new target on `C` with `enabled = true`
- **THEN** the server creates the target and returns 201

#### Scenario: Inactive target does not consume a slot
- **WHEN** a user has 2 active targets on channel `C` and creates a new one on `C` with `enabled = false`
- **THEN** the server creates it, the slot count stays at 2/2 used on that channel, and the user can still enable it later if a slot is free

#### Scenario: Disabling frees a slot
- **WHEN** a user disables one of their active targets on channel `C`
- **THEN** `restreamUsedOutputsFor(C)` decreases by 1 and `restreamRemainingSlotsFor(C)` increases by 1

### Requirement: Stream key is encrypted at rest

The `stream_key` column SHALL be encrypted at the application layer (Laravel `Crypt::encryptString` / `Crypt::decryptString`). The plaintext SHALL NEVER be returned by any API response except on the create response (one-time reveal), or via an explicit `[show key]` action that requires re-authentication.

#### Scenario: Stream key is stored encrypted
- **WHEN** a user submits `stream_key = "rtmp-secret-xyz"`
- **THEN** the database stores an opaque encrypted blob; a raw `SELECT stream_key FROM restream_targets` SHALL NOT return the plaintext

#### Scenario: Stream key is never echoed back in list
- **WHEN** a user calls `GET /client/channels/{c}/restream-targets`
- **THEN** each target in the response SHALL have `stream_key` either omitted or replaced with `stream_key_set: true` / `stream_key_last4: "xyz"`

#### Scenario: Stream key revealed on explicit action
- **WHEN** the user clicks `[Mostrar clave]` on a target and re-confirms via password
- **THEN** the server returns the plaintext once and logs the action to `audit_logs`

### Requirement: Target lifecycle status

A target's `status` SHALL be one of `idle`, `starting`, `live`, `error`. Transitions are driven by the orchestrator and the daemon's heartbeats:

- `idle → starting` when `RestreamOrchestrator::start()` writes the config and spawns the daemon.
- `starting → live` when the daemon's first heartbeat reports `status='live'` (ffmpeg child up).
- `live → error` when the daemon reports `error` (crash budget exhausted, ffmpeg failed) or when `last_heartbeat_at` is stale (> 15s).
- `live → idle` when the orchestrator stops the daemon and receives the `offline` heartbeat.
- `error → starting` when the user (or systemd) starts the target again.

Clients SHALL NOT set `status` directly from the UI; clients only toggle `enabled`, which triggers the orchestrator / daemon to drive the status transitions.

#### Scenario: Client toggle enabled does not directly change status
- **WHEN** a client PATCHes `enabled = true` on a target they own
- **THEN** the server updates `enabled` and leaves `status` to be driven by the orchestrator/daemon (the target becomes `starting` only when the start endpoint or systemd actually spawns the daemon)

#### Scenario: Daemon heartbeat drives live status
- **WHEN** the daemon for a target reports `status='live'` via heartbeat
- **THEN** the row's `status` becomes `live` and the UI shows the green pulsing dot

#### Scenario: Stale heartbeat marks error
- **WHEN** a target's `last_heartbeat_at` is older than 15 seconds while `status` is `live`
- **THEN** the status endpoint reports the target as `error` (stale) and the UI shows the red dot

#### Scenario: Admin can force status
- **WHEN** an admin PATCHes `status = 'error'` on any target
- **THEN** the server updates it and logs to `audit_logs` (no cool-down field is set; the daemon's own restart budget governs retries)

### Requirement: Target CRUD endpoints (client)

The system SHALL expose, for clients:

- `GET /client/channels/{channel}/restream-targets` — list the client's targets for that channel.
- `POST /client/channels/{channel}/restream-targets` — create a target (gated by quota + `EnsureRestreamEnabled`).
- `GET /client/channels/{channel}/restream-targets/{target}` — show one target (without plaintext key).
- `PATCH /client/channels/{channel}/restream-targets/{target}` — update `name`, `destination_url`, `platform`, `enabled`.
- `DELETE /client/channels/{channel}/restream-targets/{target}` — soft-delete (sets `enabled = false`, leaves row).

All endpoints SHALL be scoped to `effectiveChannelIds()` and SHALL 403 any cross-channel attempt.

#### Scenario: Listing returns only own targets
- **WHEN** a client calls `GET /client/channels/{c}/restream-targets`
- **THEN** the response contains only targets where `user_id = auth.id AND channel_id = c.id`

#### Scenario: Cross-channel attempt is blocked
- **WHEN** a client tries to `GET /client/channels/{other}/restream-targets/{t}` where `other` is not in `effectiveChannelIds()`
- **THEN** the server responds 403

### Requirement: Target visibility for admin (read-only)

The system SHALL allow admins to list and inspect any target across all users via `GET /admin/restream-targets?user_id=&channel_id=`. Admins SHALL NOT edit or delete targets from the admin panel in this change (engine ownership: clients own their targets).

#### Scenario: Admin lists all targets
- **WHEN** an admin calls `GET /admin/restream-targets`
- **THEN** the response includes targets for every user, with `user.display_name`, `channel.name`, `platform`, `enabled`, `status`

#### Scenario: Admin cannot delete a target from the admin panel
- **WHEN** an admin calls `DELETE /admin/restream-targets/{id}`
- **THEN** the server responds 405 (method not allowed) — no admin delete endpoint exists

### Requirement: Target changes are audited

Every create, update (including `enabled` toggle), and delete of a `restream_targets` row SHALL produce one entry in `audit_logs` with the acting user, the `channel_id`, `before`/`after`, and `action` in `{create.restream_target, update.restream_target, delete.restream_target}`. Stream key plaintext SHALL NOT appear in `audit_logs.before`/`after`.

#### Scenario: Enable toggle is audited without leaking the key
- **WHEN** a client toggles `enabled` from false to true on a target
- **THEN** `audit_logs` contains a row with `action='update.restream_target'`, `channel_id`, `before={enabled:false, ...}`, `after={enabled:true, ...}` and the `stream_key` field is replaced by `stream_key_set: true` (or omitted entirely)

### Requirement: Quota guard validates per channel

The `RestreamQuotaGuard::assertCanEnable(User $user, Channel $channel)` SHALL be the single gate for enabling a target on a channel. It SHALL lock the `restream_quotas` row for `(user, channel)` and count enabled targets for that channel. Every create, update, and start endpoint (client and admin) SHALL invoke the guard with the channel of the route.

#### Scenario: Guard blocks target on unhabilitated channel
- **WHEN** `assertCanEnable` is invoked for a channel with no enabled `restream_quotas` row
- **THEN** it throws a quota exception that surfaces as 422/403 and no target is enabled

#### Scenario: Guard permits target within the channel cap
- **WHEN** `assertCanEnable` is invoked for a channel with `max_outputs = 2` and 1 active target
- **THEN** it completes without error and the target may be enabled

### Requirement: Client index reports per-channel counts

The client index endpoint `GET /client/channels/{channel}/restream-targets` SHALL return `used_outputs`, `max_outputs`, and `remaining_slots` computed for that channel only, alongside the channel object.

#### Scenario: Index reflects the channel quota
- **WHEN** a client calls the index for channel `C` with 1 active target and cap 2
- **THEN** the response includes `used_outputs = 1`, `max_outputs = 2`, `remaining_slots = 1`

### Requirement: Heartbeat freshness columns

The `restream_targets` table SHALL include `last_heartbeat_at` (timestamp, nullable) and `loops_completed` (integer, default 0). `last_heartbeat_at` SHALL be updated by the internal heartbeat endpoint every 5 seconds while the target's daemon is running, and SHALL be the source of truth for liveness (a target whose `last_heartbeat_at` is older than 15 seconds SHALL be considered stale/error by the UI and diagnostics).

#### Scenario: Heartbeat updates freshness
- **WHEN** the daemon POSTs a heartbeat for target `t1`
- **THEN** `t1.last_heartbeat_at` is set to the current time and `t1.status` is updated from the heartbeat body

#### Scenario: Stale heartbeat is flagged
- **WHEN** a target has `last_heartbeat_at` older than 15 seconds
- **THEN** the status endpoint and UI report the target as stale/error rather than live

### Requirement: Internal heartbeat endpoint

The system SHALL expose `POST /api/internal/restream/{target}/heartbeat` (localhost-only, same guard as `/api/internal/channels/*/emission/heartbeat`) that validates `status` (`starting`/`live`/`error`/`offline`), `pipeline_pid` (nullable int), `error_message` (nullable string), and `timestamp`, and updates the `restream_targets` row accordingly. An `offline` heartbeat SHALL clear `pipeline_pid` and set `status = 'idle'`.

#### Scenario: Live heartbeat updates the row
- **WHEN** the daemon POSTs `{status:'live', pipeline_pid: 4242}`
- **THEN** the row's `status` becomes `live`, `pipeline_pid` becomes 4242, and `last_heartbeat_at` is refreshed

#### Scenario: Offline heartbeat clears the PID
- **WHEN** the daemon POSTs `{status:'offline'}`
- **THEN** the row's `pipeline_pid` is cleared and `status` becomes `idle`

#### Scenario: Non-localhost caller is rejected
- **WHEN** a request to the heartbeat endpoint does not originate from localhost
- **THEN** the endpoint responds 403

### Requirement: UI de Restream se monta sin errores de Alpine

Las vistas `/client/restream` y `/admin/restream` SHALL montarse en el navegador sin producir errores ni advertencias de Alpine en la consola del navegador relacionados con el payload inicial (`x-data`). Esto requiere que cualquier objeto JSON anidado dentro del atributo `x-data` sea inyectado mediante una cadena pre-codificada en el controlador y renderizada con `{{ … }}` (que aplica `htmlspecialchars`), no con `@json(...)` crudo.

#### Scenario: Acciones de la tabla responden al estado

- **WHEN** un usuario carga la vista con uno o más destinos configurados en el canal de contexto
- **THEN** los badges de estado (`live`, `starting`, `error`, `idle`) reflejan el valor real de cada destino y los botones "Iniciar", "Detener", "Inactivar", "Archivar", "Eliminar" muestran/ocultan correctamente según `t.status`, `t.enabled` y `t.pipeline_pid`

#### Scenario: Modal de destino se abre con datos correctos

- **WHEN** el usuario hace clic en "Editar" sobre un destino o en "+ Nuevo destino"
- **THEN** el modal se abre con `channel`, `mode`, `target`, `connectedAccounts` propagados desde el payload inicial de Alpine, y los bindings `x-text="channel ? channel.display_name : '—'"` muestran el nombre del canal

#### Scenario: Conexión de cuenta OAuth renderiza el badge correcto

- **WHEN** el cliente tiene una cuenta de YouTube o Facebook conectada (con o sin `needs_reconnect`)
- **THEN** la fila correspondiente en "Cuentas conectadas" muestra el badge verde (`border-emerald-300 bg-emerald-50`) o ámbar (`border-amber-300 bg-amber-50`) según `accountFor(platform).needs_reconnect`, y el botón "Conectar"/"Desconectar"/"Reconectar" es el correcto

### Requirement: Acciones destructivas usan el modal de confirmación

Las acciones destructivas de un destino (`remove`, `inactivate`, `forceDestroy`) SHALL abrir el `<x-confirm-modal>` con un payload específico por acción, en lugar de llamar `window.confirm()`. El payload SHALL incluir al menos `title`, `message`, `tone`, `confirmLabel` y (para `forceDestroy`) `requireText`. Las llamadas nativas `window.confirm()` SHALL eliminarse del módulo.

#### Scenario: Archivar abre el modal en tono warning
- **WHEN** un cliente o admin hace clic en el botón "Archivar" sobre un destino
- **THEN** se abre el `<x-confirm-modal>` con `title="Archivar destino"`, `message` mencionando que el slot queda libre y se puede restaurar, `tone: 'warning'`, `confirmLabel: 'Sí, archivar'`, `action` apuntando a `destroy` (DELETE) y la fila no se borra hasta que el usuario confirma

#### Scenario: Inactivar abre el modal en tono warning
- **WHEN** un cliente o admin hace clic en "Inactivar" sobre un destino activo
- **THEN** se abre el modal con `title="Inactivar destino"`, mensaje explicando que se detiene y libera el slot, `tone: 'warning'`, `confirmLabel: 'Sí, inactivar'`, y el `action` apuntando a `deactivate` (POST)

#### Scenario: Eliminar definitivo exige tipear ELIMINAR
- **WHEN** un cliente o admin hace clic en "Eliminar" sobre un destino
- **THEN** se abre el modal en `tone: 'danger'` con `requireText: 'ELIMINAR'`; el botón de acción está deshabilitado hasta que el usuario tipea exactamente `ELIMINAR`. Si el usuario cancela o tipea mal, ninguna request se dispara. Si confirma, se hace DELETE a `forceDestroy` y luego aparece el toast de éxito

#### Scenario: Confirm dispara la acción HTTP y refresca la tabla
- **WHEN** el usuario confirma cualquiera de los tres modales
- **THEN** se hace el `fetch` correspondiente, se cierra el modal, aparece el toast global (`crud-success`/`crud-error`), se actualiza `flash`/`flashKind` en el `<x-data>` raíz y se dispara `restream-targets-changed` para refrescar la tabla

#### Scenario: Cancelar / Escape cierra el modal sin acción
- **WHEN** el usuario hace clic en "Cancelar" o presiona Escape estando uno de los tres modales abiertos
- **THEN** el modal se cierra y NO se ejecuta ninguna request

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

### Requirement: Restream targets surface YouTube's real broadcast lifecycle to the operator

The system MUST persist YouTube's `lifeCycleStatus` per restream target and expose it through the client and admin index endpoints so the operator can distinguish a daemon that is running from a broadcast that YouTube actually has open.

#### Scenario: Lifecycle poll persists the YouTube state

- **GIVEN** a restream target with `platform='youtube'`, a non-null `platform_broadcast_id`, and a non-null `pipeline_pid`
- **WHEN** the artisan command `restream:sync-youtube-status` runs (scheduled every 30 seconds)
- **THEN** the command calls `YoutubeBroadcastService::broadcastLifecycle(target)` for that target
- **AND** on success persists the returned `lifeCycleStatus` into `restream_targets.platform_broadcast_lifecycle` and `now()` into `platform_broadcast_lifecycle_at` and `last_youtube_poll_at`
- **AND** clears `platform_broadcast_lifecycle_error`

#### Scenario: Lifecycle poll does not run for idle targets

- **GIVEN** a YouTube target with `pipeline_pid IS NULL`
- **WHEN** the command runs
- **THEN** that target is skipped (no API call, no quota consumption)

#### Scenario: YouTube API error does not abort the batch

- **GIVEN** one target throws a `Throwable` from the HTTP call
- **WHEN** the command processes that target
- **THEN** the error message is persisted into `platform_broadcast_lifecycle_error`
- **AND** the command continues to the next target without aborting

#### Scenario: Column guard makes the migration idempotent

- **GIVEN** `restream_targets` already has `platform_broadcast_lifecycle`
- **WHEN** `php artisan migrate` runs the migration `2026_09_30_010000_add_youtube_lifecycle_to_restream_targets`
- **THEN** the migration is a no-op (no errors, no duplicate column)

### Requirement: Restream target JSON exposes a single `effective_status` derived from heartbeat, lifecycle, and pipeline state

The client and admin index endpoints MUST include, for every target in the JSON payload, a precomputed string `effective_status` computed by `App\Services\Restream\RestreamStatusResolver::resolve(target)` so the UI does not need to derive it in Alpine.

#### Scenario: Heartbeat fresh + lifecycle live → `live`

- **GIVEN** a target with `last_heartbeat_at` ≤ 15 s old, `pipeline_pid` non-null, `platform_broadcast_lifecycle` ∈ {`live`, `testStarting`}
- **WHEN** the JSON is rendered
- **THEN** `effective_status` is the string `"live"`

#### Scenario: Heartbeat fresh + lifecycle complete → `yt-complete`

- **GIVEN** a target with fresh heartbeat and `platform_broadcast_lifecycle='complete'`
- **WHEN** the JSON is rendered
- **THEN** `effective_status` is the string `"yt-complete"`

#### Scenario: Heartbeat fresh + lifecycle null (no YouTube poll yet) → fallback to legacy heartbeat status

- **GIVEN** a target with fresh heartbeat, `pipeline_pid` non-null, `platform_broadcast_lifecycle IS NULL`
- **WHEN** the JSON is rendered
- **THEN** `effective_status` is the string `"live"` (legacy behaviour preserved until the first poll lands)

#### Scenario: Stale heartbeat → `stale` regardless of lifecycle

- **GIVEN** a target with `last_heartbeat_at` older than 15 s
- **WHEN** the JSON is rendered
- **THEN** `effective_status` is the string `"stale"`

#### Scenario: No pipeline + no lifecycle data → `idle`

- **GIVEN** a target with `pipeline_pid IS NULL` and `platform_broadcast_lifecycle IS NULL`
- **WHEN** the JSON is rendered
- **THEN** `effective_status` is the string `"idle"`

### Requirement: A persistent live banner shows every active restream target with a countdown to `ends_at`

The restream index (client + admin) MUST render a `<x-restream-live-banner>` block at the top of the page when at least one target has `effective_status` ∈ {`live`, `yt-no-data`}. The banner MUST show, per row: target name + platform, lifecycle label, daemon uptime, a `Termina en HH:MM:SS` countdown when the target has a `next_ends_at`, and a copy-link button.

#### Scenario: Banner appears when a target is live

- **GIVEN** the pruebas YouTube target is `effective_status='live'`
- **WHEN** the operator loads `/client/restream` or `/admin/restream-targets`
- **THEN** a banner at the top shows one row with the target name, lifecycle label `En vivo en YouTube`, a copy-link button, and a countdown

#### Scenario: Banner is hidden when no target is active

- **GIVEN** no target has `effective_status` ∈ {`live`, `yt-no-data`}
- **WHEN** the operator loads either restream page
- **THEN** the banner is not rendered (no empty card, no flash)

#### Scenario: Countdown ticks every second without server polling

- **GIVEN** a target with `next_ends_at = now + 1 h`
- **WHEN** the operator keeps the page open for 5 minutes
- **THEN** the countdown text decreases from ~`01:00:00` to ~`00:55:00` exactly
- **AND** no additional HTTP requests are sent by the banner (only the 10-s index poll continues)

### Requirement: Parity between client and admin restream surfaces

Every new field, badge variant, banner element, and countdown added by this change MUST appear identically in `resources/views/client/restream/index.blade.php` and `resources/views/admin/restream/index.blade.php`. Both surfaces MUST read from the same JSON shape and render the same Blade component.

#### Scenario: Same badge set on both surfaces

- **WHEN** an operator looks at the badges on `/client/restream` and `/admin/restream-targets` for the same target
- **THEN** both surfaces show one of {`En vivo en YouTube`, `Daemon activo pero YouTube sin datos`, `YouTube dice "completado"`, `Sin señal`, `Inactivo`, `Iniciando`, `Error`} for the same underlying state

#### Scenario: Banner parity

- **WHEN** a target is `effective_status='live'`
- **THEN** the banner appears on `/client/restream` AND on `/admin/restream-targets` with the same row contents

### Requirement: Inline countdown in the restream panel

The client and admin restream index MUST render, in the "Programación" column for every target that has `next_ends_at`, a per-second countdown (`Termina en HH:MM:SS` or shorter) that decrements every second without sending extra HTTP requests. Targets without a running schedule render `—` like before.

#### Scenario: Live target shows "Termina en HH:MM:SS"

- **GIVEN** a target with `next_ends_at = now + 1 h` and `effective_status = live`
- **WHEN** the operator opens `/client/restream` or `/admin/restream-targets`
- **THEN** the row's Programación column shows `Termina en 01:00:00` (or `Termina en 59m 58s` as the minute changes)
- **AND** the value decreases once per second
- **AND** no additional HTTP requests are sent by the row (only the 10-s index poll continues)

#### Scenario: Idle target shows "—"

- **GIVEN** a target with no `next_ends_at` and `effective_status = idle`
- **WHEN** the operator opens the restream page
- **THEN** the row's Programación column shows `—` (no countdown cell)

#### Scenario: Past-end schedule shows "Finalizado hace Xm"

- **GIVEN** a target whose last running window has `ends_at = now - 5 min`
- **WHEN** the operator opens the restream page
- **THEN** the row's Programación column shows `Finalizado hace 5m`

### Requirement: Heartbeat propagates daemon-stall signal to the resolver

The daemon's heartbeat payload MUST include `daemon_stalled_at` (timestamp or null). When the daemon detects the RTMP output is dead, it sets this field to `now()` and keeps sending heartbeats. The Laravel heartbeat endpoint MUST persist the field to `restream_targets.daemon_stalled_at`.

#### Scenario: Heartbeat with stall is persisted

- **GIVEN** the daemon detects the RTMP output is dead (`bitrate=0` for 10+ s plus a stderr pattern)
- **WHEN** the next heartbeat POSTs to `/api/internal/restream/{target}/heartbeat`
- **THEN** `restream_targets.daemon_stalled_at` is updated to `now()`
- **AND** the resolver returns `effective_status='yt-no-data'` while `daemon_stalled_at` is fresh (< 60 s)

#### Scenario: Resolver flips back to live after recovery

- **GIVEN** a target with `daemon_stalled_at = now - 5 s` and `effective_status='yt-no-data'`
- **WHEN** the daemon spawns a new ffmpeg child and successfully re-establishes the RTMP connection
- **THEN** the next heartbeat has `daemon_stalled_at = null` (or > 60 s old)
- **AND** the resolver returns `effective_status='live'`
- **AND** the panel badge flips back to "En vivo en YouTube" within the next 10-s poll

#### Scenario: Migration is idempotent

- **GIVEN** `restream_targets` already has `daemon_stalled_at`
- **WHEN** `php artisan migrate` runs the migration
- **THEN** the migration is a no-op (no duplicate column, no errors)

