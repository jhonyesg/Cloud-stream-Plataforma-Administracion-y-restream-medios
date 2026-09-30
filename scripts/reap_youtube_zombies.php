<?php
/**
 * Standalone script to run the YouTube zombie reap logic without the full
 * artisan kernel (which OOMs in this environment for an unrelated reason).
 *
 * This is a one-shot operator tool — it does the same thing as
 * `php artisan restream:reap-youtube-zombies` but with a minimal bootstrap.
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\RestreamTarget;
use App\Enums\RestreamTargetEventKind;
use App\Services\Restream\Platform\YoutubeBroadcastService;
use App\Services\Restream\TargetSchedulerService;

$dryRun = in_array('--dry-run', $argv, true);
echo $dryRun ? "[dry-run]\n" : "[real-run]\n";

$candidates = RestreamTarget::query()
    ->where('enabled', true)
    ->where('platform', 'youtube')
    ->whereNotNull('platform_broadcast_id')
    ->whereNotNull('platform_account_id')
    ->whereNull('pipeline_pid')
    ->get();

echo "Candidates: {$candidates->count()}\n";

if ($candidates->isEmpty()) {
    echo "Nothing to do.\n";
    exit(0);
}

$youtube = $app->make(YoutubeBroadcastService::class);
$scheduler = $app->make(TargetSchedulerService::class);

foreach ($candidates as $target) {
    echo "--- target {$target->id} ({$target->name}) ---\n";
    echo "  broadcast_id: {$target->platform_broadcast_id}\n";

    try {
        $lifecycle = $youtube->broadcastLifecycle($target);
    } catch (\Throwable $e) {
        echo "  lifecycle check failed: " . $e->getMessage() . "\n";
        continue;
    }

    echo "  youtube lifecycle: " . ($lifecycle ?? 'null') . "\n";

    if (!in_array($lifecycle, ['live', 'liveStarting', 'testing'], true)) {
        if (in_array($lifecycle, ['complete', 'revoked'], true)) {
            echo "  already closed on YouTube; cleaning binding.\n";
            if (!$dryRun) {
                $oldBroadcast = $target->platform_broadcast_id;
                $target->forceFill([
                    'platform_broadcast_id' => null,
                    'destination_url' => null,
                    'stream_key' => null,
                    'platform_broadcast_lifecycle' => $lifecycle,
                    'platform_broadcast_lifecycle_at' => now(),
                ])->save();
                $scheduler->recordEvent($target, RestreamTargetEventKind::YOUTUBE_ZOMBIE_RECOVERED, [
                    'broadcast_id' => $oldBroadcast,
                    'lifecycle' => $lifecycle,
                    'cleaned' => true,
                    'via' => 'standalone_reap',
                ]);
            }
        } else {
            echo "  lifecycle is '{$lifecycle}'; skipping.\n";
        }
        continue;
    }

    echo "  ZOMBIE: broadcast still active on YouTube.\n";
    if ($dryRun) {
        echo "  would transition to complete + record events.\n";
        continue;
    }

    try {
        $ok = $youtube->transitionToComplete($target);
        echo "  transitionToComplete: " . ($ok ? 'OK' : 'FAILED') . "\n";

        $scheduler->recordEvent($target, $ok
            ? RestreamTargetEventKind::YOUTUBE_TRANSITION_SUCCEEDED
            : RestreamTargetEventKind::YOUTUBE_TRANSITION_FAILED, [
            'broadcast_id' => $target->platform_broadcast_id,
            'via' => 'standalone_reap',
        ]);
        $scheduler->recordEvent($target, RestreamTargetEventKind::YOUTUBE_ZOMBIE_RECOVERED, [
            'broadcast_id' => $target->platform_broadcast_id,
            'lifecycle' => $lifecycle,
            'via' => 'standalone_reap',
        ]);
    } catch (\Throwable $e) {
        echo "  transition threw: " . $e->getMessage() . "\n";
        $scheduler->recordEvent($target, RestreamTargetEventKind::YOUTUBE_ZOMBIE_RECOVERY_FAILED, [
            'broadcast_id' => $target->platform_broadcast_id,
            'lifecycle' => $lifecycle,
            'error' => $e->getMessage(),
            'via' => 'standalone_reap',
        ]);
    }
}

echo "\nDone.\n";
