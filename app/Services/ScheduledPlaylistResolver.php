<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\ProgramTimelineItem;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ScheduledPlaylistResolver
{
    public function __construct(private readonly EmisionStartExceptionFactory $errors) {}

    /**
     * Resuelve la playlist que debe reproducirse HOY en el canal dado.
     *
     * Ademas, devuelve el `program_timeline_items` del dia cuando existe,
     * ordenado por `starts_at_sec`, para que los consumidores puedan
     * preferir la timeline 00:00-24:00 sobre el calculo por suma de
     * duraciones.
     *
     * @return array{playlist: Playlist, items: PlaylistItem[], template: ScheduleTemplate, block: ScheduleBlock, day_of_month: int, timelineItems: Collection<int, ProgramTimelineItem>}
     */
    public function resolveForNow(Channel $channel): array
    {
        $now = CarbonImmutable::now();
        $template = $this->resolveActiveTemplate($channel, $now->year, $now->month);
        if (! $template) {
            throw $this->errors->noActiveTemplate($channel, $now->year, $now->month);
        }

        $block = $this->resolveBlockForDay($template, $now->day);
        if (! $block) {
            throw $this->errors->noBlockForDay($channel, $template, $now->day);
        }

        $playlist = $block->playlist;
        if (! $playlist) {
            throw $this->errors->noPlaylistOnBlock($channel, $block);
        }

        $items = $playlist->items()
            ->with('mediaItem')
            ->orderBy('position')
            ->get()
            ->filter(fn ($item) => $item->mediaItem !== null && $item->mediaItem->status === 'ready')
            ->values();

        if ($items->isEmpty()) {
            throw $this->errors->noReadyItems($channel, $playlist);
        }

        $timelineItems = ProgramTimelineItem::forDay($template->id, $now->day)
            ->with('mediaItem')
            ->get()
            ->filter(fn (ProgramTimelineItem $row) => $row->mediaItem !== null && $row->mediaItem->status === 'ready')
            ->values();

        return [
            'playlist' => $playlist,
            'items' => $items,
            'template' => $template,
            'block' => $block,
            'day_of_month' => $now->day,
            'timelineItems' => $timelineItems,
        ];
    }

    private function resolveActiveTemplate(Channel $channel, int $year, int $month): ?ScheduleTemplate
    {
        return ScheduleTemplate::query()
            ->where('channel_id', $channel->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function resolveBlockForDay(ScheduleTemplate $template, int $day): ?ScheduleBlock
    {
        return ScheduleBlock::query()
            ->where('template_id', $template->id)
            ->where('day_of_month', $day)
            ->first();
    }
}
