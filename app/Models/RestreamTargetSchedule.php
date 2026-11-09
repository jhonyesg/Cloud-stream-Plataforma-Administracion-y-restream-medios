<?php

namespace App\Models;

use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled window for a restream target.
 *
 * - `ends_at` is nullable for indefinite broadcasts. The motor will not
 *   stop the daemon on its own; only a manual stop or external failure
 *   can end the stream.
 * - `timezone` is an IANA identifier. `starts_at` and `ends_at` are stored
 *   in UTC; the accessors render the values in the stored TZ for the UI.
 * - `stopped_by_user` is set to `true` when the user clicks "Detener ahora"
 *   so the engine does NOT silently restart the broadcast on the next tick.
 */
class RestreamTargetSchedule extends Model
{
    use HasUuids;

    protected $table = 'restream_target_schedules';

    protected $fillable = [
        'target_id',
        'name',
        'starts_at',
        'ends_at',
        'timezone',
        'notes',
        'thumbnail_path',
        'thumbnail_media_id',
        'schedule_title',
        'schedule_description',
        'platform_privacy',
        'keep_recording',
        'enabled',
        'stopped_by_user',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'enabled' => 'boolean',
            'keep_recording' => 'boolean',
            'stopped_by_user' => 'boolean',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(RestreamTarget::class, 'target_id');
    }

    public function scopeForTarget(Builder $query, string $targetId): Builder
    {
        return $query->where('target_id', $targetId);
    }

    public function scopeForChannel(Builder $query, string $channelId): Builder
    {
        return $query->whereHas('target', fn (Builder $q) => $q->where('channel_id', $channelId));
    }

    /**
     * Windows that should be active at the given moment for the engine.
     * The engine then picks the latest applicable one (in case multiple
     * windows overlap).
     */
    public function scopeApplicableAt(Builder $query, CarbonInterface $moment): Builder
    {
        return $query
            ->where('enabled', true)
            ->where('starts_at', '<=', $moment)
            ->where(function (Builder $q) use ($moment) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $moment);
            });
    }

    public function scopeUpcoming(Builder $query, CarbonInterface $horizon): Builder
    {
        return $query
            ->where('enabled', true)
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', $horizon);
    }

    /**
     * One of: pending, running, ended, disabled. Derivable, never persisted.
     */
    public function getStatusAttribute(): string
    {
        if (! $this->enabled) {
            return 'disabled';
        }

        // Si el destino fue detenido manualmente (status='idle' con pipeline_pid=null
        // mientras estamos dentro de la franja de la ventana), refleja que el
        // cliente paró la emisión a propósito. El motor NO la reinicia.
        $target = $this->target;
        if ($target && $target->status === 'idle' && ! $target->pipeline_pid) {
            if ($this->starts_at && $this->starts_at->lte(now()) &&
                ($this->ends_at === null || $this->ends_at->gt(now()))) {
                return 'stopped';
            }
        }

        $now = now();
        if ($this->starts_at && $this->starts_at->gt($now)) {
            return 'pending';
        }
        if ($this->ends_at && $this->ends_at->lte($now)) {
            return 'ended';
        }

        return 'running';
    }

    public function getIsIndefiniteAttribute(): bool
    {
        return $this->ends_at === null;
    }

    public function getDurationSecondsAttribute(): ?int
    {
        if (! $this->starts_at || ! $this->ends_at) {
            return null;
        }

        return $this->ends_at->diffInSeconds($this->starts_at);
    }

    public function getLocalStartsAtAttribute(): ?CarbonInterface
    {
        if (! $this->starts_at) {
            return null;
        }

        return $this->starts_at->copy()->setTimezone($this->resolveTimezone());
    }

    public function getLocalEndsAtAttribute(): ?CarbonInterface
    {
        if (! $this->ends_at) {
            return null;
        }

        return $this->ends_at->copy()->setTimezone($this->resolveTimezone());
    }

    private function resolveTimezone(): DateTimeZone
    {
        try {
            return new DateTimeZone($this->timezone ?: 'UTC');
        } catch (\Throwable) {
            return new DateTimeZone('UTC');
        }
    }
}
