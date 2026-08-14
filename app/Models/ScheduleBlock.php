<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleBlock extends Model
{
    use HasUuids;

    protected $fillable = [
        'template_id',
        'day_of_month',
        'playlist_id',
    ];

    protected function casts(): array
    {
        return [
            'day_of_month' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class, 'template_id');
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'playlist_id');
    }

    public function timelineItems(): HasMany
    {
        return $this->hasMany(ProgramTimelineItem::class, 'block_id')->orderBy('starts_at_sec');
    }
}