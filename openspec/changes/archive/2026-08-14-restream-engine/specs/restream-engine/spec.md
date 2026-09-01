## Purpose

Define the long-running supervisor daemon that keeps the configured `restream_targets` actually streaming. The supervisor periodically reconciles the desired state (which targets should be live) with the actual state (which FFmpeg PIDs are alive), spawns/monitors/restarts processes, and updates each target's `status` / `last_started_at` / `last_stopped_at` / `last_error` so the UI can show live status to clients.

## ADDED Requirements

### Requirement: Supervisor command runs as a foreground loop

The system SHALL provide the Artisan command `restream:supervise` (implemented by `App\Console\Commands\RestreamSuperviseCommand`) which, when executed, runs in the foreground in an infinite loop. Each tick (default interval 5 seconds) it SHALL call `RestreamSupervisor::tick()` and log progress.

#### Scenario: Default interval is 5 seconds
- **WHEN** the command is invoked without `--interval`
- **THEN** the loop sleeps 5 seconds between ticks

#### Scenario: Custom interval via --interval
- **WHEN** the command is invoked with `--interval=2`
- **THEN** the loop sleeps 2 seconds between ticks

#### Scenario: --once exits after a single tick
- **WHEN** the command is invoked with `--once`
- **THEN** it runs `tick()` once and exits with code 0

### Requirement: Supervisor reconciles desired vs actual state

Each tick, the supervisor SHALL:

1. For every `restream_target` with `enabled = true && status != 'active' && pipeline_pid IS NULL`: call `launcher->start($target)`.
2. For every `restream_target` with `pipeline_pid IS NOT NULL && ! launcher->isAlive($target)`: mark `status = 'error'`, capture the last error line, clear `pipeline_pid`.
3. For every `restream_target` with `enabled = false && pipeline_pid IS NOT NULL`: call `launcher->stop($target)`.

The supervisor SHALL NOT touch targets with `enabled = true && status = 'active' && isAlive() == true` (steady state, no action).

#### Scenario: Start a target that is enabled but has no PID
- **WHEN** the supervisor ticks and finds target `t1` with `enabled=true, pipeline_pid=NULL`
- **THEN** it calls `launcher->start(t1)`; `t1->fresh()->pipeline_pid` becomes non-null and `status` becomes `active` after the launcher confirms the spawn

#### Scenario: Detect dead PID and mark error
- **WHEN** the supervisor ticks and finds target `t2` with `pipeline_pid=99999` that no longer exists
- **THEN** it sets `t2->status = 'error'`, clears `pipeline_pid`, and copies the last stderr line into `last_error`

#### Scenario: Stop a target that was disabled
- **WHEN** the supervisor ticks and finds target `t3` with `enabled=false, pipeline_pid=12345`
- **THEN** it calls `launcher->stop(t3)`; `t3->fresh()->pipeline_pid` becomes NULL and `status` becomes `idle`

#### Scenario: Steady-state target is not touched
- **WHEN** the supervisor ticks and finds target `t4` with `enabled=true, status=active, pipeline_pid=12345` (alive)
- **THEN** no action is taken and `t4` is not logged at INFO level

### Requirement: Supervisor restarts targets that fail fast

If a target was started, became `active`, then died within 30 seconds (FFmpeg couldn't even establish the RTMP handshake), the supervisor SHALL mark it `error` and SHALL NOT auto-restart it for 5 minutes. After the cool-down, the next tick restarts it.

#### Scenario: Fast-fail restart with cool-down
- **WHEN** a target dies 10 seconds after the supervisor marked it `active`
- **THEN** the supervisor sets `status='error'`, stores `last_failed_at = now()`, and does NOT start it again on the next tick
- **AND** after 5 minutes (`now() - last_failed_at >= 5min`), the supervisor attempts `start()` again

#### Scenario: Long-running failure is treated normally
- **WHEN** a target dies 10 minutes after being marked `active`
- **THEN** the supervisor immediately retries `start()` on the next tick (no cool-down)

### Requirement: Supervisor exposes lifecycle commands

The system SHALL provide one-shot Artisan commands:

- `restream:start {target?}` — start all enabled targets, or just the one with the given id; `--dry-run` prints the command without spawning.
- `restream:stop {target?}` — stop all targets with non-null `pipeline_pid`, or just the one with the given id.
- `restream:status` — print a table: id, channel, platform, name, status, pid, alive, last_started_at, last_stopped_at, last_error.

#### Scenario: Bulk start runs all enabled targets
- **WHEN** `restream:start` is invoked with no id
- **THEN** every target with `enabled=true && pipeline_pid IS NULL` is started in sequence, and the command prints one line per action

#### Scenario: Targeted start runs only one
- **WHEN** `restream:start {target_id}` is invoked
- **THEN** only that target is started (or reported as already running if `pipeline_pid IS NOT NULL`)

#### Scenario: Bulk stop terminates every running PID
- **WHEN** `restream:stop` is invoked
- **THEN** every target with `pipeline_pid IS NOT NULL` is sent SIGTERM and `pipeline_pid` is cleared

#### Scenario: Status prints a tabular report
- **WHEN** `restream:status` is invoked
- **THEN** the output is an ASCII table with one row per target, including id, platform, name, status, pid, alive (✓/✗), and last_started_at / last_stopped_at / last_error (truncated to 60 chars)

### Requirement: Supervisor writes a heartbeat to the application log

Each tick, the supervisor SHALL write one `INFO` line with the number of targets it acted on (started, stopped, marked-error). When a target is started or stops, it SHALL also write one `INFO` line with `target_id`, `platform`, and the resulting `pipeline_pid` or reason for stop.

#### Scenario: Tick log includes counts
- **WHEN** the supervisor ticks and starts 2, stops 0, marks-error 1
- **THEN** the log contains `[restream] tick: started=2 stopped=0 error=1`

#### Scenario: Start event is logged
- **WHEN** the supervisor starts target `t1` with pid 4242
- **THEN** the log contains `[restream] started target=t1 platform=facebook pid=4242`

### Requirement: HTTP endpoints let clients trigger start/stop

The system SHALL expose (under the existing `restream.enabled` middleware + `canAccessChannel` scoping):

- `POST /client/channels/{channel}/restream-targets/{target}/start` — call `launcher->start($target)` and return the new pid (or the existing one if already running).
- `POST /client/channels/{channel}/restream-targets/{target}/stop` — call `launcher->stop($target)` and return the new state.

Both endpoints SHALL respect the user's quota (refuse start if `enabled=true` would exceed cap) and SHALL audit the action to `audit_logs` with `action='start.restream_target'` / `action='stop.restream_target'`.

#### Scenario: Client start endpoint spawns a process
- **WHEN** a client calls `POST .../start` on a target they own with `enabled=false`
- **THEN** the controller flips `enabled=true`, calls `launcher->start()`, returns `{status: 'active', pipeline_pid: 4242}` and writes one `audit_logs` row

#### Scenario: Client start endpoint refuses when over cap
- **WHEN** a client calls `POST .../start` and the resulting state would exceed their `max_outputs`
- **THEN** the endpoint responds 422 with the cap message and no process is spawned

#### Scenario: Stop endpoint clears PID and audits
- **WHEN** a client calls `POST .../stop` on a running target
- **THEN** the controller calls `launcher->stop()`, returns `{status: 'idle', pipeline_pid: null}` and writes one `audit_logs` row with `action='stop.restream_target'`

### Requirement: Client panel reflects live status every 5 seconds

The client `_restream_panel.blade.php` SHALL poll `GET /client/channels/{channel}/restream-targets` every 5 seconds (via `setInterval`) and re-render each target row with the new `status`, `pipeline_pid`, `last_started_at`, and `last_error`. A target with `status='active'` SHALL display a green pulsing dot; one with `status='error'` SHALL show a red dot and a tooltip with `last_error`.

#### Scenario: Active target shows pulsing dot
- **WHEN** the panel re-renders with `status='active'`
- **THEN** the row contains a `<span class="animate-pulse bg-green-500">…</span>` indicator

#### Scenario: Error target shows tooltip
- **WHEN** the panel re-renders with `status='error', last_error='Connection refused'`
- **THEN** the row contains a `<span title="Connection refused" class="bg-red-500">…</span>` indicator

#### Scenario: Polling stops when tab is hidden
- **WHEN** the browser tab becomes hidden (`document.visibilityState === 'hidden'`)
- **THEN** the panel pauses the 5s poll and resumes on `visibilitychange === 'visible'` (saves CPU/battery)