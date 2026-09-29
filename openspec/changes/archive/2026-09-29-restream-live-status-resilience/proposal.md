## Why

The previous change `restream-live-status-countdown` shipped a real "En vivo en YouTube" badge and a live banner, but operators still hit two failure modes:

1. **The panel's `Programación` column shows "—" for live targets** because we render `scheduled_starts_at` (the next pending window) instead of a per-second countdown to `next_ends_at` (the running window). The data is already in the JSON; it just isn't displayed in the row.
2. **The restream daemon silently keeps reporting `state=playing`** when YouTube's RTMP edge closes the connection. The operator must log in to the server and `kill -9` the ffmpeg child manually. We hit this exact bug today on target `a2dd4bb8-…`: YouTube's socket went `CLOSE-WAIT`, the daemon reported "live", the operator saw buffered frames on YouTube and no new data, and recovery required manual intervention.

The current watchdog (`restream_daemon/daemon/target_daemon.py:_stats_loop`) only triggers a child restart when either (a) the ffmpeg process exits or (b) `frames_sent` (a fake counter from `elapsed * fps`) stops increasing. A `CLOSE-WAIT` socket passes both checks because ffmpeg keeps reading source data while writing into a dead socket, and `frames_sent` keeps incrementing from wall-clock math.

## What Changes

- **Inline countdown in the restream panel** — for every target with `next_ends_at`, render a per-second countdown (`Termina en HH:MM:SS` / `Termina en Xm Xs` / `Finalizado`) next to the status badge in both `resources/views/client/restream/index.blade.php` and `resources/views/admin/restream/index.blade.php`. Pure Alpine `setInterval`, no extra HTTP requests, mirrors the same pattern already in the schedules view.
- **Daemon real bitrate from stderr** — `emisor_python/pipeline/ffmpeg_pipeline.py:FFmpegProcess.get_stream_stats()` MUST parse the latest `frame=`, `fps=`, and `bitrate=` lines from ffmpeg's stderr (already buffered in `self.proc.stderr`) and return them. Replaces the current `int(elapsed * fps)` estimate.
- **Daemon dead-output detection** — `_stats_loop` MUST additionally check the file descriptor of the ffmpeg child (via `/proc/<pid>/fd` or `socket(fileno)` polling) for state `CLOSE-WAIT` or `TIME-WAIT`. If detected, log `[WATCHDOG] RTMP output socket closed` and trigger `_restart_child()`. Backoff: same exponential as existing `max_restarts=5 in 60s`.
- **Resolver marks yt-no-data when daemon reports progress stall** — `App\Services\Restream\RestreamStatusResolver::resolve()` MUST map the new `daemon_stalled_at` timestamp (added to the heartbeat payload) to `effective_status='yt-no-data'` when fresh + stalled > 30 s. Gives the UI a real signal to alert on.
- **New heartbeat field** — add `daemon_stalled_at` to the daemon's heartbeat payload so Laravel knows when the daemon itself detected a stall. Optional new column `daemon_stalled_at` on `restream_targets`, nullable.

## Capabilities

### New Capabilities
<!-- None. Both surfaces are extensions of `restream-targets` + `restream-engine`. -->

### Modified Capabilities
- `restream-targets`: adds a new requirement for inline countdown display in the panel + `daemon_stalled_at` propagation from heartbeat to resolver. See `specs/restream-targets/spec.md`.
- `restream-engine`: adds a new requirement for daemon dead-output detection + real-bitrate parsing from ffmpeg stderr. See `specs/restream-engine/spec.md`.

## Impact

- **Python daemon code** (`restream_daemon/daemon/target_daemon.py`, `emisor_python/pipeline/ffmpeg_pipeline.py`): the only non-PHP change. Same standard followed by `emisor_python/` (FFmpegProcess is shared between emission and restream). Risk: changing `get_stream_stats()` is consumed by emission too — verify emission panel still shows correct values.
- **Database**: 1 new nullable column `daemon_stalled_at` on `restream_targets`. Additive, idempotent migration.
- **Heartbeat endpoint** (`POST /api/internal/restream/{target}/heartbeat`): accepts the new `daemon_stalled_at` field.
- **Code**: ~2 Python files, 1 migration, 1 column on `RestreamTarget` model + presenter + resolver, edits to 2 Blade views. Total new files: 1 (migration). Modified files: ~7.
- **UI/API**: 2 new JSON fields per target (`daemon_stalled_at`, real `video_bitrate` from daemon heartbeat). Existing 10-s index poll is unchanged.
- **Reversibility**: additive migration + daemon config-only changes (no protocol changes). `git revert <sha>` + `php artisan migrate:rollback --step=1` reverts cleanly.