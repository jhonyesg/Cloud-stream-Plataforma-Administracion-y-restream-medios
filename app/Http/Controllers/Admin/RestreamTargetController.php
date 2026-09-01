<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\RestreamTarget;
use App\Models\User;
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
        private readonly RestreamOrchestrator $orchestrator,
        private readonly RestreamQuotaGuard $guard,
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

        $targetsArray = collect($targets->getCollection()->map(function ($t) {
            $status = $t->status;
            if ($status === RestreamTarget::STATUS_ACTIVE && ! $t->isHeartbeatFresh()) {
                $status = RestreamTarget::STATUS_ERROR;
            }
            return [
                'id' => $t->id,
                'channel_id' => $t->channel_id,
                'channel' => ['display_name' => optional($t->channel)->display_name],
                'user' => ['display_name' => optional($t->user)->name],
                'platform' => $t->platform,
                'name' => $t->name,
                'destination_url' => $t->destination_url,
                'source_url' => $t->source_url,
                'enabled' => (bool) $t->enabled,
                'status' => $status,
                'pipeline_pid' => $t->pipeline_pid,
                'last_started_at' => $t->last_started_at ? $t->last_started_at->format('Y-m-d H:i:s') : null,
                'last_heartbeat_at' => $t->last_heartbeat_at ? $t->last_heartbeat_at->format('Y-m-d H:i:s') : null,
                'last_error' => $t->last_error,
            ];
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

        return view('admin.restream.index', [
            'targets' => $targets,
            'targetsJson' => $targetsArray->toJson(),
            'channels' => $channels,
            'channelsJson' => $channels->map(fn($c) => ['id' => $c->id, 'display_name' => $c->display_name])->values()->toJson(),
            'currentChannel' => $currentChannel,
            'currentChannelId' => $currentChannelId,
            'filters' => $request->only(['platform', 'status']),
            'usedOutputs' => $usedOutputs,
            'maxOutputs' => $maxOutputs,
            'remainingSlots' => $remainingSlots,
        ]);
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
            'destination_url' => ['required', 'string', 'max:2048', 'regex:/^rtmps?:\/\/.+/i'],
            'stream_key' => ['required', 'string', 'min:2', 'max:500'],
            'source_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:#^(https?|rtmps?|rtsps?)://.+$#i'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $admin = $request->user();
        $enabled = (bool) ($data['enabled'] ?? false);

        try {
            if ($enabled) {
                $this->guard->assertCanEnable($owner, $channel);
            }

            $target = DB::transaction(function () use ($owner, $channel, $admin, $data, $enabled) {
                return RestreamTarget::create([
                    'user_id' => $owner->id,
                    'channel_id' => $channel->id,
                    'platform' => $data['platform'],
                    'name' => $data['name'],
                    'destination_url' => $data['destination_url'],
                    'stream_key' => $data['stream_key'],
                    'source_url' => $data['source_url'] ?? null,
                    'enabled' => $enabled,
                    'status' => RestreamTarget::STATUS_IDLE,
                    'created_by' => $admin?->id,
                ]);
            });
        } catch (RestreamQuotaException $e) {
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
        ]);

        $willEnable = array_key_exists('enabled', $data) ? (bool) $data['enabled'] : (bool) $target->enabled;
        $becomingActive = $willEnable && ! (bool) $target->enabled;

        $owner = $channel->owner_id ? User::find($channel->owner_id) : null;

        try {
            if ($becomingActive && $owner) {
                $this->guard->assertCanEnable($owner, $channel);
            }

            $beforeArr = $target->makeHidden('stream_key')->toArray() + ['stream_key_set' => ! empty($target->getRawOriginal('stream_key'))];

            DB::transaction(function () use ($target, $data) {
                if (array_key_exists('stream_key', $data) && ! empty($data['stream_key'])) {
                    $target->stream_key = $data['stream_key'];
                }
                unset($data['stream_key']);
                $target->fill($data);
                $target->save();
            });

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
        }

        return response()->json(['message' => 'Destino actualizado.', 'target' => $target->fresh()]);
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

        return response()->json(['message' => 'Destino eliminado.']);
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
}