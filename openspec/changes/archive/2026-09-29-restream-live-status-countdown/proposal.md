## Why

The restream panel currently shows whether the **daemon** is alive (heartbeat fresh, PID set), but says nothing about whether the **stream is actually reaching the destination** (YouTube, Facebook, TikTok, custom RTMP). Operators have hit this gap multiple ways:

- Program a 4-hour window, the panel says "Activo" the whole time, but the YouTube embed stays buffering because either the source RTMP was misconfigured or the broadcast transitioned to `complete` on YouTube's side and our daemon never noticed.
- Schedule a 30-minute emission, lose track of how much time is left, and click "Detener" at the wrong moment because there is no visible countdown.
- Watch the panel flicker "Activo → Sin señal → Activo" without knowing whether the broadcast is genuinely live on YouTube or just paused on our side.

Two capabilities already exist that we are not using: `YoutubeBroadcastService::broadcastLifecycle()` returns the YouTube `lifeCycleStatus` (`live`, `complete`, `revoked`, `testStarting`, etc.), and `RestreamTarget` already carries `last_heartbeat_at`, `pipeline_pid`, and `last_error`. The index endpoint already polls every 10 s. The missing pieces are: persisting the YouTube lifecycle to DB, deriving a composite `effective_status` from the three signals, and showing a real countdown for active schedules.

## What Changes

- **Add four columns to `restream_targets`**: `platform_broadcast_lifecycle` (varchar 32, nullable, the last `lifeCycleStatus` YouTube reported), `platform_broadcast_lifecycle_at` (timestamp, nullable, when we last polled it), `platform_broadcast_lifecycle_error` (text, nullable, last poll error), and `last_youtube_poll_at` (timestamp, nullable). All nullable, no destructive DDL.
- **New artisan command `restream:sync-youtube-status`** scheduled every 30 s via `routes/console.php`. For every `restream_target` with `platform='youtube' AND platform_broadcast_id IS NOT NULL AND pipeline_pid IS NOT NULL`, calls `YoutubeBroadcastService::broadcastLifecycle()` and persists the result + timestamp. Catches and persists API errors without aborting the batch.
- **New service `App\Services\Restream\RestreamStatusResolver`** that derives a single `effective_status` string per target from the three signals: heartbeat fresh + lifecycle ∈ {`live`, `testStarting`} + last pipeline frame delta. Returns one of `live`, `starting`, `idle`, `error`, `stale`, `yt-complete`, `yt-no-data`, `yt-no-stream`, `unknown`.
- **Client index + Admin index** JSON endpoint includes the new fields and a precomputed `effective_status` per target. The Alpine layer stops recomputing the heartbeat-only check; it now renders the four badge variants.
- **New `<x-restream-live-banner>` Blade component** shown at the top of the restream index when ≥ 1 target has `effective_status` ∈ {`live`, `yt-no-data`} — a single row per active target with: countdown to `ends_at` (when present), YouTube lifecycle label, daemon uptime, link to share URL.
- **Countdown component in the schedule view** (`resources/views/{client,admin}/restream/target/schedules/index.blade.php` and `components/target-schedule-calendar.blade.php`): for schedules in `running`, show `Termina en HH:MM:SS` updated every second; for `ended`, show `Finalizó hace Xm`. Pure JS, no backend dependency.

## Capabilities

### New Capabilities
<!-- None. The change extends existing capabilities (`restream-targets`, `restream-scheduled-lifecycle`). -->

### Modified Capabilities
- `restream-targets`: adds a new requirement for `effective_status` derivation + YouTube lifecycle polling + the live banner. See `specs/restream-targets/spec.md`.
- `restream-scheduled-lifecycle`: adds a new requirement for the countdown display in the schedule view (client + admin). See `specs/restream-scheduled-lifecycle/spec.md`.

## Impact

- **Database**: one new migration `2026_09_30_010000_add_youtube_lifecycle_to_restream_targets.php`, purely additive (4 nullable columns). No destructive DDL, no `WithDataSafetySnapshot` trait needed.
- **Code**: ~1 migration, 1 artisan command, 1 service class, 1 Blade component, edits to 4 existing views (client + admin index + client + admin schedule index). Total new files: 5. Modified files: 7.
- **Cron**: 1 new entry in `routes/console.php` for `restream:sync-youtube-status` at every 30 s (after the existing every-minute `restream:launch-scheduled`).
- **YouTube API quota**: 1 call per live target every 30 s = 120 calls/h per target. With the current 2 YouTube targets this is well under 10k/day. The sync command caps to targets where `pipeline_pid IS NOT NULL` so it never polls idle targets.
- **UI/API**: client/admin JSON index adds 4 fields + `effective_status`. Existing per-target poll (every 10 s) is unchanged.
- **No data loss**: migration is additive. Migration is registered as required by `AGENTS.md` Database Safety Protocol — `php artisan db:backup` runs first; `php artisan check:destructive-migrations` stays green.
- **Reversibility**: pure additive migration, no code paths depend on the new columns existing (they are nullable + default `null`). Reverting the code does not require touching the DB.