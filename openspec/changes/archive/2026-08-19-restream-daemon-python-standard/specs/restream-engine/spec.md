# restream-engine Specification (delta)

## REMOVED Requirements

### Requirement: Supervisor command runs as a foreground loop

**Reason**: The PHP supervisor (`restream:supervise` + `RestreamSupervisor`) is replaced by the self-supervising Python daemon per target (`restream_daemon`), which owns restart/backoff/cooldown and reports via heartbeat. Keeping both would duplicate reconciliation logic and confuse operations.

**Migration**: The Python daemon per target (`restream_daemon/main.py`) provides the supervision loop. No Artisan command is needed; targets are started via the orchestrator or the systemd unit `cloudstream-restream@{targetId}.service`.

### Requirement: Supervisor reconciles desired vs actual state

**Reason**: Replaced by the daemon's own supervision: each target's daemon keeps its ffmpeg child alive, restarts it on failure, and reports state via heartbeat. There is no central PHP tick reconciling `enabled` vs `pipeline_pid`.

**Migration**: `enabled=true` targets are started by the orchestrator (HTTP start) or by systemd; the daemon maintains the process. `enabled=false` targets are stopped by the orchestrator's stop path, which signals the daemon.

### Requirement: Supervisor restarts targets that fail fast

**Reason**: The 5-minute cool-down moves into the daemon's supervisor loop (max 5 restarts in 60s, then 30s cooldown, then heartbeat `error`), matching the emisor's `main.py` behavior.

**Migration**: The daemon enforces the restart budget and cooldown internally; `last_failed_at` is no longer the source of truth for restart decisions.

### Requirement: Supervisor exposes lifecycle commands

**Reason**: `restream:start`, `restream:stop`, and `restream:status` (spawn-oriented) are removed to avoid a second way of managing processes. The orchestrator (HTTP) and systemd are the only entry points.

**Migration**: Use `POST /client/channels/{c}/restream-targets/{t}/start|stop` (or the admin equivalents) and `systemctl start|stop cloudstream-restream@{targetId}`. A read-only `restream:status` command MAY be kept for diagnostics.

### Requirement: Supervisor writes a heartbeat to the application log

**Reason**: The tick log (`[restream] tick: started=…`) is replaced by the daemon's own log lines (`[DAEMON]`, `[STATS]`, `[WATCHDOG]`) in `storage/logs/restream/{target_id}.log`.

**Migration**: Diagnostics read the per-target log via the log endpoint or directly from disk.

## MODIFIED Requirements

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
