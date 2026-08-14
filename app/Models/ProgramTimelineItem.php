<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramTimelineItem extends Model
{
    use HasUuids;

    public const KIND_CONTENT = 'content';
    public const KIND_CUE = 'cue';
    public const KIND_FALLBACK = 'fallback';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PLAYING = 'playing';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $table = 'program_timeline_items';

    protected $fillable = [
        'template_id',
        'block_id',
        'day_of_month',
        'starts_at_sec',
        'ends_at_sec',
        'effective_duration_sec',
        'kind',
        'media_item_id',
        'playlist_item_id',
        'cue_in_sec',
        'cue_out_sec',
        'resume_offset_sec',
        'parent_content_id',
        'status',
        'is_interruptible',
        'timeline_version',
    ];

    protected function casts(): array
    {
        return [
            'day_of_month' => 'integer',
            'starts_at_sec' => 'integer',
            'ends_at_sec' => 'integer',
            'effective_duration_sec' => 'float',
            'cue_in_sec' => 'float',
            'cue_out_sec' => 'float',
            'resume_offset_sec' => 'float',
            'is_interruptible' => 'boolean',
            'timeline_version' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class, 'template_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(ScheduleBlock::class, 'block_id');
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }

    public function playlistItem(): BelongsTo
    {
        return $this->belongsTo(PlaylistItem::class, 'playlist_item_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_content_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_content_id');
    }

    public function scopeForDay(Builder $query, string $templateId, int $day): Builder
    {
        return $query->where('template_id', $templateId)
            ->where('day_of_month', $day)
            ->orderBy('starts_at_sec');
    }

    public function scopeFuture(Builder $query, int $nowSec): Builder
    {
        return $query->where('starts_at_sec', '>=', $nowSec);
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function effectiveDuration(): float
    {
        if ($this->effective_duration_sec !== null) {
            return (float) $this->effective_duration_sec;
        }
        return max(0.0, (float) ($this->ends_at_sec - $this->starts_at_sec));
    }

    public function isCue(): bool
    {
        return $this->kind === self::KIND_CUE;
    }

    public function isContent(): bool
    {
        return $this->kind === self::KIND_CONTENT;
    }

    public function isFallback(): bool
    {
        return $this->kind === self::KIND_FALLBACK;
    }
}
