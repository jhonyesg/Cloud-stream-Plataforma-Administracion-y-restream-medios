<?php

namespace App\Policies;

use App\Models\MediaItem;
use App\Models\User;

class MediaItemPolicy
{
    public function view(User $user, MediaItem $mediaItem): bool
    {
        return $this->canAccess($user, $mediaItem);
    }

    public function update(User $user, MediaItem $mediaItem): bool
    {
        return $this->canAccess($user, $mediaItem);
    }

    public function delete(User $user, MediaItem $mediaItem): bool
    {
        return $this->canAccess($user, $mediaItem);
    }

    protected function canAccess(User $user, MediaItem $mediaItem): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $channel = $mediaItem->channel;
        if (! $channel) {
            return false;
        }

        return $user->canAccessChannel($channel);
    }
}