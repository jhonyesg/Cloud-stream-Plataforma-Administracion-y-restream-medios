<?php

namespace App\Observers;

use App\Models\MediaItem;
use Illuminate\Support\Facades\DB;

class MediaItemObserver
{
    public function created(MediaItem $item): void
    {
        $this->adjustUsedBytes($item, 'increment');
    }

    public function deleted(MediaItem $item): void
    {
        $this->adjustUsedBytes($item, 'decrement');
    }

    protected function adjustUsedBytes(MediaItem $item, string $operation): void
    {
        $channelId = $item->channel_id;
        if (! $channelId) {
            return;
        }

        $sizeBytes = (int) ($item->size_bytes ?? 0);
        if ($sizeBytes <= 0) {
            return;
        }

        if ($operation === 'increment') {
            DB::update(
                'UPDATE channels SET used_bytes = used_bytes + ? WHERE id = ?',
                [$sizeBytes, $channelId]
            );
        } else {
            DB::update(
                'UPDATE channels SET used_bytes = GREATEST(used_bytes - ?, 0) WHERE id = ?',
                [$sizeBytes, $channelId]
            );
        }
    }
}
