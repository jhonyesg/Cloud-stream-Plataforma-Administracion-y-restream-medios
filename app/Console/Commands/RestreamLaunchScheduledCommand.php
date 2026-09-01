<?php

namespace App\Console\Commands;

use App\Models\RestreamTarget;
use Illuminate\Console\Command;

class RestreamLaunchScheduledCommand extends Command
{
    protected $signature = 'restream:launch-scheduled';

    protected $description = 'Enable restream targets whose scheduled_start_at has passed, so the supervisor picks them up.';

    public function handle(): int
    {
        $targets = RestreamTarget::query()
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '<=', now())
            ->where('enabled', false)
            ->get();

        foreach ($targets as $target) {
            $target->update(['enabled' => true]);
            $this->info("Enabled target {$target->id} ({$target->name}) scheduled for {$target->scheduled_start_at}");
        }

        if ($targets->isEmpty()) {
            $this->line('No scheduled targets to launch.');
        }

        return self::SUCCESS;
    }
}
