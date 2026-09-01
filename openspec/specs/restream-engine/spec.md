# restream-engine Specification

## Purpose
TBD - created by archiving change restream-engine. Update Purpose after archive.
## Requirements
### Requirement: HTTP endpoints let clients trigger start/stop

The system SHALL expose (under the existing `restream.enabled` middleware + `canAccessChannel` scoping):

- `POST /client/channels/{channel}/restream-targets/{target}/start` — call `RestreamOrchestrator::start($target)` and return the daemon PID (or the existing state if already live).
- `POST /client/channels/{channel}/restream-targets/{target}/stop` — call `RestreamOrchestrator::stop($target)` and return the new state.

Both endpoints SHALL respect the user's quota (refuse start if `enabled=true` would exceed cap) and SHALL audit the action to `audit_logs` with `action='start.restream_target'` / `action='stop.restream_target'`. On start failure, the endpoint SHALL respond 422 with the real cause from the daemon log.

#### Scenario: Client start endpoint spawns the daemon
- **WHEN** a client calls `POST .../start` on a target they own with `enabled=false`
- **THEN** the controller flips `enabled=true`, calls the orchestrator, returns `{status: 'starting', pipeline_pid: <daemon pid>}` and writes one `audit_logs` row

#### Scenario: Client start endpoint refuses when over cap
- **WHEN** a client calls `POST .../start` and the resulting state would exceed their `max_outputs`
- **THEN** the endpoint responds 422 with the cap message and no daemon is spawned

#### Scenario: Start failure returns the real cause
- **WHEN** the daemon exits within 500ms of spawn (e.g. `Invalid argument` from ffmpeg)
- **THEN** the endpoint responds 422 with a message containing the cause and `last_error` is persisted

#### Scenario: Stop endpoint clears state and audits
- **WHEN** a client calls `POST .../stop` on a running target
- **THEN** the controller calls the orchestrator, returns `{status: 'idle', pipeline_pid: null}` and writes one `audit_logs` row with `action='stop.restream_target'`

### Requirement: Client panel reflects live status every 5 seconds

The client restream panel SHALL poll `GET /client/channels/{channel}/restream-targets` every 5 seconds (via `setInterval`) and re-render each target row with the new `status`, `pipeline_pid`, `last_started_at`, `last_heartbeat_at`, and `last_error`. A target with `status='live'` SHALL display a green pulsing dot; one with `status='error'` SHALL show a red dot and a tooltip with `last_error`. The panel SHALL also offer a per-target log view that polls the log endpoint every 5 seconds while open.

#### Scenario: Active target shows pulsing dot
- **WHEN** the panel re-renders with `status='live'`
- **THEN** the row contains a `<span class="animate-pulse bg-green-500">…</span>` indicator

#### Scenario: Error target shows tooltip
- **WHEN** the panel re-renders with `status='error', last_error='Connection refused'`
- **THEN** the row contains a `<span title="Connection refused" class="bg-red-500">…</span>` indicator

#### Scenario: Polling stops when tab is hidden
- **WHEN** the browser tab becomes hidden (`document.visibilityState === 'hidden'`)
- **THEN** the panel pauses the 5s poll and resumes on `visibilitychange === 'visible'` (saves CPU/battery)

#### Scenario: Log panel polls while open
- **WHEN** the user opens the log view for a target
- **THEN** the panel fetches the last 100 log lines immediately and every 5 seconds while open, rendering them in a monospace dark panel like the emission log
