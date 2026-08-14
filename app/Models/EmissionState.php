<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmissionState extends Model
{
    use HasUuids;

    protected $table = 'emission_state';

    protected $primaryKey = 'channel_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'channel_id',
        'status',
        'stop_reason',
        'current_block_id',
        'current_playlist_id',
        'current_item_id',
        'current_timeline_item_id',
        'active_timeline_item_id',
        'content_timeline_item_id',
        'content_position_sec',
        'interrupt_timeline_item_id',
        'interrupt_position_sec',
        'broadcast_clock_sec',
        'overflow_sec',
        'timeline_version',
        'mode',
        'position_in_item_sec',
        'started_at',
        'last_heartbeat_at',
        'error_message',
        'ffmpeg_pid',
        'ffmpeg_cmd',
        'pipeline_pid',
        'loops_completed',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'position_in_item_sec' => 'float',
            'content_position_sec' => 'float',
            'interrupt_position_sec' => 'float',
            'broadcast_clock_sec' => 'float',
            'overflow_sec' => 'float',
            'timeline_version' => 'integer',
            'started_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'updated_at' => 'datetime',
            'ffmpeg_pid' => 'integer',
            'pipeline_pid' => 'integer',
            'loops_completed' => 'integer',
        ];
    }

    /**
     * Compat shim: prefer the new `pipeline_pid` column, fall back to
     * the legacy `ffmpeg_pid` so callers from both code paths see the
     * running process PID.
     */
    public function getPipelinePidAttribute(): ?int
    {
        $value = $this->attributes['pipeline_pid'] ?? null;
        if ($value !== null) {
            return (int) $value;
        }
        $legacy = $this->attributes['ffmpeg_pid'] ?? null;
        return $legacy !== null ? (int) $legacy : null;
    }

    /**
     * Mirror writes: assigning pipeline_pid also keeps ffmpeg_pid in sync
     * until legacy readers are fully migrated.
     */
    public function setPipelinePidAttribute(?int $value): void
    {
        $this->attributes['pipeline_pid'] = $value;
        $this->attributes['ffmpeg_pid'] = $value;
    }

    public function secondsRemainingTo24h(): ?int
    {
        if (! $this->started_at) {
            return null;
        }
        $deadline = $this->started_at->copy()->addHours(24);
        $remaining = $deadline->diffInSeconds(now(), false);

        return $remaining > 0 ? (int) $remaining : 0;
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
