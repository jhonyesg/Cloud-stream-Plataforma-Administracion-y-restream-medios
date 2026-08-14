<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\EmissionLogRotator;
use Illuminate\Console\Command;

class EmisionLogsRotateCommand extends Command
{
    protected $signature = 'emision:logs:rotate
                            {--channel= : Scope to one channel (slug or UUID)}
                            {--dry-run : Report what would happen without mutating}';

    protected $description = 'Rotate and GC the emission daemon log file(s). Mirrors the prepare/gc logic run by EmissionOrchestrator::spawnDaemon.';

    public function handle(EmissionLogRotator $rotator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $channelOpt = $this->option('channel');

        if ($channelOpt) {
            $query = Channel::query();
            $isUuid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $channelOpt);

            if ($isUuid) {
                $query->whereKey($channelOpt);
            } else {
                $query->where('slug', $channelOpt);
            }

            $channel = $query->first();

            if (! $channel) {
                $this->error("Channel not found: {$channelOpt}");

                return self::FAILURE;
            }

            return $this->processChannel($channel, $rotator, $dryRun);
        }

        $channels = Channel::all();
        if ($channels->isEmpty()) {
            $this->warn('No channels in database; nothing to do.');

            return self::SUCCESS;
        }

        $exit = self::SUCCESS;
        foreach ($channels as $channel) {
            $code = $this->processChannel($channel, $rotator, $dryRun);
            if ($code !== self::SUCCESS) {
                $exit = $code;
            }
        }

        return $exit;
    }

    private function processChannel(Channel $channel, EmissionLogRotator $rotator, bool $dryRun): int
    {
        $logPath = storage_path("logs/emission-{$channel->id}.log");

        if ($dryRun) {
            $this->line("[{$channel->slug}] (dry-run) would prepare + gc {$logPath}");

            return self::SUCCESS;
        }

        $rotator->prepare($logPath);
        $deleted = $rotator->gc($logPath);
        $this->line(sprintf(
            '[%s] %s — rotated siblings gc deleted: %d',
            $channel->slug,
            $logPath,
            $deleted,
        ));

        return self::SUCCESS;
    }
}
