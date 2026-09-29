## Context

The `restream-live-status-countdown` change shipped YouTube lifecycle sync + countdown on the schedule view. In production we immediately hit two issues:

1. The restream panel's "Programación" column shows "—" for live targets because we render the next *pending* window's start, not the currently *running* window's end. Operators want to see "Termina en 01:23:45" inline next to the live badge without opening the schedules sub-page.

2. YouTube's RTMP edge silently closed the connection on target `a2dd4bb8-…`. The ffmpeg child kept reading from `rtmp://127.0.0.1:1935/live/pruebas123` and writing into a `CLOSE-WAIT` socket. The daemon's `_stats_loop`:
   - Polls `self.proc.poll()` every 5 s → returns `None` (process alive)
   - Reads `current_frames` from `int(elapsed * self._fps)` → keeps growing from wall-clock math
   - Compares to `last_frames` → always different → not flagged as stuck
   So `state=playing`, `bitrate=0kbps` (because `video_bitrate_kbps` was never updated), and the panel says "En vivo en YouTube" while YouTube's player just shows the last buffered frame. Recovery required `kill -9 <ffmpeg>` from the operator.

The emission pipeline already parses ffmpeg stderr for real metrics. The restream daemon inherits `FFmpegProcess` from `emisor_python/pipeline/ffmpeg_pipeline.py` but never wired up the stderr drain. This change closes that gap.

## Goals / Non-Goals

**Goals**
- Inline per-second countdown in the restream panel (client + admin).
- Real bitrate / frames in the daemon stats, parsed from ffmpeg stderr.
- Dead-output detection (CLOSE-WAIT / RST / no-progress for >15 s on the RTMP output socket) → automatic child restart.
- Surface a `daemon_stalled_at` signal from the heartbeat to the resolver, so the UI flips to `yt-no-data` while the daemon is recovering.

**Non-Goals**
- Generic output-dead detection for arbitrary RTMP targets (Facebook, TikTok, custom RTMP). The same code path works mechanically, but the lifecycle-sync only covers YouTube, so non-YouTube targets will continue to show "Daemon activo, YouTube sin datos" (which is harmless). Out of scope: making the lifecycle column source agnostic.
- Replacing the entire Python supervisor with a Rust binary. Not worth it; the existing supervisor works once it sees the right signals.
- YouTube-side reconnect / stream-key rotation. If YouTube revokes the key, the operator must use the "Limpiar" / recreate flow.

## Decisions

### Decision 1 — Inline countdown rendered by Alpine, no extra endpoint

**Rationale.** The JSON already carries `next_ends_at`. The schedules view's `data-countdown-schedule` pattern is small (30 lines, setInterval). Re-using it in the restream index keeps one implementation for both surfaces and avoids a separate JS helper.

**Alternative considered.** A separate `<x-restream-countdown>` Blade component. Rejected — the schedules view doesn't use it as a component either, so introducing a new component would create two parallel paths.

### Decision 2 — Real stats from stderr parsing, not socket polling

**Rationale.** ffmpeg already writes `frame= N fps= N bitrate= Nkbits/s` lines to stderr every ~1 s. The emission pipeline (`emisor_python/pipeline/ffmpeg_pipeline.py:drain_stderr`) already drains stderr in non-blocking chunks. The restream daemon inherits `drain_stderr` but never calls it. Adding a 5-line `_parse_stats(line)` method that regexes the most recent `frame= / bitrate=` lines is the cheapest reliable signal.

**Alternatives considered.**
- (a) Read `/proc/<pid>/net/tcp` for the ffmpeg child and look for `06` (TIME_WAIT) / `08` (CLOSE_WAIT) state. Rejected — adds a 50-line dependency on `/proc` parsing that breaks on non-Linux hosts.
- (b) Wrap ffmpeg with a stdout parser that mirrors its RTMP frames. Rejected — doubles the complexity for the same data.
- (c) Use `pyffmpeg` or `av` Python bindings. Rejected — adds a native dependency to the supervisor, complicates the systemd unit.

### Decision 3 — Dead-output detection via stderr pattern + child file descriptors

**Rationale.** ffmpeg's stderr prints `RTMP_ReadPacket: failed to read RTMP packet` and `av_interleaved_write_frame: Connection reset by peer` within seconds of the RTMP socket closing. We can detect by parsing stderr for those specific lines. If three consecutive 5-s samples show both:
- `bitrate=0kbits/s` (no progress)
- A "Connection reset" or "failed to read RTMP" line in the last 5 KB
…then `_restart_child()`.

**Alternative considered.** TCP keepalive polling via `select()`. Rejected — adds socket-level code without a Python stdlib helper for non-blocking `connect`/`recv`. The stderr signal is enough.

### Decision 4 — `daemon_stalled_at` on heartbeat payload → resolver maps to `yt-no-data`

**Rationale.** When the daemon detects the dead output, it sends a heartbeat with `daemon_stalled_at: now()`. The resolver sees a fresh heartbeat + non-null `daemon_stalled_at` ≤ 30 s old → returns `yt-no-data` (yellow badge, not red, not green). The badge flips back to `live` once the new ffmpeg child establishes a working connection.

**Alternative considered.** Have the resolver read the daemon log file. Rejected — couples the resolver to disk I/O and log rotation semantics.

### Decision 5 — Same column count and migration idempotency as before

**Rationale.** One new nullable column (`daemon_stalled_at`). Migration uses `Schema::hasColumn()` guards. No `WithDataSafetySnapshot` needed because no data is mutated; rollback drops the column safely.

## Risks / Trade-offs

| Risk | Mitigation |
|---|---|
| Stderr parsing breaks when ffmpeg changes its line format across versions | Regex matches three independent anchors (`frame=`, `fps=`, `bitrate=`); if any line fails to match, the previous values are kept. Worst case: stats freeze at last good value, no crash. |
| Stderr buffer fills up if ffmpeg is too verbose (`-loglevel info` produces ~1 line/s, not a real risk) | `drain_stderr(max_bytes=65536)` truncates. Existing pattern. |
| Restarting the ffmpeg child while YouTube is still finishing up the prior CLOSE_WAIT causes a "Stream is offline" message to viewers | Restart happens within ≤15 s of detection. YouTube's overlay reappears within 5–10 s of new connection. Net downtime: 15–25 s, not 0, but acceptable for an outage. Documented. |
| `daemon_stalled_at` is set by the daemon when *it* decides to stall, but the resolver also computes from heartbeat age. Two definitions of "stalled" | Documented in code: `daemon_stalled_at` is set by the daemon when it detects zero bytes written to the output socket for >10 s. Heartbeat age is fresh until *is*. Both signals are independent. |
| Python changes affect emission pipeline too (shared `FFmpegProcess`) | Emission pipeline already calls `get_stream_stats()` to display real bitrate; emitting behavior is unchanged because the parsing is opt-in (only kicks in if stderr is drained). Tested in step 6.4 of tasks. |
| Operator disables auto-recovery by killing the daemon; new child never starts | `_restart_child()` is called inside the supervisor loop. Killing the daemon kills the child too. Restarting the daemon reads the config JSON from disk and starts fresh. Standard. |

## Migration Plan

1. **Backup**: `php artisan db:backup` (mandatory).
2. **Deploy code**:
   - Push commit to GitHub via SSH (operator's previous request).
   - `git pull` on the production box.
3. **Migrate**: `php artisan migrate` → 1 new migration adds `daemon_stalled_at` column.
4. **Restart restream daemons** (operator action once):
   - `php artisan restream:stop-all` (if it exists) OR `systemctl restart cloudstream-restream@<target-id>` per target.
   - Reason: the running daemon is still the old code; only after restart it parses stderr.
5. **Verify in browser**:
   - Inline countdown visible on `/client/restream` and `/admin/restream-targets`.
   - Open dev tools network tab → no extra requests beyond the existing 10-s index poll.
6. **Auto-recovery smoke test** (operator runs once, not in CI):
   - Pick a live target. From the server, `kill -STOP <ffmpeg-pid>` for 20 s, then `kill -CONT`.
   - Watch the panel: badge should flip `live → yt-no-data → live` within 30 s. No manual intervention needed.

**Rollback**:
- `git revert <sha>`.
- `php artisan migrate:rollback --step=1` (drops `daemon_stalled_at`).
- Old daemon binary keeps running (no protocol change); the new heartbeat field is ignored.

## Open Questions

- **Q1.** Should `daemon_stalled_at` also be exposed in the schedules view? Probably not — schedules don't care about the daemon state. Confirmed out of scope.
- **Q2.** Should we add a Slack / email notification when a target auto-restarts? Useful for ops but out of scope. Could be a follow-up.
- **Q3.** Should the new bitrate feed into a "quality degradation" warning (e.g., <500 kbps for >30 s)? Same follow-up.