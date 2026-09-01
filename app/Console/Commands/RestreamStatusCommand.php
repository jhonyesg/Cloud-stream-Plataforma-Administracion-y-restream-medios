<?php

namespace App\Console\Commands;

use App\Models\RestreamTarget;
use Illuminate\Console\Command;

class RestreamStatusCommand extends Command
{
    protected $signature = 'restream:status {--user= : Filter by user_id}';

    protected $description = 'Print a table of all restream targets with their live state.';

    public function handle(): int
    {
        $query = RestreamTarget::with(['user', 'channel']);
        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }
        $targets = $query->orderBy('created_at', 'desc')->get();

        if ($targets->isEmpty()) {
            $this->info('No targets.');
            return self::SUCCESS;
        }

        $rows = $targets->map(function (RestreamTarget $t) {
            $alive = $t->pipeline_pid ? ($t->isHeartbeatFresh() ? '✓' : '✗') : '—';
            $err = $t->last_error ? mb_strimwidth((string) $t->last_error, 0, 60, '…') : '';
            return [
                'id' => $t->id,
                'channel' => $t->channel?->display_name ?? '—',
                'platform' => $t->platform,
                'name' => $t->name,
                'status' => $t->status,
                'pid' => $t->pipeline_pid ?? '—',
                'alive' => $alive,
                'started' => $t->last_started_at?->format('Y-m-d H:i:s') ?? '—',
                'stopped' => $t->last_stopped_at?->format('Y-m-d H:i:s') ?? '—',
                'error' => $err,
            ];
        })->all();

        $this->table(
            ['id', 'channel', 'platform', 'name', 'status', 'pid', 'alive', 'started', 'stopped', 'error'],
            $rows
        );

        return self::SUCCESS;
    }
}