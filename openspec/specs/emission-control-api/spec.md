# emission-control-api Specification

## Purpose

API de control de emisión por canal: iniciar, detener y consultar estado, más el endpoint interno de posición para recuperación del daemon.

## Requirements

### Requirement: Channel emission can be started
The system SHALL provide an authenticated endpoint to start emission for a channel. The endpoint SHALL resolve the active template, today's block, and timeline; spawn the daemon if not running; and transition `emission_state.status` from `offline` to `starting` then `live`.

#### Scenario: Successful start
- **WHEN** an authenticated user with channel access POSTs to `/api/channels/{id}/emission/start`
- **THEN** the system resolves `ScheduleTemplate` for current year/month, `ScheduleBlock` for today, and `ProgramTimelineItem` rows
- **AND** spawns the Python daemon with `--channel-id={id}` if not already running
- **AND** returns `{"status":"starting","timeline_version":N,"items_count":M}`
- **AND** sets `emission_state.status = starting`, `current_block_id`, `current_playlist_id`, `started_at = now`

#### Scenario: Refuse double start
- **WHEN** emission is already `starting` or `live` for the channel
- **THEN** the endpoint returns HTTP 422 with `{"message":"Emisión ya en curso"}`

#### Scenario: Missing playlist or no ready items
- **WHEN** today's block has no playlist, or no ready `media_item` exists
- **THEN** the endpoint returns HTTP 422 with a descriptive Spanish error message
- **AND** `emission_state.status` remains `offline`

### Requirement: Channel emission can be stopped
The system SHALL provide an authenticated endpoint to stop emission for a channel. The endpoint SHALL signal the daemon to shut down, wait for confirmation, and set `emission_state.status = offline`.

#### Scenario: Successful stop
- **WHEN** an authenticated user POSTs to `/api/channels/{id}/emission/stop`
- **THEN** the system signals the daemon via its local HTTP API (`POST /stop`)
- **AND** after the daemon process exits (or after a 10s timeout), sets `emission_state.status = offline`, `stop_reason = manual`, `pipeline_pid = null`
- **AND** returns `{"status":"offline","stop_reason":"manual"}`

#### Scenario: Stop when already offline
- **WHEN** the channel is already `offline`
- **THEN** returns HTTP 200 with `{"status":"offline","message":"No había emisión activa"}`

### Requirement: Emission status can be queried
The system SHALL provide an endpoint to read the current `emission_state` for a channel, enriched with the current playing item metadata.

#### Scenario: Status query returns enriched state
- **WHEN** an authenticated user GETs `/api/channels/{id}/emission/status`
- **THEN** returns `emission_state` fields plus `current_item` (media item filename, thumb, kind, duration)
- **AND** includes `seconds_since_last_heartbeat` to detect stale daemons

### Requirement: Internal position endpoint for daemon recovery
The system SHALL provide an internal endpoint used exclusively by the emission daemon to calculate the exact playhead position after a crash.

#### Scenario: Calculate position at a given broadcast clock
- **WHEN** the daemon GETs `/api/internal/channels/{id}/emission/position?broadcast_clock_sec={sec}`
- **THEN** the system uses `ScheduledPositionCalculator` to find the `ProgramTimelineItem` whose `[starts_at_sec, ends_at_sec)` contains `broadcast_clock_sec`
- **AND** returns `{"current_timeline_item_id":"uuid","offset_in_item_sec":N,"mode":"content","broadcast_clock_sec":sec}`
- **AND** if `broadcast_clock_sec` exceeds the day's last item, returns the last item with `offset_in_item_sec = effective_duration_sec` (loop boundary)

#### Scenario: Authentication bypass for internal endpoint
- **WHEN** the request originates from `127.0.0.1` or `::1`
- **THEN** authentication MAY be bypassed (IP allowlist)
- **AND** if from any other IP, returns HTTP 403
