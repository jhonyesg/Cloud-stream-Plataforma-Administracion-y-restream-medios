## Context

The data plane of the Restream module shipped in `add-restream-module`: `restream_quotas` decides who can re-transmit and how many simultaneous destinations, `restream_targets` stores the platform / destination URL / encrypted stream key. The habilitation UI (admin) and CRUD UI (client) work end-to-end. What is missing is the **engine** that consumes those rows and actually pushes pixels to Facebook / TikTok / YouTube / custom RTMP endpoints.

The user's requirement mirrors the (now dismounted) emission module: a long-running supervisor process watches the desired state, spawns one FFmpeg per target, monitors liveness, and updates `status` / `pipeline_pid` / `last_started_at` / `last_error` so the client panel can show live progress. The emission module used a per-channel Python daemon with a GStreamer pipeline (`scripts/playout_gst.py`); for re-streaming we use FFmpeg because RTMP fan-out is FFmpeg's sweet spot and the per-target command is one line.

The engine must coexist with an emission engine that is currently dismounted (per `AGENTS.md`): the `source_url` column accepts any reproducible URL (HLS or RTMP), defaulting to `channel.public_hls_url` as a stop-gap. When emission is rebuilt and exposes an internal RTMP push URL, the operator updates `source_url` (or the default lookup) — no spec change needed.

## Goals / Non-Goals

**Goals:**
- One FFmpeg process per `enabled=true` target, supervised by `restream:supervise`.
- Atomic status transitions persisted to `restream_targets.status` so the UI can show `active` / `error` / `idle` without polling OS state.
- Fast-failure cool-down (5 min) so a target that can't handshake doesn't burn CPU in a tight restart loop.
- Stderr captured to `storage/logs/restream/{target_id}.log` and tailed for error markers.
- HTTP `POST .../start` and `POST .../stop` endpoints so the client UI can launch/stop without waiting for the supervisor's next tick.
- Client panel polls every 5s with `visibilitychange` pause/resume (matches the old emission viewer UX).

**Non-Goals:**
- Re-implementing the emission engine. `source_url` defaults to `channel.public_hls_url`; a future emission rebuild can flip the default.
- WebSocket / SSE push for status. Polling at 5s is enough — same as the previous emission system.
- Per-platform transcoding profiles. We copy video, transcode audio to AAC 44.1kHz stereo at 128k. If a platform later needs different settings (e.g. 60fps, keyframe interval), this is an additive change in `RestreamLauncher::buildAudioArgs()`.
- Authentication / DRM of stream keys. They are already encrypted at rest via `Crypt`; reveal-once is a future enhancement.
- Per-target retry policies (max attempts, exponential backoff beyond the 5-min cool-down).

## Decisions

### D1. FFmpeg per target, not a single multi-output FFmpeg

A single FFmpeg with multiple `-rtmp_live` outputs is technically possible, but: (a) one failing destination takes down all the others on the same process, (b) per-target stderr becomes hard to parse, (c) restart granularity drops to "all or nothing". One process per target is heavier in process count but matches our model (each row has a `pipeline_pid`) and gives clean isolation.

Alternative considered: *one FFmpeg with N `-f flv` outputs*. Rejected for the failure-isolation reasons above.

### D2. Daemon in PHP (`restream:supervise`), not Python

The emission module used Python (`scripts/playout_gst.py`) because GStreamer's strong typing and pipeline DSL are awkward in PHP. FFmpeg is just a command-line tool — there is no value in leaving PHP for the supervisor. Laravel's `Artisan::call()`/`Schedule` patterns plus Symfony's `Process` component give us everything we need: process spawn, stderr capture, signal handling (`pcntl_signal` for SIGTERM/SIGINT).

Alternative considered: *rebuild the emission daemon in Python and reuse it*. Rejected — Python is not a runtime we want to add back just for this; the emission rebuild will be a separate, well-considered effort.

### D3. Process management via Symfony Process + detached `setsid`

We use `Symfony\Component\Process\Process` with `start()` to spawn FFmpeg. The process is detached via `setsid` (or `proc_open` with `=> ['setsid' => true]`) so the supervisor's death doesn't kill the children. Stderr is redirected to `storage/logs/restream/{target_id}.log` via `proc_open`'s stream spec.

Alternative considered: *`Process::start()` without detachment, trust the OS to reap orphans*. Rejected — when `restream:supervise` exits (Ctrl+C), FFmpeg must keep pushing.

### D4. PID liveness via `posix_kill($pid, 0)` (POSIX-only)

`posix_kill($pid, 0)` returns `true` if the process exists and we have permission to signal it (we own the child), `false` otherwise. This is the standard idiom and works on every Linux. We document this as a Linux-only feature (Windows is not in scope for this project).

Alternative considered: *read `/proc/{pid}/stat`*. Equivalent but more code; `posix_kill` is the established PHP idiom.

### D5. Cool-down via `last_failed_at`, not a separate table

A `last_failed_at` timestamp on the target is enough to express "don't restart within 5 minutes of the last failure". A separate `restream_target_failures` table would be overkill for a single counter.

Alternative considered: *queue + scheduled job for retry*. Rejected — adds Redis/queue complexity for what is fundamentally a 5-minute wait.

### D6. Stderr parsing is best-effort, not full regex

We look for a small fixed set of substrings (`Connection refused`, `HTTP error`, `4xx/5xx`, `Broken pipe`, etc.) in the last 64 KiB of the log. FFmpeg's stderr is line-oriented but format varies across versions; substring matching is robust enough to surface the most common failures without breaking on version bumps.

Alternative considered: *structured FFmpeg log levels (`-loglevel verbose -stats`)*. Too brittle for the value.

### D7. Client polling at 5s, pauses on `visibilitychange`

The panel uses `setInterval(refresh, 5000)` and listens to `document.visibilitychange` to clear/resume the timer. This is the same pattern as the old emission viewer. It bounds the load to ~12 requests/minute per active client and respects laptop sleep / tab switching.

Alternative considered: *SSE / WebSocket*. Deferred — adds server complexity for marginal latency improvement at this scale.

### D8. `source_url` is per-target, not a global setting

Some users will want to feed the restream engine from the emission's RTMP push URL; others will want to point at an external HLS source. Storing it per-target (with NULL → fallback to `channel.public_hls_url`) is the most flexible. The admin can later mass-update via SQL if a global change is needed.

Alternative considered: *global config `restream.source_url_default`*. Rejected because channels have different sources in practice.

## Risks / Trade-offs

- **R1 — FFmpeg binary missing in dev** → Mitigation: `RestreamLauncher` constructor throws a clear error; `--dry-run` flag on `restream:start` allows command-line validation without a binary.
- **R2 — Orphan FFmpeg processes if `restream:supervise` crashes without cleanup** → Mitigation: on supervisor boot, scan all targets with non-null `pipeline_pid` and `kill -0` each one; mark dead ones `error`. A separate `restream:cleanup` Artisan command can be run on a cron to forcibly kill orphans by querying `ps` for `ffmpeg` processes whose PID is not in the DB.
- **R3 — FFmpeg writes a lot to stderr; the log file grows unbounded** → Mitigation: the launcher truncates `storage/logs/restream/{target_id}.log` to the last 64 KiB at each tick (cheap, prevents disk-fill).
- **R4 — `proc_open` with shell injection if any field contains shell metacharacters** → Mitigation: build the command as an array passed to `Process::fromArrayCommand` (Symfony's array form is shell-free). `destination_url` and `stream_key` are passed as positional args, never through `shell_exec`.
- **R5 — `posix_kill` is not available on Windows** → Documented as Linux-only; this project already requires Linux per `AGENTS.md`.
- **R6 — Cool-down blocks legitimate recovery** → Mitigation: cool-down is 5 min; if the operator needs to force-restart now, they run `restream:start {target}` (one-shot, no cool-down check).
- **R7 — Many concurrent FFmpeg processes per server** → At tier-4 × 100 active users = 400 FFmpeg processes; acceptable on a modern server (FFmpeg uses ~50–150 MB RSS per copy-codec stream), but the supervisor should log a warning if process count exceeds e.g. 200.
- **R8 — Stale `pipeline_pid` if FFmpeg dies outside the supervisor's awareness** → Mitigation: the supervisor's tick checks liveness every 5s; stale PIDs are cleared within one tick.

## Migration Plan

1. **Backup**: `php artisan db:backup`.
2. **Migrate**: `php artisan migrate:safe` — runs the up-only `add_engine_columns_to_restream_targets` migration (adds `source_url text NULL` + `pipeline_pid integer NULL` + `last_failed_at timestamptz NULL`). No destructive change to existing rows; new columns are NULL.
3. **Deploy supervisor**: systemd unit (or `nohup`) running `php artisan restream:supervise --interval=5`. Document in `AGENTS.md` under a new "Restream Engine" subsection.
4. **Smoke test (dry-run)**: `php artisan restream:start --dry-run` on a test target — confirms the FFmpeg command line is well-formed without spawning.
5. **Smoke test (live)**: enable a target pointing at `rtmp://test.local/live/test`, run `restream:supervise --once`, confirm PID written, status `active`, log file created.
6. **Rollback**: the migration has an empty `down()` that drops the three new columns; supervisor process can be stopped without DB impact.

## Open Questions

- **Q1**: Should we expose `source_url` in the client's create/edit form, or keep it admin-only? → Default: keep it admin-only (it's an advanced knob the typical user doesn't need); admin can set it via the existing admin target list page. Confirm with operator before exposing.
- **Q2**: When emission is rebuilt and emits an RTMP push URL, do we add a `restream_targets.emission_channel_rtmp` lookup or just set `source_url` globally? → Defer until emission spec is written; for now `source_url` is the single source of truth.
- **Q3**: Should `restream:status` write to a JSON file as well, for an external monitoring tool (Prometheus, Netdata)? → Nice-to-have, defer.