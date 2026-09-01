# emission-daemon Specification (delta)

## ADDED Requirements

### Requirement: Shared Python standard for all emitters

The `emisor_python/` package SHALL be the shared standard for every process that emits media (programming emission and restream). The shared modules SHALL be: `utils/log_rotator.py` (log rotation), `utils/laravel_client.py` (internal API client, extended with restream endpoints), and `pipeline/ffmpeg_pipeline.py` (ffmpeg subprocess management: spawn, kill process group, stats, watchdog hooks). The restream daemon (`restream_daemon/`) SHALL import these shared modules rather than duplicating them, so maintenance and updates apply to both emitters at once.

#### Scenario: Restream daemon reuses shared modules
- **WHEN** the restream daemon starts
- **THEN** it imports `LogRotator` and `LaravelClient` from `emisor_python/utils/` and the ffmpeg process management from `emisor_python/pipeline/` (no copied implementations)

#### Scenario: A fix in the shared pipeline benefits both emitters
- **WHEN** `emisor_python/pipeline/ffmpeg_pipeline.py` is updated (e.g. process-group kill behavior)
- **THEN** both the emission daemon and the restream daemon pick up the change without further edits

### Requirement: Daemon registration files

Each daemon (emission and restream) SHALL write a registration file on startup: emission daemons to `storage/app/emission-daemons/{channelId}.json` and restream daemons to `storage/app/restream-daemons/{targetId}.json`, both with `{"pid":N,"started_at":"ISO8601"}` (plus `port` for emission). Laravel SHALL use these files for diagnostics and stop signaling.

#### Scenario: Restream daemon registers on startup
- **WHEN** a restream daemon starts
- **THEN** it writes `storage/app/restream-daemons/{targetId}.json` with its PID and start time
