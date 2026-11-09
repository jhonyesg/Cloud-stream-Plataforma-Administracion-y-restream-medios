<?php

namespace App\Console\Commands;

use App\Models\RestreamTargetEvent;
use Illuminate\Console\Command;

class PruneTargetEventsCommand extends Command
{
    protected $signature = 'restream:prune-target-events {--days= : Override TARGET_EVENTS_RETENTION_DAYS for this run}';

    protected $description = 'Delete restream_target_events rows older than the retention window (default 90 days).';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? env('TARGET_EVENTS_RETENTION_DAYS', 90));
        if ($days < 1) {
            $this->error('Retention must be at least 1 day.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = RestreamTargetEvent::where('occurred_at', '<', $cutoff)->delete();

        $this->line("Pruned {$deleted} events older than {$days} days (cutoff={$cutoff->toIso8601String()}).");

        return self::SUCCESS;
    }
}
