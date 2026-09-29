<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class RestreamTarget extends Model
{
    use HasUuids, SoftDeletes;

    public const PLATFORM_FACEBOOK = 'facebook';
    public const PLATFORM_TIKTOK = 'tiktok';
    public const PLATFORM_YOUTUBE = 'youtube';
    public const PLATFORM_CUSTOM = 'custom';

    public const PLATFORMS = [
        self::PLATFORM_FACEBOOK,
        self::PLATFORM_TIKTOK,
        self::PLATFORM_YOUTUBE,
        self::PLATFORM_CUSTOM,
    ];

    public const STATUS_IDLE = 'idle';
    public const STATUS_STARTING = 'starting';
    public const STATUS_ACTIVE = 'live';
    public const STATUS_ERROR = 'error';

    public const STATUSES = [self::STATUS_IDLE, self::STATUS_STARTING, self::STATUS_ACTIVE, self::STATUS_ERROR];

    protected $table = 'restream_targets';

    protected $fillable = [
        'user_id',
        'channel_id',
        'platform',
        'name',
        'destination_url',
        'stream_key',
        'source_url',
        'pipeline_pid',
        'enabled',
        'status',
        'last_error',
        'last_failed_at',
        'last_started_at',
        'last_stopped_at',
        'last_heartbeat_at',
        'loops_completed',
        'created_by',
        'platform_account_id',
        'title',
        'description',
        'thumbnail_path',
        'thumbnail_media_id',
        'scheduled_start_at',
        'scheduled_stop_at',
        'platform_broadcast_id',
        'platform_privacy',
        'keep_recording',
        'platform_broadcast_lifecycle',
        'platform_broadcast_lifecycle_at',
        'platform_broadcast_lifecycle_error',
        'last_youtube_poll_at',
        'daemon_stalled_at',
    ];

    protected $hidden = ['stream_key'];

    protected $appends = ['effective_status'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'keep_recording' => 'boolean',
            'pipeline_pid' => 'integer',
            'last_started_at' => 'datetime',
            'last_stopped_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'loops_completed' => 'integer',
            'stream_key' => 'encrypted',
            'scheduled_start_at' => 'datetime',
            'scheduled_stop_at' => 'datetime',
            'platform_broadcast_lifecycle_at' => 'datetime',
            'last_youtube_poll_at' => 'datetime',
            'daemon_stalled_at' => 'datetime',
        ];
    }

    public function getEffectiveStatusAttribute(): string
    {
        return app(\App\Services\Restream\RestreamStatusResolver::class)->resolve($this);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(RestreamPlatformAccount::class, 'platform_account_id');
    }

    public function thumbnailMedia(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'thumbnail_media_id');
    }

    public function thumbnailPreviewUrl(): ?string
    {
        if ($media = $this->thumbnailMedia) {
            return $media->thumbUrl();
        }
        if ($this->thumbnail_path && str_starts_with($this->thumbnail_path, '/storage/')) {
            return $this->thumbnail_path;
        }
        return null;
    }

    public function isPlatformManaged(): bool
    {
        return ! empty($this->platform_account_id);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(RestreamTargetSchedule::class, 'target_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RestreamTargetEvent::class, 'target_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function isCurrentlyRunning(): bool
    {
        // Trust pipeline_pid + posix_kill when present (the strong signal).
        if ($this->pipeline_pid && function_exists('posix_kill')) {
            if (@posix_kill((int) $this->pipeline_pid, 0)) {
                return true;
            }
        }
        // Fallback: a fresh heartbeat + status='live' means a daemon is
        // actually pushing bytes, even if pipeline_pid was lost (e.g.
        // operator did UPDATE ... pipeline_pid=NULL by hand, or a previous
        // stop() ran but the daemon kept running). Without this fallback,
        // the schedule engine would never call stopTargetAfterWindow() and
        // the daemon would push indefinitely past ends_at.
        if ($this->status === 'live' && $this->isHeartbeatFresh(15)) {
            return true;
        }

        return false;
    }

    public function isHeartbeatFresh(int $maxAgeSeconds = 15): bool
    {
        if (! $this->last_heartbeat_at) {
            return false;
        }
        return $this->last_heartbeat_at->gt(now()->subSeconds($maxAgeSeconds));
    }

    public function effectiveSourceUrl(): ?string
    {
        if (! empty($this->source_url)) {
            return $this->source_url;
        }

        $vs = $this->channel?->virtualScreen;
        if ($vs && ! empty($vs->output_url) && ($vs->output_protocol === 'rtmp' || str_starts_with($vs->output_url, 'rtmp://'))) {
            return $vs->output_url;
        }

        return null;
    }

    public function canRestartAfterFailure(): bool
    {
        if (! $this->last_failed_at) {
            return true;
        }
        return $this->last_failed_at->lt(now()->subMinutes(5));
    }

    public function platformLabel(): string
    {
        return match ($this->platform) {
            self::PLATFORM_FACEBOOK => 'Facebook Live',
            self::PLATFORM_TIKTOK => 'TikTok Live',
            self::PLATFORM_YOUTUBE => 'YouTube Live',
            self::PLATFORM_CUSTOM => 'Personalizado',
            default => ucfirst((string) $this->platform),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Activo',
            self::STATUS_STARTING => 'Iniciando',
            self::STATUS_ERROR => 'Error',
            default => 'Inactivo',
        };
    }

    public function revealStreamKey(): ?string
    {
        $raw = $this->getRawOriginal('stream_key');
        if (! $raw) {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function fullPushUrl(): ?string
    {
        $key = $this->revealStreamKey();
        if (! $key || ! $this->destination_url) {
            return null;
        }
        return rtrim($this->destination_url, '/') . '/' . $key;
    }

    public function streamKeyLast4(): ?string
    {
        $plain = $this->revealStreamKey();
        return $plain ? substr($plain, -4) : null;
    }

    public function toArray(): array
    {
        $arr = parent::toArray();
        $arr['stream_key'] = null;
        $arr['stream_key_set'] = ! empty($this->getRawOriginal('stream_key'));
        $arr['stream_key_last4'] = $this->streamKeyLast4();
        return $arr;
    }
}