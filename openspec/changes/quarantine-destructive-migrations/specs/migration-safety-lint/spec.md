## ADDED Requirements

### Requirement: `php artisan check:destructive-migrations --strict-data` detects data-level destruction in migration files

The lint MUST scan every `.php` file under `database/migrations/`, parse its source for data-deletion patterns, and exit non-zero with a clear message naming the file + line when any violation is found.

#### Scenario: Lint catches `->truncate()`

- **GIVEN** a migration file `2026_XX_XX_xxxxxx_drop_users_table.php` containing `DB::table('users')->truncate();`
- **WHEN** `php artisan check:destructive-migrations --strict-data` runs
- **THEN** the command exits non-zero
- **AND** the output includes the file path + line number + the matched pattern
- **AND** the message tells the operator how to fix it (add `WithDataSafetySnapshot`, declare `$criticalTables`, or quarantine the file)

#### Scenario: Lint catches raw `DELETE FROM` statements

- **GIVEN** a migration file containing `DB::statement('DELETE FROM media_items WHERE created_at < ?');`
- **WHEN** the lint runs
- **THEN** the migration is flagged as a violation

#### Scenario: Lint catches `->delete()` with no row count

- **GIVEN** a migration file containing `DB::table('channels')->where('id', $id)->delete();`
- **WHEN** the lint runs
- **THEN** the migration is flagged as a violation (unbounded delete, no safety check)

#### Scenario: Quarantined files are skipped

- **GIVEN** a migration file with `_QUARANTINED_` in its name
- **WHEN** the lint runs
- **THEN** the file is skipped (no violation reported)
- **AND** a brief note is added to the lint output listing the quarantined files found

#### Scenario: Migration with the safety trait is accepted

- **GIVEN** a migration file that contains `DB::table('channels')->truncate();` AND uses `WithDataSafetySnapshot` AND declares `'channels'` in `$criticalTables`
- **WHEN** the lint runs
- **THEN** the file passes (the trait + criticalTables combination is the legitimate way to write destructive data ops)

#### Scenario: Non-destructive files are accepted

- **GIVEN** a migration file containing only `Schema::create`, `Schema::table`, `Schema::dropColumn`, no row deletions
- **WHEN** the lint runs
- **THEN** the file passes with no output

### Requirement: `php artisan db:quarantine-migration <path>` renames a destructive migration in place and writes an audit row

The artisan command MUST rename a migration file to insert `_QUARANTINED_` in its name, refuse to proceed if the file is already quarantined, and write one `audit_logs` row with `action='migration.quarantine'` capturing the operator, the original path, the new path, and the human-supplied `--reason`.

#### Scenario: Quarantine a known-bad migration

- **GIVEN** a migration file `database/migrations/2026_07_15_xxxxxx_drop_users.php`
- **WHEN** the operator runs `php artisan db:quarantine-migration database/migrations/2026_07_15_xxxxxx_drop_users.php --reason="Wiped users in 2026-09-29 incident"`
- **THEN** the file is renamed to `database/migrations/2026_07_15_xxxxxx_drop_users_QUARANTINED_2026-09-29.php` (or similar `_QUARANTINED_<date>.php`)
- **AND** one `audit_logs` row is written with the original + new paths and the reason
- **AND** subsequent `php artisan migrate` runs skip the file silently

#### Scenario: Re-running on an already-quarantined file

- **GIVEN** a migration file already containing `_QUARANTINED_`
- **WHEN** the operator runs the command again
- **THEN** the command exits 0 with a "no-op" message
- **AND** no audit row is written

#### Scenario: Refuse to quarantine without a reason

- **GIVEN** an operator invokes `php artisan db:quarantine-migration <path>` without `--reason`
- **WHEN** the command runs
- **THEN** the command exits non-zero with "Reason required" message

### Requirement: `migrate`, `migrate:fresh`, `migrate:safe` run the strict lint before any DDL/DML executes

The artisan kernel MUST register a pre-run hook on the migration commands that runs `php artisan check:destructive-migrations --strict-data` and blocks the migration if the lint fails.

#### Scenario: Lint fails on `migrate`

- **GIVEN** a new migration is added that contains `DB::table('channels')->truncate();` without the safety trait
- **WHEN** the operator runs `php artisan migrate`
- **THEN** the command exits non-zero BEFORE running any migration
- **AND** the error message names the offending file

#### Scenario: Operator explicitly opts out

- **GIVEN** the operator knows the lint failure is expected (forensic investigation)
- **WHEN** the operator runs `php artisan migrate --allow-quarantined`
- **THEN** the lint is skipped
- **AND** the migration runs as before

#### Scenario: Quarantined files do not block migrate

- **GIVEN** the only violations are from `_QUARANTINED_` files
- **WHEN** `php artisan migrate` runs
- **THEN** the command exits 0 and the migration proceeds

### Requirement: All quarantine actions and blocked attempts write audit logs

The system MUST record every quarantine action and every blocked-migration attempt to `audit_logs` so the operator can trace incidents.

#### Scenario: Quarantine writes an audit row

- **WHEN** `db:quarantine-migration` renames a file
- **THEN** one `audit_logs` row is written with `action='migration.quarantine'`, `before.path` = original, `after.path` = new, `after.reason` = the human reason

#### Scenario: Blocked attempt writes an audit row

- **WHEN** `migrate` is blocked by the lint
- **THEN** one `audit_logs` row is written with `action='migration.blocked'`, `before.lint_violations` = the list of offending files