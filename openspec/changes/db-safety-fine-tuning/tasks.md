## 1. Refactor: extract hook logic

- [x] 1.1 In `app/Providers/AppServiceProvider.php`, extract the closure body of the `CommandStarting` listener registered in `registerMigrateSafetyHook()` into a public method `handleMigrateSafetyHook(CommandStarting $event): void`. The listener closure becomes a 2-line wrapper.
- [x] 1.2 In `handleMigrateSafetyHook`, capture the lint violations (currently the `Artisan::call` discards the output buffer). Refactor to capture the first violation's `file` + `line` from the lint output so we can include it in the log payload.

## 2. Log on block

- [x] 2.1 In `handleMigrateSafetyHook`, after capturing the first violation but before throwing, emit `Log::warning('[db-safety] blocked migrate', [...])` with the captured file/line + command name.
- [x] 2.2 The `RuntimeException` message stays unchanged so end-user UX is identical.

## 3. Integration test for the hook

- [x] 3.1 Create `tests/Feature/Commands/PreMigrateHookTest.php` with 7 tests:
  - `migrate` aborts when a destructive migration is on disk
  - `migrate --allow-quarantined` proceeds
  - `migrate:fresh` aborts
  - `migrate:rollback` aborts
  - `migrate:safe` aborts
  - non-migrate commands (e.g. `schedule:run`) are unaffected
  - blocked attempt logs at `warning` level with the expected payload
- [x] 3.2 Each test uses a tmp dir with a fixture migration written in `setUp` and cleaned in `tearDown` so the project's real `database/migrations/` is never touched.
- [x] 3.3 Tests instantiate `AppServiceProvider`, call `handleMigrateSafetyHook` directly with a synthetic `CommandStarting` event, and assert either the `RuntimeException` (for blocks) or the absence of one (for bypasses).

## 4. Verify

- [x] 4.1 `php artisan test --filter=PreMigrateHook` → 7/7 green.
- [x] 4.2 `php artisan test --filter=CheckDestructiveMigrationsStrictData` → still 6/6 (no regression).
- [x] 4.3 `php artisan check:destructive-migrations --strict-data` → exit 0, 44 migrations scanned, 0 violations (no regression on the lint).
- [x] 4.4 `php artisan migrate --pretend` (real project, no fixture) → proceeds silently (no destructive migration on disk; no false positive).
- [x] 4.5 `php artisan check:destructive-migrations` (DDL only) → still passes (no regression).

## 5. Documentation

- [x] 5.1 In `AGENTS.md` under "Database Safety Protocol", add one bullet: "When the pre-migrate lint blocks a migration, the attempt is also logged at `warning` level with structured payload (`command`, `file`, `line`) for post-mortem grep. Operators can search `Log::warning [db-safety]` to find every blocked attempt."

## 6. Commit

- [x] 6.1 `git status` + `git diff --stat` to confirm scope is limited to AppServiceProvider + new test + AGENTS.md.
- [x] 6.2 `git commit -m "feat(db-safety): log on lint block + integration tests for pre-migrate hook"`.
