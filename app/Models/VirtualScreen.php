<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualScreen extends Model
{
    use HasUuids;

    protected $fillable = [
        'channel_id',
        'name',
        'width',
        'height',
        'layout',
        'theme',
        'output_protocol',
        'output_url',
        'fps',
        'video_bitrate_kbps',
        'audio_bitrate_kbps',
        'codec_video',
        'codec_audio',
        'video_preset',
        'logo_media_item_id',
        'logo_x',
        'logo_y',
        'logo_w',
        'logo_h',
        'logo_opacity',
        'fallback_type',
        'fallback_media_item_id',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'layout' => 'array',
            'fps' => 'integer',
            'video_bitrate_kbps' => 'integer',
            'audio_bitrate_kbps' => 'integer',
            'codec_video' => 'string',
            'codec_audio' => 'string',
            'video_preset' => 'string',
            'logo_x' => 'integer',
            'logo_y' => 'integer',
            'logo_w' => 'integer',
            'logo_h' => 'integer',
            'logo_opacity' => 'float',
        ];
    }

    public const FALLBACK_BLACK = 'black';
    public const FALLBACK_TEST_PATTERN = 'test_pattern';
    public const FALLBACK_IMAGE_LOOP = 'image_loop';
    public const FALLBACK_MEDIA = 'media';

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'logo_media_item_id');
    }
}