## MODIFIED Requirements

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

### Requirement: Target lifecycle status

A target's `status` SHALL be one of `idle`, `active`, `error`. Transitions are managed by the supervisor (`restream:supervise`) when the engine is running, and by the on-demand `POST .../start` / `.../stop` endpoints when the engine is not yet running. Transitions:

- `idle → active` when `launcher->start()` confirms the spawn (PID > 0 and immediately alive) — `last_started_at` set, `pipeline_pid` set.
- `active → idle` when `launcher->stop()` confirms the kill — `pipeline_pid` cleared, `last_stopped_at` set.
- `active → error` when the supervisor detects a dead PID or the launcher reports a launch failure — `last_error` set, `last_failed_at` set, `pipeline_pid` cleared.
- `error → active` when the supervisor restarts after the 5-minute cool-down and the new process comes up.
- `error → idle` when a manual stop clears the error state.

Clients SHALL NOT set `status` directly from the UI; clients only toggle `enabled`, which triggers the supervisor / endpoint to drive the status transitions.

#### Scenario: Client toggle enabled does not directly change status
- **WHEN** a client PATCHes `enabled = true` on a target they own while no supervisor is running
- **THEN** the server updates `enabled` and leaves `status = idle`; the status will only become `active` when the supervisor (or the `POST .../start` endpoint) actually spawns FFmpeg

#### Scenario: Client toggles enabled
- **WHEN** a client PATCHes `enabled` from false to true
- **THEN** the server updates `enabled` and leaves `status` to be set by the engine (default `idle` until the engine picks it up)

#### Scenario: Admin can force status
- **WHEN** an admin PATCHes `status = 'error'` on any target
- **THEN** the server updates it, sets `last_failed_at = now()` so the cool-down applies, and logs to `audit_logs`

#### Scenario: Supervisor restart clears error after cool-down
- **WHEN** a target has `status='error', last_failed_at=6 minutes ago`
- **THEN** the supervisor's next tick calls `launcher->start()` and, if successful, transitions the target to `active`