## Purpose

Define the `RestreamLauncher` service that materializes the per-target FFmpeg command, spawns it as an OS process, tracks its PID, captures stderr, and reports whether the process is alive. This is the unit that both the supervisor daemon and the on-demand start/stop endpoints call into.

## ADDED Requirements

### Requirement: Launcher builds the FFmpeg command from a target

The system SHALL provide `App\Services\Restream\RestreamLauncher::buildCommand(RestreamTarget $target): string` that returns the exact shell command line to execute. The command SHALL consume `target.source_url` (or fall back to `target.channel->public_hls_url` if `source_url` is NULL), copy video, transcode audio to AAC, mux to FLV, and push to `target.destination_url . '/' . Crypt::decryptString(target->getRawOriginal('stream_key'))`.

#### Scenario: Default source_url fallback
- **WHEN** the launcher builds the command for a target with `source_url = NULL`
- **THEN** the resulting command's `-i` argument equals `target.channel->public_hls_url`

#### Scenario: Custom source_url overrides default
- **WHEN** the launcher builds the command for a target with `source_url = "rtmp://emision.local/app/CHAN"`
- **THEN** the resulting command's `-i` argument equals `"rtmp://emision.local/app/CHAN"`

#### Scenario: Command uses copy codec for video
- **WHEN** the launcher builds any command
- **THEN** the command contains `-c:v copy`

#### Scenario: Command transcodes audio to AAC 44.1kHz stereo
- **WHEN** the launcher builds any command
- **THEN** the command contains `-c:a aac -ar 44100 -ac 2 -b:a 128k`

#### Scenario: Output is RTMP-flv with live flag
- **WHEN** the launcher builds any command
- **THEN** the command ends with `-f flv -rtmp_live live=1 "{destination_url}/{stream_key}"`

### Requirement: Launcher spawns the process and returns the PID

`RestreamLauncher::start(RestreamTarget $target): int` SHALL spawn the built command as a detached background process (via `Symfony\Component\Process\Process::start()` or `proc_open` with redirected streams), redirect stderr to `storage/logs/restream/{target_id}.log`, and return the OS PID. The launcher SHALL persist the PID to `target->pipeline_pid` and SHALL set `target->last_started_at = now()` before returning.

#### Scenario: Successful start returns a positive PID
- **WHEN** `start()` is called on a target whose stream_key is decryptable and destination_url is a valid RTMP URL
- **THEN** the returned integer is > 0 and `target->fresh()->pipeline_pid` matches

#### Scenario: Failed start throws and does not persist a PID
- **WHEN** the OS fails to spawn the process (binary missing, permission denied)
- **THEN** `start()` throws an exception, `pipeline_pid` remains NULL, and `status` is set to `error`

### Requirement: Launcher reports liveness and stops processes

`RestreamLauncher::isAlive(RestreamTarget $target): bool` SHALL return `true` iff `pipeline_pid` is set and `posix_kill($pid, 0)` succeeds. `RestreamLauncher::stop(RestreamTarget $target): void` SHALL send `SIGTERM` (then `SIGKILL` after 5s) to the PID, clear `pipeline_pid`, and set `last_stopped_at = now()` and `status = idle`.

#### Scenario: Alive check returns true for running PID
- **WHEN** a target has `pipeline_pid = 12345` and process 12345 exists
- **THEN** `isAlive()` returns `true`

#### Scenario: Alive check returns false for dead PID
- **WHEN** a target has `pipeline_pid = 12345` and process 12345 has exited
- **THEN** `isAlive()` returns `false`

#### Scenario: Stop terminates the process gracefully
- **WHEN** `stop()` is called on a target with a running PID
- **THEN** within 6 seconds the process is dead, `pipeline_pid` is NULL, `last_stopped_at` is set, and `status` is `idle`

### Requirement: Launcher parses stderr for error markers

The launcher SHALL tail the last 64 KiB of the per-target stderr log on each health check and look for any of the patterns: `Connection refused`, `HTTP error`, `Server returned 4xx/5xx`, `Immediate exit requested`, `Broken pipe`, `Operation not permitted`. If any matches, `last_error` SHALL be updated with the offending line.

#### Scenario: Connection refused surfaces in last_error
- **WHEN** the target's stderr log contains `Connection refused` after the last start
- **THEN** `target->last_error` equals the trimmed line containing that text

#### Scenario: Clean run leaves last_error unchanged
- **WHEN** the target's stderr log has no error markers
- **THEN** `last_error` remains `null` (unless previously set from an older run)

### Requirement: Launcher requires FFmpeg binary

The launcher SHALL detect the FFmpeg binary path from the `FFMPEG_BIN` env var (default `ffmpeg`) and throw a `RuntimeException` with a clear message at construction time if the binary is missing.

#### Scenario: Missing binary throws at construction
- **WHEN** the configured FFmpeg path does not resolve to an executable file
- **THEN** instantiating `RestreamLauncher` throws `RuntimeException("FFmpeg binary not found at: {path}")`

#### Scenario: `--dry-run` flag skips process spawn
- **WHEN** `start()` is called with `dryRun = true`
- **THEN** the method returns the constructed command string without spawning a process, does not persist `pipeline_pid`, and does not throw on missing binary