## MODIFIED Requirements

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