# restream-targets Specification (delta)

## ADDED Requirements

### Requirement: Heartbeat freshness columns

The `restream_targets` table SHALL include `last_heartbeat_at` (timestamp, nullable) and `loops_completed` (integer, default 0). `last_heartbeat_at` SHALL be updated by the internal heartbeat endpoint every 5 seconds while the target's daemon is running, and SHALL be the source of truth for liveness (a target whose `last_heartbeat_at` is older than 15 seconds SHALL be considered stale/error by the UI and diagnostics).

#### Scenario: Heartbeat updates freshness
- **WHEN** the daemon POSTs a heartbeat for target `t1`
- **THEN** `t1.last_heartbeat_at` is set to the current time and `t1.status` is updated from the heartbeat body

#### Scenario: Stale heartbeat is flagged
- **WHEN** a target has `last_heartbeat_at` older than 15 seconds
- **THEN** the status endpoint and UI report the target as stale/error rather than live

### Requirement: Internal heartbeat endpoint

The system SHALL expose `POST /api/internal/restream/{target}/heartbeat` (localhost-only, same guard as `/api/internal/channels/*/emission/heartbeat`) that validates `status` (`starting`/`live`/`error`/`offline`), `pipeline_pid` (nullable int), `error_message` (nullable string), and `timestamp`, and updates the `restream_targets` row accordingly. An `offline` heartbeat SHALL clear `pipeline_pid` and set `status = 'idle'`.

#### Scenario: Live heartbeat updates the row
- **WHEN** the daemon POSTs `{status:'live', pipeline_pid: 4242}`
- **THEN** the row's `status` becomes `live`, `pipeline_pid` becomes 4242, and `last_heartbeat_at` is refreshed

#### Scenario: Offline heartbeat clears the PID
- **WHEN** the daemon POSTs `{status:'offline'}`
- **THEN** the row's `pipeline_pid` is cleared and `status` becomes `idle`

#### Scenario: Non-localhost caller is rejected
- **WHEN** a request to the heartbeat endpoint does not originate from localhost
- **THEN** the endpoint responds 403

## MODIFIED Requirements

### Requirement: Target lifecycle status

A target's `status` SHALL be one of `idle`, `starting`, `live`, `error`. Transitions are driven by the orchestrator and the daemon's heartbeats:

- `idle → starting` when `RestreamOrchestrator::start()` writes the config and spawns the daemon.
- `starting → live` when the daemon's first heartbeat reports `status='live'` (ffmpeg child up).
- `live → error` when the daemon reports `error` (crash budget exhausted, ffmpeg failed) or when `last_heartbeat_at` is stale (> 15s).
- `live → idle` when the orchestrator stops the daemon and receives the `offline` heartbeat.
- `error → starting` when the user (or systemd) starts the target again.

Clients SHALL NOT set `status` directly from the UI; clients only toggle `enabled`, which triggers the orchestrator / daemon to drive the status transitions.

#### Scenario: Client toggle enabled does not directly change status
- **WHEN** a client PATCHes `enabled = true` on a target they own
- **THEN** the server updates `enabled` and leaves `status` to be driven by the orchestrator/daemon (the target becomes `starting` only when the start endpoint or systemd actually spawns the daemon)

#### Scenario: Daemon heartbeat drives live status
- **WHEN** the daemon for a target reports `status='live'` via heartbeat
- **THEN** the row's `status` becomes `live` and the UI shows the green pulsing dot

#### Scenario: Stale heartbeat marks error
- **WHEN** a target's `last_heartbeat_at` is older than 15 seconds while `status` is `live`
- **THEN** the status endpoint reports the target as `error` (stale) and the UI shows the red dot

#### Scenario: Admin can force status
- **WHEN** an admin PATCHes `status = 'error'` on any target
- **THEN** the server updates it and logs to `audit_logs` (no cool-down field is set; the daemon's own restart budget governs retries)
