<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'slug',
        'display_name',
        'public_hls_url',
        'description',
        'logo_path',
        'root_path',
        'status',
        'last_emitted_at',
        'storage_limit_bytes',
        'used_bytes',
    ];

    protected function casts(): array
    {
        return [
            'last_emitted_at' => 'datetime',
            'storage_limit_bytes' => 'integer',
            'used_bytes' => 'integer',
        ];
    }

    protected $appends = ['resolution'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class, 'channel_id');
    }

    public function defaultPlaylist(): HasOne
    {
        return $this->hasOne(Playlist::class, 'channel_id')->where('is_default', true);
    }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ScheduleTemplate::class, 'channel_id');
    }

    public function virtualScreen(): HasOne
    {
        return $this->hasOne(VirtualScreen::class, 'channel_id');
    }

    public function getResolutionAttribute(): string
    {
        $vs = $this->virtualScreen;
        $w = $vs?->width ?? 1280;
        $h = $vs?->height ?? 720;
        return "{$w}×{$h}";
    }

    public function emissionState(): HasOne
    {
        return $this->hasOne(EmissionState::class, 'channel_id');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_user')
            ->withTimestamps();
    }

    public function scopeForOwner($query, string $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeNotArchived($query)
    {
        return $query->where('status', '!=', 'archived');
    }

    public static function accessibleBy(\App\Models\User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = static::query()->notArchived();
        if ($user->isAdmin()) {
            return $query->orderBy('display_name');
        }
        return $query
            ->whereIn('id', $user->effectiveChannelIds())
            ->orderBy('display_name');
    }

    public function hasStorageQuota(): bool
    {
        return $this->storage_limit_bytes !== null;
    }

    public function getStorageLimitHumanAttribute(): string
    {
        return \App\Models\User::humanBytes($this->storage_limit_bytes);
    }

    public function getStorageUsedHumanAttribute(): string
    {
        return \App\Models\User::humanBytes((int) $this->used_bytes);
    }

    public function getStoragePercentAttribute(): float
    {
        if (! $this->hasStorageQuota() || $this->storage_limit_bytes === 0) {
            return 0.0;
        }
        $pct = ((int) $this->used_bytes / $this->storage_limit_bytes) * 100;
        return (float) min(100, $pct);
    }

    public function getStorageRemainingBytesAttribute(): int
    {
        if (! $this->hasStorageQuota()) {
            return PHP_INT_MAX;
        }
        return max(0, $this->storage_limit_bytes - (int) $this->used_bytes);
    }

    public function getStorageIsOverQuotaAttribute(): bool
    {
        if (! $this->hasStorageQuota()) {
            return false;
        }
        return (int) $this->used_bytes >= $this->storage_limit_bytes;
    }

    public function getStorageBarColorAttribute(): string
    {
        $pct = $this->storage_percent;
        if ($pct >= 90) return 'bg-red-500';
        if ($pct >= 70) return 'bg-yellow-500';
        return 'bg-green-500';
    }

    public static function humanBytes(?int $bytes): string
    {
        return \App\Models\User::humanBytes($bytes);
    }
}
