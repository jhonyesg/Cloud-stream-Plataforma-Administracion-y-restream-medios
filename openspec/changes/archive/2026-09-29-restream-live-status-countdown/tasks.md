## 1. Pre-flight

- [ ] 1.1 `php artisan db:backup` → confirm `storage/app/backups/cloudstream_backup_<ts>.sql`.
- [ ] 1.2 Capture before-counts to `/tmp/kilo/resteram-live-status-countdown.before.csv` (users, channels, media_items, restream_targets, restream_target_schedules, audit_logs).

## 2. Migration

- [ ] 2.1 Create `database/migrations/2026_09_30_010000_add_youtube_lifecycle_to_restream_targets.php` with `Schema::table('restream_targets', function (Blueprint $t) { ... })` adding four nullable columns, each with `Schema::hasColumn()` guards. Down drops them in reverse order. No data loss.
- [ ] 2.2 `php artisan migrate` → exit 0. Verify `\d restream_targets` shows the four new columns.
- [ ] 2.3 `php artisan migrate:rollback --step=1` then `php artisan migrate` again to confirm idempotency.

## 3. Resolver service

- [ ] 3.1 Create `app/Services/Restream/RestreamStatusResolver.php` exposing `public function resolve(RestreamTarget $target): string` implementing the truth table in design.md Decision 3. Includes `public function label(string $effective): string` for the user-facing Spanish label.
- [ ] 3.2 Add `tests/Unit/Restream/RestreamStatusResolverTest.php` covering all 7 scenarios from `specs/restream-targets/spec.md` (heartbeat fresh + lifecycle live → live, …).

## 4. Artisan command + schedule

- [ ] 4.1 Create `app/Console/Commands/SyncRestreamYoutubeStatusCommand.php`. Signature `restream:sync-youtube-status {--once : Run once and exit (default for tests)}`. Iterates `RestreamTarget::where('platform', 'youtube')->whereNotNull('platform_broadcast_id')->whereNotNull('pipeline_pid')->cursor()`. For each: calls `app(YoutubeBroadcastService::class)->broadcastLifecycle($target)`; on success persists lifecycle + timestamp, clears error; on throwable persists error, continues. `--json` flag for piping.
- [ ] 4.2 Register in `routes/console.php`: `Schedule::command('restream:sync-youtube-status')->everyThirtySeconds()->withoutOverlapping(60)->runInBackground();`.
- [ ] 4.3 `php artisan schedule:list` → confirms the entry. `php artisan restream:sync-youtube-status --once --json` → confirms output for the pruebas target.
- [ ] 4.4 Verify quota math: 2 live targets × 120 calls/h × 24h = 5,760/day (well under 10k).

## 5. Controller + JSON shape

- [ ] 5.1 Refactor: extract `App\Http\Resources\RestreamTargetResource` (or add a static method on `RestreamTarget`) that returns the canonical JSON shape used by both `app/Http/Controllers/Client/RestreamTargetController.php::index` and `app/Http/Controllers/Admin/RestreamTargetController.php::index`. Includes the 4 new fields, `effective_status` via the resolver, and `next_ends_at` (computed from the running schedule for the target).
- [ ] 5.2 Both controllers now return the same shape. Drift lint: grep the two controllers for `toArray(` / manual key sets → both call the shared resource.
- [ ] 5.3 Update the Alpine `refresh()` in `resources/views/client/restream/index.blade.php` and the parallel block in `resources/views/admin/restream/index.blade.php` to consume `effective_status` from the JSON instead of computing `_effectiveStatus`. The badges now use 4 variants (`live`, `yt-no-data`, `yt-complete`, `stale`, `idle`, `starting`, `error`).

## 6. Live banner component

- [ ] 6.1 Create `resources/views/components/restream-live-banner.blade.php`. Props: `:targets` Alpine prop (array). Renders one row per target where `effective_status ∈ ['live', 'yt-no-data']`: platform icon, name, lifecycle badge, `Termina en HH:MM:SS` countdown, copy-link button. Hidden when the array is empty.
- [ ] 6.2 Insert `<x-restream-live-banner />` at the top of `resources/views/client/restream/index.blade.php` and `resources/views/admin/restream/index.blade.php`, before the channel-context card.
- [ ] 6.3 Build CSS: `npm run build:css` after the Blade edits (AGENTS.md mandatory). Verify the new classes exist in `public/css/tailwind.min.css` (the static JIT bundle).

## 7. Countdown in schedule views

- [ ] 7.1 Add a small Alpine helper `countdownLabel($el, starts_at, ends_at, status)` in `resources/views/client/restream/target/schedules/index.blade.php` (and the admin copy) that returns one of: `Termina en HH:MM:SS`, `Inicia en HH:MM:SS`, `Finalizó hace Xm`, `Sin fin`, empty. Driven by a single `setInterval` per row that re-evaluates and updates `textContent`.
- [ ] 7.2 Same helper added to `resources/views/components/target-schedule-calendar.blade.php` for the chip label.
- [ ] 7.3 Verify both client and admin surfaces render the same label for the same schedule row (curl + DOM diff).

## 8. Verification

- [ ] 8.1 `php artisan check:destructive-migrations` → exit 0.
- [ ] 8.2 `php artisan test --filter=RestreamStatusResolverTest` → all green.
- [ ] 8.3 Re-run row counts to `/tmp/kilo/resteram-live-status-countdown.after.csv` and diff against `.before.csv`. Expect only `restream_target_schedules` possibly changed (none expected) plus no DML on existing tables.
- [ ] 8.4 `php artisan route:list | grep restream` → same routes, same handlers.
- [ ] 8.5 Manual: load `/client/restream` as `pruebas` in a browser. Expect the live banner with one row (pruebas target), lifecycle badge flipping from `yt-no-data` to `En vivo en YouTube` within 30 s, countdown ticking.
- [ ] 8.6 Manual: load `/admin/restream-targets` and confirm parity (same row, same badge, same countdown).
- [ ] 8.7 Tail `storage/logs/laravel.log` for the past 10 minutes → no `SQLSTATE` errors, no exceptions from the new sync command.

## 9. Commit + push via SSH

- [ ] 9.1 `git status` and `git diff --stat` to confirm scope.
- [ ] 9.2 `git remote -v` to confirm `git@github.com:…` (SSH).
- [ ] 9.3 `git add` the new files + the modified ones.
- [ ] 9.4 `git commit -m "feat(restream): countdown + YouTube lifecycle sync + composite status"` (and the migration files).
- [ ] 9.5 `git push origin main`. Confirm SSH key `~/.ssh/github_deploy` is used. Confirm the new commit hash on `origin/main`.
- [ ] 9.6 Document the commit SHA in this change's proposal as the "deploy marker" for easy revert: `git revert <sha>` + `php artisan migrate:rollback --step=1`.

## 10. Documentation

- [ ] 10.1 Append to `AGENTS.md` under "Restream Module — Engine":
  - `php artisan restream:sync-youtube-status` runs every 30 s via `routes/console.php`; per-target YouTube `lifeCycleStatus` is persisted to `restream_targets.platform_broadcast_lifecycle` and surfaced as `effective_status` in the index JSON.
- [ ] 10.2 Reference the new `restream-targets` and `restream-scheduled-lifecycle` requirements in `openspec/specs/` once this change is archived.