<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'playlist_id',
        'media_item_id',
        'position',
        'start_sec',
        'split_from_id',
        'cue_in_sec',
        'cue_out_sec',
        'transition_in',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'start_sec' => 'integer',
            'cue_in_sec' => 'float',
            'cue_out_sec' => 'float',
        ];
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'playlist_id');
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }

    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(PlaylistItem::class, 'split_from_id');
    }

    public function effectiveDuration(): float
    {
        $total = (float) ($this->mediaItem?->duration_sec ?? 0);
        if ($this->cue_in_sec !== null) {
            $total -= (float) $this->cue_in_sec;
        }
        if ($this->cue_out_sec !== null) {
            $total = (float) $this->cue_out_sec - (float) ($this->cue_in_sec ?? 0);
        }
        return max(0.0, $total);
    }

    public function effectiveEndSec(): int
    {
        return (int) ($this->start_sec ?? 0) + (int) round($this->effectiveDuration());
    }
}
