<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FIX 2026-09-17: Se elimina Schedule::job(PurgeSoftDeletedMedia::class)
// porque la clase App\Jobs\PurgeSoftDeletedMedia no existe en el repo.
// El job estaba programado a las 03:00 y fallaba cada noche con:
//   "Target class [App\Jobs\PurgeSoftDeletedMedia] does not exist."
// Ver git log: el commit 75cd505 anadio el Schedule pero jamas creo la clase.
// Si en el futuro se necesita purgar media soft-deleted, recrear la clase
// (purga de MediaItem con deleted_at NOT NULL tras N dias) y volver a agregar:
//   Schedule::job(\App\Jobs\PurgeSoftDeletedMedia::class)->daily()->at('03:00');

Schedule::command('restream:launch-scheduled')->everyMinute()->name('restream-launch-scheduled');

// YouTube auto-completes broadcasts on the first RTMP micro-disconnect when
// `enableAutoStop=true` was set. This watchdog polls YouTube every minute,
// detects broadcasts YouTube has marked complete while our daemon still
// reports live, and asks the orchestrator to recreate them.
Schedule::command('restream:reap-dead-broadcasts')->everyMinute()->name('restream-reap-dead-broadcasts');

// Per-target schedule engine (the new way to schedule restreams). Walks
// each target's `restream_target_schedules` windows and reconciles them
// with the daemon's current state. Idempotent + lock-protected.
Schedule::command('restream:run-target-schedules')->everyMinute()->withoutOverlapping()->name('restream-run-target-schedules');

// Prune append-only event history. Default 90d, override with TARGET_EVENTS_RETENTION_DAYS.
Schedule::command('restream:prune-target-events')->daily()->name('restream-prune-target-events');

// Poll YouTube's lifeCycleStatus for every YouTube restream target with an
// active daemon. Persists to restream_targets.platform_broadcast_lifecycle so
// the UI can surface a real "En vivo en YouTube" / "yt-complete" badge instead
// of guessing from the heartbeat alone. Caps to live targets (idle targets
// don't burn quota). 30s cadence keeps us well under the 10k/day YouTube API
// quota even with many concurrent targets.
Schedule::command('restream:sync-youtube-status')->everyThirtySeconds()->withoutOverlapping(60)->name('restream-sync-youtube-status');
