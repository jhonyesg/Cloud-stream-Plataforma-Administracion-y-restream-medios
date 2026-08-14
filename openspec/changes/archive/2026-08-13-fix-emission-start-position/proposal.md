## Why

When the user stops and restarts a channel at, say, 3pm, the daemon starts playing the first item of the playlist (`timeline_queue[0]`) instead of the item that should be airing at 3pm. The `ScheduledPositionCalculator` already knows how to compute the correct `{item, offset_sec}` for the current time, but neither `EmissionOrchestrator::start()` nor the daemon config/code consumes it — the orchestrator seeds `emission_state` with `broadcast_clock_sec=0` and `content_position_sec=0`, and the daemon has no concept of "start at this item with this offset."

The result is a playhead that resets on every restart, instead of resuming the broadcast clock. Today the user only restarts when the channel has been off-air long enough for someone to notice, which makes the bug invisible until they catch up on the playback; we need the start path to honor the schedule.

## What Changes

- **`EmissionOrchestrator::start()`** will invoke `ScheduledPositionCalculator::calculate()` after resolving the timeline and use the result to:
  - write a `start` block (timeline item UUID + offset within the timeline row) into the daemon config JSON
  - seed `emission_state` with `current_timeline_item_id`, `content_position_sec`, and `broadcast_clock_sec = secondsSinceMidnight` so the UI shows the correct position immediately after start
- **`EmissionOrchestrator::writeDaemonConfig()`** will accept and emit the `start` block.
- **`emisor_python/pipeline/ffmpeg_pipeline.py::play_item()`** will accept an optional `seek_offset_sec` parameter and prepend `-ss <offset>` to the ffmpeg args (fast seek before `-re`).
- **`emisor_python/pipeline/gst_pipeline.py::play_item()`** will accept an optional `seek_offset_sec` and set the GStreamer `start-position` property on the playbin3 / source element.
- **`emisor_python/daemon/channel_daemon.py`** will read `config['start']` and, if present, resolve the matching `TimelineItem` by id and pass its offset to `play_item()`; also set `daemon_state['item_index']` and `current_timeline_item_id` accordingly before the first heartbeat.
- The `start` block is **optional**: a missing or empty `start` block keeps the current behavior (start at item 0). This preserves backwards compatibility if a future caller wants to override the calculation.

## Capabilities

### New Capabilities
- `emission-start-position`: Defines how the emission start path computes and applies the correct starting timeline item + offset for the current time, including the contract between the orchestrator (PHP) and the daemon (Python).

### Modified Capabilities
- None. The public contract of `POST /api/channels/{id}/emission/start` (defined in `emission-engine-rebuild` and still in flight) is unchanged: it still returns `{status: "starting", timeline_version, items_count}` on success. Only the *internal mechanics* of where the daemon starts playing change.

## Impact

- **PHP code**:
  - `app/Services/EmissionOrchestrator.php` — `start()` calls `ScheduledPositionCalculator::calculate()` after `TimelineBuilder::buildForDay()`; `writeDaemonConfig()` accepts a third `?array $start = null` parameter and emits a `start` JSON block.
  - `app/Services/EmissionOrchestrator.php` — `emission_state` fill is updated to include `current_timeline_item_id`, `content_position_sec`, `broadcast_clock_sec`.
  - `app/Services/ScheduledPositionCalculator.php` — no changes; already returns the right shape.
- **Python code**:
  - `emisor_python/pipeline/ffmpeg_pipeline.py` — `play_item(item, seek_offset_sec=None)`; when provided, insert `-ss <offset>` after `-re` and before `-i`.
  - `emisor_python/pipeline/gst_pipeline.py` — `play_item(item, seek_offset_sec=None)`; wire `start-position` on the source element.
  - `emisor_python/daemon/channel_daemon.py` — read `config.get('start', {})`, resolve the `TimelineItem` by `timeline_item_id`, set `_current_item_index` and `daemon_state['item_index']` before playing.
- **Behavior**:
  - Restart-at-15:00 now starts at the timeline row that contains `15:00:00` (or the closest row that has started but not finished), at the second offset within that row.
  - The `broadcast_clock_sec` shown in the UI refreshes to the real seconds-since-midnight on start (was hardcoded to 0).
- **APIs/schemas**: no public API changes.
- **Dependencies**: none.
- **Risk**: `cue_in_sec` is part of the timeline row but the calculator returns `offset_sec` measured against the row's `starts_at_sec` (not against the underlying media file). For rows with `cue_in_sec > 0`, the actual ffmpeg seek will be `cue_in_sec + offset_sec`. This is the correct semantics ("start at the 3pm slot of the broadcast clock"), but worth flagging for the operator.
- **No interaction with the previous change**: `fix-emission-log-permissions-and-rotation` is unrelated. The orchestrator wiring added here just calls the calculator and writes more config; the log-handling sanitization still runs before `proc_open` as before.
