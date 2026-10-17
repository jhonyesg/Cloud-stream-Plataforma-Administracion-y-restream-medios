## MODIFIED Requirements

### Requirement: Restream target data model

The system SHALL persist targets in a `restream_targets` table with the following columns:

- `id` (uuid, primary key)
- `user_id` (uuid, FK to `users.id`)
- `channel_id` (uuid, FK to `channels.id`)
- `platform` (enum: `facebook`, `tiktok`, `youtube`, `custom`)
- `name` (string, ≤ 80 chars, required, user-defined label)
- `destination_url` (string, required unless `platform_account_id` is set, validated as URL)
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
- `platform_account_id` (uuid, nullable, FK to `restream_platform_accounts.id`) — set when this target's `destination_url`/`stream_key` were obtained via a connected OAuth account rather than entered manually.
- `title` (string, nullable) — broadcast title sent to the platform API when `platform_account_id` is set.
- `description` (text, nullable) — broadcast description sent to the platform API when `platform_account_id` is set.
- `thumbnail_path` (string, nullable) — stored thumbnail image reference uploaded to the platform on broadcast creation.
- `scheduled_start_at` (timestamp, nullable) — when set, the target SHALL be auto-enabled and started at this time instead of requiring a manual start.
- `platform_broadcast_id` (string, nullable) — the id assigned by the platform (e.g. YouTube `liveBroadcast.id`, Facebook `live_video.id`) for later updates/lookups.
- timestamps (`created_at`, `updated_at`)

A unique compound constraint SHALL exist on `(user_id, channel_id, platform)` to prevent two targets with the exact same triple for the same user.

#### Scenario: Required fields are enforced
- **WHEN** a user submits a target without `destination_url` and without `platform_account_id`
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

#### Scenario: destination_url is not required when a platform account is linked
- **WHEN** a user submits a target with `platform_account_id` set and no `destination_url`
- **THEN** the server does not reject the request for a missing `destination_url`; the field SHALL be populated by the broadcast-creation flow (see `restream-platform-broadcast`) before the target can be enabled

#### Scenario: Manual targets are unaffected
- **WHEN** a user submits a target without `platform_account_id` (manual entry), providing `destination_url` and `stream_key` directly
- **THEN** the target is created exactly as it behaves today, with `platform_account_id`, `title`, `description`, `thumbnail_path`, `scheduled_start_at`, and `platform_broadcast_id` all `NULL`
