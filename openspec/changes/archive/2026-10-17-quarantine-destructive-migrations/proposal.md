## Why

On 2026-09-29, a destructive migration wiped the `users` table. The operator restored a PostgreSQL dump from `storage/app/backups/` and had to manually rebase the `migrations` ledger. The `php artisan check:destructive-migrations` lint catches DDL-level destruction (`Schema::drop` / `dropIfExists`) but does **not** catch data-level destruction (`DB::table('users')->delete()`, `->truncate()`, raw `DELETE FROM users`). The same migration could be re-deployed on a fresh environment and wipe data again because (a) the offending file is still in `database/migrations/` (or was re-introduced) and (b) nothing blocks it at the `php artisan migrate` level.

## What Changes

- **New `php artisan check:destructive-migrations --strict-data` lint** — parses every migration file in `database/migrations/` for data-level destructive patterns (`DB::table(...)->truncate()`, `->delete()`, raw `DELETE FROM`, raw `TRUNCATE`). For each match, requires the migration to either (a) use the `WithDataSafetySnapshot` trait and declare the table in `$criticalTables`, or (b) be explicitly quarantined by filename (matches `*_QUARANTINED_*.php`). Exits non-zero with a clear message naming the offending file + line when any pattern is detected.
- **Quarantine-by-filename convention** — destructive migrations that should never run again get a `*_QUARANTINED_*.php` filename. The new lint treats those as inert and skips the check. A small helper command `php artisan db:quarantine-migration <path> --reason="…"` renames an existing migration in place, writes an audit row, and refuses to apply the file's `up()` body if Laravel ever loads it (the filename is non-numeric so Laravel's discovery silently skips it; the lint double-checks).
- **Pre-migrate guard hook** — extend `app/Console/Kernel.php` (or equivalent) to call the new lint at the start of `migrate`, `migrate:fresh`, and `migrate:safe` with `--allow-quarantined` opt-out. The hook short-circuits before any DDL/DML runs.
- **Audit trail** — every quarantine action and every blocked attempt is written to `audit_logs` with `action='migration.quarantine'` and `action='migration.blocked'` respectively.

## Capabilities

### New Capabilities
- `migration-safety-lint`: CI-grade static analysis that catches destructive data operations in migrations before they reach production.

### Modified Capabilities
- `migration-data-safety`: extends the existing `WithDataSafetySnapshot` discipline to cover data-level destruction (currently only DDL).

## Impact

- **Code**: 1 new artisan command (`db:quarantine-migration`), 1 lint command (`check:destructive-migrations --strict-data`), 1 small `Kernel.php` hook for `migrate*` interception, 1 audit_logs hook.
- **CI**: the lint must be added to the CI pipeline (`composer check-migrations` or similar) so any PR introducing a destructive data op fails the build.
- **Quarantined migrations**: any existing migration that's known-bad gets a one-time rename + audit row. Idempotent — re-running on an already-quarantined file is a no-op.
- **Reversibility**: rename is reversible via `git mv`. The lint output tells the operator exactly which file and which line is the problem.