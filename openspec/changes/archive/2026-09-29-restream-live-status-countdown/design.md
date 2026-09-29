## Context

After fixing the `virtual_screens.output_url` typo (prior session), the pruebas user's YouTube target is now actually transmitting data — but the panel still shows "Activo" with `bitrate=0` because:

1. The panel's status badge is derived from the daemon heartbeat only, never from YouTube's actual `lifeCycleStatus`.
2. The YouTube embed player buffers indefinitely during the `testStarting` / `noData` window because the broadcast exists but hasn't seen enough RTMP data to flip to `live`.
3. There is no countdown to `ends_at` for running schedules, so 4-hour windows are opaque.

Two unused capabilities already exist in the codebase:

- `App\Services\Restream\Platform\YoutubeBroadcastService::broadcastLifecycle(RestreamTarget): ?string` — calls `GET https://www.googleapis.com/youtube/v3/liveBroadcasts?id=…&part=status` and returns the `lifeCycleStatus` field (`ready`, `testStarting`, `live`, `complete`, `revoked`, …).
- `App\Services\Restream\Platform\YoutubeBroadcastService::isBroadcastConsumed(RestreamTarget): bool` — returns true when lifecycle ∈ {`complete`, `revoked`}.

These are wired into the orchestrator's `refreshDeadBroadcast()` (called at start) but never polled after start, so a `complete` transition that happens mid-stream is invisible to the operator.

The dual-implementation rule applies: every change here MUST appear symmetrically in `client/` and `admin/` paths. Both surfaces poll the same JSON shape and render the same Alpine component.

## Goals / Non-Goals

**Goals**
- Persist YouTube's `lifeCycleStatus` per restream target and surface it in the UI.
- Derive a single `effective_status` per target from heartbeat + lifecycle + pipeline state so the operator never sees "Activo" when YouTube has marked the broadcast `complete`.
- Show a live countdown to `ends_at` for active schedules (client + admin).
- A persistent banner above the restream index when any target is genuinely live on YouTube, with a countdown and a copy-to-clipboard link.
- Reuse existing code (`YoutubeBroadcastService`, `RestreamTarget`, schedule model) without breaking any public API.

**Non-Goals**
- Changing the YouTube OAuth flow or `broadcastLifecycle` internals.
- Adding live stats parsing from ffmpeg's stderr (separate change, deferred per Q3 in `restream-schedule-schema-repair`).
- Building a streaming analytics dashboard (viewers over time, chat moderation, etc.).
- Optimising for non-YouTube platforms. Facebook Live + TikTok Live + custom RTMP will still display only the heartbeat-based status; the lifecycle column for them stays `NULL` and the resolver falls back to the legacy behaviour.

## Decisions

### Decision 1 — Four nullable columns, no destructive DDL

**Rationale.** AGENTS.md is strict about destructive migrations and `WithDataSafetySnapshot`. The change is additive: `platform_broadcast_lifecycle` (varchar 32, nullable), `platform_broadcast_lifecycle_at` (timestamp, nullable), `platform_broadcast_lifecycle_error` (text, nullable), `last_youtube_poll_at` (timestamp, nullable). All nullable, no defaults that mutate data. `php artisan check:destructive-migrations` stays green.

**Alternatives considered.**
- (a) Store lifecycle as JSONB on a single column. Rejected — breaks the principle of additive column-per-attribute and complicates index/filter usage in `effective_status`.
- (b) Reuse `last_heartbeat_at` for the YouTube poll timestamp. Rejected — semantically different and would corrupt the heartbeat-freshness check.

### Decision 2 — Artisan command + `routes/console.php` schedule, not a daemon hook

**Rationale.** The orchestrator already polls on its own (every 5 s heartbeat). Adding the YouTube lifecycle poll to the orchestrator would either slow down the start path or require a background thread inside the daemon. A scheduled artisan command at `everyThirtySeconds()` is cleaner:

- One PHP process, easy to debug (`php artisan restream:sync-youtube-status --once`).
- Per-target call to `YoutubeBroadcastService::broadcastLifecycle()`, already implemented.
- Caps to targets where `pipeline_pid IS NOT NULL` so it never burns quota on idle targets.
- A failure in one target is caught and persisted to `platform_broadcast_lifecycle_error`; the loop continues to the next.

**Alternatives considered.**
- (a) Extend the heartbeat endpoint to also call YouTube. Rejected — mixes platform-specific concerns into the platform-agnostic heartbeat handler.
- (b) Move the poll into the restream daemon's main loop. Rejected — would require modifying 3 Python files for a PHP-only feature.
- (c) Run as a sidecar Laravel queue worker. Rejected — over-engineered for a 30-s polling job.

### Decision 3 — `effective_status` derived server-side, not in Alpine

**Rationale.** Today the client/index view computes `_effectiveStatus` in Alpine from `last_heartbeat_at` and `pipeline_pid` (lines 162–175 of `resources/views/client/restream/index.blade.php`). That computation is duplicated logic that cannot be unit-tested, and the operator's monitor sees different badges from the Alpine layer than the JSON consumer would. Moving the derivation to a new `App\Services\Restream\RestreamStatusResolver::resolve(RestreamTarget): string` makes it testable and consistent across surfaces.

**Resolver truth table** (simplified, see `RestreamStatusResolver` for full):

| heartbeat fresh | pipeline_pid | lifecycle ∈ {live, testStarting} | lifecycle = complete | lifecycle = NULL | effective_status |
|---|---|---|---|---|---|
| yes | yes | yes | — | — | `live` |
| yes | yes | no | yes | — | `yt-complete` |
| yes | yes | no | no | no | `yt-no-data` |
| no (>15 s) | — | — | — | — | `stale` |
| no | no | — | — | — | `idle` |
| — | yes | — | — | — | `starting` |
| — | — | — | — | yes | `live` (legacy fallback) |
| — | — | — | — | — | `error` (if last_error set) |

### Decision 4 — Live banner uses the same Alpine data, no extra polling

**Rationale.** The restream index already polls every 10 s. Adding `<x-restream-live-banner>` that filters `targets` to those with `effective_status ∈ {live, yt-no-data}` and re-uses the existing JSON shape. Zero new HTTP endpoints. The countdown inside the banner uses `setInterval(…, 1000)` — purely client-side, second-precision.

### Decision 5 — Schedule countdown as a small Alpine directive on the existing list

**Rationale.** The schedules index already renders a `running`/`pending`/`ended`/`disabled` badge per row (`resources/views/client/restream/target/schedules/index.blade.php` lines 44–62). Adding a `<span x-text="countdownLabel($el, starts_at, ends_at, status)">` per row gives us countdown without restructuring the view. Same in `components/target-schedule-calendar.blade.php` for the chip on the calendar grid.

**Alternative considered.** A WebSocket-backed countdown. Rejected — the existing 10-s JSON poll already refreshes `starts_at` / `ends_at` and a JS interval interpolates between polls.

### Decision 6 — Cron registration via `routes/console.php`, no crontab edits

**Rationale.** AGENTS.md + the existing `restream:launch-scheduled` schedule already runs via `* * * * * php artisan schedule:run`. Adding `Schedule::command('restream:sync-youtube-status')->everyThirtySeconds()->withoutOverlapping()->runInBackground()` to `routes/console.php` is a single-file edit. No operator-side crontab changes.

## Risks / Trade-offs

| Risk | Mitigation |
|---|---|
| YouTube API quota overrun if many targets go live | Sync caps to `pipeline_pid IS NOT NULL`; spec asserts a single-target max of 120 calls/h. With 5 live targets = 600 calls/h, 14 400/day — under the 10k/day default; if it grows, raise the quota in GCP. |
| `effective_status` says `yt-no-data` for the first 5 s of every stream, flickering the badge | Resolver maps `lifecycle ∈ {testStarting, noData}` to `yt-no-data` (yellow, not red) and Alpine's poll interval (10 s) means the badge is at most 15 s in `yt-no-data` before flipping to `live` if YouTube confirms. Tested in step 6.5 of tasks. |
| Two surfaces diverge (admin missing a field that client has) | Both `Client\RestreamTargetController::index` and `Admin\RestreamTargetController::index` call the same `RestreamTargetResource::toArray()` (or its equivalent). One JSON shape, two views. Lint catches drift. |
| Schedule countdown runs on `setInterval(1 s)` and leaks memory | The component lifecycle in Alpine 3 tears down intervals when the view re-renders; the countdown directive is scoped to `x-data` element only. Manual test for > 1 h leaves the page open and confirms `performance.memory` does not grow. |
| Sync command blocks on a slow YouTube API call for one target | `withoutOverlapping()` in the schedule prevents stacking. If a single call exceeds 30 s, Laravel signals a previous-run timeout and skips the next tick; no compounding backpressure. |
| Migration deployed to a DB that already has the columns from a different branch | `Schema::hasColumn()` guard in `up()` is the standard Laravel defensive pattern. If columns exist, the migration is a no-op. |

## Migration Plan

1. **Backup**: `php artisan db:backup` (mandatory per AGENTS.md). Confirms file in `storage/app/backups/cloudstream_backup_<ts>.sql`.
2. **Deploy code**: push to GitHub via SSH (operator's explicit request in the conversation), pull on the production box, `composer install` if needed (no new packages expected — only `Carbon`, `Illuminate\Support\Facades\Http`, `Illuminate\Support\Facades\DB` are used).
3. **Migrate**: `php artisan migrate`. Expects 1 new migration, 4 nullable columns added. Exit 0.
4. **Verify**: `php artisan check:destructive-migrations` → exit 0. `\d restream_targets` → 4 new columns present.
5. **Smoke**:
   - Reload `/admin/restream-targets` and `/client/restream` (as `pruebas`). The pruebatarget shows `effective_status=yt-no-data` for 5–15 s, then `live` once YouTube confirms.
   - The live banner appears at the top of both views with the pruebas row and a `Termina en HH:MM:SS` countdown (the `Prueba dist` schedule ends at 21:30 UTC).
   - On the schedules page, the `running` row has the same countdown; on the calendar grid, the chip for today shows the same.
6. **Confirm cron**: `php artisan schedule:list` → `restream:sync-youtube-status` listed as `Every 30 seconds`, recent last-run.

**Rollback**:
- `git revert` the GitHub commit → revert the deploy.
- `php artisan migrate:rollback --step=1` → drops the 4 columns (nullable, no data loss).
- The dual-implementation surfaces stay correct because the changes are additive.

## Open Questions

- **Q1.** Do we want the live banner to also appear on non-restreampages (admin dashboard, client dashboard)? Currently scoped to restream index only. Out of scope for this change but worth tracking.
- **Q2.** The schedule countdown shows seconds-resolution only when the schedule is within 1 hour of `ends_at`. Beyond that, it shows minutes/hours. Same precision strategy as `restream-engine`? To be confirmed during implementation.
- **Q3.** Should we also show a "Live stream preview" iframe (YouTube embed) inside the banner? Risk: extra page weight; benefit: visual confirmation without leaving the panel. Deferred.