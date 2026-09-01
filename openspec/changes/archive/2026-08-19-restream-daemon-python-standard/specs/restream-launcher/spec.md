# restream-launcher Specification (delta)

## MODIFIED Requirements

### Requirement: Launcher builds the FFmpeg command from a target

The system SHALL provide `App\Services\Restream\RestreamOrchestrator::buildConfig(RestreamTarget $target): array` that returns the JSON config consumed by the Python daemon. The config SHALL contain `source_url` (or the resolved `channel->public_hls_url` fallback when `source_url` is NULL), `destination_url`, the decrypted `stream_key`, `channel_id`, `platform`, and `ffmpeg_bin`. The FFmpeg command itself SHALL be built by the Python daemon (`restream_daemon`), which SHALL consume the source, copy video, transcode audio to AAC, mux to FLV, and push to `destination_url . '/' . stream_key` with the RTMP live flag passed as `-rtmp_live live` (never `live=1`).

#### Scenario: Default source_url fallback
- **WHEN** the orchestrator builds the config for a target with `source_url = NULL`
- **THEN** the config's `source_url` equals `target.channel->public_hls_url`

#### Scenario: Custom source_url overrides default
- **WHEN** the orchestrator builds the config for a target with `source_url = "rtmp://emision.local/app/CHAN"`
- **THEN** the config's `source_url` equals `"rtmp://emision.local/app/CHAN"`

#### Scenario: Command uses copy codec for video
- **WHEN** the daemon builds the command from the config
- **THEN** the command contains `-c:v copy`

#### Scenario: Command transcodes audio to AAC 44.1kHz stereo
- **WHEN** the daemon builds the command from the config
- **THEN** the command contains `-c:a aac -ar 44100 -ac 2 -b:a 128k`

#### Scenario: Output is RTMP-flv with corrected live flag
- **WHEN** the daemon builds the command from the config
- **THEN** the command ends with `-f flv -rtmp_live live "{destination_url}/{stream_key}"` (the `-rtmp_live` value is `live`, not `live=1`)

### Requirement: Launcher spawns the process and returns the PID

`RestreamOrchestrator::start(RestreamTarget $target): array` SHALL write the config JSON to `storage/app/restream-daemons/{target_id}-config.json`, update `restream_targets.status = 'starting'`, spawn the Python daemon via `proc_open` (stderr appended to `storage/logs/restream/{target_id}.log`), wait ~500ms, and verify the daemon process is still running. If the daemon exited immediately, the orchestrator SHALL read the log tail, set `status = 'error'` with the cause in `last_error`, and throw an exception carrying that cause.

#### Scenario: Successful start returns daemon PID
- **WHEN** `start()` is called on a target whose config is valid
- **THEN** the daemon process is alive after 500ms, `target->fresh()->status` is `starting`, and the returned PID is the daemon's PID
- **AND** the daemon takes over: it spawns ffmpeg and reports `status='live'` via heartbeat

#### Scenario: Failed start surfaces the real cause
- **WHEN** the daemon exits within 500ms (e.g. invalid config, missing binary)
- **THEN** `start()` throws an exception whose message contains the last stderr line, `status` is set to `error`, and `last_error` holds the cause

### Requirement: Launcher reports liveness and stops processes

`RestreamOrchestrator::stop(RestreamTarget $target): void` SHALL signal the daemon to stop (SIGTERM to the daemon PID, then SIGKILL after 5s if needed), wait for the daemon's final `offline` heartbeat (up to 10s), and clear `pipeline_pid` / set `status = 'idle'` / `last_stopped_at = now()`. Liveness of a target SHALL be determined by heartbeat freshness (`last_heartbeat_at` within 15s) rather than `posix_kill` on a stored PID.

#### Scenario: Stop terminates the daemon gracefully
- **WHEN** `stop()` is called on a target with a running daemon
- **THEN** within 10 seconds the daemon and its ffmpeg child are dead, `pipeline_pid` is NULL, `last_stopped_at` is set, and `status` is `idle`

#### Scenario: Alive check uses heartbeat freshness
- **WHEN** a target has `last_heartbeat_at` within the last 15 seconds
- **THEN** the target is considered live
- **AND** when `last_heartbeat_at` is older than 15 seconds, the target is considered stale/error

### Requirement: Launcher parses stderr for error markers

The daemon SHALL tail the last 64 KiB of the per-target stderr log on each restart and look for any of the patterns: `Connection refused`, `HTTP error`, `Server returned 4xx/5xx`, `Immediate exit requested`, `Broken pipe`, `Operation not permitted`, `Invalid argument`, `Conversion failed`. If any matches, the daemon SHALL include the offending line in its next heartbeat's `error_message`, and Laravel SHALL persist it to `last_error`.

#### Scenario: Connection refused surfaces in last_error
- **WHEN** the target's stderr log contains `Connection refused` after the last start
- **THEN** `target->last_error` equals the trimmed line containing that text

#### Scenario: Clean run leaves last_error unchanged
- **WHEN** the target's stderr log has no error markers
- **THEN** `last_error` remains `null` (unless previously set from an older run)

### Requirement: Launcher requires FFmpeg binary

The orchestrator SHALL resolve the FFmpeg binary path from the `FFMPEG_BIN` env var (default `ffmpeg`) and write it into the daemon config. The daemon SHALL throw a clear error at startup if the binary is missing, which surfaces as `last_error` via the failed-start path.

#### Scenario: Missing binary surfaces as last_error
- **WHEN** the configured FFmpeg path does not resolve to an executable file
- **THEN** the daemon exits with a clear message and the target's `last_error` contains `FFmpeg binary not found`

#### Scenario: `--dry-run` flag skips process spawn
- **WHEN** `start()` is called with `dryRun = true`
- **THEN** the method returns the config JSON and the built command string without spawning a process and without persisting `pipeline_pid`
