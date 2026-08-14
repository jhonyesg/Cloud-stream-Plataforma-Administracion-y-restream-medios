<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Playlist extends Model
{
    use HasUuids;

    protected $fillable = [
        'channel_id',
        'name',
        'description',
        'loop',
        'is_default',
        'total_duration_sec',
    ];

    protected function casts(): array
    {
        return [
            'loop' => 'boolean',
            'is_default' => 'boolean',
            'total_duration_sec' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $playlist) {
            self::recalculateDuration($playlist);
        });

        static::deleted(function (self $playlist) {
            // handled by FK cascade
        });
    }

    public static function recalculateDuration(self $playlist): void
    {
        $total = 0;
        $maxEnd = 0;
        foreach ($playlist->items()->orderBy('position')->get() as $item) {
            $total += $item->effectiveDuration();
            if ($item->start_sec !== null) {
                $maxEnd = max($maxEnd, $item->effectiveEndSec());
            }
        }
        $computed = $maxEnd > 0 ? (float) $maxEnd : $total;
        if (abs($playlist->total_duration_sec - $computed) > 0.0001) {
            $playlist->withoutEvents(function () use ($playlist, $computed) {
                $playlist->total_duration_sec = $computed;
                $playlist->save();
            });
        }
    }

    public static function cleanupOrphanSplits(self $playlist): int
    {
        $items = $playlist->items()->orderBy('position')->get();
        $merged = 0;
        $changed = true;
        while ($changed) {
            $changed = false;
            $items = $playlist->items()->orderBy('position')->get();
            foreach ($items as $tail) {
                if ($tail->cue_in_sec === null || $tail->split_from_id === null) continue;
                $parent = $items->firstWhere('id', $tail->split_from_id);
                if (!$parent || $parent->cue_out_sec === null) continue;
                if ($parent->media_item_id !== $tail->media_item_id) continue;

                $parentStart = $parent->start_sec ?? 0;
                $tailStart = $tail->start_sec ?? 0;

                $hasBetween = $items->contains(function ($it) use ($parent, $tail, $parentStart, $tailStart) {
                    if ($it->id === $parent->id || $it->id === $tail->id) return false;
                    $s = $it->start_sec;
                    if ($s === null) return false;
                    return $s > $parentStart && $s < $tailStart;
                });

                if (!$hasBetween) {
                    $parent->cue_out_sec = null;
                    $parent->save();
                    $tail->delete();
                    $merged++;
                    $changed = true;
                    break;
                }
            }
        }
        return $merged;
    }

    public static function normalizeSplitOrdering(self $playlist): int
    {
        $items = $playlist->items()->with('mediaItem')->orderBy('position')->get();
        $fixed = 0;

        foreach ($items as $tail) {
            if ($tail->split_from_id === null || $tail->cue_in_sec === null) {
                continue;
            }

            $parent = $items->firstWhere('id', $tail->split_from_id);
            if (! $parent || $parent->media_item_id !== $tail->media_item_id || $parent->cue_out_sec === null) {
                continue;
            }

            $parentStart = $parent->start_sec;
            $parentEnd = ($parentStart ?? 0) + (int) round($parent->effectiveDuration());
            $cues = $items->filter(function (PlaylistItem $item) use ($parent, $parentEnd) {
                return $item->position > $parent->position
                    && $item->mediaItem?->kind === 'ad'
                    && ($item->start_sec === null || $item->start_sec >= $parentEnd);
            });

            if ($cues->isEmpty()) {
                continue;
            }

            $cue = $cues->sortBy(function (PlaylistItem $item) use ($tail) {
                return abs((int) ($item->start_sec ?? $tail->start_sec ?? 0) - (int) ($tail->start_sec ?? 0));
            })->first();
            if ($parentStart === null) {
                $cursor = 0;
                foreach ($items as $item) {
                    if ($item->id === $parent->id) {
                        $parentStart = $cursor;
                        break;
                    }
                    if ($item->start_sec !== null) {
                        $cursor = (int) $item->start_sec;
                    }
                    $cursor += (int) round($item->effectiveDuration());
                }
            }
            $parentStart ??= 0;
            $cueStart = (int) $parentStart + (int) round($parent->effectiveDuration());
            $tailStart = $cueStart + (int) round($cue->effectiveDuration());
            $oldTailStart = $tail->start_sec;

            $ordered = $items->reject(fn (PlaylistItem $item) => in_array($item->id, [$cue->id, $tail->id], true))->values();
            $parentIndex = $ordered->search(fn (PlaylistItem $item) => $item->id === $parent->id);
            $ordered->splice($parentIndex + 1, 0, [$cue, $tail]);

            DB::table('playlist_items')
                ->where('playlist_id', $playlist->id)
                ->update(['position' => DB::raw('position + 1000000')]);
            foreach ($ordered as $index => $item) {
                $item->position = $index + 1;
                PlaylistItem::whereKey($item->id)->update(['position' => $item->position]);
            }

            PlaylistItem::whereKey($cue->id)->update(['start_sec' => $cueStart]);
            PlaylistItem::whereKey($tail->id)->update(['start_sec' => $tailStart]);
            $cue->start_sec = $cueStart;
            $tail->start_sec = $tailStart;

            if ($oldTailStart !== null) {
                $delta = $tailStart - (int) $oldTailStart;
                if ($delta !== 0) {
                    PlaylistItem::where('playlist_id', $playlist->id)
                        ->where('position', '>', (int) $tail->position)
                        ->whereNotNull('start_sec')
                        ->update(['start_sec' => DB::raw('start_sec + ' . $delta)]);
                }
            }

            $fixed++;
            $items = $playlist->items()->with('mediaItem')->orderBy('position')->get();
        }

        return $fixed;
    }

    public static function normalizeSequentialStarts(self $playlist): void
    {
        $cursor = 0;
        foreach ($playlist->items()->with('mediaItem')->orderBy('position')->get() as $item) {
            PlaylistItem::whereKey($item->id)->update(['start_sec' => $cursor]);
            $cursor += (int) round($item->effectiveDuration());
        }
    }

    public static function normalizeCueConflicts(self $playlist): int
    {
        $cues = PlaylistItem::with('mediaItem')
            ->where('playlist_id', $playlist->id)
            ->whereNotNull('start_sec')
            ->whereHas('mediaItem', fn ($query) => $query->where('kind', 'ad'))
            ->orderBy('start_sec')
            ->get();

        $shifted = 0;
        foreach ($cues as $cue) {
            $cueStart = (int) $cue->start_sec;
            $cueDur = (int) round($cue->effectiveDuration());
            $cueEnd = $cueStart + $cueDur;
            if ($cueDur <= 0) continue;

            PlaylistItem::where('playlist_id', $playlist->id)
                ->where('id', '!=', $cue->id)
                ->whereNotNull('start_sec')
                ->where('start_sec', '>=', $cueStart)
                ->where('start_sec', '<', $cueEnd)
                ->update(['start_sec' => $cueEnd]);
        }
        return $shifted;
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class, 'playlist_id')->orderBy('position');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }
}
