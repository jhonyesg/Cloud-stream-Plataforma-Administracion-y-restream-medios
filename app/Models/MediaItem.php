<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'channel_id',
        'filename',
        'sha256',
        'size_bytes',
        'mime_type',
        'kind',
        'status',
        'status_reason',
        'duration_sec',
        'width',
        'height',
        'codec_video',
        'codec_audio',
        'bitrate_kbps',
        'thumb_path',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'duration_sec' => 'float',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'bitrate_kbps' => 'integer',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function scopeOfKind($query, string $kind)
    {
        return $query->where('kind', $kind);
    }

    public function scopeAds($query)
    {
        return $query->where('kind', 'ad');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeForChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function canAccess(\App\Models\User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $channel = $this->channel;
        if (! $channel) {
            return false;
        }
        return $user->canAccessChannel($channel);
    }

    public function playUrl(): string
    {
        return url('/api/media-items/' . $this->id . '/stream');
    }

    public function thumbUrl(): string
    {
        return url('/api/media-items/' . $this->id . '/thumb');
    }
}