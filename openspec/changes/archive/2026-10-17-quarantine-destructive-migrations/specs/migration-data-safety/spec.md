## ADDED Requirements

### Requirement: Destructive data operations in migrations require the `WithDataSafetySnapshot` trait

Any migration that contains `DB::table(...)->truncate()`, `->delete()`, raw `DELETE FROM`, or raw `TRUNCATE TABLE` MUST use the `WithDataSafetySnapshot` trait AND declare the affected tables in `$criticalTables`. Migrations that do not meet this requirement MUST be either fixed (add the trait) or quarantined (`*_QUARANTINED_*.php`).

#### Scenario: Migration without the trait is flagged

- **GIVEN** a migration that contains `DB::table('users')->truncate();` but does not use `WithDataSafetySnapshot`
- **WHEN** `php artisan check:destructive-migrations --strict-data` runs
- **THEN** the migration is flagged as a violation
- **AND** the operator is told either to add the trait or quarantine the file

#### Scenario: Migration with the trait but missing criticalTables is flagged

- **GIVEN** a migration that uses `WithDataSafetySnapshot` and contains `DB::table('channels')->truncate();` but does NOT declare `'channels'` in `$criticalTables`
- **WHEN** the lint runs
- **THEN** the migration is flagged (the trait's snapshot would not include the truncated table)

#### Scenario: Pre-migrate hook blocks unsafe migrations

- **GIVEN** an unfixed destructive migration is on disk
- **WHEN** `php artisan migrate` runs
- **THEN** the pre-migrate lint catches the violation and blocks the command before any DDL/DML runs