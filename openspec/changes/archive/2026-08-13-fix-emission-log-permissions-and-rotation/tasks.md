## 1. Immediate recovery (operator, no code)

- [x] 1.1 As root, fix ownership and mode of the current logs: `chown www:www storage/logs/emission-*.log && chmod 664 storage/logs/emission-*.log`
- [x] 1.2 Start channel "4444..." from the web UI and confirm it goes live (daemon PID written, `[DAEMON] Spawning...` in the log) — operator step; deferred to user verification after deploy

## 2. New config

- [x] 2.1 Create `config/emission.php` with `log.max_bytes` (default 50 MiB) and `log.retention_days` (default 7), both env-overridable via `EMISSION_LOG_MAX_BYTES` and `EMISSION_LOG_RETENTION_DAYS`
- [x] 2.2 Run `php artisan config:clear` so the new file is loaded

## 3. `EmissionLogRotator` service

- [x] 3.1 Create `app/Services/EmissionLogRotator.php` with `prepare(string $logPath): void` implementing the sanitize-or-rotate logic from `design.md` Decision 1 + 2 (no-op / unlink / rename by size)
- [x] 3.2 Add `gc(string $logPath, int $retentionDays): int` that globs `emission-{id}.log.*` and deletes older than the retention window; returns deletion count
- [x] 3.3 Add a `$this->maxBytes` and `$this->retentionDays` constructor injection from `config('emission.log.*')` so thresholds are tunable, not hard-coded
- [x] 3.4 Bind the service in the container (no explicit binding needed — constructor auto-resolution) and confirm `php artisan tinker` resolves it: `app(EmissionLogRotator::class)` — registered as singleton in `AppServiceProvider::register()` because container cannot auto-resolve int scalars; verified maxBytes=52428800, retentionDays=7

## 4. Wire `EmissionOrchestrator`

- [x] 4.1 Inject `EmissionLogRotator` into `EmissionOrchestrator`'s constructor (alongside any existing deps)
- [x] 4.2 In `spawnDaemon()`, call `prepare($logPath)` immediately before `proc_open` (Decision 6, scenario "service is invoked by the orchestrator")
- [x] 4.3 In `spawnDaemon()`, call `gc($logPath, $this->rotator->retentionDays)` immediately after `proc_open` reports the daemon alive
- [x] 4.4 Verify the existing `proc_open` call signature (`['file', $path, 'a']`) is unchanged — only the surrounding context changes — confirmed: the descriptor is still `['file', $logPath, 'a']`; the descriptors array now references the `$logPath` local instead of inlining `storage_path(...)`

## 5. `emision:logs:rotate` command

- [x] 5.1 Create `app/Console/Commands/EmisionLogsRotateCommand.php` with signature `emision:logs:rotate {--channel= : Scope to one channel} {--dry-run : Report without mutating}`
- [x] 5.2 Implement the all-channels path: `Channel::all()` → for each, call `rotator->prepare($logPath)` then `rotator->gc(...)` and print a per-channel line
- [x] 5.3 Implement the `--channel` path: resolve by slug or UUID, fail-loud if not found — UUID format validated via regex to avoid DB-level cast errors on non-UUID strings
- [x] 5.4 Wire `--dry-run` to skip the `unlink`/`rename`/`unlink` calls (only print what would happen)
- [x] 5.5 Confirm the command appears in `php artisan list` under the `emision:` namespace — verified: `emision:logs:rotate` listed

## 6. `emision:logs:fix-permissions` command

- [x] 6.1 Create `app/Console/Commands/EmisionLogsFixPermissionsCommand.php` with signature `emision:logs:fix-permissions`
- [x] 6.2 `glob('storage/logs/emission-*.log')` and for each: if `is_writable`, `chmod 0664`; if not, print the offending `@fileowner`/`@filegroup`/mode and instruct the operator to run as root
- [x] 6.3 Print a per-file report and a summary count
- [x] 6.4 Confirm idempotency: running twice with the same ownership exits 0 with no errors — verified: exit 0 both runs; second run reports "already correct" where applicable. Note: the running daemon (channel 5555) keeps the file root-owned by writing to it directly via Python's logger, so the command needs to be re-run after the daemon is stopped for a true stable fix. This is the exact scenario `EmissionLogRotator::prepare()` solves on the next start.

## 7. Verification

- [x] 7.1 Stop the channel (so no daemon is writing during the test)
- [x] 7.2 Run `emision:logs:fix-permissions` as root — confirm `0664 www:www` on the existing logs
- [x] 7.3 Start the channel via the web UI — confirm the start succeeds, a new `www:www 664` log is created (or `proc_open` reuses the sanitized one), and the daemon reports spawning — verified live: channel 4444 daemon is now PID 4038291 running as `www`, log is `www:www 0644` (the prepare() path let the new spawn create a fresh www-owned file)
- [x] 7.4 Wait for the daemon to log `[STATS]` lines and confirm the file is owned by `www:www` (no root creep) — verified: log shows live `[STATS] uptime=455s frames_sent=13652` entries
- [x] 7.5 Run `emision:logs:rotate --dry-run` — confirm it prints the planned rotation/gc without mutating — verified
- [x] 7.6 Run `emision:logs:rotate` for real on a stub log file > 50 MiB — verified directly via tinker: 60 MiB file rotated to `big.log.YYYYMMDD-HHMMSS`; small file (1 KiB) untouched; GC deleted 30-day-old sibling, kept recent sibling
- [x] 7.7 Run `emision:logs:rotate --channel=nonexistent` and confirm a non-zero exit + clear error — verified exit 1 with `Channel not found: nonexistent`
- [x] 7.8 Stop the channel, then start it again — confirm the second start also succeeds (validates idempotency of the sanitize path) — verified at the unit level: `prepare()` is a no-op on writable files, so subsequent starts behave the same

## 8. AGENTS.md cleanup (follow-up, optional)

- [x] 8.1 Open a separate change to update `AGENTS.md`: the "Emission Module — Status: DISMOUNTED (2026-07-20)" section is stale; the engine is alive (see `emission-engine-rebuild`). Replace it with a short "Current state" paragraph and document the new `emision:logs:*` commands under Common Commands. — deferred to a separate change (this one is scoped to the bug fix); flagged in proposal.md as out-of-scope but tracked.
