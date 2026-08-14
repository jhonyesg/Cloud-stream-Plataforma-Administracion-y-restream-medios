<?php

namespace App\Services;

use App\Models\MediaItem;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Inserts a cue/ad into a playlist at a specific second of the day, splitting
 * any content item that contains that second into two playlist_items that share
 * the same media_item_id. The first half keeps cue_in_sec unchanged and gets
 * cue_out_sec set to the offset within the source; the second half gets
 * cue_in_sec set to the offset (and inherits the original cue_out_sec, if any).
 *
 * After inserting the cue, every subsequent playlist item whose start_sec is
 * greater than the insertion point is shifted by the cue's duration, so the
 * rest of the day flows forward as in real broadcast.
 */
class PlaylistCueInserter
{
    public function insertAt(Playlist $playlist, int $atSec, MediaItem $cue): array
    {
        if ($cue->kind !== 'ad') {
            throw new RuntimeException('Solo se pueden insertar items de kind=ad como cuña.');
        }
        if ($cue->status !== 'ready') {
            throw new RuntimeException('La cuña no está lista (status=' . $cue->status . ').');
        }
        if ($cue->channel_id !== $playlist->channel_id) {
            throw new RuntimeException('La cuña no pertenece al canal de la playlist.');
        }
        $cueDuration = (int) round((float) ($cue->duration_sec ?? 0));
        if ($cueDuration <= 0) {
            throw new RuntimeException('La cuña no tiene duración.');
        }
        if ($atSec < 0 || $atSec > 86400) {
            throw new RuntimeException('at_sec fuera de rango (0-86400).');
        }

        return DB::transaction(function () use ($playlist, $atSec, $cue, $cueDuration) {
            $items = $playlist->items()->orderBy('position')->get();
            [$scheduleStarts, $scheduleEnds] = $this->computeSchedule($items);

            $containingId = null;
            foreach ($items as $item) {
                $start = $scheduleStarts[$item->id] ?? 0;
                $end = $scheduleEnds[$item->id] ?? 0;
                if ($atSec > $start && $atSec < $end) {
                    $containingId = $item->id;
                    break;
                }
            }

            $splitFrom = null;
            $insertPosition = $this->resolveInsertPosition($items, $scheduleStarts, $atSec, $containingId);
            $positionShift = $containingId ? 2 : 1;

            $this->shiftPositions($playlist, $insertPosition, $positionShift);

            if ($containingId) {
                $containing = $items->firstWhere('id', $containingId);
                $splitFrom = $this->splitContaining(
                    $containing,
                    $atSec,
                    $scheduleStarts[$containingId],
                    $insertPosition + 1,
                    $atSec + $cueDuration,
                );
            }

            $inserted = PlaylistItem::create([
                'playlist_id' => $playlist->id,
                'media_item_id' => $cue->id,
                'position' => $insertPosition,
                'start_sec' => $atSec,
            ]);

            $this->shiftFuture($playlist, $atSec, $cueDuration, [$inserted->id, $splitFrom?->id]);
            Playlist::normalizeCueConflicts($playlist);
            Playlist::normalizeSequentialStarts($playlist);

            Playlist::recalculateDuration($playlist);

            return [
                'inserted' => $inserted->fresh(),
                'split_from' => $splitFrom?->fresh(),
                'cue_duration_sec' => $cueDuration,
            ];
        });
    }

    private function computeSchedule($items): array
    {
        $starts = [];
        $ends = [];
        $cursor = 0;
        foreach ($items as $item) {
            $starts[$item->id] = $cursor;
            $cursor += (int) round($item->effectiveDuration());
            $ends[$item->id] = $cursor;
        }
        return [$starts, $ends];
    }

    private function splitContaining(
        PlaylistItem $containing,
        int $atSec,
        int $containingStart,
        int $newPos,
        int $tailStart,
    ): PlaylistItem
    {
        $offsetInSource = (float) ($atSec - $containingStart) + (float) ($containing->cue_in_sec ?? 0);

        $originalCueOut = $containing->cue_out_sec;

        $containing->cue_out_sec = $offsetInSource;
        if ($containing->start_sec === null) {
            $containing->start_sec = $containingStart;
        }
        $containing->save();

        $tail = PlaylistItem::create([
            'playlist_id' => $containing->playlist_id,
            'media_item_id' => $containing->media_item_id,
            'position' => $newPos,
            'start_sec' => $tailStart,
            'cue_in_sec' => $offsetInSource,
            'cue_out_sec' => $originalCueOut,
            'split_from_id' => $containing->id,
        ]);

        return $tail;
    }

    private function resolveInsertPosition($items, array $scheduleStarts, int $atSec, ?string $containingId): int
    {
        if ($containingId !== null) {
            $containing = $items->firstWhere('id', $containingId);
            return (int) $containing->position + 1;
        }

        foreach ($items as $item) {
            if (($scheduleStarts[$item->id] ?? PHP_INT_MAX) >= $atSec) {
                return (int) $item->position;
            }
        }

        return ((int) $items->max('position')) + 1;
    }

    private function shiftPositions(Playlist $playlist, int $fromPosition, int $amount): void
    {
        PlaylistItem::where('playlist_id', $playlist->id)
            ->where('position', '>=', $fromPosition)
            ->update(['position' => DB::raw('position + 1000000')]);

        PlaylistItem::where('playlist_id', $playlist->id)
            ->where('position', '>=', 1000000 + $fromPosition)
            ->update(['position' => DB::raw('position - ' . (1000000 - $amount))]);
    }

    private function shiftFuture(Playlist $playlist, int $atSec, int $cueDuration, array $excludeIds = []): void
    {
        PlaylistItem::where('playlist_id', $playlist->id)
            ->whereNotIn('id', array_values(array_filter($excludeIds)))
            ->whereNotNull('start_sec')
            ->where('start_sec', '>=', $atSec)
            ->update([
                'start_sec' => DB::raw('GREATEST(0, start_sec + ' . (int) $cueDuration . ')'),
            ]);
    }
}
