<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestreamQuota extends Model
{
    use HasUuids;

    public const TIER_BASIC = 1;
    public const TIER_PLUS1 = 2;
    public const TIER_PLUS2 = 3;
    public const TIER_PLUS3 = 4;

    public const VALID_TIERS = [self::TIER_BASIC, self::TIER_PLUS1, self::TIER_PLUS2, self::TIER_PLUS3];

    protected $table = 'restream_quotas';

    protected $fillable = [
        'user_id',
        'channel_id',
        'enabled',
        'max_outputs',
        'granted_by',
        'granted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'max_outputs' => 'integer',
            'granted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function tierLabel(): string
    {
        return match ($this->max_outputs) {
            self::TIER_BASIC => 'Base (gratis)',
            self::TIER_PLUS1 => '+1 destino (pago)',
            self::TIER_PLUS2 => '+2 destinos (pago)',
            self::TIER_PLUS3 => '+3 destinos (pago)',
            default => "Tier {$this->max_outputs}",
        };
    }
}