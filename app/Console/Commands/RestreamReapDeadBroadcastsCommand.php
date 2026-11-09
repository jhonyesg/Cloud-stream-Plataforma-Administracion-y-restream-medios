<?php

namespace App\Console\Commands;

use App\Enums\RestreamTargetEventKind;
use App\Models\RestreamTarget;
use App\Services\Restream\Platform\YoutubeBroadcastService;
use App\Services\Restream\RestreamOrchestrator;
use App\Services\Restream\TargetSchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Detects YouTube broadcasts that YouTube has already marked `complete` while
 * our daemon still thinks they're live (heartbeat fresh, FFmpeg child alive),
 * and triggers a recreation through the orchestrator. Runs every minute.
 *
 * Why this exists: YouTube's `enableAutoStop=true` (or any RTMP micro-disconnect
 * on a broadcast with it enabled) auto-transitions the broadcast to
 * `complete` within seconds. Our daemon's heartbeat keeps reporting `live`
 * because FFmpeg is still sending data to a now-orphaned stream key. This
 * command is the watchdog that closes the gap — it queries YouTube, sees the
 * broadcast is dead, asks the orchestrator to recreate it, and writes a
 * classified event to the target's history (`kind=youtube_revoked` if the
 * channel revoked the broadcast, `kind=youtube_consumed_remotely` if the
 * broadcast ended normally on YouTube's side).
 *
 * For broadcasts created with `enableAutoStop=false`, the only way they end
 * up `complete` is via the orchestrator's manual transition or the API
 * transitioning externally (admin action on YouTube Studio). Either way,
 * the recovery path is identical.
 */
class RestreamReapDeadBroadcastsCommand extends Command
{
    protected $signature = 'restream:reap-dead-broadcasts {--dry-run : List dead broadcasts without recovering}';

    protected $description = 'Detect YouTube broadcasts marked complete while our daemon still reports live, and recreate them.';

    public function __construct(
        private readonly RestreamOrchestrator $orchestrator,
        private readonly YoutubeBroadcastService $youtube,
        private readonly TargetSchedulerService $scheduler,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = RestreamTarget::query()
            ->where('platform', 'youtube')
            ->where('enabled', true)
            ->whereNotNull('platform_broadcast_id')
            ->whereNotNull('pipeline_pid')
            ->whereNotNull('platform_account_id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->line('No youtube targets to check.');

            return self::SUCCESS;
        }

        $recovered = 0;
        foreach ($candidates as $target) {
            $lifecycle = $this->detectLifecycle($target);

            if ($lifecycle === null) {
                continue;
            }

            $oldBroadcast = $target->platform_broadcast_id;
            $kind = $this->kindForLifecycle($lifecycle);

            // Record the canonical "this is what YouTube did" event FIRST so
            // even a failed recovery leaves an auditable trace.
            $this->scheduler->recordEvent($target, $kind, [
                'broadcast_id' => $oldBroadcast,
                'lifecycle' => $lifecycle,
                'recovered' => false,
            ]);

            if ($dryRun) {
                $this->warn("Target {$target->id} ({$target->name}) has dead broadcast {$oldBroadcast} ({$lifecycle}) — would recreate.");
                continue;
            }

            Log::info('[restream:reap] detected dead youtube broadcast, recovering', [
                'target_id' => $target->id,
                'dead_broadcast' => $oldBroadcast,
                'lifecycle' => $lifecycle,
                'previous_pid' => $target->pipeline_pid,
            ]);

            try {
                $this->orchestrator->start($target);
                $recovered++;

                $this->scheduler->recordEvent($target, RestreamTargetEventKind::WATCHDOG_RECOVERY_SUCCEEDED, [
                    'broadcast_id' => $oldBroadcast,
                    'lifecycle' => $lifecycle,
                ]);

                $this->info("Recovered target {$target->id} ({$target->name}) — broadcast {$oldBroadcast} recreated.");
            } catch (\Throwable $e) {
                Log::error('[restream:reap] recovery failed', [
                    'target_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
                $this->scheduler->recordEvent($target, RestreamTargetEventKind::WATCHDOG_RECOVERY_EXHAUSTED, [
                    'broadcast_id' => $oldBroadcast,
                    'lifecycle' => $lifecycle,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Recovery failed for {$target->id}: {$e->getMessage()}");
            }
        }

        $this->line("Checked {$candidates->count()} youtube target(s), recovered {$recovered}.");

        return self::SUCCESS;
    }

    /**
     * Returns one of `complete`, `revoked`, or null when the broadcast is
     * still alive (or the API call failed and we decided to skip).
     */
    private function detectLifecycle(RestreamTarget $target): ?string
    {
        try {
            $lifecycle = $this->youtube->broadcastLifecycle($target);
        } catch (\Throwable $e) {
            Log::warning('[restream:reap] youtube check failed, skipping', [
                'target_id' => $target->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($lifecycle === null || in_array($lifecycle, ['live', 'liveStarting', 'testing'], true)) {
            return null;
        }

        return $lifecycle;
    }

    private function kindForLifecycle(string $lifecycle): string
    {
        return $lifecycle === 'revoked'
            ? RestreamTargetEventKind::YOUTUBE_REVOKED
            : RestreamTargetEventKind::YOUTUBE_CONSUMED_REMOTELY;
    }
}
