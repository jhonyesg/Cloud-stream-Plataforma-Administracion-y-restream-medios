<?php

namespace App\Services;

use App\Models\MediaItem;
use App\Models\ProgramTimelineItem;
use App\Models\ScheduleTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mutates an existing timeline in place while honoring the rule
 * "the active item is frozen; only the future can shift".
 *
 * Currently supported operation: insertCue(template, day, atSec, mediaItem).
 *   - If `atSec` falls inside a future content item, the item is split into
 *     two rows (second row keeps the same `media_item_id` and gets a
 *     `parent_content_id` referencing the first).
 *   - All timeline items with `starts_at_sec > atSec` are shifted by the
 *     cue's effective duration (policy = "shift").
 *   - `timeline_version` is bumped on every row of that day.
 *   - If the cue would land in the past (before the currently playing item),
 *     the operation is rejected.
 */
class TimelineMutator
{
    public function __construct(private readonly PlaylistDurationService $durations)
    {
    }

    /**
     * @return array{inserted: ProgramTimelineItem, split_from: ?ProgramTimelineItem, version: int}
     */
    public function insertCue(
        ScheduleTemplate $template,
        int $dayOfMonth,
        int $atSec,
        MediaItem $cue,
        ?int $nowSec = null,
    ): array {
        if ($cue->kind !== 'ad') {
            throw new RuntimeException('insertCue only accepts media items of kind=ad.');
        }
        if ($cue->status !== 'ready') {
            throw new RuntimeException('Cue media item is not ready (status='.$cue->status.').');
        }

        $nowSec ??= $this->broadcastNowSec();
        $cueDuration = (float) ($cue->duration_sec ?? 0);
        if ($cueDuration <= 0) {
            throw new RuntimeException('Cue media item has no duration; cannot insert.');
        }

        return DB::transaction(function () use ($template, $dayOfMonth, $atSec, $cue, $cueDuration, $nowSec) {
            // Find the "now" boundary: the currently-playing or last-started
            // item. Anything <= its starts_at_sec is frozen.
            $activeBoundary = ProgramTimelineItem::query()
                ->where('template_id', $template->id)
                ->where('day_of_month', $dayOfMonth)
                ->whereIn('status', [ProgramTimelineItem::STATUS_PLAYING, ProgramTimelineItem::STATUS_QUEUED, ProgramTimelineItem::STATUS_PAUSED])
                ->orderByDesc('starts_at_sec')
                ->value('starts_at_sec');

            $minFuture = $activeBoundary !== null ? ((int) $activeBoundary + 1) : $nowSec;
            if ($atSec < $minFuture) {
                throw new RuntimeException("Cannot insert in the past (min future sec={$minFuture}).");
            }

            $version = $this->bumpVersion($template, $dayOfMonth);

            // Locate the content item that contains $atSec (if any).
            $containing = ProgramTimelineItem::query()
                ->where('template_id', $template->id)
                ->where('day_of_month', $dayOfMonth)
                ->where('kind', ProgramTimelineItem::KIND_CONTENT)
                ->where('starts_at_sec', '<', $atSec)
                ->where('ends_at_sec', '>', $atSec)
                ->orderBy('starts_at_sec')
                ->first();

            $splitFrom = null;
            if ($containing) {
                $splitFrom = $this->splitContentAt($containing, $atSec, $version);
            }

            // Shift every future item by $cueDuration.
            $futureItems = ProgramTimelineItem::query()
                ->where('template_id', $template->id)
                ->where('day_of_month', $dayOfMonth)
                ->where('starts_at_sec', '>=', $atSec)
                ->orderBy('starts_at_sec')
                ->get();

            $shift = (int) round($cueDuration);
            foreach ($futureItems as $item) {
                $item->starts_at_sec = $item->starts_at_sec + $shift;
                $item->ends_at_sec = $item->ends_at_sec + $shift;
                $item->timeline_version = $version;
                $item->save();
            }

            // Insert the cue itself.
            $inserted = ProgramTimelineItem::create([
                'template_id' => $template->id,
                'block_id' => $futureItems->first()?->block_id,
                'day_of_month' => $dayOfMonth,
                'starts_at_sec' => $atSec,
                'ends_at_sec' => $atSec + $shift,
                'effective_duration_sec' => $cueDuration,
                'kind' => ProgramTimelineItem::KIND_CUE,
                'media_item_id' => $cue->id,
                'playlist_item_id' => null,
                'cue_in_sec' => null,
                'cue_out_sec' => null,
                'resume_offset_sec' => 0,
                'parent_content_id' => null,
                'status' => ProgramTimelineItem::STATUS_PLANNED,
                'is_interruptible' => false,
                'timeline_version' => $version,
            ]);

            return [
                'inserted' => $inserted,
                'split_from' => $splitFrom,
                'version' => $version,
            ];
        });
    }

    private function splitContentAt(ProgramTimelineItem $containing, int $atSec, int $version): ProgramTimelineItem
    {
        $originalEnd = (int) $containing->ends_at_sec;
        $containing->ends_at_sec = $atSec;
        $containing->effective_duration_sec = (float) max(0, $atSec - (int) $containing->starts_at_sec);
        $containing->timeline_version = $version;
        $containing->save();

        $tail = ProgramTimelineItem::create([
            'template_id' => $containing->template_id,
            'block_id' => $containing->block_id,
            'day_of_month' => $containing->day_of_month,
            'starts_at_sec' => $atSec,
            'ends_at_sec' => $originalEnd,
            'effective_duration_sec' => (float) max(0, $originalEnd - $atSec),
            'kind' => ProgramTimelineItem::KIND_CONTENT,
            'media_item_id' => $containing->media_item_id,
            'playlist_item_id' => $containing->playlist_item_id,
            'cue_in_sec' => $containing->cue_in_sec,
            'cue_out_sec' => $containing->cue_out_sec,
            'resume_offset_sec' => 0,
            'parent_content_id' => $containing->id,
            'status' => ProgramTimelineItem::STATUS_PLANNED,
            'is_interruptible' => $containing->is_interruptible,
            'timeline_version' => $version,
        ]);

        return $tail;
    }

    private function bumpVersion(ScheduleTemplate $template, int $dayOfMonth): int
    {
        $current = ProgramTimelineItem::query()
            ->where('template_id', $template->id)
            ->where('day_of_month', $dayOfMonth)
            ->max('timeline_version');

        $next = ((int) ($current ?? 0)) + 1;

        ProgramTimelineItem::query()
            ->where('template_id', $template->id)
            ->where('day_of_month', $dayOfMonth)
            ->update(['timeline_version' => $next]);

        return $next;
    }

    private function broadcastNowSec(): int
    {
        $now = Carbon::now();
        return ($now->hour * 3600) + ($now->minute * 60) + $now->second;
    }
}
