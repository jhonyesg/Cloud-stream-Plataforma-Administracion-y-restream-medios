## Why

`quarantine-destructive-migrations` (archived 2026-10-17) shipped the strict-data lint + pre-migrate hook that closes the data-level destruction gap. The 6 feature tests cover the lint command, but **zero tests cover the pre-migrate hook as a system**: nobody asserts that `php artisan migrate` actually aborts when a destructive migration is on disk, that `--allow-quarantined` actually bypasses, or that the `RuntimeException` carries the right message.

Operationally, when the hook blocks a migrate, the user sees the abort message but **nothing is logged** — no `Log::warning`, no `audit_logs` row. After the fact, an operator has no way to ask "did anyone try to run migrate past the safety net last Tuesday?".

## What Changes

- **New integration test suite** `tests/Feature/Commands/PreMigrateHookTest.php` with tests that:
  - Run `php artisan migrate --pretend` against a tmp dir with a destructive fixture migration and assert abort + correct error message + correct file/line in the output.
  - Run the same with `--allow-quarantined` and assert proceed.
  - Verify the lint also runs on `migrate:fresh`, `migrate:rollback`, `migrate:reset`, `migrate:refresh`, `migrate:safe` (5 separate tests).
  - Capture `Log::warning` and assert it carries `[db-safety] blocked migrate, file=<path>, line=<n>, command=<migrate>`.
- **`AppServiceProvider::registerMigrateSafetyHook()` enhanced** to emit `Log::warning` with the structured payload before throwing the `RuntimeException`. The log level is `warning` (not `error`) because it's a **defensive** block, not a system failure.
- **`AGENTS.md`** updated under "Database Safety Protocol" with one bullet noting that blocked attempts are logged at `warning` level for post-mortem trace.

## Capabilities

### Modified Capabilities
- `migration-safety-lint` (existing): gains operational logging + a test surface for the pre-migrate hook end-to-end.

## Impact

- **Code**: ~80 lines of tests + ~5 lines in the hook.
- **Tests**: 7 new feature tests in `tests/Feature/Commands/PreMigrateHookTest.php`.
- **No new migrations, no new commands, no new dependencies**.
- **No breaking changes**: the new log is additive, the tests assert current behaviour.

## Out of scope

- **Audit log row** (`action='migration.blocked'`) — the spec mentions it but writing one row per blocked attempt would pollute the `audit_logs` table for what is, operationally, a defensive block. Operators can grep `Log::warning [db-safety]` instead. Reconsider only if the volume of blocked attempts becomes a real signal.
- **JSON output for the lint** — there's no CI to consume it.
- **CI integration** — there's no CI workflow in the repo.
- **`down()` lint** — by design, only `up()` is checked; the `WithDataSafetySnapshot` trait covers rollback safety.
