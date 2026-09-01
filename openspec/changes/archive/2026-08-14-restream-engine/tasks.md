## 1. Setup & safety

- [x] 1.1 Run `php artisan db:backup` (mandatory per AGENTS.md before any migration)
- [x] 1.2 Confirm `php artisan check:destructive-migrations` passes baseline

## 2. Database schema

- [x] 2.1 Create migration `2026_08_14_100000_add_engine_columns_to_restream_targets.php` adding: `source_url text NULL`, `pipeline_pid integer NULL`, `last_failed_at timestamptz NULL`. Provide empty `down()` that drops these three columns. Run `php artisan migrate:safe`.
- [x] 2.2 Confirm new columns exist on `restream_targets` via `php artisan db:table restream_targets`

## 3. Model & helpers

- [x] 3.1 Add `source_url`, `pipeline_pid`, `last_failed_at` to `App\Models\RestreamTarget::$fillable` and `casts()` (pipeline_pid→int, last_failed_at→datetime)
- [x] 3.2 Add `RestreamTarget::isCurrentlyRunning(): bool` returning `posix_kill($this->pipeline_pid, 0)` when pid is set, else false
- [x] 3.3 Add `RestreamTarget::platformDefaults()` static helper that returns the default FFmpeg args per platform (currently a single profile for all)

## 4. RestreamLauncher service

- [x] 4.1 Create `App\Services\Restream\RestreamLauncher.php` with constructor that resolves `$ffmpegBin` from `env('FFMPEG_BIN', 'ffmpeg')` and throws `RuntimeException` if missing
- [x] 4.2 Implement `buildCommand(RestreamTarget $t): array` returning the Symfony array command form (no shell). Use `[$ffmpegBin, '-hide_banner', '-loglevel', 'info', '-re', '-i', $sourceUrl, '-c:v', 'copy', '-c:a', 'aac', '-ar', '44100', '-ac', '2', '-b:a', '128k', '-f', 'flv', '-rtmp_live', 'live=1', $destUrl . '/' . $streamKey]`. Source = `$t->source_url ?? $t->channel->public_hls_url ?? ''`. Throw if either is empty.
- [x] 4.3 Implement `start(RestreamTarget $t, bool $dryRun = false): int` using `Symfony\Component\Process\Process::fromArrayCommand($cmd)`. Set working dir to `storage/logs/restream/` (create if missing). Redirect stderr to `{target_id}.log` (truncate first). Call `$process->start()` and return pid. Persist `pipeline_pid`, `last_started_at = now()`, `status = 'active'` on success. On failure, throw and set `status = 'error', last_error = $e->getMessage(), last_failed_at = now()`.
- [x] 4.4 Implement `isAlive(RestreamTarget $t): bool` using `posix_kill($t->pipeline_pid, 0)`. Return false if pid is null.
- [x] 4.5 Implement `stop(RestreamTarget $t): void` using `posix_kill($t->pipeline_pid, SIGTERM)` then poll `isAlive()` every 500ms for 5s; escalate to `SIGKILL` if still alive. Persist `pipeline_pid = null`, `last_stopped_at = now()`, `status = 'idle'`, clear `last_error`.
- [x] 4.6 Implement `tailStderr(RestreamTarget $t, int $bytes = 65536): string` reading the tail of the log file; implement `parseErrorMarkers(string $tail): ?string` returning the first line matching one of the failure substrings or null. Implement `recordErrorIfAny(RestreamTarget $t)` that calls the two and persists `last_error` if a marker is found.

## 5. RestreamSupervisor service

- [x] 5.1 Create `App\Services\Restream\RestreamSupervisor.php` with `__construct(RestreamLauncher $launcher)` injection
- [x] 5.2 Implement `tick(): array` returning `['started' => N, 'stopped' => N, 'error' => N]` after running the reconciliation logic from spec `restream-engine` §"Supervisor reconciles desired vs actual state" + §"Supervisor restarts targets that fail fast" (5-min cool-down via `last_failed_at`)
- [x] 5.3 Each action writes one `INFO` line via `Log::info('[restream] …', [...])` with target_id, platform, pid, action
- [x] 5.4 On supervisor boot, scan all targets with non-null `pipeline_pid`, kill -0 each, mark `status='error', last_failed_at=now(), pipeline_pid=null` for the dead ones (orphan recovery)

## 6. Artisan commands

- [x] 6.1 Create `App\Console\Commands\RestreamSuperviseCommand` with signature `restream:supervise {--interval=5} {--once}`. On boot, call the orphan-recovery logic. Loop calling `tick()` and `sleep($interval)`. Handle SIGINT/SIGTERM gracefully (log + exit 0).
- [x] 6.2 Create `App\Console\Commands\RestreamStartCommand` with signature `restream:start {target?} {--dry-run}`. If target id given, start just that one; otherwise iterate over `enabled=true && pipeline_pid IS NULL`. With `--dry-run`, call `launcher->buildCommand()` and print, never persist.
- [x] 6.3 Create `App\Console\Commands\RestreamStopCommand` with signature `restream:stop {target?}`. Iterates and calls `launcher->stop()`. Idempotent — skips targets with null pid.
- [x] 6.4 Create `App\Console\Commands\RestreamStatusCommand` with signature `restream:status`. Iterates all targets and prints an ASCII table (id / channel / platform / name / status / pid / alive / last_started / last_stopped / last_error truncated to 60 chars)
- [x] 6.5 Register the four commands in `app/Console/Kernel.php` (or `routes/console.php` if using Laravel 11's auto-discovery — confirm by reading `bootstrap/app.php`)
- [x] 6.6 Run `php artisan list` and confirm `restream:supervise`, `restream:start`, `restream:stop`, `restream:status` appear

## 7. HTTP start/stop endpoints (client)

- [x] 7.1 Add `start(Request, Channel, RestreamTarget)` and `stop(...)` methods to `App\Http\Controllers\Client\RestreamTargetController`. Both methods: `abort_unless($user->canAccessChannel($channel), 403)` + ownership check + `EnsureRestreamEnabled` middleware already covers habilitation.
- [x] 7.2 `start()`: if `enabled=false`, validate the user isn't over cap (`RestreamQuotaGuard::assertCanEnable($user)` only when the toggle would push over cap). Flip `enabled=true`, call `launcher->start($target)`. Return `{status: 'active' | 'error', pipeline_pid: int | null, last_error: ?string}` and write one `audit_logs` row with `action='start.restream_target'`.
- [x] 7.3 `stop()`: call `launcher->stop($target)` and write one `audit_logs` row with `action='stop.restream_target'`.
- [x] 7.4 Add routes `POST /client/channels/{channel}/restream-targets/{target}/start` and `.../stop` inside the existing `restream.enabled` middleware group in `routes/web.php`. Confirm with `php artisan route:list`.

## 8. Client panel live status

- [x] 8.1 Update `resources/views/client/channels/_restream_panel.blade.php` to add a `<span class="animate-pulse bg-green-500 rounded-full">…</span>` next to each target row when `t.status === 'active'`, and a red dot with `title={t.last_error}` when `t.status === 'error'`.
- [x] 8.2 Add a `setInterval(refresh, 5000)` poll that re-fetches `/client/channels/{c}/restream-targets` every 5s and re-renders the targets list. Pause the interval when `document.visibilityState === 'hidden'` and resume on `'visible'`. Stop the interval when the panel's Alpine scope is destroyed (`$cleanup`).
- [x] 8.3 Add a manual `[Actualizar]` button next to the Slots badge that triggers an immediate refresh.

## 9. Audit, encryption & safety

- [x] 9.1 Confirm the launcher builds the FFmpeg command via array form (`Process::fromArrayCommand`), never `shell_exec` — grep the file to be sure.
- [x] 9.2 Confirm `stream_key` is decrypted only inside `buildCommand()` via `Crypt::decryptString($t->getRawOriginal('stream_key'))` and never written to logs (audit log payloads use `stream_key_set` + `stream_key_last4`).
- [x] 9.3 Add smoke test script `scripts/restream-engine-smoke.php` that: (a) creates a target pointing at a non-existent RTMP URL; (b) calls `launcher->start()` with `--dry-run` and prints the command; (c) calls `restream:status` and verifies the table renders; (d) cleans up. Confirm it runs end-to-end.

## 10. Documentation & final checks

- [x] 10.1 Append a "Restream Engine" section to `AGENTS.md` summarizing: the supervisor command, the per-target FFmpeg process model, the `source_url` fallback to `channel.public_hls_url`, the 5-min cool-down, the systemd deployment hint.
- [x] 10.2 Run `php artisan view:clear && php artisan config:clear && php artisan route:clear && php artisan view:cache && php artisan view:clear` to confirm new templates compile.
- [x] 10.3 Run `php artisan check:destructive-migrations` to confirm no destructive migration slipped in.
- [x] 10.4 Grep verification per AGENTS.md dual-implementation rule:
  - `grep -n "canAccessChannel" app/Http/Controllers/Client/RestreamTargetController.php` → must show scoping on the new `start`/`stop` methods
  - `grep -rn "restream.enabled" routes/` → must match the middleware alias used on the route group
  - `grep -rn "ffmpeg\|RestreamLauncher" app/Console/Commands/` → must show the four commands wired to the launcher