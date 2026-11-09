<?php

namespace App\Console\Commands;

use App\Models\RestreamTarget;
use App\Services\Restream\RestreamOrchestrator;
use App\Services\Restream\RestreamQuotaException;
use App\Services\Restream\RestreamQuotaGuard;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\User;
use App\Services\Restream\Platform\RestreamPlatformAccountException;
use App\Services\Restream\Platform\YoutubeBroadcastService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RestreamLaunchScheduledCommand extends Command
{
    /**
     * Seconds before scheduled_start_at to start pushing video, so YouTube
     * already has signal at the exact scheduled moment (avoids dead air).
     */
    const PREHEAT_SECONDS = 45;

    protected $signature = 'restream:launch-scheduled';

    protected $description = '[DEPRECATED] Per-target scheduling now lives in restream_target_schedules and is executed by restream:run-target-schedules every minute. This command is kept as a no-op shim until all cron references are removed.';

    public function __construct(
        private readonly RestreamOrchestrator $orchestrator,
        private readonly RestreamQuotaGuard $guard,
        private readonly YoutubeBroadcastService $youtube,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->line('No-op: per-target scheduling is now handled by restream:run-target-schedules.');

        return self::SUCCESS;
    }

    protected function startDueTargets(): void
    {
        // Preheat: start slightly BEFORE the scheduled time so the platform
        // already receives signal when the scheduled moment arrives.
        $preheatAt = now()->addSeconds(self::PREHEAT_SECONDS);

        $targets = \DB::transaction(function () use ($preheatAt) {
            return RestreamTarget::query()
                ->whereNotNull('scheduled_start_at')
                ->where('scheduled_start_at', '<=', $preheatAt)
                ->where('enabled', false)
                ->lockForUpdate()
                ->get();
        });

        if ($targets->isEmpty()) {
            $this->line('No scheduled starts due.');

            return;
        }

        foreach ($targets as $target) {
            try {
                $channel = Channel::find($target->channel_id);
                $owner = User::find($target->user_id);

                if (! $channel || ! $owner) {
                    $target->update([
                        'enabled' => true,
                        'last_error' => 'Canal o propietario no encontrado al arrancar programadamente.',
                    ]);
                    $this->warn("Target {$target->id} ({$target->name}): missing channel/owner, enabled only.");

                    continue;
                }

                $this->guard->assertCanEnable($owner, $channel);

                // YouTube broadcasts are single-use: if the bound broadcast was
                // already completed (previous emission), transparently create a
                // fresh one so the scheduled emission actually reaches the channel.
                // Also create when there is NO broadcast bound (fresh or cleaned target).
                if ($target->platform === 'youtube' && $target->platformAccount) {
                    $needsBroadcast = ! $target->platform_broadcast_id;
                    $recreate = $target->platform_broadcast_id && $this->youtube->isBroadcastConsumed($target);

                    if ($needsBroadcast || $recreate) {
                        $oldId = $target->platform_broadcast_id;
                        $result = $this->youtube->create($target);
                        $target->forceFill([
                            'destination_url' => $result['destination_url'],
                            'stream_key' => $result['stream_key'],
                            'platform_broadcast_id' => $result['platform_broadcast_id'],
                        ])->save();
                        $this->info('Created YouTube broadcast for target ' . $target->id . ' (' . ($oldId ?: 'none') . ' → ' . $result['platform_broadcast_id'] . ').');
                    }
                }

                $target->enabled = true;
                $result = $this->orchestrator->start($target);
                $target->save();

                AuditLog::record(
                    action: 'auto_start.restream_target',
                    entityType: 'restream_target',
                    entityId: $target->id,
                    before: null,
                    after: ['status' => 'starting', 'pipeline_pid' => $result['pid'], 'scheduled_start_at' => $target->scheduled_start_at?->toDateTimeString()],
                    channelId: $target->channel_id,
                );

                $this->info("Started target {$target->id} ({$target->name}) scheduled for {$target->scheduled_start_at} — pid {$result['pid']}.");
            } catch (RestreamQuotaException $e) {
                $target->update([
                    'last_error' => 'Arranque programado falló por cuota: ' . $e->getMessage(),
                    'last_failed_at' => now(),
                ]);
                $this->warn("Target {$target->id} ({$target->name}): quota exceeded, left disabled.");
            } catch (\Throwable $e) {
                $target->update([
                    'last_error' => 'Arranque programado falló: ' . $e->getMessage(),
                    'last_failed_at' => now(),
                ]);
                $this->error("Target {$target->id} ({$target->name}): start failed — {$e->getMessage()}");
            }
        }
    }

    protected function stopDueTargets(): void
    {
        $targets = RestreamTarget::query()
            ->whereNotNull('scheduled_stop_at')
            ->where('scheduled_stop_at', '<=', now())
            ->where(function ($q) {
                $q->where('enabled', true)->orWhereNotNull('pipeline_pid');
            })
            ->get();

        if ($targets->isEmpty()) {
            return;
        }

        foreach ($targets as $target) {
            try {
                $this->orchestrator->stop($target);

                // Clear the schedule so the stop is idempotent. The target stays
                // enabled; releasing the quota slot is an explicit `deactivate`.
                $target->forceFill(['scheduled_stop_at' => null])->save();

                AuditLog::record(
                    action: 'auto_stop.restream_target',
                    entityType: 'restream_target',
                    entityId: $target->id,
                    before: null,
                    after: ['status' => 'idle', 'pipeline_pid' => null],
                    channelId: $target->channel_id,
                );

                // If the user opted out of keeping the recording, delete the
                // finished broadcast (and thus its VOD) from the platform.
                if ($target->platform === 'youtube' && $target->platform_broadcast_id && $target->platformAccount && $target->keep_recording === false) {
                    $oldBroadcast = $target->platform_broadcast_id;
                    try {
                        $this->youtube->delete($target->platformAccount, $oldBroadcast);
                        $target->forceFill(['platform_broadcast_id' => null])->save();
                        $this->info("Deleted non-kept recording {$oldBroadcast} for target {$target->id}.");
                    } catch (\Throwable $e) {
                        Log::warning('[restream] failed to delete finished broadcast', [
                            'target_id' => $target->id,
                            'broadcast' => $oldBroadcast,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $this->info("Stopped target {$target->id} ({$target->name}) at its scheduled stop time.");
            } catch (\Throwable $e) {
                // Best-effort: clear the schedule regardless so we do not retry forever.
                $target->forceFill(['scheduled_stop_at' => null])->save();
                $this->error("Target {$target->id} ({$target->name}): stop failed — {$e->getMessage()}");
            }
        }
    }
}