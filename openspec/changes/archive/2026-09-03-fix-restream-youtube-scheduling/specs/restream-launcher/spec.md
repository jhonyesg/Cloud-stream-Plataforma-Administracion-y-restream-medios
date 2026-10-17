## MODIFIED Requirements

### Requirement: Launcher spawns the process and returns the PID

`RestreamOrchestrator::start(RestreamTarget $target): array` SHALL write the config JSON to `storage/app/restream-daemons/{target_id}-config.json`, update `restream_targets.status = 'starting'`, spawn the Python daemon via `proc_open` (stderr appended to `storage/logs/restream/{target_id}.log`), wait ~500ms, and verify the daemon process is still running. If the daemon exited immediately, the orchestrator SHALL read the log tail, set `status = 'error'` with the cause in `last_error`, and throw an exception carrying that cause. The **scheduled launcher** (`restream:launch-scheduled`) SHALL call this same `start()` method (through the quota guard) instead of only flipping `enabled=true` — the old PHP supervisor path no longer exists, so enabling without spawning leaves the target silently idle. The command SHALL also call `RestreamOrchestrator::stop($target)` for targets whose `scheduled_stop_at` has passed.

#### Scenario: Successful start returns daemon PID
- **WHEN** `start()` is called on a target whose config is valid
- **THEN** the daemon process is alive after 500ms, `target->fresh()->status` is `starting`, and the returned PID is the daemon's PID
- **AND** the daemon takes over: it spawns ffmpeg and reports `status='live'` via heartbeat

#### Scenario: Failed start surfaces the real cause
- **WHEN** the daemon exits within 500ms (e.g. invalid config, missing binary)
- **THEN** `start()` throws an exception whose message contains the last stderr line, `status` is set to `error`, and `last_error` holds the cause

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

#### Scenario: Scheduled launch goes through the orchestrator
- **WHEN** `restream:launch-scheduled` finds a target whose `scheduled_start_at` has passed (within the 45s preheat window)
- **THEN** the spawned process exists (`pipeline_pid` set, `status='starting'`), not merely `enabled=true` with no process

#### Scenario: Scheduled stop goes through the orchestrator
- **WHEN** `restream:launch-scheduled` finds a running target whose `scheduled_stop_at` has passed
- **THEN** `RestreamOrchestrator::stop()` is invoked and the target ends with `pipeline_pid=NULL, status='idle'`