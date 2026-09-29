## ADDED Requirements

### Requirement: Operator can rebaseline the migrations ledger after a backup restore without re-running destructive scaffolding migrations

The system MUST provide an artisan command `php artisan db:mark-migrations-applied <migration-name>…` that records one or more migration files as "already applied" in the `migrations` table without executing their `up()` bodies. The command is intended for the case where an operator restored a PostgreSQL dump whose `migrations` ledger is older than the migration files on disk, and where running the pending `up()` bodies would either recreate existing tables or attempt to drop live enum types in active use.

#### Scenario: Restored dump with older migrations ledger
- **GIVEN** the operator ran `php artisan db:backup` and restored a PostgreSQL dump whose `migrations` table stops at `2026_09_29_022700_add_thumbnail_media_id_to_restream_target_schedules`
- **AND** the filesystem contains `database/migrations/0001_01_01_000000_create_users_table.php`, `…_create_cache_table.php`, `…_create_cloudstream_core_tables.php`, `…_create_jobs_table.php`, and `…_2026_09_29_034500_add_emission_overrides_to_restream_target_schedules.php`
- **AND** the live DB already has every table, enum, and index those `0001_*` migrations would create
- **WHEN** the operator runs `php artisan db:mark-migrations-applied 0001_01_01_000000_create_users_table 0001_01_01_000001_create_cache_table 0001_01_01_000001_create_cloudstream_core_tables 0001_01_01_000002_create_jobs_table`
- **THEN** the command exits 0
- **AND** four rows appear in `migrations` with `batch = MAX(batch)+1`
- **AND** no `Schema::create(...)`, `DROP TYPE`, or `CREATE TYPE` statement runs against the live DB
- **AND** one `audit_logs` row is written with `action='migrations.rebaseline'`

#### Scenario: Command refuses to mark a migration as applied if the live DB is missing its objects
- **GIVEN** the operator passes the name of a migration whose `up()` body creates tables `users` and `sessions`
- **AND** the live DB does NOT have table `sessions`
- **WHEN** the operator runs `php artisan db:mark-migrations-applied 0001_01_01_000000_create_users_table`
- **THEN** the command exits non-zero
- **AND** the error message names the missing object(s): `sessions`
- **AND** no row is inserted into `migrations`
- **AND** no `audit_logs` row is written

#### Scenario: Command refuses to mark a migration whose file is missing on disk
- **GIVEN** the operator passes a migration name whose file does not exist under `database/migrations/`
- **WHEN** the operator runs `php artisan db:mark-migrations-applied <missing-name>`
- **THEN** the command exits non-zero
- **AND** the error message names the missing file path

#### Scenario: Re-running the command is idempotent
- **GIVEN** the operator previously ran `db:mark-migrations-applied` and the named migrations are already recorded in `migrations`
- **WHEN** the operator runs the same command again
- **THEN** the command exits 0
- **AND** the `migrations` table is unchanged (no duplicate rows, no batch bump)

#### Scenario: Subsequent `php artisan migrate` runs only the real pending migrations
- **GIVEN** the four `0001_*` migrations are recorded as applied via `db:mark-migrations-applied`
- **AND** `2026_09_29_034500_add_emission_overrides_to_restream_target_schedules` is the only migration actually pending DDL
- **WHEN** the operator runs `php artisan migrate`
- **THEN** only the `034500` migration's `up()` body runs
- **AND** the four nullable columns (`schedule_title`, `schedule_description`, `platform_privacy`, `keep_recording`) appear on `restream_target_schedules`
- **AND** zero rows are inserted, updated, or deleted in any other table