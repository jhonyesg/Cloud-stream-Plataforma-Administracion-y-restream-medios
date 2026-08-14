<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ScheduleTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'channel_id',
        'name',
        'year',
        'month',
        'status',
        'is_replicated',
        'cloned_from_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'is_replicated' => 'boolean',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class, 'template_id')->orderBy('day_of_month');
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_id');
    }

    public function clones(): HasMany
    {
        return $this->hasMany(self::class, 'cloned_from_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function cloneTo(int $year, int $month): self
    {
        return DB::transaction(function () use ($year, $month) {
            $existing = self::where('channel_id', $this->channel_id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($existing) {
                AuditLog::record(
                    action: 'clone.schedule_template.replace',
                    entityType: 'ScheduleTemplate',
                    entityId: $existing->id,
                    before: $existing->toArray() + ['blocks' => $existing->blocks()->get()->toArray()],
                    after: null,
                    channelId: $existing->channel_id,
                );
                $existing->delete();
            }

            $clone = self::create([
                'channel_id' => $this->channel_id,
                'name' => $this->name,
                'year' => $year,
                'month' => $month,
                'status' => 'draft',
                'is_replicated' => true,
                'cloned_from_id' => $this->id,
            ]);

            foreach ($this->blocks()->get() as $block) {
                $clone->blocks()->create([
                    'day_of_month' => $block->day_of_month,
                    'playlist_id' => $block->playlist_id,
                ]);
            }

            return $clone;
        });
    }
}
