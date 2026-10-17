## MODIFIED Requirements

### Requirement: `migrate`, `migrate:fresh`, `migrate:rollback`, `migrate:reset`, `migrate:refresh`, `migrate:safe` run the strict lint before any DDL/DML executes

The system MUST register a pre-run hook on every `migrate*` command that runs `php artisan check:destructive-migrations --strict-data`. If the lint fails, the command MUST abort BEFORE any DDL or DML executes, and the abort MUST be logged at `warning` level with structured payload (`command`, `file`, `line`) for post-mortem trace.

The escape hatch `--allow-quarantined` is a Symfony global option that bypasses the lint.

#### Scenario: Lint fails on `migrate`

- **GIVEN** a new migration is added that contains `DB::table('channels')->truncate();` without the safety trait
- **WHEN** the operator runs `php artisan migrate`
- **THEN** the command exits non-zero BEFORE running any migration
- **AND** the error message names the offending file + line + the recommended fix
- **AND** a `Log::warning('[db-safety] blocked migrate', [...])` entry is written with the offending file + line + command name

#### Scenario: Lint fails on `migrate:fresh`

- **GIVEN** the same destructive migration is on disk
- **WHEN** the operator runs `php artisan migrate:fresh`
- **THEN** the command exits non-zero BEFORE running any DDL
- **AND** the error message names the offending file
- **AND** a `Log::warning` is written

#### Scenario: Lint fails on `migrate:safe`

- **GIVEN** the same destructive migration is on disk
- **WHEN** the operator runs `php artisan migrate:safe`
- **THEN** the command exits non-zero BEFORE running any DDL or backup
- **AND** no `db:backup` is taken (lint runs first)
- **AND** a `Log::warning` is written

#### Scenario: Operator explicitly opts out

- **GIVEN** the operator knows the lint failure is expected (forensic investigation)
- **WHEN** the operator runs `php artisan migrate --allow-quarantined`
- **THEN** the lint is skipped
- **AND** the migration runs as before
- **AND** no `Log::warning` is written (the bypass is intentional, not a defensive block)

#### Scenario: `--allow-quarantined` is recognised by every migrate* command

- **WHEN** the operator runs `php artisan migrate:rollback --allow-quarantined` (or any other migrate* variant)
- **THEN** Symfony does not reject the option as unknown
- **AND** the lint is bypassed
- **AND** no `Log::warning` is written

#### Scenario: Other commands are not affected

- **GIVEN** the lint would catch a violation
- **WHEN** the operator runs any non-migrate command (e.g. `php artisan db:backup`, `php artisan schedule:run`)
- **THEN** the command runs normally — the hook is scoped to `migrate*` only
- **AND** no `Log::warning` is written
