## Context

The emission engine was rebuilt end-to-end by the `emission-engine-rebuild` change. Today, when a user starts a channel (via the web UI's "Iniciar emisión" button), the orchestrator:

1. Resolves the active template + block + playlist for today (`ScheduledPlaylistResolver`).
2. Builds the per-day timeline via `TimelineBuilder::buildForDay()`.
3. Writes a config JSON to `storage/app/emission-daemons/{channelId}-config.json` containing the full `timeline.items` list.
4. Spawns the Python daemon which reads the config and starts playing `timeline_queue[0]`.

The `ScheduledPositionCalculator` already exists and knows how to compute the correct `{item, offset_sec}` for the current time, but it is currently used only by `EmissionPositionController` (a read-side endpoint that asks the question "what should be playing right now"). It is **not** invoked on the start path. The orchestrator hardcodes `broadcast_clock_sec = 0`, `content_position_sec = 0`, and `current_timeline_item_id = null` in `emission_state`; the daemon in turn picks `timeline_queue[0]` and starts there.

The query "what should be playing at 15:00?" already has an answer; the start path simply doesn't ask it. Today's symptom is observable after every restart: the user sees the channel rebroadcast from the beginning of the timeline instead of picking up at the live schedule slot.

## Goals / Non-Goals

**Goals:**
- The start path computes the correct timeline item + offset for the current time using `ScheduledPositionCalculator`.
- The daemon starts playing that item at that offset, not `timeline_queue[0]`.
- `emission_state` is seeded with the correct `current_timeline_item_id`, `content_position_sec`, and `broadcast_clock_sec` so the UI reflects the right state immediately after start.
- The change is backward-compatible: callers that don't supply a `start` block (e.g. a hypothetical future CLI caller that wants to start at item 0) still get the current behavior.

**Non-Goals:**
- Persisting the playhead across daemon crashes that happen *while running* (the existing heartbeat → `emission_state` flow already covers that; this change only addresses the start case).
- Changing the `ScheduledPlaylistResolver` semantics or the timeline model.
- Altering the legacy loop-based fallback in `ScheduledPositionCalculator::calculate()` (the timeline-aware branch is the one that handles scheduled slots; the loop branch is fine for ad-hoc playlists without a ScheduleTemplate).
- Touching the `gst_pipeline.py` path beyond the minimal `start-position` wiring — the active production pipeline is `ffmpeg_pipeline.py`.
- Adding a `--start-position` CLI flag to the daemon. The position is passed via config, not via CLI, so the existing `proc_open` invocation does not change.

## Decisions

### Decision 1: Pass `start` via config, not via CLI

**Choice:** Add a `start` block to the JSON config file at `storage/app/emission-daemons/{channelId}-config.json`:

```json
{
  "start": {
    "timeline_item_id": "uuid-of-derived-row",
    "playlist_item_id": "uuid-of-the-actual-playlist-row",
    "offset_sec": 123.45,
    "broadcast_clock_sec": 54000
  },
  ...
}
```

**Why over CLI:** keeps the `proc_open` invocation identical (no new env var, no new arg vector), so the log-handling work from `fix-emission-log-permissions-and-rotation` is unaffected. The daemon already reads the config file once at startup; adding a key is one line of parsing.

**Why not write to `emission_state` and have the daemon read it:** that introduces a Laravel round-trip on startup, which the engine was specifically designed to avoid (see `emission-engine-rebuild` — "daemon reads config from file, so it doesn't need initial API calls"). The config file is the right transport.

### Decision 2: Pre-seed `emission_state` so the UI is correct before the first heartbeat

**Choice:** `EmissionOrchestrator::start()` writes:

- `current_timeline_item_id = start.timeline_item_id`
- `content_position_sec = start.offset_sec`
- `broadcast_clock_sec = start.broadcast_clock_sec` (= seconds-since-midnight)

into `emission_state` *before* spawning the daemon.

**Why:** the daemon's first heartbeat fires ~5s after spawn. The UI's `GET /api/channels/{id}/emission/status` is polled more aggressively than that. Without pre-seeding, the user sees zeros for 5–10 seconds after clicking "Iniciar emisión" before the position catches up. Pre-seeding gives the UI the correct state immediately.

### Decision 3: `play_item` accepts an optional `seek_offset_sec`

**Choice:** Both `ffmpeg_pipeline.py::play_item` and `gst_pipeline.py::play_item` get a new optional kwarg `seek_offset_sec: Optional[float] = None`. When `None`, the current behavior is preserved (no `-ss`, no `start-position`). When set:

- ffmpeg: insert `-ss <seek_offset_sec>` immediately after `-re` and before `-i` (fast seek — no decode to the seek point).
- gstreamer: set `playbin3.set_property("start-position", seek_offset_sec * Gst.SECOND)` before `set_state(PLAYING)`.

**Why:** the existing `_build_ffmpeg_args` and `build_pipeline_for_item` already encapsulate the per-item pipeline construction. Adding the seek at the parameter boundary keeps the change surgical. The optional kwarg preserves backward compatibility — any other call site that doesn't care about the seek still works.

### Decision 4: Mapping `timeline_item_id` → `playlist_item_id` is the orchestrator's job

**Choice:** The orchestrator resolves the right `PlaylistItem` via `ScheduledPositionCalculator::calculate()` (which already returns `['item' => PlaylistItem, 'offset_sec' => float]`). It then looks up the corresponding `ProgramTimelineItem` by `playlist_item_id` to write the `start.timeline_item_id` field. The daemon uses `timeline_item_id` to find the row in `timeline_queue` (a list of `TimelineItem` objects) and indexes into it for `_current_item_index`.

**Why:** the daemon's `TimelineItem` doesn't carry a `playlist_item_id` directly — it derives from the `program_timeline_items` row. Looking up by the timeline row's id is the natural Python key. The PHP side does the cross-table join once, at start, instead of pushing that complexity into the daemon.

### Decision 5: `start` block is opt-in; missing/empty = start at 0

**Choice:** If `ScheduledPositionCalculator::calculate()` throws (e.g. no template, no items), the orchestrator logs the error and writes the config **without** a `start` block. The daemon then falls back to item 0 — the current behavior. Errors are surfaced via the existing HTTP 422 path (`EmissionController::start` catches `\Throwable` and returns the message).

**Why:** a missing `start` block is the safest fallback. We don't want a calculator edge case to brick the start path; the user still gets a channel on-air, just from the beginning of the timeline. The error is logged so the operator can investigate.

## Risks / Trade-offs

- **[Fast seek keyframe-snap]** — `-ss` before `-i` in ffmpeg seeks to the nearest keyframe before the target. For a 3pm start in the middle of a long file, the broadcast clock may be a few seconds off the exact wall-clock target. → Mitigation: use `-noaccurate_seek` only if we ever need frame-accurate seeks; the broadcast clock is already an approximation, and the calculator rounds to the row's `starts_at_sec`. Acceptable for v1.
- **[Timeline row with `cue_in_sec > 0`** — the calculator returns `offset_sec = elapsed - starts_at_sec + cue_in_sec`. The orchestrator must pass `cue_in_sec` separately or include it in the calculation so the daemon's `seek_offset_sec` is the right number to pass to ffmpeg. → Mitigation: the calculator already returns the right number; the orchestrator just stores it. Verified by reading `ScheduledPositionCalculator::calculateFromTimeline()`.
- **[Daemon race with concurrent timeline reload]** — `_reload_timeline()` (heartbeat trigger) re-fetches the timeline via API and rebuilds `timeline_queue`. If a reload happens between config-read and `play_item`, the index could be stale. → Mitigation: the existing reload does not preserve `_current_item_index` either; this is a pre-existing edge case not made worse by this change. Out of scope for v1.
- **[Calculator throws on a degenerate playlist]** — the orchestrator's existing try/catch surfaces a 422 to the user. The channel is not started. That's the right behavior; we don't want the daemon to start without a target.
- **[Midnight rollover]** — at 23:59:59, `secondsSinceMidnight()` is 86399, the timeline-aware branch returns the last row's `final` offset. If the timeline ends before midnight, the calculator's "past last row" path handles it (returns the last row). Acceptable.
- **[Loop-mode playlists]** — for `playlist->loop = true` without a ScheduleTemplate, the calculator's legacy branch uses `fmod(elapsed, total)`. This is by design and unchanged. The operator's complaint is about scheduled slots, which means they're using a template + blocks — the timeline-aware branch.

## Migration Plan

1. **Deploy** (no schema migration, no downtime required):
   - PHP changes go live on the next php-fpm reload (Laravel Octane/FPM will pick up on deploy).
   - Python changes are picked up next time the daemon restarts (which is the natural test moment).
2. **Verify in production**:
   - Stop and start a channel mid-day. Confirm the daemon transitions to the correct timeline item at the correct offset.
   - Confirm `GET /api/channels/{id}/emission/status` reports the correct `current_timeline_item_id`, `content_position_sec`, and `broadcast_clock_sec` immediately after start (not 0/0/null).
   - Confirm the heartbeat matches the seeded values.
3. **Rollback**:
   - Revert the PHP commit → orchestrator falls back to writing no `start` block → daemon starts at item 0 (current behavior).
   - Revert the Python commit → daemon ignores the `start` block if present (because it's wrapped in `if config.get('start'):`) → same fallback.
   - Both rollbacks are independent and safe.

## Open Questions

- Should `broadcast_clock_sec` reflect the current wall-clock at the moment of `start()` (and then derive forward) or should it be the offset of the resolved item? → Current decision: wall-clock seconds since midnight. The daemon overrides it on the next heartbeat with its own counter. This matches the legacy field semantics.
- Should we also pass a `start_at_override` for testing/replay? → Tabled. Out of scope.
- Should the orchestrator store the planned position in `emission_state` so a mid-stream crash can be recovered? → That's the existing heartbeat flow. This change only touches the start path.
