# emission-daemon Specification

## Purpose

Daemon Python independiente por canal: aísla la emisión, consume la timeline diaria, reporta heartbeat, recupera posición tras crash y expone una API HTTP de control local.

## Requirements

### Requirement: One independent daemon per channel
The system SHALL run one Python daemon process per channel that has emission enabled. Each daemon SHALL be completely isolated; a failure in one channel's daemon SHALL NOT affect others.

#### Scenario: Daemon startup
- **WHEN** the daemon is launched with `--channel-id={uuid} --port=0`
- **THEN** it reads its `VirtualScreen` config and today's `ProgramTimelineItem` list from the Laravel API
- **AND** starts an HTTP control server on an OS-assigned port
- **AND** writes a PID + port registration file to `storage/app/emission-daemons/{channelId}.json`
- **AND** builds the GStreamer pipeline and sets it to `PLAYING`

#### Scenario: Port registration
- **WHEN** the daemon binds to port 0 and receives port `P`
- **THEN** it writes `{"pid":N,"port":P,"started_at":"ISO8601"}` to the registration file
- **AND** the Laravel `EmissionOrchestrator` reads this file to discover the daemon's control URL

### Requirement: Daemon fetches and caches timeline
The daemon SHALL fetch the current day's timeline from Laravel on startup and at 00:00 rollover. It SHALL keep an in-memory queue of items and advance sequentially.

#### Scenario: Timeline fetch on startup
- **WHEN** the daemon starts
- **THEN** it GETs `/api/schedule-templates/{tmpl}/days/{day}/timeline` (or a dedicated internal endpoint)
- **AND** receives an ordered array of items with `starts_at_sec`, `ends_at_sec`, `media_item_id`, `filename`, `kind`, `cue_in_sec`, `cue_out_sec`
- **AND** stores them in an internal `TimelineQueue`

#### Scenario: Day rollover
- **WHEN** the local system time crosses 00:00
- **THEN** the daemon fetches the new template/block for the new day
- **AND** rebuilds the `TimelineQueue`
- **AND** performs a `seek_simple(0)` to the first item of the new day
- **AND** increments `emission_state.loops_completed` (or resets it)

### Requirement: Heartbeat reporting
The daemon SHALL report its state to Laravel every 5 seconds via an internal API endpoint.

#### Scenario: Successful heartbeat
- **WHEN** 5 seconds have elapsed since the last heartbeat
- **THEN** the daemon POSTs to `/api/internal/channels/{id}/emission/heartbeat`
- **WITH** body containing: `status` (`live`/`paused`/`error`), `broadcast_clock_sec`, `current_timeline_item_id`, `content_position_sec`, `mode`, `pipeline_pid`, `timeline_version`, `loops_completed`
- **AND** Laravel updates the `emission_state` row

#### Scenario: Stale heartbeat detection
- **WHEN** Laravel has not received a heartbeat for > 15 seconds
- **THEN** `emission_state.status` is considered `error` (or `offline` if process confirmed dead)
- **AND** the system MAY attempt auto-restart if configured

### Requirement: Crash recovery with exact resume
The daemon SHALL detect pipeline errors (GStreamer `ERROR` bus message), query Laravel for the current broadcast position, and resume from the exact item and offset.

#### Scenario: Resume after crash
- **WHEN** the GStreamer pipeline posts an `ERROR` or unexpected `EOS`
- **THEN** the daemon notes the current system time and calculates `broadcast_clock_sec = seconds_since_midnight()`
- **AND** GETs `/api/internal/channels/{id}/emission/position?broadcast_clock_sec={sec}`
- **AND** receives the `current_timeline_item_id` and `offset_in_item_sec`
- **AND** rebuilds the pipeline starting at that item with `ffmpeg -ss offset` equivalent (GStreamer `seek` on the source)
- **AND** sets `emission_state.status = starting` → `live`

#### Scenario: Resume when API is unreachable
- **WHEN** the Laravel API is unreachable during recovery
- **THEN** the daemon uses its cached `TimelineQueue` and local system time to calculate position locally
- **AND** resumes playback
- **AND** retries the API every 5s until successful

### Requirement: Control HTTP API
Each daemon SHALL expose a minimal HTTP API for Laravel to control it.

#### Scenario: POST /stop
- **WHEN** Laravel POSTs `/stop` to the daemon
- **THEN** the daemon sets pipeline to `NULL`, cleans up, writes `status=offline` to registration file, and exits

#### Scenario: GET /status
- **WHEN** Laravel GETs `/status`
- **THEN** the daemon returns `{"status":"live","broadcast_clock_sec":N,"current_timeline_item_id":"uuid","item_index":I,"loops":L}`

#### Scenario: POST /reload-timeline
- **WHEN** Laravel POSTs `/reload-timeline` (e.g., after a user rebuilds the day's timeline)
- **THEN** the daemon re-fetches the timeline, compares `timeline_version`
- **AND** if newer, rebuilds the queue and seeks to the equivalent position in the new timeline

### Requirement: Shared Python standard for all emitters

The `emisor_python/` package SHALL be the shared standard for every process that emits media (programming emission and restream). The shared modules SHALL be: `utils/log_rotator.py` (log rotation), `utils/laravel_client.py` (internal API client, extended with restream endpoints), and `pipeline/ffmpeg_pipeline.py` (ffmpeg subprocess management: spawn, kill process group, stats, watchdog hooks). The restream daemon (`restream_daemon/`) SHALL import these shared modules rather than duplicating them, so maintenance and updates apply to both emitters at once.

#### Scenario: Restream daemon reuses shared modules
- **WHEN** the restream daemon starts
- **THEN** it imports `LogRotator` and `LaravelClient` from `emisor_python/utils/` and the ffmpeg process management from `emisor_python/pipeline/` (no copied implementations)

#### Scenario: A fix in the shared pipeline benefits both emitters
- **WHEN** `emisor_python/pipeline/ffmpeg_pipeline.py` is updated (e.g. process-group kill behavior)
- **THEN** both the emission daemon and the restream daemon pick up the change without further edits

### Requirement: Daemon registration files

Each daemon (emission and restream) SHALL write a registration file on startup: emission daemons to `storage/app/emission-daemons/{channelId}.json` and restream daemons to `storage/app/restream-daemons/{targetId}.json`, both with `{"pid":N,"started_at":"ISO8601"}` (plus `port` for emission). Laravel SHALL use these files for diagnostics and stop signaling.

#### Scenario: Restream daemon registers on startup
- **WHEN** a restream daemon starts
- **THEN** it writes `storage/app/restream-daemons/{targetId}.json` with its PID and start time
