## 1. Pre-flight

- [ ] 1.1 `php artisan db:backup` → confirm new file in `storage/app/backups/`.
- [ ] 1.2 Capture before state of `database/migrations/` (count, destructive markers) to `/tmp/kilo/quarantine.before.txt`.

## 2. Lint command

- [ ] 2.1 Extend `app/Console/Commands/CheckDestructiveMigrationsCommand.php` to add `--strict-data` flag. When set, run a second pass that scans every migration file for these regex patterns:
  - `DB::table\\(['\"]([\\w.]+)['\"]\\)->truncate\\(\\)`
  - `DB::table\\(['\"]([\\w.]+)['\"]\\).*?->delete\\(\\s*\\d`
  - `DB::table\\(['\"]([\\w.]+)['\"]\\).*?->delete\\(\\)` (unbounded)
  - `DB::statement\\(\\s*['\"](?:DELETE FROM|TRUNCATE TABLE)`
  - `DELETE FROM\\s+[\\w.]+` / `TRUNCATE TABLE\\s+[\\w.]+` (raw)
- [ ] 2.2 For each match, determine if the file uses `WithDataSafetySnapshot` AND declares the affected table in `$criticalTables`. Skip the violation if both conditions are true.
- [ ] 2.3 Skip files matching `/_QUARANTINED_/` in their name. Report them in a summary line at the end.
- [ ] 2.4 Output: `OK — N migration(s) scanned, 0 violations found.` on success. On violation: file path, line number, matched pattern, recommended fix (use trait + criticalTables, or `php artisan db:quarantine-migration <path>`). Exit non-zero.
- [ ] 2.5 Add feature tests under `tests/Feature/Console/CheckDestructiveMigrationsStrictDataTest.php`:
  - `truncate()` without trait → flagged
  - `truncate()` with trait + criticalTables → accepted
  - `_QUARANTINED_` file skipped
  - Raw `DELETE FROM` → flagged
  - `->delete()` with row count → flagged

## 3. Quarantine command

- [ ] 3.1 Create `app/Console/Commands/DbQuarantineMigrationCommand.php`. Signature: `db:quarantine-migration {path : Path relative to database/migrations/} {--reason= : Required human-readable reason}`.
- [ ] 3.2 Validate path exists, is a `.php` file under `database/migrations/`, and is not already `_QUARANTINED_`. Refuse with non-zero exit + clear message.
- [ ] 3.3 Validate `--reason` is non-empty. Refuse with non-zero exit.
- [ ] 3.4 Rename the file to insert `_QUARANTINED_<YYYY-MM-DD>` before the `.php` extension (so `2026_07_15_drop_users.php` → `2026_07_15_drop_users_QUARANTINED_2026-09-29.php`). Use `File::move()`.
- [ ] 3.5 Insert one `audit_logs` row: `action='migration.quarantine'`, `entity_type='migration'`, `before.path=<original>`, `after.path=<new>` + `after.reason=<reason>`. Wrap in `DB::transaction` with the rename.
- [ ] 3.6 Output: `Quarantined: <original> → <new>` + audit row id. Exit 0.
- [ ] 3.7 Add feature tests:
  - Refuse without `--reason`
  - Refuse when already quarantined (no-op exit 0)
  - Refuse when path doesn't exist
  - Happy path: rename + audit row

## 4. Pre-migrate hook

- [ ] 4.1 Add `app/Console/Kernel.php::schedule()` registration? No — the lint runs on demand, not on a schedule. Instead, add a `bootstrap` callback in `app/Providers/AppServiceProvider.php` (or equivalent) that registers a `Console\Events\ArtisanStarting` listener:
  - If the command name is `migrate`, `migrate:fresh`, or `migrate:safe` AND `--allow-quarantined` is NOT set → invoke `CheckDestructiveMigrationsCommand` with `--strict-data` first; if it exits non-zero, the underlying migrate aborts with a clear error.
- [ ] 4.2 Add `--allow-quarantined` option to the `migrate` command via a small wrapper in `app/Console/Commands/MigrateWithLintCommand.php` OR via a Laravel `Command::getDefinition()->addOption` override on the existing migrate command. Pick the cleaner path during implementation.
- [ ] 4.3 Test: with a fake destructive migration on disk, `php artisan migrate` exits non-zero with the lint message; `--allow-quarantined` makes it proceed.

## 5. Verify

- [ ] 5.1 Run `php artisan check:destructive-migrations` → exit 0 (existing behaviour preserved).
- [ ] 5.2 Run `php artisan check:destructive-migrations --strict-data` → exit 0 (no destructive data ops in current tree, or all use the trait correctly).
- [ ] 5.3 Add a temporary destructive migration, run lint → exit non-zero. Quarantine it, re-run lint → exit 0. Delete the file.
- [ ] 5.4 `php artisan test --filter=Quarantine` → all green.
- [ ] 5.5 `php artisan db:backup` again as a final safety net.

## 6. Documentation

- [ ] 6.1 Append to `AGENTS.md` under "Database Safety Protocol":
  - `php artisan check:destructive-migrations --strict-data` — same scope as the base lint, plus data-level destruction (truncate / delete / DELETE FROM). Run in CI.
  - `php artisan db:quarantine-migration <path> --reason="…"` — rename a destructive migration to `*_QUARANTINED_*.php`. Laravel silently skips it; the lint skips it. The original file content is preserved in `git log`.
  - `migrate`, `migrate:fresh`, `migrate:safe` now refuse to run if the lint fails. `--allow-quarantined` opts out for forensic investigation.
- [ ] 6.2 Add a CHANGELOG entry (or PR description) listing the patterns the lint catches.

## 7. Commit + push via SSH

- [ ] 7.1 `git status` + `git diff --stat`.
- [ ] 7.2 Commit: `feat(db-safety): data-level destructive lint + quarantine convention`.
- [ ] 7.3 `git push origin main`. Confirm new SHA on `origin/main`.