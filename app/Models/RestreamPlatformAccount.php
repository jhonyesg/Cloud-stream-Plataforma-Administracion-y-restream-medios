<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestreamPlatformAccount extends Model
{
    use HasUuids;

    public const PLATFORM_YOUTUBE = 'youtube';
    public const PLATFORM_FACEBOOK = 'facebook';

    public const PLATFORMS = [self::PLATFORM_YOUTUBE, self::PLATFORM_FACEBOOK];

    protected $table = 'restream_platform_accounts';

    protected $fillable = [
        'user_id',
        'platform',
        'platform_account_id',
        'display_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }
        return $this->token_expires_at->isPast();
    }

    public function needsReconnect(): bool
    {
        return $this->isTokenExpired() && empty($this->refresh_token);
    }

    public function platformLabel(): string
    {
        return match ($this->platform) {
            self::PLATFORM_YOUTUBE => 'YouTube',
            self::PLATFORM_FACEBOOK => 'Facebook',
            default => ucfirst((string) $this->platform),
        };
    }
}
