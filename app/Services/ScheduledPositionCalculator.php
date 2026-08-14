<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\ProgramTimelineItem;
use App\Models\ScheduleTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

class ScheduledPositionCalculator
{
    public function __construct(private readonly EmisionStartExceptionFactory $errors) {}

    /**
     * Calcula el item y offset (segundos dentro del item) que corresponde
     * reproducir ahora.
     *
     * Si existe un `program_timeline_items` (00:00-24:00) para el template y
     * día, se prefiere ese timeline por su `starts_at_sec`/`ends_at_sec`
     * explícitos. Si no, cae al modo legacy basado en suma de duraciones
     * desde 00:00.
     *
     * @param  Collection<int, PlaylistItem>  $items  Items ready ordenados por position (legacy fallback).
     * @return array{item: PlaylistItem, offset_sec: float}
     *
     * @throws RuntimeException Cuando no hay items o la duracion total es 0 con loop.
     */
    public function calculate(Playlist $playlist, Collection $items, CarbonImmutable $now): array
    {
        $timeline = $this->resolveTimeline($playlist, $now);
        if ($timeline !== null) {
            return $this->calculateFromTimeline($timeline, $now);
        }

        if ($items->isEmpty()) {
            throw new RuntimeException(
                'La playlist no tiene items validos (todos estan fallidos o sin media asociado).'
            );
        }

        $durations = $items->map(fn (PlaylistItem $item) => $item->effectiveDuration())->values();
        $total = (float) $durations->sum();

        $elapsed = (float) $now->secondsSinceMidnight();

        if ($playlist->loop) {
            if ($total <= 0.0) {
                throw new RuntimeException(
                    'La playlist tiene loop activo pero su duracion total es 0: no se puede calcular el punto de reanudacion.'
                );
            }
            $cursor = fmod($elapsed, $total);
        } else {
            $cursor = $elapsed;
        }

        $accum = 0.0;
        foreach ($items->values() as $index => $item) {
            $dur = (float) $durations[$index];
            if ($dur <= 0.0) {
                continue;
            }
            if ($accum + $dur > $cursor) {
                return [
                    'item' => $item,
                    'offset_sec' => max(0.0, $cursor - $accum),
                ];
            }
            $accum += $dur;
        }

        $last = $this->lastItemWithDuration($items, $durations);
        if ($last !== null) {
            return [
                'item' => $last['item'],
                'offset_sec' => $playlist->loop ? 0.0 : $last['duration'],
            ];
        }

        return [
            'item' => $items->first(),
            'offset_sec' => 0.0,
        ];
    }

    /**
     * Resolve a timeline for the playlist's channel/today, or null if none.
     */
    private function resolveTimeline(Playlist $playlist, CarbonImmutable $now): ?Collection
    {
        $template = ScheduleTemplate::query()
            ->where('channel_id', $playlist->channel_id)
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->first();

        if (! $template) {
            return null;
        }

        $rows = ProgramTimelineItem::query()
            ->where('template_id', $template->id)
            ->where('day_of_month', $now->day)
            ->whereNotNull('playlist_item_id')
            ->orderBy('starts_at_sec')
            ->get();

        return $rows->isEmpty() ? null : $rows;
    }

    /**
     * @param  Collection<int, ProgramTimelineItem>  $timeline
     * @return array{item: PlaylistItem, offset_sec: float}
     */
    private function calculateFromTimeline(Collection $timeline, CarbonImmutable $now): array
    {
        $elapsed = (float) $now->secondsSinceMidnight();

        foreach ($timeline as $row) {
            if ($elapsed < (float) $row->starts_at_sec) {
                continue;
            }
            if ($elapsed < (float) $row->ends_at_sec) {
                $pi = $row->playlistItem;
                if (! $pi) {
                    continue;
                }
                $offset = $elapsed - (float) $row->starts_at_sec;
                return [
                    'item' => $pi,
                    'offset_sec' => max(0.0, $offset),
                ];
            }
        }

        // elapsed is past the last timeline item: fall back to its final
        // offset.
        $last = $timeline->last();
        if ($last && $last->playlistItem) {
            $final = (float) ($last->effective_duration_sec ?? max(0.0, $last->ends_at_sec - $last->starts_at_sec));
            return [
                'item' => $last->playlistItem,
                'offset_sec' => $final,
            ];
        }

        throw new RuntimeException('Timeline resolved but contains no usable playlist_item rows.');
    }

    /**
     * @param  Collection<int, PlaylistItem>  $items
     * @param  Collection<int, float>  $durations
     * @return array{item: PlaylistItem, duration: float}|null
     */
    private function lastItemWithDuration(Collection $items, Collection $durations): ?array
    {
        for ($i = $items->count() - 1; $i >= 0; $i--) {
            $dur = (float) $durations[$i];
            if ($dur > 0.0) {
                return [
                    'item' => $items->values()[$i],
                    'duration' => $dur,
                ];
            }
        }

        return null;
    }
}
