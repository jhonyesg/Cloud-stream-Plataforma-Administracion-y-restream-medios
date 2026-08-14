# emission-log-handling Specification

## Purpose

Manejo de los archivos de log del daemon de emisión: sanitización al abrir, rotación por tamaño, retención de rotados, y comandos de operación para rotar y corregir permisos sin reiniciar el canal.

## Requirements

### Requirement: Emission log path is self-healing on daemon start
The system SHALL sanitize the emission log file path before `proc_open` so that the start succeeds regardless of which user previously created or wrote to the file.

#### Scenario: Log file exists but is not writable by the spawner
- **WHEN** `EmissionOrchestrator::spawnDaemon` is invoked and `storage/logs/emission-{channelId}.log` exists with ownership or permissions that prevent the current PHP process from opening it in append mode
- **THEN** the system unlinks the file before opening it
- **AND** the new file is created by `proc_open` with the spawner's UID/GID
- **AND** the daemon spawn proceeds normally

#### Scenario: Log file does not exist
- **WHEN** `EmissionOrchestrator::spawnDaemon` is invoked and no log file exists at `storage/logs/emission-{channelId}.log`
- **THEN** the system does not attempt to create or unlink anything
- **AND** `proc_open` creates the file with the spawner's UID/GID (0664)

#### Scenario: Log file is writable by the spawner
- **WHEN** `storage/logs/emission-{channelId}.log` exists and `is_writable()` returns true
- **THEN** the system leaves the file untouched and proceeds to `proc_open`

### Requirement: Emission log rotates by size on daemon start
The system SHALL rotate the emission log file when its size exceeds a configurable threshold, before the new daemon opens it.

#### Scenario: Log file exceeds the size threshold
- **WHEN** `storage/logs/emission-{channelId}.log` exists, is writable, and its size in bytes exceeds `config('emission.log.max_bytes')` (default 50 MiB)
- **THEN** the system renames the file to `storage/logs/emission-{channelId}.log.YYYYMMDD-HHMMSS` using the current local time
- **AND** the new (empty) `storage/logs/emission-{channelId}.log` is created by the next `proc_open`
- **AND** the rotated sibling is preserved on disk for retention

#### Scenario: Log file is below the size threshold
- **WHEN** `storage/logs/emission-{channelId}.log` exists and its size is at or below the threshold
- **THEN** the system does not rotate and the file is appended to as-is

#### Scenario: Size threshold is configurable
- **WHEN** the environment variable `EMISSION_LOG_MAX_BYTES` is set
- **THEN** `config('emission.log.max_bytes')` returns that value
- **WHEN** the env var is absent
- **THEN** the value defaults to `52428800` (50 MiB)

### Requirement: Rotated emission logs are garbage-collected
The system SHALL delete rotated emission log siblings whose age exceeds the retention window.

#### Scenario: Rotated log is older than the retention window
- **WHEN** `spawnDaemon` runs and a rotated file `storage/logs/emission-{channelId}.log.YYYYMMDD-HHMMSS` has a timestamp suffix older than `config('emission.log.retention_days')` (default 7) days
- **THEN** the system deletes that rotated file
- **AND** the deletion count is returned by the log rotator

#### Scenario: Rotated log is within the retention window
- **WHEN** a rotated file's timestamp suffix is within the retention window
- **THEN** the system preserves it

#### Scenario: Retention window is configurable
- **WHEN** the environment variable `EMISSION_LOG_RETENTION_DAYS` is set
- **THEN** `config('emission.log.retention_days')` returns that value
- **WHEN** the env var is absent
- **THEN** the value defaults to `7`

### Requirement: Operator can force log rotation on demand
The system SHALL provide an artisan command `emision:logs:rotate` that runs the prepare + gc logic for one or all channels without requiring a daemon start.

#### Scenario: Run without arguments
- **WHEN** an operator runs `php artisan emision:logs:rotate`
- **THEN** the system iterates over every channel in the database
- **AND** for each channel, calls `EmissionLogRotator::prepare()` on its log path
- **AND** calls `EmissionLogRotator::gc()` on the same path
- **AND** prints a per-channel summary (rotated yes/no, deleted count, path)

#### Scenario: Run scoped to a single channel
- **WHEN** an operator runs `php artisan emision:logs:rotate --channel={slug-or-uuid}`
- **THEN** the system rotates and gc's only the matching channel's log
- **AND** exits non-zero if the channel does not exist

### Requirement: Operator can fix log permissions without restarting the channel
The system SHALL provide an artisan command `emision:logs:fix-permissions` that corrects the file mode (and where possible the ownership) of every emission log under `storage/logs/`.

#### Scenario: Running as the file owner
- **WHEN** an operator runs `php artisan emision:logs:fix-permissions` as the user that owns the log files
- **THEN** the system chmods each `storage/logs/emission-*.log` to `0664`
- **AND** prints a one-line report per file (path, prior mode, new mode)

#### Scenario: Running as root with files owned by another user
- **WHEN** an operator runs `php artisan emision:logs:fix-permissions` as root and the log files are owned by a different user
- **THEN** the system chowns each file to the web user (`www`) and chmods it to `0664`
- **AND** prints a one-line report per file (path, prior owner/mode, new owner/mode)
