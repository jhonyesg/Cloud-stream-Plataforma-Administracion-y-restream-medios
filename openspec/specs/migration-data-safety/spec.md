## Purpose
Define the migration data safety pipeline: snapshot capture before destructive migrations, automatic restore on rollback, release snapshots for `migrate:fresh` recovery, and supporting artisan commands.

## Requirements

### Requirement: Destructive migrations MUST capture pre-migration data snapshots

The system MUST provide a mechanism that automatically captures the rows of any table that is about to be dropped, truncated, or have key columns removed, **before** the destructive DDL runs. The captured data MUST be persisted to disk at `database/snapshots/<migration-name>/`, one file per affected table, in a compressed format that does not require a live database connection to read.

#### Scenario: Snapshot is created before a destructive migration

- **GIVEN** a migration declares `protected array $criticalTables = ['media_items', 'media_folders']` and uses the `WithDataSafetySnapshot` trait
- **AND** the `media_items` table contains 1,000 rows
- **WHEN** `php artisan migrate` runs that migration
- **THEN** a file `database/snapshots/<migration-name>/media_items.json.gz` exists
- **AND** decompressing it yields the 1,000 rows that were in the table immediately before the destructive DDL ran

#### Scenario: Snapshot survives `migrate:fresh`

- **GIVEN** a migration with `criticalTables` has been executed at least once
- **WHEN** `php artisan migrate:fresh` is run
- **THEN** the snapshot file from the previous run remains on disk (it is outside the database)
- **AND** can be used to restore data without contacting any external backup system

### Requirement: Destructive migrations MUST auto-restore data on rollback

The system MUST, when a destructive migration's `down()` is invoked, recreate the affected tables/columns and re-insert the rows from the snapshot captured during the matching `up()` call. The restore MUST be idempotent (running it twice on a clean DB produces the same state as running it once).

#### Scenario: Rollback restores rows

- **GIVEN** migration `M` was run and captured snapshot S for table `T` with 50 rows
- **AND** table `T` was dropped by `M.up()`
- **WHEN** `php artisan migrate:rollback` runs `M.down()`
- **THEN** table `T` is recreated with the original schema
- **AND** table `T` contains exactly the 50 rows that were in snapshot S
- **AND** no manual SQL or support intervention was required

#### Scenario: Restore is wrapped in a transaction

- **WHEN** a snapshot restore fails partway through (e.g. FK violation on the 30th row)
- **THEN** the entire restore is rolled back
- **AND** no partial rows are left in the target table

### Requirement: Production data MUST be restorable from a single release snapshot

The system MUST support exporting the current production database state to a single file (a "release snapshot") and re-importing that file as the final step of `migrate:fresh`. The release snapshot MUST include all "critical tables" defined in `config/migration-safety.php`.

#### Scenario: Fresh database is auto-seeded from release snapshot

- **GIVEN** `database/snapshots/release/production.json.gz` exists and contains a full release snapshot
- **WHEN** `php artisan migrate:fresh --seed` runs
- **THEN** after schema migrations run, the `ProductionDataSeeder` runs automatically
- **AND** every critical table listed in `config/migration-safety.php` is populated from the release snapshot
- **AND** the resulting database state matches the production state at the moment the snapshot was taken (modulo transient tables)

#### Scenario: `migrate:fresh` without release snapshot degrades gracefully

- **GIVEN** no release snapshot exists at the expected path
- **WHEN** `php artisan migrate:fresh --seed` runs
- **THEN** the schema migrations complete
- **AND** a warning is logged naming the missing snapshot path
- **AND** the seeder phase completes without error (does NOT throw)
- **AND** the critical tables remain empty (which is the existing pre-change behavior)

### Requirement: Snapshots MUST NOT be committed to version control

The directory `database/snapshots/` MUST be excluded from git. The only file inside it that is committed is `.gitignore` itself.

#### Scenario: Snapshot directory is git-ignored

- **WHEN** a developer runs `git status` after `php artisan migrate` creates a snapshot
- **THEN** the snapshot files do not appear as untracked or modified
- **AND** `git check-ignore -v database/snapshots/<migration-name>/<table>.json.gz` confirms the file is ignored

### Requirement: Operators MUST be able to inspect and restore snapshots manually

The system MUST provide artisan commands to list available snapshots and restore any one of them on demand, without requiring a migration rollback.

#### Scenario: List snapshots

- **WHEN** `php artisan db:list-snapshots` is run
- **THEN** the output lists every directory under `database/snapshots/` with: migration name, table count, total uncompressed row count, capture timestamp, and on-disk size

#### Scenario: Manual restore of a specific migration's snapshot

- **WHEN** `php artisan db:restore-snapshot 2026_07_15_drop_X` is run
- **THEN** the named snapshot's tables are restored to the live database
- **AND** a confirmation prompt is shown unless `--force` is passed

### Requirement: `migrate:safe` MUST chain backup + migrate + verify

The system MUST provide a single `php artisan migrate:safe` command that runs `db:backup` first, then runs `migrate`, then verifies that every expected post-migration snapshot exists.

#### Scenario: Safe migrate creates backup before any migration runs

- **GIVEN** no backup exists for today
- **WHEN** `php artisan migrate:safe` runs
- **THEN** `db:backup` completes successfully before any migration DDL runs
- **AND** a new file appears in `storage/app/backups/` with today's timestamp

### Requirement: Critical tables are declared in configuration

The list of tables considered "critical" (must be snapshotted and restored) MUST be declared in `config/migration-safety.php`, not hard-coded. The default configuration MUST include: `channels`, `media_items`, `playlists`, `playlist_items`, `schedule_templates`, `schedule_blocks`, `virtual_screens`, `emission_states`, `users`.

#### Scenario: Configuration change takes effect without code edits

- **WHEN** an operator adds a new table name to `config/migration-safety.php`'s `critical_tables` array
- **THEN** the next `db:export-production-data` includes that table in the release snapshot
- **AND** the next `migrate:fresh` auto-restore includes that table
- **WITHOUT** any code change to the snapshot service or seeder

### Requirement: Destructive migrations MUST be auditable

The system MUST provide a check command that scans `database/migrations/` for files containing destructive DDL (`DROP TABLE`, `DROP COLUMN`, `TRUNCATE`) without the `WithDataSafetySnapshot` trait, and reports violations.

#### Scenario: New destructive migration is detected

- **WHEN** a developer adds a new migration file that contains `Schema::dropIfExists('foo')` but does not `use WithDataSafetySnapshot;`
- **AND** `php artisan check:destructive-migrations` is run
- **THEN** the command exits with non-zero status
- **AND** lists the offending migration file in the output

### Requirement: AGENTS.md documents the migration safety protocol

The repository's `AGENTS.md` MUST be updated to state that: (a) `db:backup` must run before any migration touching production; (b) any new destructive migration must use `WithDataSafetySnapshot`; (c) `migrate:fresh` is allowed but auto-restores from release snapshot.

#### Scenario: AGENTS.md contains the new protocol

- **WHEN** a developer reads `AGENTS.md`
- **THEN** it contains a section titled "Migration Data Safety" explaining the snapshot/restore pipeline
- **AND** it lists the three rules above
