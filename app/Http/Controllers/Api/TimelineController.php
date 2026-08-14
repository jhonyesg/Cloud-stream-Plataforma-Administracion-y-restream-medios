<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MediaItem;
use App\Models\ProgramTimelineItem;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use App\Services\TimelineBuilder;
use App\Services\PlaylistCueInserter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TimelineController extends Controller
{
    public function __construct(
        private readonly TimelineBuilder $builder,
        private readonly PlaylistCueInserter $cueInserter,
    ) {}

    /**
     * GET /api/schedule-templates/{template}/days/{day}/timeline
     */
    public function show(Request $request, ScheduleTemplate $scheduleTemplate, int $day): JsonResponse
    {
        if ($day < 1 || $day > 31) {
            return response()->json(['message' => 'Día inválido.'], 422);
        }

        $items = ProgramTimelineItem::forDay($scheduleTemplate->id, $day)
            ->with('mediaItem')
            ->get()
            ->map(fn (ProgramTimelineItem $it) => [
                'id' => $it->id,
                'kind' => $it->kind,
                'status' => $it->status,
                'starts_at_sec' => (int) $it->starts_at_sec,
                'ends_at_sec' => (int) $it->ends_at_sec,
                'effective_duration_sec' => (float) $it->effective_duration_sec,
                'media_item_id' => $it->media_item_id,
                'filename' => $it->mediaItem?->filename,
                'cue_in_sec' => $it->cue_in_sec !== null ? (float) $it->cue_in_sec : null,
                'cue_out_sec' => $it->cue_out_sec !== null ? (float) $it->cue_out_sec : null,
                'resume_offset_sec' => (float) $it->resume_offset_sec,
                'parent_content_id' => $it->parent_content_id,
                'is_interruptible' => (bool) $it->is_interruptible,
                'timeline_version' => (int) $it->timeline_version,
            ])
            ->values();

        $last = $items->last();
        $overflowSec = $last ? max(0, (int) ($last['ends_at_sec'] ?? 0) - 86_400) : 0;

        return response()->json([
            'template_id' => $scheduleTemplate->id,
            'day_of_month' => $day,
            'broadcast_clock_ref' => now()->toIso8601String(),
            'overflow_sec' => $overflowSec,
            'timeline_policy' => ScheduleBlock::where('template_id', $scheduleTemplate->id)
                ->where('day_of_month', $day)
                ->value('timeline_policy') ?? 'shift',
            'items' => $items,
        ]);
    }

    /**
     * POST /api/schedule-templates/{template}/days/{day}/timeline/insert-cue
     */
    public function insertCue(Request $request, ScheduleTemplate $scheduleTemplate, int $day): JsonResponse
    {
        if ($day < 1 || $day > 31) {
            return response()->json(['message' => 'Día inválido.'], 422);
        }

        if (! $request->user()->canAccessChannel($scheduleTemplate->channel)) {
            abort(403, 'No tienes acceso a este canal.');
        }

        $data = $request->validate([
            'at_sec' => ['required', 'integer', 'min:0', 'max:86400'],
            'media_item_id' => ['required', 'uuid'],
        ]);

        $cue = MediaItem::find($data['media_item_id']);
        if (! $cue) {
            return response()->json(['message' => 'media_item no encontrado.'], 404);
        }
        if ($cue->kind !== 'ad') {
            return response()->json(['message' => 'Solo se permiten media_items de kind=ad como cuña.'], 422);
        }
        if ($cue->status !== 'ready') {
            return response()->json(['message' => 'El media_item no está listo (status='.$cue->status.').'], 422);
        }

        $nowSec = (int) now()->secondsSinceMidnight();
        $minFuture = $nowSec;
        if ((int) $data['at_sec'] < $minFuture) {
            return response()->json(['message' => "Solo se puede insertar en el futuro (min sec={$minFuture})."], 422);
        }

        $block = ScheduleBlock::query()
            ->where('template_id', $scheduleTemplate->id)
            ->where('day_of_month', $day)
            ->with('playlist')
            ->first();
        if (! $block?->playlist) {
            return response()->json(['message' => 'No hay playlist asignada para este día.'], 422);
        }

        $before = ProgramTimelineItem::forDay($scheduleTemplate->id, $day)->get()->toArray();

        try {
            $result = DB::transaction(function () use ($scheduleTemplate, $day, $data, $cue, $block) {
                $playlistResult = $this->cueInserter->insertAt(
                    $block->playlist,
                    (int) $data['at_sec'],
                    $cue,
                );

                $timeline = $this->builder->buildForDay($scheduleTemplate, $day);
                $inserted = $timeline['items']->firstWhere('playlist_item_id', $playlistResult['inserted']->id);
                $split = $playlistResult['split_from']
                    ? $timeline['items']->firstWhere('playlist_item_id', $playlistResult['split_from']->id)
                    : null;

                return [
                    'inserted' => $inserted,
                    'split_from' => $split,
                    'version' => $timeline['version'],
                ];
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $after = ProgramTimelineItem::forDay($scheduleTemplate->id, $day)->get()->toArray();
        $this->audit('timeline.insert_cue', $scheduleTemplate, $day, [
            'at_sec' => $data['at_sec'],
            'cue_media_item_id' => $cue->id,
            'timeline_version' => $result['version'],
            'inserted_timeline_item_id' => $result['inserted']?->id,
            'before' => $before,
            'after' => $after,
        ]);

        // Notify control plane (best effort).
        $this->notifyControlPlane($scheduleTemplate, $day, 'cue_inserted');

        return response()->json([
            'inserted_timeline_item_id' => $result['inserted']->id,
            'split_from_timeline_item_id' => $result['split_from']?->id,
            'timeline_version' => $result['version'],
        ], 201);
    }

    /**
     * DELETE /api/schedule-templates/{template}/days/{day}/timeline/{timelineItemId}
     */
    public function destroy(Request $request, ScheduleTemplate $scheduleTemplate, int $day, string $timelineItemId): JsonResponse
    {
        if ($day < 1 || $day > 31) {
            return response()->json(['message' => 'Día inválido.'], 422);
        }

        $row = ProgramTimelineItem::query()
            ->where('template_id', $scheduleTemplate->id)
            ->where('day_of_month', $day)
            ->where('id', $timelineItemId)
            ->first();
        if (! $row) {
            return response()->json(['message' => 'Item no encontrado en este día.'], 404);
        }

        $nowSec = (int) (now()->hour * 3600 + now()->minute * 60 + now()->second);
        $activeBoundary = ProgramTimelineItem::query()
            ->where('template_id', $scheduleTemplate->id)
            ->where('day_of_month', $day)
            ->whereIn('status', [ProgramTimelineItem::STATUS_PLAYING, ProgramTimelineItem::STATUS_QUEUED, ProgramTimelineItem::STATUS_PAUSED])
            ->orderByDesc('starts_at_sec')
            ->value('starts_at_sec');
        $minFuture = $activeBoundary !== null ? ((int) $activeBoundary + 1) : $nowSec;
        if ((int) $row->starts_at_sec < $minFuture) {
            return response()->json(['message' => "Solo se puede borrar el futuro (min sec={$minFuture})."], 422);
        }

        $removedSec = (int) $row->effective_duration_sec;
        $shift = -$removedSec;

        DB::transaction(function () use ($row, $shift, $scheduleTemplate, $day, $removedSec) {
            $row->delete();

            ProgramTimelineItem::query()
                ->where('template_id', $scheduleTemplate->id)
                ->where('day_of_month', $day)
                ->where('starts_at_sec', '>', $row->starts_at_sec)
                ->orderBy('starts_at_sec')
                ->each(function (ProgramTimelineItem $it) use ($shift) {
                    $it->starts_at_sec = max(0, (int) $it->starts_at_sec + $shift);
                    $it->ends_at_sec = max(0, (int) $it->ends_at_sec + $shift);
                    $it->timeline_version = (int) $it->timeline_version + 1;
                    $it->save();
                });

            $this->audit('timeline.delete', $scheduleTemplate, $day, [
                'timeline_item_id' => $row->id,
                'shifted_sec' => $shift,
            ]);
        });

        $this->notifyControlPlane($scheduleTemplate, $day, 'item_deleted');

        return response()->json(['deleted' => true]);
    }

    /**
     * POST /api/schedule-templates/{template}/days/{day}/timeline/rebuild
     */
    public function rebuild(Request $request, ScheduleTemplate $scheduleTemplate, int $day): JsonResponse
    {
        if ($day < 1 || $day > 31) {
            return response()->json(['message' => 'Día inválido.'], 422);
        }

        $data = $request->validate([
            'confirm' => ['sometimes', 'boolean'],
        ]);

        if (! ($data['confirm'] ?? false)) {
            return response()->json([
                'message' => 'Confirma con confirm=true. Solo permitido si el canal está offline.',
            ], 422);
        }

        $result = $this->builder->buildForDay($scheduleTemplate, $day);

        $this->audit('timeline.rebuild', $scheduleTemplate, $day, [
            'items' => $result['items']->count(),
            'overflow_sec' => $result['overflow_sec'],
            'version' => $result['version'],
        ]);

        return response()->json([
            'rebuilt' => true,
            'items' => $result['items']->count(),
            'overflow_sec' => $result['overflow_sec'],
            'timeline_version' => $result['version'],
        ]);
    }

    private function audit(string $action, ScheduleTemplate $template, int $day, array $context): void
    {
        try {
            $before = $context['before'] ?? null;
            $rows = $context['after'] ?? null;
            $metadata = \Illuminate\Support\Arr::except($context, ['before', 'after']);
            $after = array_merge($metadata, ['day_of_month' => $day], $rows ?? []);
            AuditLog::record(
                $action,
                ScheduleTemplate::class,
                $template->id,
                $before,
                $after,
                $template->channel_id,
            );
        } catch (\Throwable $e) {
            Log::warning('audit log insert failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyControlPlane(ScheduleTemplate $template, int $day, string $event): void
    {
        $channelId = $template->channel_id;
        $registrationPath = storage_path("app/emission-daemons/{$channelId}.json");
        if (! is_file($registrationPath)) {
            return;
        }

        try {
            $registration = json_decode(file_get_contents($registrationPath), true);
            if (! is_array($registration) || empty($registration['port'])) {
                return;
            }
            (new \GuzzleHttp\Client(['timeout' => 2, 'connect_timeout' => 1]))
                ->post("http://127.0.0.1:{$registration['port']}/reload-timeline");
        } catch (\Throwable $e) {
            Log::warning('Timeline control-plane notification failed', [
                'channel_id' => $channelId,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

function CarbonNow(): string
{
    return \Carbon\CarbonImmutable::now()->toIso8601String();
}
