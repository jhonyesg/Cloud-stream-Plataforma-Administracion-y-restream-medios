<?php

namespace App\Console\Commands;

use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecomputeQuotasCommand extends Command
{
    protected $signature = 'media:recompute-quotas
        {--channel= : Recompute only one channel (id or slug)}
        {--json : Emit structured JSON for piping}';

    protected $description = 'Recompute channels.used_bytes from current media_items.size_bytes.';

    public function handle(): int
    {
        $rows = DB::table('media_items as m')
            ->whereNotNull('m.size_bytes')
            ->groupBy('m.channel_id')
            ->selectRaw('m.channel_id as channel_id, COALESCE(SUM(m.size_bytes), 0)::bigint as used')
            ->get()
            ->keyBy('channel_id');

        $query = Channel::query();
        if ($this->option('channel')) {
            $needle = (string) $this->option('channel');
            $query->where(function ($q) use ($needle) {
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $needle)) {
                    $q->where('id', $needle)->orWhere('slug', $needle);
                } else {
                    $q->where('slug', $needle);
                }
            });
        }

        $channels = $query->orderBy('slug')->get();
        if ($channels->isEmpty()) {
            $this->warn('No channels matched.');
            return self::SUCCESS;
        }

        $json = [];
        $table = [];
        $changed = 0;

        DB::transaction(function () use ($channels, $rows, &$changed, &$json, &$table) {
            foreach ($channels as $channel) {
                $before = (int) $channel->used_bytes;
                $after = (int) ($rows[$channel->id]->used ?? 0);
                $delta = $after - $before;
                if ($delta !== 0) {
                    $channel->used_bytes = $after;
                    $channel->save();
                    $changed++;
                }
                $entry = [
                    'slug' => $channel->slug,
                    'before' => $before,
                    'after' => $after,
                    'delta' => $delta,
                    'limit' => $channel->storage_limit_bytes,
                ];
                $json[] = $entry;
                $table[] = [
                    $channel->slug,
                    Channel::humanBytes($before),
                    Channel::humanBytes($after),
                    ($delta >= 0 ? '+' : '') . Channel::humanBytes($delta),
                    Channel::humanBytes($channel->storage_limit_bytes),
                ];
            }
        });

        if ($this->option('json')) {
            $this->line(json_encode(['recomputed' => $changed, 'channels' => $json], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['slug', 'before', 'after', 'delta', 'limit'], $table);
            $this->info("Updated {$changed} channel(s).");
        }

        return self::SUCCESS;
    }
}
