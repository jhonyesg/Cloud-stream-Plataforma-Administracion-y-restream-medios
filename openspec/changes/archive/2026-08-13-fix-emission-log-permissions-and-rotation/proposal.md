## Why

The emission engine's per-channel log file (`storage/logs/emission-{channelId}.log`) is currently created by whichever process first opens it — frequently a `php artisan` invocation running as `root`, or by the Python daemon itself which runs as `root`. When a user subsequently tries to **start** an emission through the web UI (which executes PHP-FPM as `www`), `proc_open($logPath, 'a')` fails with `Permission denied` because `www` cannot append to a root-owned `0644` file.

This is the failure observed on 2026-07-24: the channel "4444..." daemon died during an internal log rotation; the user was told the stream was down; they clicked "Iniciar emisión"; the start endpoint crashed before the daemon could re-spawn. The channel has been off-air since.

Beyond the immediate permission trap, the file has no growth control. Once a daemon runs uninterrupted for days it grows unbounded, and the daemon's own `.tmp` → `.log` rename rotation has already proven unreliable (the 2026-07-24 death was caused by exactly that rename failing).

We need: (1) a self-healing write path that doesn't depend on who created the file last, (2) a deterministic rotation policy with size cap, and (3) cleanup of stale rotated logs.

## What Changes

- **`EmissionOrchestrator::spawnDaemon()`** will, before opening the daemon log file, sanitize the path: if the file exists and is not writable by the current process, it is `unlink()`ed (fresh log, owned by the spawner). If the file exists and exceeds a size threshold, it is renamed to a timestamped sibling before being replaced. After `proc_open`, a GC pass deletes rotated logs older than the retention window.
- **New helper `EmissionLogRotator`** centralizes the sanitize / rotate / gc logic so it can be unit-tested and reused by future commands (e.g. `emision:logs:gc`).
- **New `emision:logs:rotate` artisan command** lets an operator force rotation + GC on demand (useful when the daemon is down and `spawnDaemon` therefore won't run).
- **New `emision:logs:fix-permissions` artisan command** is the one-shot rescue for the current state: it walks `storage/logs/emission-*.log`, fixes ownership/mode so the web user can write, and is safe to run repeatedly.
- **Specs** will codify the behavioral contract: sanitize-on-open, size-based rotation, retention window, and the rescue command.

## Capabilities

### New Capabilities
- `emission-log-handling`: Defines how the emission daemon log files are created, sanitized, rotated, and retained. Covers the `proc_open` write path, size-based rotation, retention window, and the rescue command.

### Modified Capabilities
- None. No existing spec requirements change; the `/api/channels/{id}/emission/start` behavior is unchanged from the API consumer's perspective (it still returns the same JSON on success and an HTTP 422 on failure, and the underlying daemon-spawn contract from `emission-engine-rebuild` still holds). Only the internal mechanics of `spawnDaemon` change.

## Impact

- **Code**:
  - `app/Services/EmissionOrchestrator.php::spawnDaemon()` — add log sanitization + rotation + GC around the existing `proc_open` call.
  - `app/Services/EmissionLogRotator.php` — new service.
  - `app/Console/Commands/EmisionLogsRotateCommand.php` — new `emision:logs:rotate`.
  - `app/Console/Commands/EmisionLogsFixPermissionsCommand.php` — new `emision:logs:fix-permissions`.
- **Filesystem**:
  - `storage/logs/emission-*.log` — existing files will be touched (mode / ownership on first run; rotate-in-place on subsequent starts).
  - `storage/logs/emission-*.log.YYYYMMDD-HHMMSS` — new rotated siblings.
  - No new directories.
- **Process model**:
  - Daemon startup gains a few extra fs operations (microseconds); no behavioral change to the daemon itself.
  - `proc_open` failure as currently observed goes away.
- **Operations**:
  - Operator can run `emision:logs:fix-permissions` directly to recover without restarting PHP-FPM or the daemon.
  - Operator can run `emision:logs:rotate` ad-hoc to free disk without restarting the channel.
- **Dependencies**: none (uses `is_writable`, `unlink`, `rename`, `glob`, `stat` — all built-in).
- **`AGENTS.md`**: the "Emission Module — Status: DISMOUNTED" section is stale and contradicts reality (the engine is live, see `emission-engine-rebuild` change). The follow-up task is to update `AGENTS.md` to reflect that the engine is mounted and operational. This change does not modify AGENTS.md (out of scope), but the discrepancy is flagged for the operator.
