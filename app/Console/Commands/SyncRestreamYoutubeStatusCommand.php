<?php

namespace App\Console\Commands;

use App\Models\RestreamTarget;
use App\Services\Restream\Platform\YoutubeBroadcastService;
use Illuminate\Console\Command;
use Throwable;

class SyncRestreamYoutubeStatusCommand extends Command
{
    protected $signature = 'restream:sync-youtube-status
                            {--once : Run a single pass and exit (useful for tests and ops)}
                            {--json : Emit a structured JSON summary instead of human output}';

    /**
     * Poll YouTube Live Streaming API for every YouTube restream target that
     * has an active daemon and persist the lifeCycleStatus to the target row.
     * Catches errors per target and continues.
     *
     * As of 2026-09-30, this also polls idle targets that have a bound
     * `platform_broadcast_id`. Those are the targets that stopped locally but
     * whose YouTube broadcast was never transitioned to `complete` (the
     * "zombie live" symptom). Polling them keeps `platform_broadcast_lifecycle`
     * fresh so the `RestreamStatusResolver` can flip `effective_status` to
     * `zombie-live` and the UI badge can prompt the operator to act.
     */
    protected $description = 'Poll YouTube Live Streaming API for every YouTube restream target (active or zombie-bound) and persist lifeCycleStatus.';

    public function handle(): int
    {
        $jsonOutput = (bool) $this->option('json');
        $once = (bool) $this->option('once');

        $youtube = app(YoutubeBroadcastService::class);

        $query = RestreamTarget::query()
            ->where('platform', 'youtube')
            ->whereNotNull('platform_broadcast_id')
            ->whereNotNull('platform_account_id')
            ->where(function ($q) {
                $q->whereNotNull('pipeline_pid')
                    ->orWhere('status', 'idle');
            });

        $summary = [
            'scanned' => 0,
            'succeeded' => 0,
            'errored' => 0,
            'skipped' => 0,
            'rows' => [],
        ];

        foreach ($query->cursor() as $target) {
            $summary['scanned']++;
            try {
                $lifecycle = $youtube->broadcastLifecycle($target);
                if ($lifecycle === null) {
                    $target->update([
                        'platform_broadcast_lifecycle' => null,
                        'platform_broadcast_lifecycle_at' => now(),
                        'platform_broadcast_lifecycle_error' => 'YouTube broadcast not found (deleted or revoked)',
                        'last_youtube_poll_at' => now(),
                    ]);
                    $summary['skipped']++;
                    $summary['rows'][] = ['target_id' => $target->id, 'lifecycle' => null, 'status' => 'skipped'];
                    continue;
                }

                $target->update([
                    'platform_broadcast_lifecycle' => $lifecycle,
                    'platform_broadcast_lifecycle_at' => now(),
                    'platform_broadcast_lifecycle_error' => null,
                    'last_youtube_poll_at' => now(),
                ]);
                $summary['succeeded']++;
                $summary['rows'][] = ['target_id' => $target->id, 'lifecycle' => $lifecycle, 'status' => 'ok'];
            } catch (Throwable $e) {
                $target->update([
                    'platform_broadcast_lifecycle_error' => $e->getMessage(),
                    'last_youtube_poll_at' => now(),
                ]);
                $summary['errored']++;
                $summary['rows'][] = ['target_id' => $target->id, 'lifecycle' => null, 'status' => 'error', 'error' => $e->getMessage()];
            }
        }

        if ($jsonOutput) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));
        } else {
            $this->info(sprintf(
                '[restream:sync-youtube-status] scanned=%d succeeded=%d errored=%d skipped=%d',
                $summary['scanned'],
                $summary['succeeded'],
                $summary['errored'],
                $summary['skipped'],
            ));
            foreach ($summary['rows'] as $row) {
                $line = "  - {$row['target_id']} → " . ($row['lifecycle'] ?? 'NULL');
                if ($row['status'] === 'error') {
                    $line .= ' (ERROR: ' . $row['error'] . ')';
                }
                $this->line($line);
            }
        }

        return $once && $summary['errored'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}