## Purpose

Define the per-destination configuration records (`restream_targets`) that a habilitated user creates to tell the system "send this channel to this external platform". Each target binds a user, one of the user's accessible channels, a platform, a destination URL, and a stream key, and tracks its lifecycle state. Target CRUD is the user's surface (client panel); target visibility for the admin is read-only diagnostic.

## ADDED Requirements

### Requirement: Restream target data model

The system SHALL persist targets in a `restream_targets` table with the following columns:

- `id` (uuid, primary key)
- `user_id` (uuid, FK to `users.id`)
- `channel_id` (uuid, FK to `channels.id`)
- `platform` (enum: `facebook`, `tiktok`, `youtube`, `custom`)
- `name` (string, ≤ 80 chars, required, user-defined label)
- `destination_url` (string, required, validated as URL)
- `stream_key` (string, encrypted at rest, never returned in plaintext except on create)
- `enabled` (boolean, default `false`)
- `status` (enum: `idle`, `active`, `error`, default `idle`)
- `last_error` (text, nullable)
- `last_started_at` (timestamp, nullable)
- `last_stopped_at` (timestamp, nullable)
- `created_by` (uuid, FK to `users.id`)
- timestamps (`created_at`, `updated_at`)

A unique compound constraint SHALL exist on `(user_id, channel_id, platform)` to prevent two targets with the exact same triple for the same user.

#### Scenario: Required fields are enforced
- **WHEN** a user submits a target without `destination_url`
- **THEN** the server responds 422 with a validation error on `destination_url`

#### Scenario: Platform enum is enforced
- **WHEN** a user submits `platform = "instagram"`
- **THEN** the server responds 422 (only `facebook`, `tiktok`, `youtube`, `custom` are allowed)

#### Scenario: Duplicate triple is rejected
- **WHEN** a user already has a target with `(channel_id=X, platform=facebook)` and submits another with the same triple
- **THEN** the server responds 422 indicating a duplicate target

### Requirement: Channel must be accessible by the user

The system SHALL only allow a target to be linked to a channel that is in `auth()->user()->effectiveChannelIds()`. The check MUST happen on both create and update.

#### Scenario: Target on unowned channel is rejected
- **WHEN** a client tries to create a target for a channel that is NOT in `effectiveChannelIds()`
- **THEN** the server responds 403 and no target is created

#### Scenario: Target on owned channel succeeds
- **WHEN** a client creates a target for a channel they own
- **THEN** the server creates the target with that `channel_id`

### Requirement: Active target count respects the user's quota

The system SHALL count only targets where `enabled = true` against the user's `max_outputs`. The check MUST run inside a DB transaction with row locking (or equivalent atomic guard) to avoid race conditions when the user clicks "create" twice in quick succession.

#### Scenario: User at cap cannot create another active target
- **WHEN** a habilitated user with `max_outputs = 2` already has 2 active targets and submits a third with `enabled = true`
- **THEN** the server responds 422 indicating the cap is reached and no target is created

#### Scenario: User below cap can create a new active target
- **WHEN** a habilitated user with `max_outputs = 3` has 1 active target and submits a new target with `enabled = true`
- **THEN** the server creates the target and returns 201

#### Scenario: Inactive target does not consume a slot
- **WHEN** a user has 2 active targets and creates a new one with `enabled = false`
- **THEN** the server creates it, the slot count stays at 2/2 used, and the user can still enable it later if a slot is free

#### Scenario: Disabling frees a slot
- **WHEN** a user disables one of their active targets
- **THEN** `restreamUsedOutputs()` decreases by 1 and `restreamRemainingSlots()` increases by 1

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

A target's `status` SHALL be one of `idle`, `active`, `error`. Transitions are managed by the engine (future work); the CRUD layer MUST accept any of these values when the requester is admin (override) and MUST NOT let a client manually set `status = active` from the UI — clients can only toggle `enabled`.

#### Scenario: Client toggles enabled
- **WHEN** a client PATCHes `enabled = true` on a target they own
- **THEN** the server updates `enabled` and leaves `status` to be set by the engine (default `idle` until the engine picks it up)

#### Scenario: Admin can force status
- **WHEN** an admin PATCHes `status = 'error'` on any target
- **THEN** the server updates it and logs to `audit_logs`

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

Every create, update (including `enabled` toggle), and delete of a `restream_targets` row SHALL produce one entry in `audit_logs` with the acting user, `before`/`after`, and `action` in `{create.restream_target, update.restream_target, delete.restream_target}`. Stream key plaintext SHALL NOT appear in `audit_logs.before`/`after`.

#### Scenario: Enable toggle is audited without leaking the key
- **WHEN** a client toggles `enabled` from false to true
- **THEN** `audit_logs` contains a row with `action='update.restream_target'`, `before={enabled:false, ...}`, `after={enabled:true, ...}` and the `stream_key` field is replaced by `stream_key_set: true` (or omitted entirely)

### Requirement: Admin user view shows the Restream section

The admin user detail view (`/admin/users/{user}`) SHALL render a Restream section that shows:
- Current habilitation state (`enabled`, `max_outputs`, `granted_at`, `granted_by_admin`, `notes`).
- A control to grant habilitation (if no row yet) or to update `enabled` / `max_outputs` / `notes` (if row exists).
- A read-only list of that user's `restream_targets` with `[Ver]` link per row.

#### Scenario: User without habilitation shows grant control
- **WHEN** an admin opens `/admin/users/{u}` for a user with no `restream_quotas` row
- **THEN** the rendered HTML SHALL include a "Habilitar Restream" form with tier selector (1/2/3/4) and "Habilitar" submit button

#### Scenario: Habilitated user shows tier control and target list
- **WHEN** an admin opens `/admin/users/{u}` for a habilitated user
- **THEN** the rendered HTML SHALL show current tier, an edit form (tier + enabled toggle + notes), and a table of that user's restream targets
