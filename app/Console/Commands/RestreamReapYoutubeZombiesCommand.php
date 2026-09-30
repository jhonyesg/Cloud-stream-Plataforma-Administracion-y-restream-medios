<?php

namespace App\Console\Commands;

use App\Enums\RestreamTargetEventKind;
use App\Models\RestreamTarget;
use App\Services\Restream\Platform\YoutubeBroadcastService;
use App\Services\Restream\TargetSchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Safety net for the "zombie live on YouTube" symptom: a target whose
 * local state is `idle` (no daemon) but whose bound YouTube broadcast is
 * still `live` (because the previous `stop()` predated the YouTube
 * transition fix, or the YouTube API call failed, or an operator manually
 * orphaned a broadcast via YouTube Studio).
 *
 * Runs every minute. For each idle target with a non-null
 * `platform_broadcast_id`:
 *
 *   - If YouTube reports `live|liveStarting|testing` → record
 *     `kind=youtube_zombie_detected`, try to transition to `complete`,
 *     record `kind=youtube_zombie_recovered` (or `_recovery_failed` on
 *     exception/non-2xx). The next `restream:run-target-schedules` tick
 *     will pick the target up normally if a window applies.
 *   - If YouTube reports `complete|revoked` → the broadcast already
 *     closed; clear `platform_broadcast_id`, `destination_url`, `stream_key`
 *     so the next `start()` creates a fresh broadcast. Record
 *     `kind=youtube_zombie_recovered` with `metadata.cleaned=true`.
 *   - Otherwise → skip silently (transient API error, next tick will retry).
 *
 * Idempotent: a second run on a target whose binding has been cleaned
 * yields no candidates because the `platform_broadcast_id IS NOT NULL`
 * filter excludes them.
 */
class RestreamReapYoutubeZombiesCommand extends Command
{
    protected $signature = 'restream:reap-youtube-zombies {--dry-run : Report zombies without transitioning}';

    protected $description = 'Detect YouTube broadcasts still `live` while the local target is idle, transition them to complete and clean up the binding.';

    public function __construct(
        private readonly YoutubeBroadcastService $youtube,
        private readonly TargetSchedulerService $scheduler,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = RestreamTarget::query()
            ->where('enabled', true)
            ->where('platform', 'youtube')
            ->whereNotNull('platform_broadcast_id')
            ->whereNotNull('platform_account_id')
            ->whereNull('pipeline_pid')
            ->get();

        if ($candidates->isEmpty()) {
            $this->line('No youtube zombies to check.');
            return self::SUCCESS;
        }

        $detected = 0;
        $recovered = 0;
        $cleaned = 0;
        $failed = 0;

        foreach ($candidates as $target) {
            try {
                $lifecycle = $this->youtube->broadcastLifecycle($target);
            } catch (Throwable $e) {
                Log::warning('[restream:reap-zombies] youtube check failed, skipping', [
                    'target_id' => $target->id,
                    'broadcast_id' => $target->platform_broadcast_id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($lifecycle === null) {
                continue;
            }

            if (in_array($lifecycle, ['live', 'liveStarting', 'testing'], true)) {
                $detected++;
                $this->scheduler->recordEvent($target, RestreamTargetEventKind::YOUTUBE_ZOMBIE_DETECTED, [
                    'broadcast_id' => $target->platform_broadcast_id,
                    'lifecycle' => $lifecycle,
                ]);

                if ($dryRun) {
                    $this->warn("Target {$target->id} ({$target->name}) has zombie broadcast {$target->platform_broadcast_id} ({$lifecycle}) — would transition.");
                    continue;
                }

                $ok = false;
                try {
                    $ok = $this->youtube->transitionToComplete($target);
                } catch (Throwable $e) {
                    Log::warning('[restream:reap-zombies] transition threw', [
                        'target_id' => $target->id,
                        'broadcast_id' => $target->platform_broadcast_id,
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($ok) {
                    $this->scheduler->recordEvent($target, RestreamTargetEventKind::YOUTUBE_ZOMBIE_RECOVERED, [
                        'broadcast_id' => $target->platform_broadcast_id,
                        'lifecycle' => $lifecycle,
                        'via' => 'reap_youtube_zombies',
                    ]);
                    $recovered++;
                    $this->info("Recovered zombie on target {$target->id} ({$target->name}) — broadcast {$target->platform_broadcast_id} → complete.");
                } else {
                    $this->scheduler->recordEvent($target, RestreamTargetEventKind::YOUTUBE_ZOMBIE_RECOVERY_FAILED, [
                        'broadcast_id' => $target->platform_broadcast_id,
                        'lifecycle' => $lifecycle,
                    ]);
                    $failed++;
                    $this->error("Failed to recover zombie on target {$target->id} ({$target->name}).");
                }

                continue;
            }

            if (in_array($lifecycle, ['complete', 'revoked'], true)) {
                $oldBroadcast = $target->platform_broadcast_id;

                $target->forceFill([
                    'platform_broadcast_id' => null,
                    'destination_url' => null,
                    'stream_key' => null,
                    'platform_broadcast_lifecycle' => $lifecycle,
                    'platform_broadcast_lifecycle_at' => now(),
                    'platform_broadcast_lifecycle_error' => null,
                ])->save();

                $this->scheduler->recordEvent($target, RestreamTargetEventKind::YOUTUBE_ZOMBIE_RECOVERED, [
                    'broadcast_id' => $oldBroadcast,
                    'lifecycle' => $lifecycle,
                    'cleaned' => true,
                ]);
                $cleaned++;
                $this->info("Cleaned dead binding on target {$target->id} ({$target->name}) — broadcast {$oldBroadcast} was already {$lifecycle} on YouTube.");
                continue;
            }

            // Some other lifecycle (e.g. 'ready') we don't act on.
        }

        $this->line("Checked {$candidates->count()} youtube target(s): {$detected} zombies detected, {$recovered} recovered, {$cleaned} cleaned, {$failed} failed.");

        return self::SUCCESS;
    }
}
