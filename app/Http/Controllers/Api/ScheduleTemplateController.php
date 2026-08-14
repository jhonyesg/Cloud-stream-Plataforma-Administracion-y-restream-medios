<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Playlist;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ScheduleTemplate::with('channel:id,slug,display_name');

        if ($user->isClient()) {
            $query->whereHas('channel', fn ($q) => $q->where('owner_id', $user->effectiveOwnerId()));
        }

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->string('channel_id'));
        }
        if ($request->filled('year')) {
            $query->where('year', (int) $request->input('year'));
        }
        if ($request->filled('month')) {
            $query->where('month', (int) $request->input('month'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $perPage = min((int) $request->input('per_page', 50), 200);

        return response()->json($query->orderByDesc('year')->orderByDesc('month')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'year' => ['required', 'integer', 'min:2000', 'max:2999'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'status' => ['in:draft,active,archived'],
        ]);
        $data['status'] = $data['status'] ?? 'draft';

        $existing = ScheduleTemplate::where('channel_id', $data['channel_id'])
            ->where('year', $data['year'])
            ->where('month', $data['month'])
            ->where('status', $data['status'])
            ->first();

        if ($existing) {
            $existing->update(['name' => $data['name']]);

            return response()->json($existing, 200);
        }

        $template = ScheduleTemplate::create($data);
        AuditLog::record('create.schedule_template', 'schedule_template', $template->id, null, $template->toArray(), $template->channel_id);

        return response()->json($template, 201);
    }

    public function show(ScheduleTemplate $scheduleTemplate): JsonResponse
    {
        $scheduleTemplate->load(['channel:id,slug,display_name', 'blocks.playlist']);

        return response()->json($scheduleTemplate);
    }

    public function update(Request $request, ScheduleTemplate $scheduleTemplate): JsonResponse
    {
        $before = $scheduleTemplate->toArray();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'in:draft,active,archived'],
        ]);

        if (($data['status'] ?? null) === 'active') {
            $this->validateActivation($scheduleTemplate);
        }

        if (($data['status'] ?? null) === 'active' && $scheduleTemplate->status !== 'active') {
            DB::transaction(function () use ($scheduleTemplate, $data) {
                ScheduleTemplate::where('channel_id', $scheduleTemplate->channel_id)
                    ->where('year', $scheduleTemplate->year)
                    ->where('month', $scheduleTemplate->month)
                    ->where('status', 'active')
                    ->where('id', '!=', $scheduleTemplate->id)
                    ->update(['status' => 'archived']);
                $scheduleTemplate->update($data);
            });
        } else {
            $scheduleTemplate->update($data);
        }

        AuditLog::record('update.schedule_template', 'schedule_template', $scheduleTemplate->id, $before, $scheduleTemplate->toArray(), $scheduleTemplate->channel_id);

        return response()->json($scheduleTemplate);
    }

    public function destroy(ScheduleTemplate $scheduleTemplate): JsonResponse
    {
        if ($scheduleTemplate->status === 'active') {
            return response()->json(['message' => 'No se puede eliminar una plantilla activa. Archívala primero.'], 422);
        }
        $scheduleTemplate->delete();

        return response()->json(['deleted' => true]);
    }

    public function clone(Request $request, ScheduleTemplate $scheduleTemplate): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2999'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $clone = $scheduleTemplate->cloneTo($data['year'], $data['month']);
        AuditLog::record('clone.schedule_template', 'schedule_template', $clone->id, null, $clone->toArray(), $clone->channel_id);

        return response()->json($clone, 201);
    }

    public function clonePreview(Request $request, ScheduleTemplate $scheduleTemplate): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2999'],
            'months' => ['required', 'array', 'min:1'],
            'months.*' => ['integer', 'min:1', 'max:12'],
        ]);

        $existing = ScheduleTemplate::where('channel_id', $scheduleTemplate->channel_id)
            ->where('year', $data['year'])
            ->whereIn('month', $data['months'])
            ->get();

        $conflicts = $existing->map(function ($t) {
            return [
                'month' => $t->month,
                'status' => $t->status,
                'blocks_count' => $t->blocks()->count(),
                'template_id' => $t->id,
            ];
        })->values();

        return response()->json(['conflicts' => $conflicts]);
    }

    public function assignDay(Request $request, ScheduleTemplate $scheduleTemplate, int $day): JsonResponse
    {
        if ($day < 1 || $day > 31) {
            return response()->json(['message' => 'Día inválido.'], 422);
        }

        $data = $request->validate([
            'playlist_id' => ['nullable', 'uuid'],
            'timeline_policy' => ['sometimes', 'string', 'in:shift,rigid'],
            'is_interruptible' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['playlist_id'])) {
            $pl = Playlist::find($data['playlist_id']);
            if (! $pl || $pl->channel_id !== $scheduleTemplate->channel_id) {
                return response()->json(['message' => 'playlist_id no pertenece al canal.'], 422);
            }
        }

        $playlistId = $data['playlist_id'] ?? null;

        if ($playlistId === null) {
            ScheduleBlock::where('template_id', $scheduleTemplate->id)
                ->where('day_of_month', $day)
                ->delete();

            return response()->json(['cleared' => true, 'day_of_month' => $day]);
        }

        $attrs = ['playlist_id' => $playlistId];
        if (array_key_exists('timeline_policy', $data)) {
            $attrs['timeline_policy'] = $data['timeline_policy'];
        }
        if (array_key_exists('is_interruptible', $data)) {
            $attrs['is_interruptible'] = (bool) $data['is_interruptible'];
        }

        $block = ScheduleBlock::updateOrCreate(
            ['template_id' => $scheduleTemplate->id, 'day_of_month' => $day],
            $attrs
        );

        $this->autoActivateIfNeeded($scheduleTemplate);

        // Regenerate timeline so the new playlist policy is reflected.
        $builder = app(\App\Services\TimelineBuilder::class);
        $builder->buildForDay($scheduleTemplate, $day);

        return response()->json($block);
    }

    public function replicate(Request $request, ScheduleTemplate $scheduleTemplate): JsonResponse
    {
        $data = $request->validate([
            'source_day' => ['required', 'integer', 'min:1', 'max:31'],
            'target_days' => ['required', 'array', 'min:1'],
            'target_days.*' => ['integer', 'min:1', 'max:31'],
        ]);

        $sourceBlock = $scheduleTemplate->blocks()
            ->where('day_of_month', $data['source_day'])
            ->first();

        if (! $sourceBlock) {
            return response()->json(['message' => 'No hay playlist en el día origen.'], 422);
        }

        $updated = 0;
        DB::transaction(function () use ($scheduleTemplate, $sourceBlock, $data, &$updated) {
            foreach ($data['target_days'] as $targetDay) {
                if ($targetDay == $data['source_day']) {
                    continue;
                }

                ScheduleBlock::updateOrCreate(
                    ['template_id' => $scheduleTemplate->id, 'day_of_month' => $targetDay],
                    ['playlist_id' => $sourceBlock->playlist_id]
                );
                $updated++;
            }
        });

        $this->autoActivateIfNeeded($scheduleTemplate);

        return response()->json(['replicated' => $updated, 'source_day' => $data['source_day']]);
    }

    public function replicateRange(Request $request, ScheduleTemplate $scheduleTemplate): JsonResponse
    {
        $data = $request->validate([
            'source_day' => ['required', 'integer', 'min:1', 'max:31'],
            'target_days' => ['required', 'array', 'min:1'],
            'target_days.*' => ['integer', 'min:1', 'max:31'],
        ]);

        $sourceBlock = $scheduleTemplate->blocks()
            ->where('day_of_month', $data['source_day'])
            ->first();

        if (! $sourceBlock) {
            return response()->json(['message' => 'No hay playlist en el día origen.'], 422);
        }

        $updated = 0;
        DB::transaction(function () use ($scheduleTemplate, $sourceBlock, $data, &$updated) {
            foreach ($data['target_days'] as $targetDay) {
                if ($targetDay == $data['source_day']) {
                    continue;
                }

                ScheduleBlock::updateOrCreate(
                    ['template_id' => $scheduleTemplate->id, 'day_of_month' => $targetDay],
                    ['playlist_id' => $sourceBlock->playlist_id]
                );
                $updated++;
            }
        });

        return response()->json(['replicated' => $updated]);
    }

    private function validateActivation(ScheduleTemplate $t): void
    {
        if ($t->blocks()->count() === 0) {
            abort(422, 'La plantilla no tiene bloques asignados.');
        }
    }

    private function autoActivateIfNeeded(ScheduleTemplate $scheduleTemplate): void
    {
        if ($scheduleTemplate->status !== 'draft') {
            return;
        }

        $before = $scheduleTemplate->only(['status']);

        DB::transaction(function () use ($scheduleTemplate) {
            $archivedIds = ScheduleTemplate::where('channel_id', $scheduleTemplate->channel_id)
                ->where('year', $scheduleTemplate->year)
                ->where('month', $scheduleTemplate->month)
                ->where('status', 'active')
                ->where('id', '!=', $scheduleTemplate->id)
                ->pluck('id');

            if ($archivedIds->isNotEmpty()) {
                ScheduleTemplate::whereIn('id', $archivedIds)->update(['status' => 'archived']);
                foreach ($archivedIds as $tplId) {
                    AuditLog::record(
                        'update.schedule_template.auto_archive_on_promote',
                        'schedule_template',
                        $tplId,
                        ['status' => 'active'],
                        ['status' => 'archived'],
                        $scheduleTemplate->channel_id
                    );
                }
            }

            $scheduleTemplate->update(['status' => 'active']);
        });

        AuditLog::record(
            'update.schedule_template.auto_activate',
            'schedule_template',
            $scheduleTemplate->id,
            $before,
            ['status' => 'active'],
            $scheduleTemplate->channel_id
        );
    }
}
