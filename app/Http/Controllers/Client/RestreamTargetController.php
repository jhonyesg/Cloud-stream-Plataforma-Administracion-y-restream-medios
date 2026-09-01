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
            'targets' => $targets,
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
            'destination_url' => [$usesPlatformAccount ? 'prohibited' : 'required', 'string', 'max:2048', 'regex:/^rtmps?:\/\/.+/i'],
            'stream_key' => [$usesPlatformAccount ? 'prohibited' : 'required', 'string', 'min:2', 'max:500'],
            'enabled' => ['sometimes', 'boolean'],
            'platform_account_id' => ['sometimes', 'nullable', 'uuid'],
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'thumbnail' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'scheduled_start_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $enabled = (bool) ($data['enabled'] ?? false);
        $platformAccount = $this->resolvePlatformAccount($user, $data['platform'] ?? null, $data['platform_account_id'] ?? null);

        try {
            if ($enabled) {
                $this->guard->assertCanEnable($user, $channel);
            }

            $target = DB::transaction(function () use ($user, $channel, $data, $enabled, $platformAccount, $request) {
                return RestreamTarget::create([
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
                    'description' => $data['description'] ?? null,
                    'thumbnail_path' => $request->hasFile('thumbnail')
                        ? $request->file('thumbnail')->store('restream-thumbnails', 'public')
                        : null,
                    'scheduled_start_at' => $data['scheduled_start_at'] ?? null,
                ]);
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
            'scheduled_start_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $willEnable = array_key_exists('enabled', $data) ? (bool) $data['enabled'] : (bool) $target->enabled;
        $becomingActive = $willEnable && ! (bool) $target->enabled;
        $metadataChanged = $target->isPlatformManaged()
            && (array_key_exists('title', $data) || array_key_exists('description', $data) || array_key_exists('scheduled_start_at', $data));

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
}