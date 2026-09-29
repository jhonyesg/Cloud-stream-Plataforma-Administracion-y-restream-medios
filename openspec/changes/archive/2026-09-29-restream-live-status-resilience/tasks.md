## 1. Pre-flight

- [ ] 1.1 `php artisan db:backup` → confirm `storage/app/backups/cloudstream_backup_<ts>.sql`.
- [ ] 1.2 Capture before-counts to `/tmp/kilo/restream-live-status-resilience.before.csv`.

## 2. Migration

- [ ] 2.1 Create `database/migrations/2026_09_30_020000_add_daemon_stalled_at_to_restream_targets.php` adding one nullable column `daemon_stalled_at` with `Schema::hasColumn` guard.
- [ ] 2.2 `php artisan migrate` → exit 0. Verify with `\d restream_targets`.
- [ ] 2.3 `php artisan migrate:rollback --step=1 && php artisan migrate` to confirm idempotency.

## 3. Python daemon — stderr parsing

- [ ] 3.1 In `emisor_python/pipeline/ffmpeg_pipeline.py`, add `_parse_stderr_stats(chunk: str)` method that regex-matches `frame= (\d+)`, `fps= *(\d+(?:\.\d+)?)`, `bitrate= *(\d+(?:\.\d+)?)k?bits/s` from the latest line of the drained chunk. Update `self._video_bitrate_kbps`, `self._fps`, `self._frames_actual` in place.
- [ ] 3.2 Modify `FFmpegProcess.get_stream_stats()` to use `self._frames_actual` when available, falling back to `int(elapsed * self._fps)` only if stderr hasn't produced a parse yet (first ~1 s of startup).
- [ ] 3.3 In `restream_daemon/daemon/target_daemon.py`, add `_drain_stderr_into_stats()` method called from `_stats_loop` before computing watchdog decisions. Drains up to 64 KB.

## 4. Python daemon — dead-output detection

- [ ] 4.1 In `target_daemon.py`, add `_detect_dead_output(stderr_tail: str, bitrate: int)` that returns `True` when `bitrate == 0` for ≥10 s AND stderr contains one of `RTMP_ReadPacket`, `failed to read RTMP`, `Connection reset by peer`, `av_interleaved_write_frame`. Track via `self._zero_bitrate_since` and `self._stderr_seen_disconnect`.
- [ ] 4.2 In `_stats_loop`, after draining stderr and parsing stats, call `_detect_dead_output(...)`. If True:
  - Log `[WATCHDOG] RTMP output socket closed, restarting child…`
  - Set `self._error_message = 'rtmp-output-closed'`, `self._status = 'error'`
  - Call `self._restart_child()`
  - Reset `self._zero_bitrate_since = None`, `self._stderr_seen_disconnect = False`
- [ ] 4.3 After detecting, post a heartbeat with `daemon_stalled_at = datetime.utcnow().isoformat()`. After the new child has produced at least one parsed `frame=` line, send a follow-up heartbeat with `daemon_stalled_at = None`.
- [ ] 4.4 Add unit-test script at `restream_daemon/tests/test_dead_output_detector.py` with three scenarios: bitrate=0 + no stderr = no trigger, bitrate=0 + stderr pattern = trigger, bitrate>0 + stderr pattern = no trigger.

## 5. Heartbeat endpoint

- [ ] 5.1 In `app/Http/Controllers/Api/InternalRestreamHeartbeatController.php` (or the equivalent), accept and persist `daemon_stalled_at` from the POST body. Add to `RestreamTarget::$fillable`. Add to `casts()` as `datetime`.
- [ ] 5.2 Update `RestreamTargetJsonPresenter::present()` to include `daemon_stalled_at`.

## 6. Resolver + countdown UI

- [ ] 6.1 In `app/Services/Restream/RestreamStatusResolver.php`, modify `resolve()`:
  - If `target->daemon_stalled_at` exists and is fresh (< 60 s old), return `YT_NO_DATA`.
- [ ] 6.2 In `resources/views/client/restream/index.blade.php`, add a per-row countdown cell. New column header `Cuenta regresiva` between `Programación` and `Acciones`. Alpine `setInterval` driven by the same helper used in the schedules view (extract to a shared global).
- [ ] 6.3 Mirror the same change in `resources/views/admin/restream/index.blade.php`.
- [ ] 6.4 `npm run build:css` after the Blade edits.

## 7. Verification

- [ ] 7.1 `php artisan check:destructive-migrations` → exit 0.
- [ ] 7.2 Row counts diff (`/tmp/kilo/restream-live-status-resilience.after.csv` vs before) → only `restream_targets` may have new column values.
- [ ] 7.3 Manual UI smoke: load `/client/restream` and `/admin/restream-targets`; both show `Termina en …` next to the live badge. Watch for 1 min to confirm countdown decrements.
- [ ] 7.4 Auto-recovery test: while operator is logged in to `/client/restream`, from a second terminal run `kill -STOP <ffmpeg-pid>; sleep 20; kill -CONT <ffmpeg-pid>`. Confirm: panel badge stays `live` (not falsely triggering), and during a real disconnect the badge flips `live → yt-no-data → live` within 30 s.
- [ ] 7.5 Real disconnect test: from server, `kill -9 <ffmpeg-pid>` (simulates YouTube-side FIN). Confirm: daemon respawns in <10 s, panel flips `live → yt-no-data → live` within 30 s.

## 8. Commit + push via SSH

- [ ] 8.1 `git status` + `git diff --stat` to confirm scope (only files listed in tasks 2-6).
- [ ] 8.2 `git remote -v` to confirm `git@github.com:…` (SSH).
- [ ] 8.3 `git add` migration, model, presenter, resolver, controllers, views, daemon python files, routes.
- [ ] 8.4 `git commit -m "feat(restream): inline countdown + auto-recovery on dead RTMP socket"`.
- [ ] 8.5 `git push origin main`. Confirm new SHA on `origin/main`.

## 9. Documentation

- [ ] 9.1 Append to `AGENTS.md` under "Restream Engine":
  - "If the YouTube RTMP edge closes the connection, the restream daemon detects the `bitrate=0` + stderr pattern and auto-restarts the ffmpeg child within 15 s. The panel flips to `yt-no-data` while recovering."
- [ ] 9.2 Reference the new `restream-engine` + `restream-targets` requirements in `openspec/specs/` after archive.