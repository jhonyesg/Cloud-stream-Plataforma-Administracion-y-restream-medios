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
- `destination_url` (string, required, validated as URL)
- `stream_key` (string, encrypted at rest, never returned in plaintext except on create)
- `source_url` (text, nullable) — input URL that FFmpeg consumes. If NULL, the engine falls back to `channel.public_hls_url` at start time.
- `pipeline_pid` (integer, nullable) — live OS PID of the FFmpeg process managed by the supervisor; cleared on stop.
- `enabled` (boolean, default `false`)
- `status` (enum: `idle`, `active`, `error`, default `idle`)
- `last_error` (text, nullable)
- `last_failed_at` (timestamp, nullable) — set when the supervisor marks the target `error` to enforce the 5-minute restart cool-down.
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

#### Scenario: source_url is optional and nullable
- **WHEN** a user submits a target without `source_url`
- **THEN** the row is created with `source_url = NULL` and the launcher will fall back to `channel.public_hls_url` at start time

#### Scenario: Custom source_url is accepted
- **WHEN** a user submits `source_url = "rtmp://emision.local/app/canal-demo"`
- **THEN** the row is created and the launcher uses exactly that URL as the FFmpeg `-i` argument

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

