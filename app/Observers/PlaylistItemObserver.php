<?php

namespace App\Observers;

use App\Models\Playlist;
use App\Models\PlaylistItem;

class PlaylistItemObserver
{
    public function created(PlaylistItem $item): void
    {
        $this->recalculatePlaylist($item);
    }

    public function updated(PlaylistItem $item): void
    {
        $this->recalculatePlaylist($item);
    }

    public function deleted(PlaylistItem $item): void
    {
        $this->recalculatePlaylist($item);
    }

    protected function recalculatePlaylist(PlaylistItem $item): void
    {
        $playlist = $item->playlist()->first();
        if (! $playlist) {
            return;
        }

        Playlist::recalculateDuration($playlist);
    }
}
