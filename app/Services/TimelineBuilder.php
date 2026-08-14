<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\ProgramTimelineItem;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Builds a 00:00-24:00 timeline for a (template, day) pair from the
 * `ScheduleBlock -> playlist_id -> PlaylistItems` chain.
 *
 * Items are emitted sequentially starting at 00:00. Duplicate
 * `media_item_id` values are allowed (e.g. a movie split across two
 * timeline entries around a cue); each becomes its own timeline row.
 *
 * Cue fields from `PlaylistItem` are copied onto the timeline row
 * (`cue_in_sec` / `cue_out_sec`).
 *
 * Returns the persisted timeline rows and a summary containing
 * `overflow_sec` (positive if items spill past 86_400s).
 */
class TimelineBuilder
{
    public const DAY_SECONDS = 86_400;

    public function __construct(
        private readonly PlaylistDurationService $durations,
        private readonly TimelineIntervalValidator $intervals,
    )
    {
    }

    /**
     * @return array{items: \Illuminate\Support\Collection<int, ProgramTimelineItem>, overflow_sec: int, version: int}
     */
    public function buildForDay(ScheduleTemplate $template, int $dayOfMonth): array
    {
        return DB::transaction(function () use ($template, $dayOfMonth) {
            $block = ScheduleBlock::query()
                ->where('template_id', $template->id)
                ->where('day_of_month', $dayOfMonth)
                ->first();

            if (! $block) {
                return ['items' => collect(), 'overflow_sec' => 0, 'version' => $this->nextVersion($template, $dayOfMonth)];
            }

            $version = $this->nextVersion($template, $dayOfMonth);

            // Upsert by natural key (template_id, day_of_month, playlist_item_id)
            // so existing row UUIDs are preserved across rebuilds. Recreating
            // rows with fresh UUIDs invalidated emission_state.current_timeline_item_id
            // whenever the supervisor rebuilt the timeline mid-flight (FK violation).
            $existing = ProgramTimelineItem::query()
                ->where('template_id', $template->id)
                ->where('day_of_month', $dayOfMonth)
                ->get()
                ->keyBy('playlist_item_id');

            $items = collect();
            $rowsByPlaylistItem = [];
            if (! $block->playlist_id) {
                // No playlist — drop everything for this day/template.
                $existing->each(fn (ProgramTimelineItem $item) => $item->delete());
                return ['items' => $items, 'overflow_sec' => 0, 'version' => $version];
            }

            $playlist = Playlist::with(['items' => fn ($q) => $q->orderBy('position')])
                ->findOrFail($block->playlist_id);

            $cursor = 0;
            $seenPlaylistItemIds = [];
            foreach ($playlist->items as $pi) {
                $this->durations->assertValid($pi);
                $duration = $this->durations->effectiveDuration($pi);

                $starts = $cursor;
                $ends = $cursor + (int) round($duration);
                $seenPlaylistItemIds[] = $pi->id;

                $parentRow = $pi->split_from_id
                    ? ($rowsByPlaylistItem[$pi->split_from_id] ?? null)
                    : null;

                $attrs = [
                    'template_id' => $template->id,
                    'block_id' => $block->id,
                    'day_of_month' => $dayOfMonth,
                    'starts_at_sec' => $starts,
                    'ends_at_sec' => $ends,
                    'effective_duration_sec' => $duration,
                    'kind' => $this->kindForPlaylistItem($pi),
                    'media_item_id' => $pi->media_item_id,
                    'cue_in_sec' => $pi->cue_in_sec,
                    'cue_out_sec' => $pi->cue_out_sec,
                    'resume_offset_sec' => 0,
                    'parent_content_id' => $parentRow?->id,
                    'status' => ProgramTimelineItem::STATUS_PLANNED,
                    'is_interruptible' => true,
                    'timeline_version' => $version,
                ];

                /** @var ProgramTimelineItem|null $row */
                $row = $existing->get($pi->id);
                if ($row) {
                    // Update in place — keeps the row's UUID stable so any
                    // emission_state foreign key reference stays valid.
                    $row->fill($attrs);
                    $row->save();
                    $row->refresh();
                } else {
                    $row = ProgramTimelineItem::create(array_merge([
                        'playlist_item_id' => $pi->id,
                    ], $attrs));
                }

                $items->push($row);
                $rowsByPlaylistItem[$pi->id] = $row;
                $cursor = $ends;
            }

            $this->intervals->assertSequential($items->map(fn (ProgramTimelineItem $item) => [
                'starts_at_sec' => $item->starts_at_sec,
                'ends_at_sec' => $item->ends_at_sec,
                'kind' => $item->kind,
            ]));

            // Delete rows whose playlist_item_id is no longer part of the
            // playlist (removed/shortened items). Never touch rows still referenced.
            $existing->each(function (ProgramTimelineItem $item) use ($seenPlaylistItemIds) {
                if (! in_array($item->playlist_item_id, $seenPlaylistItemIds, true)) {
                    $item->delete();
                }
            });

            $overflow = max(0, $cursor - self::DAY_SECONDS);

            return [
                'items' => $items,
                'overflow_sec' => $overflow,
                'version' => $version,
            ];
        });
    }

    private function kindForPlaylistItem(PlaylistItem $pi): string
    {
        $mediaKind = $pi->mediaItem?->kind;
        if ($mediaKind === 'ad') {
            return ProgramTimelineItem::KIND_CUE;
        }
        return ProgramTimelineItem::KIND_CONTENT;
    }

    private function nextVersion(ScheduleTemplate $template, int $dayOfMonth): int
    {
        $current = ProgramTimelineItem::query()
            ->where('template_id', $template->id)
            ->where('day_of_month', $dayOfMonth)
            ->max('timeline_version');

        return ((int) ($current ?? 0)) + 1;
    }
}
