<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\RestreamPlatformAccount;
use App\Models\RestreamTarget;
use App\Services\Restream\Platform\FacebookBroadcastService;
use App\Services\Restream\Platform\RestreamPlatformAccountException;
use App\Services\Restream\Platform\YoutubeBroadcastService;
use App\Services\Restream\RestreamOrchestrator;
use App\Services\Restream\RestreamQuotaException;
use App\Services\Restream\RestreamQuotaGuard;
use App\Services\Restream\RestreamTargetJsonPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestreamTargetController extends Controller
{
    public function __construct(
        private readonly RestreamQuotaGuard $guard,
        private readonly RestreamOrchestrator $orchestrator,
        private readonly YoutubeBroadcastService $youtube,
        private readonly FacebookBroadcastService $facebook,
        private readonly RestreamTargetJsonPresenter $presenter,
    ) {
    }

    public function index(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');

        $targets = RestreamTarget::where('user_id', $user->id)
            ->where('channel_id', $channel->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'channel' => ['id' => $channel->id, 'name' => $channel->display_name],
            'targets' => $targets->map(fn ($t) => $this->presenter->present($t))->values(),
            'max_outputs' => $user->restreamMaxOutputsFor($channel->id),
            'used_outputs' => $targets->where('enabled', true)->count(),
            'remaining_slots' => $user->restreamRemainingSlotsFor($channel->id),
        ]);
    }

    public function store(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');

        $usesPlatformAccount = $request->filled('platform_account_id');

$data = $request->validate([
            'platform' => ['required', 'string', 'in:facebook,tiktok,youtube,custom'],
            'name' => ['required', 'string', 'max:80'],
            'destination_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:/^rtmps?:\/\/.+/i'],
            'stream_key' => ['sometimes', 'nullable', 'string', 'min:2', 'max:500'],
            'enabled' => ['sometimes', 'boolean'],
            'platform_account_id' => ['sometimes', 'nullable', 'uuid'],
            'platform_privacy' => ['sometimes', 'nullable', 'in:public,unlisted,private'],
            'keep_recording' => ['sometimes', 'nullable', 'boolean'],
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'thumbnail_media_id' => ['sometimes', 'nullable', 'uuid', 'exists:media_items,id'],
            'thumbnail' => ['sometimes', 'nullable', 'image', 'max:5120'],
            // NOTE: scheduled_start_at / scheduled_stop_at were removed in the
            // 2026_09_28_091500 migration. Per-target scheduling now lives in
            // restream_target_schedules (see RestreamTargetScheduleController).
            'scheduled_starts_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_ends_at' => ['sometimes', 'nullable', 'date', 'after:scheduled_starts_at'],
        ]);

        $this->assertNoDuplicateTriple($user->id, $channel->id, $data['platform'] ?? null, $data['platform_account_id'] ?? null);

        // Por defecto el destino se crea activado. El cliente puede desactivarlo
        // desde la columna Acciones si no quiere consumir el slot.
        $enabled = (bool) ($data['enabled'] ?? true);
        $platformAccount = $this->resolvePlatformAccount($user, $data['platform'] ?? null, $data['platform_account_id'] ?? null);

        try {
            if ($enabled) {
                $this->guard->assertCanEnable($user, $channel);
            }

            $target = DB::transaction(function () use ($user, $channel, $data, $enabled, $platformAccount, $request) {
                $payload = [
                    'user_id' => $user->id,
                    'channel_id' => $channel->id,
                    'platform' => $data['platform'],
                    'name' => $data['name'],
                    'destination_url' => $data['destination_url'] ?? null,
                    'stream_key' => $data['stream_key'] ?? null,
                    'enabled' => $enabled,
                    'status' => RestreamTarget::STATUS_IDLE,
                    'created_by' => $user->id,
                    'platform_account_id' => $platformAccount?->id,
                    'title' => $data['title'] ?? null,
                    'platform_privacy' => $data['platform_privacy'] ?? null,
                    'keep_recording' => $data['keep_recording'] ?? true,
                    'description' => $data['description'] ?? null,
                    'thumbnail_media_id' => $data['thumbnail_media_id'] ?? null,
                    'thumbnail_path' => $request->hasFile('thumbnail') && empty($data['thumbnail_media_id'])
                        ? $request->file('thumbnail')->store('restream-thumbnails', 'public')
                        : null,
                    'scheduled_starts_at' => $data['scheduled_starts_at'] ?? null,
                    'scheduled_ends_at' => $data['scheduled_ends_at'] ?? null,
                ];
                return RestreamTarget::create($payload);
            });

            if ($platformAccount) {
                $this->createPlatformBroadcast($target);
            }
        } catch (RestreamQuotaException $e) {
            throw ValidationException::withMessages(['restream' => $e->getMessage()]);
        } catch (RestreamPlatformAccountException $e) {
            if (isset($target)) {
                $target->delete();
            }
            throw ValidationException::withMessages(['restream' => $e->getMessage()]);
        }

        AuditLog::record(
            action: 'create.restream_target',
            entityType: 'restream_target',
            entityId: $target->id,
            before: null,
            after: $target->makeHidden('stream_key')->toArray() + ['stream_key_set' => true],
            channelId: $channel->id,
        );

        return response()->json([
            'message' => 'Destino creado.',
            'target' => $target,
        ], 201);
    }

    public function show(Request $request, Channel $channel, RestreamTarget $target): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id && $target->channel_id === $channel->id, 403, 'Destino no pertenece a este canal.');

        return response()->json([
            'target' => $target,
            'stream_key' => $target->revealStreamKey(),
            'full_push_url' => $target->fullPushUrl(),
            'source_url' => $target->effectiveSourceUrl(),
            'ffmpeg_command' => $this->orchestrator->buildCommandPreview($target),
        ]);
    }

    public function update(Request $request, Channel $channel, RestreamTarget $target): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id && $target->channel_id === $channel->id, 403, 'Destino no pertenece a este canal.');

        $data = $request->validate([
            'platform' => ['sometimes', 'string', 'in:facebook,tiktok,youtube,custom'],
            'name' => ['sometimes', 'string', 'max:80'],
            'destination_url' => ['sometimes', 'string', 'max:2048', 'regex:/^rtmps?:\/\/.+/i'],
            'stream_key' => ['sometimes', 'nullable', 'string', 'min:2', 'max:500'],
            'enabled' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'thumbnail' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'thumbnail_media_id' => ['sometimes', 'nullable', 'uuid', 'exists:media_items,id'],
            'scheduled_starts_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_ends_at' => [
                'sometimes',
                'nullable',
                'date',
                $request->filled('scheduled_starts_at') ? 'after:scheduled_starts_at' : '',
            ],
        ]);

        $willEnable = array_key_exists('enabled', $data) ? (bool) $data['enabled'] : (bool) $target->enabled;
        $becomingActive = $willEnable && ! (bool) $target->enabled;
        $metadataChanged = $target->isPlatformManaged()
            && (array_key_exists('title', $data) || array_key_exists('description', $data) || array_key_exists('scheduled_starts_at', $data));

        try {
            if ($becomingActive) {
                $this->guard->assertCanEnable($user, $channel);
            }

            $beforeArr = $target->makeHidden('stream_key')->toArray() + ['stream_key_set' => ! empty($target->getRawOriginal('stream_key'))];

            DB::transaction(function () use ($target, $data, $request) {
                if (array_key_exists('stream_key', $data) && $data['stream_key']) {
                    $target->stream_key = $data['stream_key'];
                }
                unset($data['stream_key']);
                if ($request->hasFile('thumbnail')) {
                    $data['thumbnail_path'] = $request->file('thumbnail')->store('restream-thumbnails', 'public');
                }
                $target->fill($data);
                if (! array_key_exists('status', $data)) {
                    if (! $target->enabled) {
                        $target->status = RestreamTarget::STATUS_IDLE;
                    }
                }
                $target->save();
            });

            if ($metadataChanged) {
                $this->updatePlatformBroadcast($target);
            }

            $afterArr = $target->fresh()->makeHidden('stream_key')->toArray() + ['stream_key_set' => ! empty($target->getRawOriginal('stream_key'))];

            AuditLog::record(
                action: 'update.restream_target',
                entityType: 'restream_target',
                entityId: $target->id,
                before: $beforeArr,
                after: $afterArr,
                channelId: $channel->id,
            );
        } catch (RestreamQuotaException $e) {
            throw ValidationException::withMessages(['restream' => $e->getMessage()]);
        } catch (RestreamPlatformAccountException $e) {
            throw ValidationException::withMessages(['restream' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Destino actualizado.',
            'target' => $target->fresh(),
        ]);
    }

    /**
     * Pre-check the (user_id, channel_id, platform) triplet — including soft-deleted
     * rows — so duplicates surface as a 422 with an actionable message instead of
     * a 500 from the partial unique index.
     */
    protected function assertNoDuplicateTriple(int|string $userId, int|string $channelId, ?string $platform, ?string $platformAccountId = null): void
    {
        if (! $platform) {
            return;
        }

        $existing = RestreamTarget::withTrashed()
            ->where('user_id', $userId)
            ->where('channel_id', $channelId)
            ->where('platform', $platform)
            ->first();

        if ($existing) {
            $message = $existing->trashed()
                ? 'Ya existe un destino eliminado para esta plataforma en este canal. Restáuralo o elimínalo definitivamente para crear uno nuevo.'
                : 'Ya tienes un destino activo para esta plataforma en este canal.';

            throw ValidationException::withMessages(['restream' => $message]);
        }
    }

    protected function resolvePlatformAccount($user, ?string $platform, ?string $platformAccountId): ?RestreamPlatformAccount
    {
        if (! $platformAccountId) {
            return null;
        }

        $account = RestreamPlatformAccount::where('id', $platformAccountId)
            ->where('user_id', $user->id)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages(['platform_account_id' => 'La cuenta conectada no existe o no te pertenece.']);
        }

        if ($account->platform !== $platform) {
            throw ValidationException::withMessages(['platform_account_id' => 'La cuenta conectada no corresponde a la plataforma seleccionada.']);
        }

        return $account;
    }

    protected function createPlatformBroadcast(RestreamTarget $target): void
    {
        $result = match ($target->platform) {
            RestreamPlatformAccount::PLATFORM_YOUTUBE => $this->youtube->create($target),
            RestreamPlatformAccount::PLATFORM_FACEBOOK => $this->facebook->create($target),
            default => throw RestreamPlatformAccountException::broadcastCreationFailed($target->platform, 'Plataforma no soportada para cuentas conectadas.'),
        };

        $target->forceFill([
            'destination_url' => $result['destination_url'],
            'stream_key' => $result['stream_key'],
            'platform_broadcast_id' => $result['platform_broadcast_id'],
        ])->save();
    }

    protected function updatePlatformBroadcast(RestreamTarget $target): void
    {
        match ($target->platform) {
            RestreamPlatformAccount::PLATFORM_YOUTUBE => $this->youtube->update($target),
            RestreamPlatformAccount::PLATFORM_FACEBOOK => $this->facebook->update($target),
            default => null,
        };
    }

    public function destroy(Request $request, Channel $channel, RestreamTarget $target): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id && $target->channel_id === $channel->id, 403, 'Destino no pertenece a este canal.');

        $beforeArr = $target->makeHidden('stream_key')->toArray() + ['stream_key_set' => ! empty($target->getRawOriginal('stream_key'))];
        $target->update(['enabled' => false]);
        AuditLog::record(
            action: 'delete.restream_target',
            entityType: 'restream_target',
            entityId: $target->id,
            before: $beforeArr,
            after: ['enabled' => false],
            channelId: $channel->id,
        );

        return response()->json(['message' => 'Destino deshabilitado.']);
    }

    public function start(Request $request, Channel $channel, RestreamTarget $target): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id && $target->channel_id === $channel->id, 403, 'Destino no pertenece a este canal.');

        if (! $target->effectiveSourceUrl()) {
            throw ValidationException::withMessages([
                'restream' => 'El canal no tiene una salida RTMP configurada. El restream requiere RTMP como origen.',
            ]);
        }

        if ($target->pipeline_pid && $target->isHeartbeatFresh()) {
            return response()->json([
                'message' => 'El destino ya está transmitiendo.',
                'target' => $target->fresh(),
                'status' => $target->status,
                'pipeline_pid' => $target->pipeline_pid,
            ]);
        }

        if (! $target->enabled) {
            try {
                $this->guard->assertCanEnable($user, $channel);
            } catch (RestreamQuotaException $e) {
                throw ValidationException::withMessages(['restream' => $e->getMessage()]);
            }
            $target->enabled = true;
        }

        try {
            $result = $this->orchestrator->start($target);
        } catch (\Throwable $e) {
            $target->save();
            AuditLog::record(
                action: 'start.restream_target',
                entityType: 'restream_target',
                entityId: $target->id,
                before: null,
                after: ['status' => $target->status, 'last_error' => $target->last_error],
                channelId: $channel->id,
            );
            return response()->json([
                'message' => 'Falló al arrancar el proceso: ' . $e->getMessage(),
                'target' => $target->fresh(),
                'status' => $target->status,
                'pipeline_pid' => null,
                'last_error' => $target->last_error,
            ], 422);
        }

        $target->save();

        AuditLog::record(
            action: 'start.restream_target',
            entityType: 'restream_target',
            entityId: $target->id,
            before: null,
            after: ['status' => 'starting', 'pipeline_pid' => $result['pid']],
            channelId: $channel->id,
        );

        return response()->json([
            'message' => 'Destino arrancado.',
            'target' => $target->fresh(),
            'status' => 'starting',
            'pipeline_pid' => $result['pid'],
        ]);
    }

    public function log(Request $request, Channel $channel, RestreamTarget $target): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id && $target->channel_id === $channel->id, 403, 'Destino no pertenece a este canal.');

        $lines = (int) $request->input('lines', 100);
        $lines = max(1, min($lines, 500));

        $logPath = storage_path('logs/restream/' . $target->id . '.log');

        if (! file_exists($logPath)) {
            return response()->json(['lines' => [], 'total_lines' => 0, 'target_id' => $target->id]);
        }

        $content = file_get_contents($logPath);
        $allLines = explode("\n", $content);
        $allLines = array_filter($allLines, fn ($l) => trim($l) !== '');
        $tail = array_slice($allLines, -$lines);

        $parsed = [];
        foreach ($tail as $line) {
            $entry = ['raw' => $line];

            if (str_contains($line, '[STATS]')) {
                preg_match_all('/(\w+)=([^\s]+)/', $line, $matches, PREG_SET_ORDER);
                foreach ($matches as $m) {
                    $val = $m[2];
                    $numStr = preg_replace('/^(\d+(?:\.\d+)?)(?:kbps|fps|s|m|frames)$/i', '$1', $val);
                    if (is_numeric($numStr)) {
                        $entry[$m[1]] = str_contains($numStr, '.') ? (float) $numStr : (int) $numStr;
                    } else {
                        $entry[$m[1]] = $val;
                    }
                }
                $entry['type'] = 'stats';
            } elseif (str_contains($line, '[WATCHDOG]')) {
                $entry['type'] = 'watchdog';
            } elseif (str_contains($line, '[DAEMON]')) {
                $entry['type'] = 'daemon';
            } else {
                $entry['type'] = 'other';
            }

            $parsed[] = $entry;
        }

        return response()->json([
            'lines' => $parsed,
            'total_lines' => count($allLines),
            'target_id' => $target->id,
        ]);
    }

    public function stop(Request $request, Channel $channel, RestreamTarget $target): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id && $target->channel_id === $channel->id, 403, 'Destino no pertenece a este canal.');

        $this->orchestrator->stop($target);

        // Marcar la ventana actualmente aplicable como stopped_by_user para
        // que el motor restream:run-target-schedules no la reencienda al
        // siguiente tick (el cliente detuvo a propósito; respetamos esa decisión).
        $now = now();
        \App\Models\RestreamTargetSchedule::where('target_id', $target->id)
            ->where('enabled', true)
            ->where('stopped_by_user', false)
            ->where('starts_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->update(['stopped_by_user' => true]);

        AuditLog::record(
            action: 'stop.restream_target',
            entityType: 'restream_target',
            entityId: $target->id,
            before: null,
            after: ['status' => 'idle', 'pipeline_pid' => null],
            channelId: $channel->id,
        );

        return response()->json([
            'message' => 'Destino detenido.',
            'target' => $target->fresh(),
            'status' => 'idle',
            'pipeline_pid' => null,
        ]);
    }

    public function deactivate(Request $request, Channel $channel, RestreamTarget $target): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id && $target->channel_id === $channel->id, 403, 'Destino no pertenece a este canal.');

        if ($target->pipeline_pid) {
            try {
                $this->orchestrator->stop($target);
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $before = ['enabled' => (bool) $target->enabled, 'status' => $target->status];

        $target->enabled = false;
        $target->status = RestreamTarget::STATUS_IDLE;
        $target->save();

        AuditLog::record(
            action: 'deactivate.restream_target',
            entityType: 'restream_target',
            entityId: $target->id,
            before: $before,
            after: ['enabled' => false, 'status' => 'idle'],
            channelId: $channel->id,
        );

        return response()->json([
            'message' => 'Destino inactivado. El slot queda libre para crear otro destino.',
            'target' => $target->fresh(),
            'enabled' => false,
            'status' => 'idle',
        ]);
    }

    /**
     * Borra el broadcast de YouTube Studio del destino. NO toca el destino:
     * el target sigue existiendo. La próxima vez que se encienda, se creará
     * un broadcast nuevo. Útil para limpiar el Studio del cliente cuando
     * acumuló muchos broadcasts de pruebas.
     */
    public function cleanBroadcast(Request $request, Channel $channel, RestreamTarget $target): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id && $target->channel_id === $channel->id, 403, 'Destino no pertenece a este canal.');

        if (! $target->platform_broadcast_id || $target->platform !== 'youtube') {
            return response()->json([
                'message' => 'No hay broadcast que limpiar.',
            ]);
        }

        // Si hay daemon vivo, primero lo paramos para que YouTube marque el broadcast como complete.
        if ($target->pipeline_pid) {
            try {
                $this->orchestrator->stop($target);
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $deletedBroadcast = $target->platform_broadcast_id;

        try {
            $this->youtube->delete($target->platformAccount, $deletedBroadcast);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo borrar el broadcast en YouTube: ' . $e->getMessage(),
            ], 502);
        }

        $target->platform_broadcast_id = null;
        $target->save();

        AuditLog::record(
            action: 'clean_broadcast.restream_target',
            entityType: 'restream_target',
            entityId: $target->id,
            after: ['deleted_broadcast' => $deletedBroadcast],
            channelId: $channel->id,
        );

        return response()->json([
            'message' => "Broadcast {$deletedBroadcast} borrado de YouTube. El destino sigue activo; la próxima vez que lo enciendas se creará uno nuevo.",
            'target' => $target->fresh(),
        ]);
    }

    public function restore(Request $request, Channel $channel, RestreamTarget $target): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id && $target->channel_id === $channel->id, 403, 'Destino no pertenece a este canal.');

        if ($target->trashed()) {
            $target->restore();
        }

        AuditLog::record(
            action: 'restore.restream_target',
            entityType: 'restream_target',
            entityId: $target->id,
            before: ['deleted_at' => $target->deleted_at],
            after: ['deleted_at' => null],
            channelId: $channel->id,
        );

        return response()->json([
            'message' => 'Destino restaurado.',
            'target' => $target->fresh(),
        ]);
    }

    public function forceDestroy(Request $request, Channel $channel, RestreamTarget $target): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id && $target->channel_id === $channel->id, 403, 'Destino no pertenece a este canal.');

        if ($target->pipeline_pid) {
            try {
                $this->orchestrator->stop($target);
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $beforeArr = $target->makeHidden('stream_key')->toArray() + ['stream_key_set' => ! empty($target->getRawOriginal('stream_key'))];

        $target->forceDelete();

        AuditLog::record(
            action: 'force_delete.restream_target',
            entityType: 'restream_target',
            entityId: $target->id,
            before: $beforeArr,
            after: null,
            channelId: $channel->id,
        );

        return response()->json(['message' => 'Destino eliminado DEFINITIVAMENTE. No se puede recuperar.']);
    }
}