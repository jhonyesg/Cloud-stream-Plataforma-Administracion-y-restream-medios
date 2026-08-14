<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'display_name',
        'avatar_path',
        'role',
        'status',
        'owner_id',
        'storage_limit_bytes',
        'used_bytes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getNameAttribute(): string
    {
        return $this->display_name ?? $this->username ?? $this->email ?? 'User';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(self::class, 'owner_id');
    }

    public function subUsers(): HasMany
    {
        return $this->hasMany(self::class, 'owner_id');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class, 'owner_id');
    }

    public function assignedChannels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_user')
            ->withTimestamps();
    }

    public function effectiveChannelIds(): Collection
    {
        $owned = $this->channels()->pluck('channels.id');
        $assigned = $this->assignedChannels()->pluck('channels.id');
        return $owned->merge($assigned)->unique()->values();
    }

    public function canAccessChannel(Channel $channel): bool
    {
        if ($channel->owner_id === $this->id) {
            return true;
        }
        if ($this->relationLoaded('assignedChannels')) {
            return $this->assignedChannels->contains('id', $channel->id);
        }
        return $this->assignedChannels()->where('channels.id', $channel->id)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function effectiveOwnerId(): ?string
    {
        return $this->owner_id ?? $this->id;
    }

    public function hasStorageQuota(): bool
    {
        return $this->storage_limit_bytes !== null;
    }

    public function getStorageLimitHumanAttribute(): string
    {
        return self::humanBytes($this->storage_limit_bytes);
    }

    public function getStorageUsedHumanAttribute(): string
    {
        return self::humanBytes((int) $this->used_bytes);
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
        if ($bytes === null) return 'Ilimitado';
        if ($bytes < 0) return '0 B';
        if ($bytes < 1024) return $bytes . ' B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }
}
