<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restream\StoreRestreamTargetScheduleRequest;
use App\Http\Requests\Restream\UpdateRestreamTargetScheduleRequest;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\RestreamTarget;
use App\Models\RestreamTargetSchedule;
use App\Services\Restream\TargetSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-target schedule CRUD. Both clients and admins hit this controller;
 * ownership is enforced via channel access.
 */
class RestreamTargetScheduleController extends Controller
{
    public function __construct(
        private readonly TargetSchedulerService $scheduler,
    ) {
    }

    private function authorize(Request $request, RestreamTarget $target): void
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(
            $user->isAdmin() || $target->user_id === $user->id,
            403,
            'No tienes acceso a este destino restream.'
        );
    }

    public function index(Request $request, RestreamTarget $target): JsonResponse
    {
        $this->authorize($request, $target);

        $status = $request->query('status');
        $query = RestreamTargetSchedule::forTarget($target->id);
        if (in_array($status, ['pending', 'running', 'ended', 'disabled'], true)) {
            $query->getQuery()->whereRaw('1=1'); // no-op; status is computed
        }
        $rows = $query->orderBy('starts_at', 'desc')->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => $this->serialize($r))->all(),
            'meta' => ['total' => $rows->count()],
        ]);
    }

    public function show(Request $request, RestreamTarget $target, string $schedule): JsonResponse
    {
        $this->authorize($request, $target);
        $row = RestreamTargetSchedule::forTarget($target->id)->where('id', $schedule)->firstOrFail();

        return response()->json(['data' => $this->serialize($row)]);
    }

    public function store(StoreRestreamTargetScheduleRequest $request, RestreamTarget $target): JsonResponse
    {
        $this->authorize($request, $target);
        $data = $request->validated();
        $data['target_id'] = $target->id;

        $row = RestreamTargetSchedule::create($data);
        $this->audit($request, $target, 'create.restream_target_schedule', null, $row->toArray(), entityId: $row->id);

        return response()->json(['data' => $this->serialize($row)], 201);
    }

    public function update(UpdateRestreamTargetScheduleRequest $request, RestreamTarget $target, string $schedule): JsonResponse
    {
        $this->authorize($request, $target);
        $row = RestreamTargetSchedule::forTarget($target->id)->where('id', $schedule)->firstOrFail();
        $before = $row->toArray();
        $row->fill($request->validated())->save();
        $this->audit($request, $target, 'update.restream_target_schedule', $before, $row->toArray());

        return response()->json(['data' => $this->serialize($row)]);
    }

    public function destroy(Request $request, RestreamTarget $target, string $schedule): JsonResponse
    {
        $this->authorize($request, $target);
        $row = RestreamTargetSchedule::forTarget($target->id)->where('id', $schedule)->firstOrFail();
        $before = $row->toArray();
        $row->delete();
        $this->audit($request, $target, 'delete.restream_target_schedule', $before, null);

        return response()->json(null, 204);
    }

    public function triggerNow(Request $request, RestreamTarget $target, string $schedule): JsonResponse
    {
        $this->authorize($request, $target);
        $row = RestreamTargetSchedule::forTarget($target->id)->where('id', $schedule)->firstOrFail();
        $row->forceFill(['stopped_by_user' => false])->save();
        $this->scheduler->startTargetForWindow($row, $request->user()->isAdmin() ? 'admin' : 'manual');

        return response()->json(['data' => $this->serialize($row->fresh())]);
    }

    public function stopNow(Request $request, RestreamTarget $target, string $schedule): JsonResponse
    {
        $this->authorize($request, $target);
        $row = RestreamTargetSchedule::forTarget($target->id)->where('id', $schedule)->firstOrFail();
        $this->scheduler->stopTargetAfterWindow($row, $request->user()->isAdmin() ? 'admin' : 'manual', userInitiated: true);

        return response()->json(['data' => $this->serialize($row->fresh())]);
    }

    private function serialize(RestreamTargetSchedule $row): array
    {
        $thumb = $row->thumbnail_path;
        if (! $thumb && $row->thumbnail_media_id) {
            $media = \App\Models\MediaItem::find($row->thumbnail_media_id);
            if ($media) {
                $thumb = $media->thumbnail_path ?: (\Illuminate\Support\Facades\Storage::disk(config('cloudstream.media.thumb_disk', 'public'))->url('media/thumbs/'.$media->id.'.jpg'));
            }
        }

        return [
            'id' => $row->id,
            'target_id' => $row->target_id,
            'name' => $row->name,
            'starts_at' => optional($row->starts_at)->toIso8601String(),
            'ends_at' => optional($row->ends_at)->toIso8601String(),
            'timezone' => $row->timezone,
            'notes' => $row->notes,
            'thumbnail_path' => $row->thumbnail_path,
            'thumbnail_media_id' => $row->thumbnail_media_id,
            'thumbnail_url' => $thumb,
            'schedule_title' => $row->schedule_title,
            'schedule_description' => $row->schedule_description,
            'platform_privacy' => $row->platform_privacy,
            'keep_recording' => $row->keep_recording,
            'enabled' => (bool) $row->enabled,
            'stopped_by_user' => (bool) $row->stopped_by_user,
            'status' => $row->status,
            'is_indefinite' => $row->is_indefinite,
            'duration_seconds' => $row->duration_seconds,
            'local_starts_at' => optional($row->local_starts_at)->toIso8601String(),
            'local_ends_at' => optional($row->local_ends_at)->toIso8601String(),
            'created_at' => optional($row->created_at)->toIso8601String(),
            'updated_at' => optional($row->updated_at)->toIso8601String(),
        ];
    }

    private function audit(Request $request, RestreamTarget $target, string $action, ?array $before, ?array $after, ?string $entityId = null): void
    {
        AuditLog::record(
            action: $action,
            entityType: 'restream_target_schedule',
            entityId: $entityId ?? (string) $request->route('schedule'),
            before: $before,
            after: $after,
            channelId: $target->channel_id,
        );
    }
}
