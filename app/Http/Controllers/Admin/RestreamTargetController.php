<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\RestreamPlatformAccount;
use App\Models\RestreamTarget;
use App\Models\User;
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
        private readonly RestreamOrchestrator $orchestrator,
        private readonly RestreamQuotaGuard $guard,
        private readonly YoutubeBroadcastService $youtube,
        private readonly FacebookBroadcastService $facebook,
        private readonly RestreamTargetJsonPresenter $presenter,
    ) {
    }

    public function index(Request $request): \Illuminate\View\View|JsonResponse
    {
        $channels = Channel::with('owner')
            ->whereNotNull('owner_id')
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'owner_id']);

        $requested = $request->query('channel_id');
        $currentChannelId = ($requested && $channels->contains('id', $requested))
            ? $requested
            : ($channels->first()?->id);

        $currentChannel = $currentChannelId ? $channels->firstWhere('id', $currentChannelId) : null;
        $owner = $currentChannel?->owner;

        $query = RestreamTarget::with(['user', 'channel'])
            ->where('channel_id', $currentChannelId);

        if ($platform = $request->query('platform')) {
            $query->where('platform', $platform);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $targets = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        $maxOutputs = 0;
        $usedOutputs = 0;
        if ($owner) {
            $maxOutputs = $owner->restreamMaxOutputsFor($currentChannelId);
            $usedOutputs = $owner->restreamUsedOutputsFor($currentChannelId);
        }
        $remainingSlots = max(0, $maxOutputs - $usedOutputs);

        $connectedAccounts = RestreamPlatformAccount::with(['user:id,display_name,username,email'])
            ->orderBy('user_id')
            ->orderBy('platform')
            ->get()
            ->map(fn (RestreamPlatformAccount $a) => [
                'id' => $a->id,
                'platform' => $a->platform,
                'platform_label' => $a->platformLabel(),
                'display_name' => $a->display_name,
                'owner_id' => optional($a->user)->id,
                'owner_display_name' => optional($a->user)->display_name
                    ?? optional($a->user)->username
                    ?? optional($a->user)->email,
                'connected_at' => $a->created_at,
                'token_expires_at' => $a->token_expires_at,
                'needs_reconnect' => $a->needsReconnect(),
            ])
            ->values();

        $clients = User::query()
            ->where('role', 'client')
            ->orderBy('display_name')
            ->orderBy('username')
            ->orderBy('email')
            ->get(['id', 'display_name', 'username', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'display_name' => $u->display_name ?: $u->username ?: $u->email,
            ])
            ->values();

        $mediaImagesJson = $this->buildMediaImagesJson($owner?->id);

        $targetsArray = collect($targets->getCollection()->map(function ($t) {
            $row = $this->presenter->present($t, withChannel: true, withUser: true);
            $row['last_auto_start_at'] = AuditLog::where('action', 'auto_start.restream_target')
                ->where('entity_type', 'restream_target')->where('entity_id', $t->id)
                ->orderByDesc('id')->value('at')?->format('Y-m-d H:i:s');
            $row['last_auto_stop_at'] = AuditLog::where('action', 'auto_stop.restream_target')
                ->where('entity_type', 'restream_target')->where('entity_id', $t->id)
                ->orderByDesc('id')->value('at')?->format('Y-m-d H:i:s');
            return $row;
        }))->values();

        if ($request->wantsJson()) {
            return response()->json([
                'targets' => $targetsArray,
                'total' => $targets->total(),
                'usedOutputs' => $usedOutputs,
                'maxOutputs' => $maxOutputs,
                'remainingSlots' => $remainingSlots,
            ]);
        }

        $currentChannelJson = $currentChannel
            ? json_encode(['id' => $currentChannel->id, 'display_name' => $currentChannel->display_name], JSON_UNESCAPED_UNICODE)
            : 'null';

        return view('admin.restream.index', [
            'targets' => $targets,
            'targetsJson' => $targetsArray->toJson(),
            'channels' => $channels,
            'channelsJson' => $channels->map(fn($c) => ['id' => $c->id, 'display_name' => $c->display_name])->values()->toJson(),
            'currentChannel' => $currentChannel,
            'currentChannelId' => $currentChannelId,
            'currentChannelJson' => $currentChannelJson,
            'filters' => $request->only(['platform', 'status']),
            'usedOutputs' => $usedOutputs,
            'maxOutputs' => $maxOutputs,
            'remainingSlots' => $remainingSlots,
            'connectedAccountsJson' => $connectedAccounts->toJson(),
            'clientsJson' => $clients->toJson(),
            'mediaImagesJson' => $mediaImagesJson,
        ]);
    }

    protected function buildMediaImagesJson(?string $ownerId): string
    {
        if (! $ownerId) {
            return '[]';
        }
        $channelIds = \App\Models\User::find($ownerId)?->effectiveChannelIds() ?? [];
        if (empty($channelIds)) {
            return '[]';
        }

        $images = \App\Models\MediaItem::whereIn('channel_id', $channelIds)
            ->where('kind', 'image')
            ->where('status', 'ready')
            ->orderBy('created_at', 'desc')
            ->limit(60)
            ->get(['id', 'filename', 'channel_id', 'width', 'height', 'mime_type', 'created_at']);

        return $images->map(fn (\App\Models\MediaItem $m) => [
            'id' => $m->id,
            'filename' => $m->filename,
            'thumb_url' => $m->thumbUrl(),
            'play_url' => $m->playUrl(),
            'width' => $m->width,
            'height' => $m->height,
            'channel_id' => $m->channel_id,
            'mime_type' => $m->mime_type,
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request, Channel $channel): JsonResponse
    {
        $owner = $channel->owner_id ? User::find($channel->owner_id) : null;
        if (! $owner) {
            throw ValidationException::withMessages([
                'channel' => 'Este canal no tiene un owner asignado. Asigna un owner antes de crear destinos.',
            ]);
        }

        $data = $request->validate([
            'platform' => ['required', 'string', 'in:facebook,tiktok,youtube,custom'],
            'name' => ['required', 'string', 'max:80'],
            'destination_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:/^rtmps?:\/\/.+/i'],
            'stream_key' => ['sometimes', 'nullable', 'string', 'min:2', 'max:500'],
            'source_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:#^(https?|rtmps?|rtsps?)://.+$#i'],
            'enabled' => ['sometimes', 'boolean'],
            'platform_account_id' => ['sometimes', 'nullable', 'uuid'],
            'platform_privacy' => ['sometimes', 'nullable', 'in:public,unlisted,private'],
            'keep_recording' => ['sometimes', 'nullable', 'boolean'],
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'thumbnail_media_id' => ['sometimes', 'nullable', 'uuid', 'exists:media_items,id'],
            'thumbnail' => ['sometimes', 'nullable', 'image', 'max:5120'],
        ]);

        $platformAccount = $this->resolvePlatformAccount($data['platform_account_id'] ?? null, $data['platform']);

        $admin = $request->user();
        $enabled = (bool) ($data['enabled'] ?? false);

        try {
            if ($enabled) {
                $this->guard->assertCanEnable($owner, $channel);
            }

            $target = DB::transaction(function () use ($owner, $channel, $admin, $data, $enabled, $platformAccount, $request) {
                return RestreamTarget::create([
                    'user_id' => $owner->id,
                    'channel_id' => $channel->id,
                    'platform' => $data['platform'],
                    'name' => $data['name'],
                    'destination_url' => $data['destination_url'] ?? null,
                    'stream_key' => $data['stream_key'] ?? null,
                    'source_url' => $data['source_url'] ?? null,
                    'enabled' => $enabled,
                    'status' => RestreamTarget::STATUS_IDLE,
                    'created_by' => $admin?->id,
                    'platform_account_id' => $platformAccount?->id,
                    'title' => $data['title'] ?? null,
                    'platform_privacy' => $data['platform_privacy'] ?? null,
                    'keep_recording' => $data['keep_recording'] ?? true,
                    'description' => $data['description'] ?? null,
                    'thumbnail_media_id' => $data['thumbnail_media_id'] ?? null,
                    'thumbnail_path' => $request->hasFile('thumbnail') && empty($data['thumbnail_media_id'])
                        ? $request->file('thumbnail')->store('restream-thumbnails', 'public')
                        : null,
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

    public function show(Channel $channel, RestreamTarget $target): JsonResponse
    {
        abort_unless($target->channel_id === $channel->id, 404, 'Destino no pertenece a este canal.');
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
        abort_unless($target->channel_id === $channel->id, 404, 'Destino no pertenece a este canal.');

        $data = $request->validate([
            'platform' => ['sometimes', 'string', 'in:facebook,tiktok,youtube,custom'],
            'name' => ['sometimes', 'string', 'max:80'],
            'destination_url' => ['sometimes', 'string', 'max:2048', 'regex:/^rtmps?:\/\/.+/i'],
            'stream_key' => ['sometimes', 'nullable', 'string', 'min:2', 'max:500'],
            'source_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:#^(https?|rtmps?|rtsps?)://.+$#i'],
            'enabled' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:idle,starting,live,error'],
            'platform_account_id' => ['sometimes', 'nullable', 'uuid'],
            'platform_privacy' => ['sometimes', 'nullable', 'in:public,unlisted,private'],
            'keep_recording' => ['sometimes', 'nullable', 'boolean'],
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'thumbnail_media_id' => ['sometimes', 'nullable', 'uuid', 'exists:media_items,id'],
            'thumbnail' => ['sometimes', 'nullable', 'image', 'max:5120'],
        ]);

        $willEnable = array_key_exists('enabled', $data) ? (bool) $data['enabled'] : (bool) $target->enabled;
        $becomingActive = $willEnable && ! (bool) $target->enabled;
        $metadataChanged = $target->isPlatformManaged()
            && (array_key_exists('title', $data) || array_key_exists('description', $data)
                || array_key_exists('platform_privacy', $data));

        $owner = $channel->owner_id ? User::find($channel->owner_id) : null;

        try {
            if ($becomingActive && $owner) {
                $this->guard->assertCanEnable($owner, $channel);
            }

            $beforeArr = $target->makeHidden('stream_key')->toArray() + ['stream_key_set' => ! empty($target->getRawOriginal('stream_key'))];

            DB::transaction(function () use ($target, $data, $request) {
                if (array_key_exists('stream_key', $data) && ! empty($data['stream_key'])) {
                    $target->stream_key = $data['stream_key'];
                }
                unset($data['stream_key']);
                if ($request->hasFile('thumbnail')) {
                    $data['thumbnail_path'] = $request->file('thumbnail')->store('restream-thumbnails', 'public');
                }
                $target->fill($data);
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

        return response()->json(['message' => 'Destino actualizado.', 'target' => $target->fresh()]);
    }

    protected function resolvePlatformAccount(?string $platformAccountId, string $platform): ?RestreamPlatformAccount
    {
        if (! $platformAccountId) {
            return null;
        }

        $account = RestreamPlatformAccount::find($platformAccountId);

        if (! $account) {
            throw ValidationException::withMessages(['platform_account_id' => 'La cuenta conectada no existe.']);
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

    public function destroy(Channel $channel, RestreamTarget $target): JsonResponse
    {
        abort_unless($target->channel_id === $channel->id, 404, 'Destino no pertenece a este canal.');

        if ($target->pipeline_pid) {
            try {
                $this->orchestrator->stop($target);
            } catch (\Throwable $e) {
                // best-effort; force-soft-delete below
            }
        }

        $beforeArr = $target->makeHidden('stream_key')->toArray() + ['stream_key_set' => ! empty($target->getRawOriginal('stream_key'))];
        $target->delete();

        AuditLog::record(
            action: 'delete.restream_target',
            entityType: 'restream_target',
            entityId: $target->id,
            before: $beforeArr,
            after: null,
            channelId: $channel->id,
        );

        return response()->json(['message' => 'Destino archivado (soft-delete). Se puede restaurar desde admin si fue un error.']);
    }

    public function start(Channel $channel, RestreamTarget $target): JsonResponse
    {
        abort_unless($target->channel_id === $channel->id, 404, 'Destino no pertenece a este canal.');

        if (! $target->effectiveSourceUrl()) {
            throw ValidationException::withMessages([
                'restream' => 'El canal no tiene una salida RTMP configurada (virtualScreen.output_url). El restream requiere RTMP como origen.',
            ]);
        }

        if ($target->pipeline_pid && $target->isHeartbeatFresh()) {
            return response()->json([
                'message' => 'El destino ya está transmitiendo.',
                'target' => $target->fresh(),
            ]);
        }

        if (! $target->enabled) {
            $owner = $channel->owner_id ? User::find($channel->owner_id) : null;
            if ($owner) {
                try {
                    $this->guard->assertCanEnable($owner, $channel);
                } catch (RestreamQuotaException $e) {
                    throw ValidationException::withMessages(['restream' => $e->getMessage()]);
                }
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
                'message' => 'Falló al arrancar: ' . $e->getMessage(),
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
        abort_unless($target->channel_id === $channel->id, 404, 'Destino no pertenece a este canal.');

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

    public function stop(Channel $channel, RestreamTarget $target): JsonResponse
    {
        abort_unless($target->channel_id === $channel->id, 404, 'Destino no pertenece a este canal.');

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

    public function deactivate(Channel $channel, RestreamTarget $target): JsonResponse
    {
        abort_unless($target->channel_id === $channel->id, 404, 'Destino no pertenece a este canal.');

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
            'message' => 'Destino inactivado. El slot queda libre.',
            'target' => $target->fresh(),
            'enabled' => false,
            'status' => 'idle',
        ]);
    }

    public function restore(Channel $channel, RestreamTarget $target): JsonResponse
    {
        abort_unless($target->channel_id === $channel->id, 404, 'Destino no pertenece a este canal.');

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

    public function forceDestroy(Channel $channel, RestreamTarget $target): JsonResponse
    {
        abort_unless($target->channel_id === $channel->id, 404, 'Destino no pertenece a este canal.');

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