## ADDED Requirements

### Requirement: Broadcast creation obtains destination and credentials automatically

When a user creates or updates a `restream_target` with `platform_account_id` set, the system SHALL call the corresponding platform's Live API to create (or update) a live broadcast using the target's `title`, `description`, `thumbnail_path`, and `scheduled_start_at`, and SHALL populate the target's `destination_url`, `stream_key`, and `platform_broadcast_id` from the platform's response. The user SHALL NOT need to manually enter `destination_url`/`stream_key` for these targets.

#### Scenario: YouTube broadcast creation populates destination and key
- **WHEN** a user submits a target with `platform_account_id` set to a connected YouTube account, `title = "Partido en vivo"`, and no manual `destination_url`
- **THEN** the system calls `liveBroadcasts.insert`, `liveStreams.insert`, and `liveBroadcasts.bind` in sequence, and stores the returned ingestion address as `destination_url`, the stream name as `stream_key` (encrypted), and the broadcast id as `platform_broadcast_id`

#### Scenario: Facebook broadcast creation populates destination and key
- **WHEN** a user submits a target with `platform_account_id` set to a connected Facebook account and a `title`
- **THEN** the system calls `POST /{page-id}/live_videos` with the given metadata and stores the returned `stream_url` split into `destination_url`/`stream_key`, and the `id` as `platform_broadcast_id`

#### Scenario: Partial failure rolls back the platform-side broadcast
- **WHEN** YouTube's `liveBroadcasts.insert` succeeds but the subsequent `liveStreams.insert` call fails
- **THEN** the system SHALL attempt to delete the just-created broadcast via the platform API, SHALL NOT save a `restream_target` with a `platform_broadcast_id` pointing to an incomplete broadcast, and SHALL return a clear error to the user

#### Scenario: Updating title/description after creation syncs to the platform
- **WHEN** a user edits the `title` or `description` of a target that already has a `platform_broadcast_id`
- **THEN** the system calls the platform's update endpoint (e.g. `liveBroadcasts.update` / Graph API update) for that broadcast id, without creating a duplicate broadcast

### Requirement: Scheduled targets auto-start

When a `restream_target` has `scheduled_start_at` set to a future time and `enabled = false`, the system SHALL automatically set `enabled = true` at that time so the existing supervisor/orchestrator picks it up on its normal cadence, without requiring the user to manually start it.

#### Scenario: Target auto-enables at scheduled time
- **WHEN** a target has `scheduled_start_at` equal to a time that has just passed and `enabled = false`
- **THEN** the next run of `restream:launch-scheduled` (or equivalent scheduled job) sets `enabled = true`, and the target starts through the existing enable→start path with no other change to the push mechanism

#### Scenario: Past scheduled time on an already-enabled target is a no-op
- **WHEN** a target has `scheduled_start_at` in the past but `enabled` is already `true`
- **THEN** the scheduled job SHALL take no action for that target (it is already following the normal enable/start path)

### Requirement: TikTok is explicitly unsupported for broadcast creation

The system SHALL NOT offer broadcast creation via a connected account for `platform = 'tiktok'` targets, since no self-serve public Live API exists for third parties. TikTok targets SHALL continue to use manual `destination_url`/`stream_key` entry only, and the UI SHALL indicate that account connection is not yet available for TikTok.

#### Scenario: TikTok target requires manual entry
- **WHEN** a user creates a target with `platform = 'tiktok'`
- **THEN** the system SHALL require manual `destination_url`/`stream_key` entry (no `platform_account_id` option is offered) exactly as it behaves today
