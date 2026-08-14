## Context

The emission engine spawns a Python daemon via `proc_open` whose stderr is redirected to `storage/logs/emission-{channelId}.log` opened in append mode (`'a'`). The PHP process doing the `proc_open` is whatever user serves the request: typically `www` (php-fpm) for HTTP starts, occasionally `root` for CLI starts.

The daemon itself runs as `root` (verified 2026-07-24: `python3 emisor_python/main.py --channel-id=5555... --port=0` had `root` as owner). It also writes to the same log file directly through Python's logger, and performs its own atomic rotation (write to `.tmp`, then `rename` to `.log`). That rotation is what killed channel "4444..." on 2026-07-24: the `rename` failed with `[Errno 2] No such file or directory` and the daemon exited.

The result on disk today:

```
-rw-r--r-- root root 26717 storage/logs/emission-44444444-...log
-rw-r--r-- root root 18412 storage/logs/emission-55555555-...log
drwxrwxr-x www  www  storage/logs/
```

`'a'` append requires write permission, which `www` lacks on root-owned files. The "stop & start" cycle then hangs the start path with `Permission denied` indefinitely.

The current process model couples file ownership to whoever initialized the directory tree, with no recovery path.

## Goals / Non-Goals

**Goals:**
- The `proc_open` write path must succeed regardless of who previously created or wrote to the log file.
- Logs older than a size threshold must rotate to a timestamped sibling without operator intervention.
- Rotated logs older than a retention window must be garbage-collected.
- A rescue command must let an operator fix the current state without restarting PHP-FPM, the daemon, or the channel.
- The fix is minimal and surgical: no re-architecture of the emission engine, no new filesystem layout, no new dependencies.

**Non-Goals:**
- Migrating logs to a separate directory (would touch `EmissionController::log()` and the rotation contract — out of scope).
- Replacing the daemon's own internal rotation (the Python-side rotation is broken but that is a separate engine bug, not a log-handling bug).
- Closing the `AGENTS.md` "dismounted" discrepancy (tracked separately as a follow-up note in the proposal).
- Switching to a syslog/`logrotate`/Monolog pipeline (too invasive for an emergency fix).
- Changing the daemon's process model (running it as `www` instead of `root` is a larger security/perms effort).

## Decisions

### Decision 1: Sanitize-on-open rather than fix-on-write

**Choice:** Before `proc_open`, `spawnDaemon` calls `EmissionLogRotator::prepare($logPath)` which:
1. If `$logPath` does not exist → no-op.
2. If `$logPath` exists and `is_writable($logPath)` → no-op (happy path).
3. If `$logPath` exists and **not** writable → `unlink($logPath)` (start fresh; the new file will be created by the next `proc_open` with the spawner's UID).
4. Independently, if `$logPath` exists and `filesize($logPath) > $maxBytes` → `rename($logPath, $logPath . '.' . date('Ymd-His'))` (rotation by size).

**Why over "chmod/chown on the fly":** `chown` requires the process to be root and silently fails otherwise; `chmod` only works if the process owns the file. Both are partial fixes. `unlink` works for any process that can write to the *directory* (which `storage/logs/` is — `www:www 775`). It also provides natural rotation-on-restart and removes the need to track "who created this file last."

**Trade-off:** we lose the previous log content when we unlink. Acceptable: the log is daemon stderr, not audit data. If the operator cares about old content they can change the threshold or rescue with `emision:logs:fix-permissions` instead of letting `spawnDaemon` sanitize.

### Decision 2: Size-based rotation, not time-based

**Choice:** Threshold is `50 MiB` (`config('emission.log.max_bytes', 50 * 1024 * 1024)`). Rotated filename is `emission-{id}.log.YYYYMMDD-HHMMSS`.

**Why:** Time-based rotation requires a clock event (cron, scheduler) that does not exist today. Size-based rotation piggybacks on the existing start path, which is the only place we have a hook to do fs work synchronously. The Python daemon emits a `[STATS]` line roughly every iteration, so 50 MiB is days-to-weeks of headroom under normal load.

**Why 50 MiB:** Comfortable envelope for the rate Monolog typically caps at (daily) without surprising operators. Configurable via the new `emission.log` config block (which lives in `config/emission.php` — note: this file does not exist yet, see Decision 5).

### Decision 3: Retention is 7 days, GC happens on start

**Choice:** `EmissionLogRotator::gc($logPath, $retentionDays = 7)` globs `storage/logs/emission-{id}.log.*` and `unlink`s anything older than the retention window. Called at the end of `prepare()`.

**Why on start, not on a cron:** same reasoning as Decision 2 — no cron infrastructure touches this path. The cost is one extra `glob` per start; negligible.

**Why 7 days:** enough headroom for offline diagnosis, low enough to keep disk usage bounded across many channels.

### Decision 4: A rescue command for the live state

**Choice:** `php artisan emision:logs:fix-permissions` walks `storage/logs/emission-*.log`, attempts `chmod 0664` on each, and emits a report. If a file is owned by a different user, it prints the offending ownership and instructs the operator to run the command as root (or with `sudo`).

**Why a command and not just a fix in `spawnDaemon`:** the current state requires `chown` (which requires root), and PHP-FPM running as `www` cannot perform it. The command is the only path that works without escalating the web process. It is also a one-shot command — does not need to be safe-against-concurrent-runs.

**Idempotency:** safe to run repeatedly; `chmod 0664` is a no-op if already `0664`.

### Decision 5: Centralize knobs in `config/emission.php`

**Choice:** New `config/emission.php` with:

```php
return [
    'log' => [
        'max_bytes' => env('EMISSION_LOG_MAX_BYTES', 50 * 1024 * 1024),
        'retention_days' => env('EMISSION_LOG_RETENTION_DAYS', 7),
    ],
];
```

**Why:** the codebase follows the env-overridable config pattern (every other tunable goes through `config/*.php`). Putting the thresholds in code — not in `spawnDaemon` constants — keeps them greppable and tunable.

`AGENTS.md` lists `config/emision.php` as one of the "gone" things, but the file is absent anyway and the emission engine is alive per the `emission-engine-rebuild` change. Introducing `config/emission.php` is consistent with the engine being mounted.

### Decision 6: `EmissionLogRotator` is a service, not a trait

**Choice:** `app/Services/EmissionLogRotator.php` with two public methods: `prepare(string $logPath): void` and `gc(string $logPath, int $retentionDays): int` (returns deletion count).

**Why:** easier to unit-test in isolation, easier to reuse from the `emision:logs:rotate` command, and keeps `spawnDaemon` focused on its existing responsibility.

## Risks / Trade-offs

- **[Loss of previous log content on unlink]** → Acceptable for daemon stderr; `emision:logs:fix-permissions` exists for the case where the operator wants to preserve it.
- **[The Python daemon's own internal rotation may still fail]** → Out of scope for this change. The `.tmp → .log` rename bug is a separate engine issue. The proposed change reduces its blast radius (the daemon can no longer strand the channel solely through a rotation failure, because the next start sanitizes automatically).
- **[Filename collision when two rotations happen in the same second]** → Real but astro-nominal (rotations only happen on a >50 MiB start, days apart). The rotated filename uses `Ymd-His`; if it ever collides, `rename` overwrites the older rotated file. Acceptable.
- **[Operator runs `emision:logs:fix-permissions` against a long-running daemon and the daemon keeps writing root-owned]** → Documented in the command's help text: "run while the channel is offline; if the daemon is live, stop it first."
- **[Config file name conflict with `emision.php` Spanish spelling]** → All engine code uses `Emission` (English) in PHP identifiers (`EmissionOrchestrator`, `EmissionController`). The new config file matches that naming. The AGENTS.md reference to `config/emision.php` was stale.
- **[No concurrency lock between two simultaneous starts]** → `EmissionController::start` already short-circuits if `emission_state.status ∈ {starting, live}` (see `emission-engine-rebuild`). Two concurrent starts cannot both reach `spawnDaemon` for the same channel.

## Migration Plan

This is a code change with no schema migration. Deploy steps:

1. **Pre-deploy (operator, as root, while channel is offline):**
   ```bash
   chown www:www /www/wwwroot/cloudstream.mediaserver.com.co/storage/logs/emission-*.log
   chmod 664 /www/wwwroot/cloudstream.mediaserver.com.co/storage/logs/emission-*.log
   ```
   This is the immediate recovery that puts channel "4444..." back on air. The code fix below makes this step unnecessary on subsequent restarts.

2. **Deploy:**
   - `git pull` (or equivalent). No need to take the channel offline — the new `prepare()` is a no-op on a healthy path.
   - `php artisan config:clear` to pick up the new `config/emission.php`.

3. **Post-deploy verification:**
   - Stop the channel (so any root-owned log gets sanitized on next start).
   - Start the channel; expect `proc_open` to succeed.
   - `ls -la storage/logs/emission-*.log` should show `www:www 664`.
   - `cat storage/logs/emission-*.log` should show `[DAEMON] Spawning...` followed by the live stats loop.

4. **Rollback:** revert the deploy. Existing root-owned files re-emerge as a problem; run step 1.

5. **Follow-up (separate change, not part of this fix):** update `AGENTS.md` to remove the "DISMOUNTED" claim and add a one-line entry for the new `emision:logs:*` commands.

## Open Questions

- Should `emision:logs:rotate` operate on a single channel or all channels by default? Plan: all channels by default, `--channel=<id>` to scope. Confirm during implementation.
- Should the `max_bytes` threshold also be enforced by a periodic check inside the daemon (so it rotates even when the channel isn't restarted)? Tabled — out of scope; the daemon's own rotation is broken anyway and that fix belongs in `emission-engine-rebuild` v2.
