<?php

namespace App\Console\Commands;

use App\Models\RestreamTarget;
use App\Models\RestreamTargetSchedule;
use App\Services\Restream\TargetSchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Every-minute engine that reconciles each enabled target's applicable
 * window with the state of its daemon. Idempotent (delegates to
 * RestreamOrchestrator which already no-ops on alive daemons and handles
 * dead YouTube broadcasts) and single-instance per machine via
 * `Cache::lock` so two cron workers cannot double-fire.
 */
class RestreamRunTargetSchedulesCommand extends Command
{
    protected $signature = 'restream:run-target-schedules {--once : Run a single tick and exit (for tests / cron bypass)}';

    protected $description = 'Per-target schedule engine: starts/stops each enabled target per its window(s). Replaces the legacy restream:launch-scheduled for window-driven scheduling.';

    public function __construct(
        private readonly TargetSchedulerService $scheduler,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $lock = Cache::lock('restream:run-target-schedules', 60);
        if (! $lock->get()) {
            $this->line('Another tick is already in progress, skipping.');

            return self::SUCCESS;
        }

        try {
            $checked = 0;
            $started = 0;
            $stopped = 0;

            RestreamTarget::query()
                ->where('enabled', true)
                ->orderBy('id')
                ->chunkById(100, function ($targets) use (&$checked, &$started, &$stopped) {
                    foreach ($targets as $target) {
                        $checked++;
                        $this->reconcileTarget($target, $started, $stopped);
                    }
                });

            $this->line("Checked {$checked} enabled target(s); started {$started}, stopped {$stopped}.");

            return self::SUCCESS;
        } finally {
            optional($lock)->release();
        }
    }

    private function reconcileTarget(RestreamTarget $target, int &$started, int &$stopped): void
    {
        $window = $this->scheduler->findApplicableWindow($target);

        if ($window instanceof RestreamTargetSchedule) {
            // Apply the latest applicable window.
            if (! $target->isCurrentlyRunning()) {
                $this->scheduler->startTargetForWindow($window, 'auto');
                $started++;
            }

            return;
        }

        // No applicable window right now. If a previous tick was holding the
        // daemon open on this target's behalf, we must let it go.
        $hadPreviouslyAppliedWindow = RestreamTargetSchedule::query()
            ->forTarget($target->id)
            ->where(function ($q) {
                $q->where('ends_at', '<=', now())
                    ->orWhere('stopped_by_user', true);
            })
            ->where('starts_at', '<=', now())
            ->orderByDesc('starts_at')
            ->first();

        if ($hadPreviouslyAppliedWindow && $target->isCurrentlyRunning()) {
            $this->scheduler->stopTargetAfterWindow($hadPreviouslyAppliedWindow, 'auto');
            $stopped++;
        }
    }
}
