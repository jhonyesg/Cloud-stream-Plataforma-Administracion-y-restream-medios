## ADDED Requirements

### Requirement: Inline countdown in the restream panel

The client and admin restream index MUST render, in the "Programación" column for every target that has `next_ends_at`, a per-second countdown (`Termina en HH:MM:SS` or shorter) that decrements every second without sending extra HTTP requests. Targets without a running schedule render `—` like before.

#### Scenario: Live target shows "Termina en HH:MM:SS"

- **GIVEN** a target with `next_ends_at = now + 1 h` and `effective_status = live`
- **WHEN** the operator opens `/client/restream` or `/admin/restream-targets`
- **THEN** the row's Programación column shows `Termina en 01:00:00` (or `Termina en 59m 58s` as the minute changes)
- **AND** the value decreases once per second
- **AND** no additional HTTP requests are sent by the row (only the 10-s index poll continues)

#### Scenario: Idle target shows "—"

- **GIVEN** a target with no `next_ends_at` and `effective_status = idle`
- **WHEN** the operator opens the restream page
- **THEN** the row's Programación column shows `—` (no countdown cell)

#### Scenario: Past-end schedule shows "Finalizado hace Xm"

- **GIVEN** a target whose last running window has `ends_at = now - 5 min`
- **WHEN** the operator opens the restream page
- **THEN** the row's Programación column shows `Finalizado hace 5m`

### Requirement: Heartbeat propagates daemon-stall signal to the resolver

The daemon's heartbeat payload MUST include `daemon_stalled_at` (timestamp or null). When the daemon detects the RTMP output is dead, it sets this field to `now()` and keeps sending heartbeats. The Laravel heartbeat endpoint MUST persist the field to `restream_targets.daemon_stalled_at`.

#### Scenario: Heartbeat with stall is persisted

- **GIVEN** the daemon detects the RTMP output is dead (`bitrate=0` for 10+ s plus a stderr pattern)
- **WHEN** the next heartbeat POSTs to `/api/internal/restream/{target}/heartbeat`
- **THEN** `restream_targets.daemon_stalled_at` is updated to `now()`
- **AND** the resolver returns `effective_status='yt-no-data'` while `daemon_stalled_at` is fresh (< 60 s)

#### Scenario: Resolver flips back to live after recovery

- **GIVEN** a target with `daemon_stalled_at = now - 5 s` and `effective_status='yt-no-data'`
- **WHEN** the daemon spawns a new ffmpeg child and successfully re-establishes the RTMP connection
- **THEN** the next heartbeat has `daemon_stalled_at = null` (or > 60 s old)
- **AND** the resolver returns `effective_status='live'`
- **AND** the panel badge flips back to "En vivo en YouTube" within the next 10-s poll

#### Scenario: Migration is idempotent

- **GIVEN** `restream_targets` already has `daemon_stalled_at`
- **WHEN** `php artisan migrate` runs the migration
- **THEN** the migration is a no-op (no duplicate column, no errors)