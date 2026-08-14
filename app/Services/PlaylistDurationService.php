<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\PlaylistItem;
use RuntimeException;

class PlaylistDurationService
{
    public function recalculate(Playlist $playlist): float
    {
        $total = 0.0;
        foreach ($playlist->items()->orderBy('position')->get() as $item) {
            $total += $this->effectiveDuration($item);
        }

        if (abs($playlist->total_duration_sec - $total) > 0.0001) {
            $playlist->withoutEvents(function () use ($playlist, $total) {
                $playlist->total_duration_sec = $total;
                $playlist->save();
            });
        }
        return $total;
    }

    public function effectiveDuration(PlaylistItem $item): float
    {
        $total = (float) ($item->mediaItem?->duration_sec ?? 0);
        if ($item->cue_in_sec !== null) {
            $total = $total - (float) $item->cue_in_sec;
        }
        if ($item->cue_out_sec !== null) {
            $total = (float) $item->cue_out_sec - (float) ($item->cue_in_sec ?? 0);
        }
        return max(0.0, $total);
    }

    public function assertValid(PlaylistItem $item): void
    {
        $duration = (float) ($item->mediaItem?->duration_sec ?? 0);
        $cueIn = $item->cue_in_sec !== null ? (float) $item->cue_in_sec : null;
        $cueOut = $item->cue_out_sec !== null ? (float) $item->cue_out_sec : null;

        if ($duration <= 0) {
            throw new RuntimeException('Playlist item has no usable media duration.');
        }
        if ($cueIn !== null && ($cueIn < 0 || $cueIn >= $duration)) {
            throw new RuntimeException('cue_in_sec is outside the media duration.');
        }
        if ($cueOut !== null && ($cueOut <= 0 || $cueOut > $duration)) {
            throw new RuntimeException('cue_out_sec is outside the media duration.');
        }
        if ($cueIn !== null && $cueOut !== null && $cueOut <= $cueIn) {
            throw new RuntimeException('cue_out_sec must be greater than cue_in_sec.');
        }
        if ($this->effectiveDuration($item) <= 0) {
            throw new RuntimeException('Playlist item must have a positive effective duration.');
        }
    }
}
