# emission-start-position Specification

## Purpose

Cálculo de la posición exacta de inicio de emisión: el orquestador resuelve el item de timeline que corresponde a la hora actual y lo pasa al daemon vía config, respetando `cue_in_sec` en el offset de búsqueda del archivo.

## Requirements

### Requirement: Emission start computes the correct timeline item for the current time
The system SHALL compute the timeline item that should be playing at the moment of emission start, using the existing `ScheduledPositionCalculator`, and pass it to the daemon via the config file.

#### Scenario: Start mid-day at a scheduled slot
- **WHEN** an authenticated user starts emission at a time that falls within a `program_timeline_items` row's `[starts_at_sec, ends_at_sec)` range
- **THEN** the orchestrator writes a `start` block in the daemon config containing the resolved `timeline_item_id`, `playlist_item_id`, `offset_sec`, and `broadcast_clock_sec`
- **AND** the daemon starts playing that item at that offset instead of `timeline_queue[0]`

#### Scenario: Start before the first scheduled item of the day
- **WHEN** an emission is started before the earliest `starts_at_sec` of the day's timeline
- **THEN** the orchestrator writes a `start` block pointing at the first timeline row
- **AND** `offset_sec` is `0` (or `cue_in_sec` if set)

#### Scenario: Start after the last scheduled item of the day
- **WHEN** an emission is started after the last `ends_at_sec` of the day's timeline
- **THEN** the orchestrator writes a `start` block pointing at the last timeline row
- **AND** `offset_sec` is the row's `effective_duration_sec` (i.e. the row is "complete" by the broadcast clock)

### Requirement: Emission start respects `cue_in_sec` in the file seek offset
The system SHALL include `cue_in_sec` in the file seek offset so that the daemon starts at the correct point inside the media file, not at the file's beginning.

#### Scenario: Resolved row has a non-zero `cue_in_sec`
- **WHEN** the resolved timeline row has `cue_in_sec > 0`
- **THEN** the `seek_offset_sec` passed to the daemon pipeline equals `cue_in_sec + offset_sec`
- **AND** the daemon seeks the media file to that combined offset

### Requirement: FFmpeg pipeline supports an optional seek offset at play_item
The system SHALL allow `FFmpegPipelineManager.play_item()` to receive an optional `seek_offset_sec` and SHALL prepend `-ss <offset>` to the ffmpeg arguments when provided.

#### Scenario: play_item called without seek_offset_sec
- **WHEN** `play_item()` is invoked without a `seek_offset_sec` argument
- **THEN** the ffmpeg args omit `-ss` and the pipeline starts at the file's beginning (current behavior)

#### Scenario: play_item called with seek_offset_sec
- **WHEN** `play_item()` is invoked with a `seek_offset_sec` value
- **THEN** the ffmpeg args include `-ss <seek_offset_sec>` immediately after `-re` and before `-i`
- **AND** the pipeline starts at the seek point in the media file

### Requirement: GStreamer pipeline supports an optional seek offset at play_item
The system SHALL allow `GstPipelineManager.play_item()` to receive an optional `seek_offset_sec` and SHALL set the GStreamer `start-position` property when provided.

#### Scenario: play_item called without seek_offset_sec
- **WHEN** `play_item()` is invoked without a `seek_offset_sec` argument
- **THEN** the GStreamer pipeline starts at the default position (current behavior)

#### Scenario: play_item called with seek_offset_sec
- **WHEN** `play_item()` is invoked with a `seek_offset_sec` value
- **THEN** the GStreamer `start-position` property is set to `seek_offset_sec * Gst.SECOND` before the pipeline transitions to PLAYING

### Requirement: Daemon reads the start block from config and applies it before the first frame
The system SHALL have the daemon read `config['start']` at startup and, when present, resolve the matching `TimelineItem` by `timeline_item_id` and play it at the given offset.

#### Scenario: Config has a start block
- **WHEN** the daemon reads a config whose `start` block contains a `timeline_item_id` that exists in `timeline.items`
- **THEN** the daemon sets `_current_item_index` to the index of that timeline item
- **AND** calls `play_item(item, seek_offset_sec=start.offset_sec)` (mapped through the FFmpeg/GStreamer pipeline)
- **AND** seeds `daemon_state['item_index']` and `daemon_state['current_timeline_item_id']` before the first heartbeat

#### Scenario: Config has no start block
- **WHEN** the daemon reads a config with no `start` block or an empty `start` block
- **THEN** the daemon starts at `timeline_queue[0]` with no seek offset (current behavior)

#### Scenario: Config start references an unknown timeline item id
- **WHEN** the daemon reads a config whose `start.timeline_item_id` does not match any row in `timeline.items`
- **THEN** the daemon logs a warning with the unknown id
- **AND** falls back to starting at `timeline_queue[0]` (graceful degradation)

### Requirement: emission_state is seeded with the correct start position
The system SHALL populate `emission_state` with the resolved starting position *before* spawning the daemon, so the UI reflects the correct state immediately after the start endpoint returns.

#### Scenario: Start with a resolved timeline item
- **WHEN** the orchestrator computes a starting timeline item
- **THEN** the `emission_state` row for the channel is updated with:
  - `current_timeline_item_id` = start.timeline_item_id
  - `content_position_sec` = start.offset_sec
  - `broadcast_clock_sec` = start.broadcast_clock_sec (= seconds since midnight)
- **AND** these values are visible via `GET /api/channels/{id}/emission/status` before the first heartbeat arrives

#### Scenario: Start with no resolved item (calculator fallback)
- **WHEN** the orchestrator cannot compute a starting timeline item (e.g. no template, no ready items)
- **THEN** the existing 422 path is taken and `emission_state` is not modified
- **AND** the daemon is not spawned

### Requirement: Start position applies to both new schedule templates and legacy loop playlists
The system SHALL use the timeline-aware branch of `ScheduledPositionCalculator` when a ScheduleTemplate is active for the current month, and the legacy loop-based branch otherwise.

#### Scenario: Active ScheduleTemplate for the channel's current month
- **WHEN** the channel has a `ScheduleTemplate` with `status='active'` for the current year and month
- **THEN** the calculator uses `program_timeline_items` for the current day
- **AND** the resulting start position points to the row containing the current time

#### Scenario: No active ScheduleTemplate
- **WHEN** the channel has no active template for the current month
- **THEN** the calculator falls back to the legacy sum-of-durations loop
- **AND** the resulting start position is `fmod(elapsed, total_playlist_duration)` if the playlist loops, or `elapsed` if not
- **AND** the channel still starts (because the `start` block is still writable)
