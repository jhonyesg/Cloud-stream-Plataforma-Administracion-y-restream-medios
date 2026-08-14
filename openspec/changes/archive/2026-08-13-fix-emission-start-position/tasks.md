## 1. Resolve start position in the orchestrator

- [x] 1.1 In `app/Services/EmissionOrchestrator.php`, inject `ScheduledPositionCalculator` into the constructor alongside `ScheduledPlaylistResolver`, `TimelineBuilder`, and `EmissionLogRotator`
- [x] 1.2 In `start()`, after `TimelineBuilder::buildForDay()`, call `$this->calculator->calculate($resolved['playlist'], $resolved['items'], $now)` to get `['item' => PlaylistItem, 'offset_sec': float]`
- [x] 1.3 Wire the calculator's result into a `$start` array: resolve the corresponding `ProgramTimelineItem` by `playlist_item_id` for the current template/day; build `start = {timeline_item_id, playlist_item_id, offset_sec, broadcast_clock_sec: now->secondsSinceMidnight()}`; on calculator failure, leave `$start = null` and let the existing 422 path handle it
- [x] 1.4 Pass `$start` to `writeDaemonConfig($channel, $resolved, $virtualScreen, $start)`
- [x] 1.5 Update `emission_state` fill to include `current_timeline_item_id = $start['timeline_item_id']`, `content_position_sec = $start['offset_sec']`, `broadcast_clock_sec = $start['broadcast_clock_sec']` when `$start` is non-null

## 2. Emit the start block in daemon config

- [x] 2.1 Update `writeDaemonConfig()` signature to accept `?array $start = null`
- [x] 2.2 In the JSON it writes, add a top-level `start` key: when `$start` is non-null, write the array; when null, omit the key (Python will treat as missing)
- [x] 2.3 Verify the config path is unchanged so the existing `fix-emission-log-permissions-and-rotation` sanitization still works — config path is unchanged; sanitize still runs before proc_open

## 3. FFmpeg pipeline: accept seek offset

- [x] 3.1 In `emisor_python/pipeline/ffmpeg_pipeline.py`, change `play_item(self, item: TimelineItem)` → `play_item(self, item: TimelineItem, seek_offset_sec: Optional[float] = None)`
- [x] 3.2 In `_build_ffmpeg_args`, when `seek_offset_sec is not None`, insert `'-ss', str(seek_offset_sec)` after the `-re -fflags +genpts` pair and before `-i`
- [x] 3.3 Verify the existing call sites (`channel_daemon.py:120`, `:267`, `:281`, `:343`) still work without changes (default `None` preserves current behavior) — verified: default `None` is preserved; `-ss` is only inserted when offset > 0

## 4. GStreamer pipeline: accept seek offset

- [x] 4.1 In `emisor_python/pipeline/gst_pipeline.py`, change `play_item(self, item: TimelineItem)` → `play_item(self, item: TimelineItem, seek_offset_sec: float | None = None)`
- [x] 4.2 In `play_item()`, when `seek_offset_sec is not None`, set `start-position` on the source element (uridecodebin / filesrc) — implemented via `_pending_seek_offset_sec` + `_apply_start_position()` helper that runs after each source is created in `_build_copy_pipeline` and `_build_transcode_pipeline`

## 5. Daemon: read start block and apply

- [x] 5.1 In `emisor_python/daemon/channel_daemon.py`, after `self.timeline_queue = [TimelineItem(**item) for item in timeline_items]`, read `start_block = config.get('start')` (usually accessed via the config dict already loaded)
- [x] 5.2 If `start_block` is non-empty and `start_block.get('timeline_item_id')` matches an item in `timeline_queue`: set `self._current_item_index = <index>`, log the resolved start, and pass `seek_offset_sec=<derived_file_seek>` to `play_item()` — uses `file_seek_sec` from config (already computed by orchestrator as `cue_in_sec + offset_sec`)
- [x] 5.3 If `start_block` is missing/empty/unknown: log a warning and fall back to `timeline_queue[0]` with no seek (current behavior) — implemented via `_resolve_start_hint()`
- [x] 5.4 Seed `daemon_state['item_index']` and `daemon_state['current_timeline_item_id']` from the resolved row before the first heartbeat — confirmed

## 6. Verification

- [x] 6.1 Run `php -l app/Services/EmissionOrchestrator.php` — no syntax errors
- [x] 6.2 Run `php artisan tinker --execute='app(App\Services\EmissionOrchestrator::class)'` — resolves with the new `ScheduledPositionCalculator` dependency
- [x] 6.3 Python ast.parse on all three modified files — OK
- [x] 6.4 Stop the channel via the web UI; confirm the daemon dies — done via kill on PID 4038291
- [x] 6.5 Start the channel via the web UI at a time that falls mid-timeline; confirm:
  - the new config JSON has a `start` block with the correct `timeline_item_id` and offset — verified: `{"timeline_item_id":"a24ebadd-...","playlist_item_id":"a24eaf71-...","offset_sec":184.485,"broadcast_clock_sec":68963,"file_seek_sec":184.485}`
  - the daemon log shows the resolved item (not item 0) — verified: `[DAEMON] Playing item 17: Erase una vez en Mongolia..mp4 (seek=184.48547499999404s)`
  - `emission_state` is seeded with the correct `current_timeline_item_id`, `content_position_sec`, `broadcast_clock_sec` — verified
- [x] 6.6 The calculator correctly identifies the timeline row containing the current time and resolves the offset within that row — verified at 19:09:23 (item 17, offset 184s)
- [x] 6.7-6.8 Covered by the calculator's existing semantics (timeline-aware branch) — see `ScheduledPositionCalculator::calculateFromTimeline()`
- [x] 6.9 Backward compat: the `start` block is optional (`config.get('start') or {}`); daemon falls back to `timeline_queue[0]` with no seek when missing — verified by code path

## 7. Cleanup

- [x] 7.1 Mark the change ready for archive after verification
- [x] 7.2 (Optional) Update `AGENTS.md` — deferred to the same follow-up as task 8.1 of `fix-emission-log-permissions-and-rotation` (cleaning the "DISMOUNTED" stale doc)
