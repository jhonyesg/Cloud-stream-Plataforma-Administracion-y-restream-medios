## ADDED Requirements

### Requirement: `php artisan check:destructive-migrations --strict-data` detects data-level destruction in migration files

The lint MUST scan every `.php` file under `database/migrations/`, parse its source for data-deletion patterns, and exit non-zero with a clear message naming the file + line when any violation is found. Migrations that use the `WithDataSafetySnapshot` trait AND declare the affected table(s) in `$criticalTables` MUST be accepted (the trait provides pre-DDL snapshot capture and on-rollback restore, so the operational risk is contained).

#### Scenario: Lint catches `->truncate()`

- **GIVEN** a migration file `2026_XX_XX_xxxxxx_drop_users_table.php` containing `DB::table('users')->truncate();`
- **AND** the file does not use `WithDataSafetySnapshot` and does not declare `$criticalTables`
- **WHEN** `php artisan check:destructive-migrations --strict-data` runs
- **THEN** the command exits non-zero
- **AND** the output includes the file path + line number + the matched pattern
- **AND** the message recommends the fix: add `WithDataSafetySnapshot` + declare the table in `$criticalTables`

#### Scenario: Lint catches raw `DELETE FROM` statements

- **GIVEN** a migration file containing `DB::statement('DELETE FROM media_items WHERE created_at < ?');`
- **AND** the file does not use `WithDataSafetySnapshot`
- **WHEN** the lint runs
- **THEN** the migration is flagged as a violation

#### Scenario: Lint catches `->delete()` with row count

- **GIVEN** a migration file containing `DB::table('channels')->where('id', '<', 100)->delete(50);`
- **AND** the file does not use `WithDataSafetySnapshot`
- **WHEN** the lint runs
- **THEN** the migration is flagged as a violation

#### Scenario: Lint catches unbounded `->delete()`

- **GIVEN** a migration file containing `DB::table('channels')->where('id', $id)->delete();` (no row count argument)
- **AND** the file does not use `WithDataSafetySnapshot`
- **WHEN** the lint runs
- **THEN** the migration is flagged as a violation

#### Scenario: Lint catches raw `TRUNCATE TABLE`

- **GIVEN** a migration file containing `TRUNCATE TABLE audit_log;` (raw SQL)
- **AND** the file does not use `WithDataSafetySnapshot`
- **WHEN** the lint runs
- **THEN** the migration is flagged as a violation

#### Scenario: Migration with the safety trait is accepted

- **GIVEN** a migration file that contains `DB::table('channels')->truncate();` AND uses `WithDataSafetySnapshot` AND declares `'channels'` in `$criticalTables`
- **WHEN** the lint runs
- **THEN** the file passes (the trait + `$criticalTables` combination is the legitimate way to write destructive data ops)

#### Scenario: Non-destructive files are accepted

- **GIVEN** a migration file containing only `Schema::create`, `Schema::table`, `Schema::dropColumn`, no row deletions
- **WHEN** the lint runs
- **THEN** the file passes with no output

#### Scenario: Base lint (without `--strict-data`) only catches DDL

- **GIVEN** a migration file containing only `DB::table('users')->truncate();` (no DDL, no trait)
- **WHEN** `php artisan check:destructive-migrations` runs (without `--strict-data`)
- **THEN** the command exits 0 — data-level destruction is opt-in detection, not a breaking change

### Requirement: `migrate`, `migrate:fresh`, `migrate:rollback`, `migrate:reset`, `migrate:refresh`, `migrate:safe` run the strict lint before any DDL/DML executes

The system MUST register a pre-run hook that runs `php artisan check:destructive-migrations --strict-data` and blocks the migration if the lint fails. The hook MUST be registered for ALL `migrate*` commands that can apply or roll back migrations. Escape hatch: `--allow-quarantined` is a Symfony global option that bypasses the lint.

#### Scenario: Lint fails on `migrate`

- **GIVEN** a new migration is added that contains `DB::table('channels')->truncate();` without the safety trait
- **WHEN** the operator runs `php artisan migrate`
- **THEN** the command exits non-zero BEFORE running any migration
- **AND** the error message names the offending file + line + the recommended fix

#### Scenario: Lint fails on `migrate:fresh`

- **GIVEN** the same destructive migration is on disk
- **WHEN** the operator runs `php artisan migrate:fresh`
- **THEN** the command exits non-zero BEFORE running any DDL
- **AND** the error message names the offending file

#### Scenario: Lint fails on `migrate:safe`

- **GIVEN** the same destructive migration is on disk
- **WHEN** the operator runs `php artisan migrate:safe`
- **THEN** the command exits non-zero BEFORE running any DDL or backup
- **AND** no `db:backup` is taken (lint runs first)

#### Scenario: Operator explicitly opts out

- **GIVEN** the operator knows the lint failure is expected (forensic investigation)
- **WHEN** the operator runs `php artisan migrate --allow-quarantined`
- **THEN** the lint is skipped
- **AND** the migration runs as before

#### Scenario: `--allow-quarantined` is recognised by every migrate* command

- **WHEN** the operator runs `php artisan migrate:rollback --allow-quarantined` (or any other migrate* variant)
- **THEN** Symfony does not reject the option as unknown
- **AND** the lint is bypassed

#### Scenario: Other commands are not affected

- **GIVEN** the lint would catch a violation
- **WHEN** the operator runs any non-migrate command (e.g. `php artisan db:backup`, `php artisan schedule:run`)
- **THEN** the command runs normally — the hook is scoped to `migrate*` only
