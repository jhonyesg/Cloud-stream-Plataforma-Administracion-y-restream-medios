## ADDED Requirements

### Requirement: Restream targets surface YouTube's real broadcast lifecycle to the operator

The system MUST persist YouTube's `lifeCycleStatus` per restream target and expose it through the client and admin index endpoints so the operator can distinguish a daemon that is running from a broadcast that YouTube actually has open.

#### Scenario: Lifecycle poll persists the YouTube state

- **GIVEN** a restream target with `platform='youtube'`, a non-null `platform_broadcast_id`, and a non-null `pipeline_pid`
- **WHEN** the artisan command `restream:sync-youtube-status` runs (scheduled every 30 seconds)
- **THEN** the command calls `YoutubeBroadcastService::broadcastLifecycle(target)` for that target
- **AND** on success persists the returned `lifeCycleStatus` into `restream_targets.platform_broadcast_lifecycle` and `now()` into `platform_broadcast_lifecycle_at` and `last_youtube_poll_at`
- **AND** clears `platform_broadcast_lifecycle_error`

#### Scenario: Lifecycle poll does not run for idle targets

- **GIVEN** a YouTube target with `pipeline_pid IS NULL`
- **WHEN** the command runs
- **THEN** that target is skipped (no API call, no quota consumption)

#### Scenario: YouTube API error does not abort the batch

- **GIVEN** one target throws a `Throwable` from the HTTP call
- **WHEN** the command processes that target
- **THEN** the error message is persisted into `platform_broadcast_lifecycle_error`
- **AND** the command continues to the next target without aborting

#### Scenario: Column guard makes the migration idempotent

- **GIVEN** `restream_targets` already has `platform_broadcast_lifecycle`
- **WHEN** `php artisan migrate` runs the migration `2026_09_30_010000_add_youtube_lifecycle_to_restream_targets`
- **THEN** the migration is a no-op (no errors, no duplicate column)

### Requirement: Restream target JSON exposes a single `effective_status` derived from heartbeat, lifecycle, and pipeline state

The client and admin index endpoints MUST include, for every target in the JSON payload, a precomputed string `effective_status` computed by `App\Services\Restream\RestreamStatusResolver::resolve(target)` so the UI does not need to derive it in Alpine.

#### Scenario: Heartbeat fresh + lifecycle live → `live`

- **GIVEN** a target with `last_heartbeat_at` ≤ 15 s old, `pipeline_pid` non-null, `platform_broadcast_lifecycle` ∈ {`live`, `testStarting`}
- **WHEN** the JSON is rendered
- **THEN** `effective_status` is the string `"live"`

#### Scenario: Heartbeat fresh + lifecycle complete → `yt-complete`

- **GIVEN** a target with fresh heartbeat and `platform_broadcast_lifecycle='complete'`
- **WHEN** the JSON is rendered
- **THEN** `effective_status` is the string `"yt-complete"`

#### Scenario: Heartbeat fresh + lifecycle null (no YouTube poll yet) → fallback to legacy heartbeat status

- **GIVEN** a target with fresh heartbeat, `pipeline_pid` non-null, `platform_broadcast_lifecycle IS NULL`
- **WHEN** the JSON is rendered
- **THEN** `effective_status` is the string `"live"` (legacy behaviour preserved until the first poll lands)

#### Scenario: Stale heartbeat → `stale` regardless of lifecycle

- **GIVEN** a target with `last_heartbeat_at` older than 15 s
- **WHEN** the JSON is rendered
- **THEN** `effective_status` is the string `"stale"`

#### Scenario: No pipeline + no lifecycle data → `idle`

- **GIVEN** a target with `pipeline_pid IS NULL` and `platform_broadcast_lifecycle IS NULL`
- **WHEN** the JSON is rendered
- **THEN** `effective_status` is the string `"idle"`

### Requirement: A persistent live banner shows every active restream target with a countdown to `ends_at`

The restream index (client + admin) MUST render a `<x-restream-live-banner>` block at the top of the page when at least one target has `effective_status` ∈ {`live`, `yt-no-data`}. The banner MUST show, per row: target name + platform, lifecycle label, daemon uptime, a `Termina en HH:MM:SS` countdown when the target has a `next_ends_at`, and a copy-link button.

#### Scenario: Banner appears when a target is live

- **GIVEN** the pruebas YouTube target is `effective_status='live'`
- **WHEN** the operator loads `/client/restream` or `/admin/restream-targets`
- **THEN** a banner at the top shows one row with the target name, lifecycle label `En vivo en YouTube`, a copy-link button, and a countdown

#### Scenario: Banner is hidden when no target is active

- **GIVEN** no target has `effective_status` ∈ {`live`, `yt-no-data`}
- **WHEN** the operator loads either restream page
- **THEN** the banner is not rendered (no empty card, no flash)

#### Scenario: Countdown ticks every second without server polling

- **GIVEN** a target with `next_ends_at = now + 1 h`
- **WHEN** the operator keeps the page open for 5 minutes
- **THEN** the countdown text decreases from ~`01:00:00` to ~`00:55:00` exactly
- **AND** no additional HTTP requests are sent by the banner (only the 10-s index poll continues)

### Requirement: Parity between client and admin restream surfaces

Every new field, badge variant, banner element, and countdown added by this change MUST appear identically in `resources/views/client/restream/index.blade.php` and `resources/views/admin/restream/index.blade.php`. Both surfaces MUST read from the same JSON shape and render the same Blade component.

#### Scenario: Same badge set on both surfaces

- **WHEN** an operator looks at the badges on `/client/restream` and `/admin/restream-targets` for the same target
- **THEN** both surfaces show one of {`En vivo en YouTube`, `Daemon activo pero YouTube sin datos`, `YouTube dice "completado"`, `Sin señal`, `Inactivo`, `Iniciando`, `Error`} for the same underlying state

#### Scenario: Banner parity

- **WHEN** a target is `effective_status='live'`
- **THEN** the banner appears on `/client/restream` AND on `/admin/restream-targets` with the same row contents