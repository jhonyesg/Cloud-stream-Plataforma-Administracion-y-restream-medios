<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\RestreamQuota;
use App\Models\RestreamTarget;
use App\Models\User;
use App\Services\Restream\RestreamOrchestrator;
use App\Services\Restream\RestreamQuotaGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestreamQuotaController extends Controller
{
    public function __construct(
        private readonly RestreamQuotaGuard $guard,
        private readonly RestreamOrchestrator $orchestrator,
    ) {
    }

    /**
     * Stop (kill the daemon of) every currently-running target for this
     * user/channel and mark it disabled, so an orphaned process never keeps
     * pushing to the destination platform after Restream is turned off for
     * the channel.
     */
    private function stopRunningTargets(User $user, Channel $channel): void
    {
        RestreamTarget::where('user_id', $user->id)
            ->where('channel_id', $channel->id)
            ->where(function ($q) {
                $q->whereNotNull('pipeline_pid')->orWhereIn('status', [RestreamTarget::STATUS_ACTIVE, RestreamTarget::STATUS_STARTING]);
            })
            ->get()
            ->each(function (RestreamTarget $target) {
                try {
                    $this->orchestrator->stop($target);
                } catch (\Throwable $e) {
                    // Best-effort: still disable the row below even if the
                    // daemon couldn't be reached/killed cleanly.
                }
                $target->update(['enabled' => false]);
            });
    }

    public function show(Channel $channel): JsonResponse
    {
        $user = $this->resolveOwner($channel);
        $quota = $user
            ? RestreamQuota::where('user_id', $user->id)->where('channel_id', $channel->id)->first()
            : null;

        $targets = RestreamTarget::with('channel')
            ->where('channel_id', $channel->id)
            ->where('user_id', $user?->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'channel' => ['id' => $channel->id, 'display_name' => $channel->display_name],
            'owner' => $user ? ['id' => $user->id, 'display_name' => $user->display_name, 'username' => $user->username] : null,
            'quota' => $quota,
            'max_outputs' => $user ? $user->restreamMaxOutputsFor($channel->id) : 0,
            'used_outputs' => $user ? $user->restreamUsedOutputsFor($channel->id) : 0,
            'remaining_slots' => $user ? $user->restreamRemainingSlotsFor($channel->id) : 0,
            'targets' => $targets,
            'tiers' => RestreamQuota::VALID_TIERS,
        ]);
    }

    public function store(Request $request, Channel $channel): JsonResponse
    {
        $user = $this->resolveOwner($channel);
        if (! $user) {
            throw ValidationException::withMessages([
                'channel' => 'Este canal no tiene un owner asignado. Asigna un owner antes de habilitar Restream.',
            ]);
        }

        $existing = RestreamQuota::where('user_id', $user->id)->where('channel_id', $channel->id)->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'restream_quota' => 'Este canal ya tiene una cuota de Restream. Usa PATCH para modificarla.',
            ]);
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'max_outputs' => ['required', 'integer', 'in:1,2,3,4'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $admin = $request->user();

        $quota = DB::transaction(function () use ($user, $channel, $admin, $data) {
            return RestreamQuota::create([
                'user_id' => $user->id,
                'channel_id' => $channel->id,
                'enabled' => $data['enabled'],
                'max_outputs' => $data['max_outputs'],
                'granted_by' => $admin?->id,
                'granted_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
        });

        AuditLog::record(
            action: 'create.restream_quota',
            entityType: 'restream_quota',
            entityId: $quota->id,
            before: null,
            after: $quota->only(['user_id', 'channel_id', 'enabled', 'max_outputs', 'granted_by', 'granted_at', 'notes']),
            channelId: $channel->id,
        );

        return response()->json([
            'message' => 'Restream habilitado para el canal.',
            'quota' => $quota,
        ], 201);
    }

    public function update(Request $request, Channel $channel): JsonResponse
    {
        $user = $this->resolveOwner($channel);
        $quota = $user
            ? RestreamQuota::where('user_id', $user->id)->where('channel_id', $channel->id)->first()
            : null;

        if (! $quota) {
            throw ValidationException::withMessages([
                'restream_quota' => 'Este canal aún no tiene una cuota de Restream. Usa POST para crearla.',
            ]);
        }

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'max_outputs' => ['sometimes', 'integer', 'in:1,2,3,4'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if (array_key_exists('max_outputs', $data) && $data['max_outputs'] < $quota->max_outputs) {
            $activeCount = RestreamTarget::where('user_id', $user->id)
                ->where('channel_id', $channel->id)
                ->where('enabled', true)
                ->count();
            if ($activeCount > $data['max_outputs']) {
                throw ValidationException::withMessages([
                    'max_outputs' => "No se puede bajar a {$data['max_outputs']} destinos: el canal tiene {$activeCount} destinos activos. Desactive o elimine destinos primero.",
                ]);
            }
        }

        $disabling = array_key_exists('enabled', $data) && ! $data['enabled'] && $quota->enabled;

        $before = $quota->only(['user_id', 'channel_id', 'enabled', 'max_outputs', 'notes']);
        $quota->fill($data);
        $quota->save();

        if ($disabling) {
            $this->stopRunningTargets($user, $channel);
        }

        AuditLog::record(
            action: 'update.restream_quota',
            entityType: 'restream_quota',
            entityId: $quota->id,
            before: $before,
            after: $quota->only(['user_id', 'channel_id', 'enabled', 'max_outputs', 'notes']),
            channelId: $channel->id,
        );

        return response()->json([
            'message' => 'Cuota de Restream actualizada.',
            'quota' => $quota->fresh(),
        ]);
    }

    public function destroy(Channel $channel): JsonResponse
    {
        $user = $this->resolveOwner($channel);
        $quota = $user
            ? RestreamQuota::where('user_id', $user->id)->where('channel_id', $channel->id)->first()
            : null;

        if (! $quota) {
            return response()->json(['message' => 'No hay cuota para eliminar.'], 404);
        }

        $before = $quota->only(['user_id', 'channel_id', 'enabled', 'max_outputs', 'notes']);

        // Outside the transaction: stopping a daemon involves signalling a
        // process and waiting on it, which shouldn't happen inside a held
        // DB transaction/lock.
        $this->stopRunningTargets($user, $channel);

        DB::transaction(function () use ($user, $channel, $quota) {
            RestreamTarget::where('user_id', $user->id)->where('channel_id', $channel->id)->update(['enabled' => false]);
            $quota->delete();
        });

        AuditLog::record(
            action: 'delete.restream_quota',
            entityType: 'restream_quota',
            entityId: $quota->id,
            before: $before,
            after: null,
            channelId: $channel->id,
        );

        return response()->json(['message' => 'Cuota de Restream eliminada.']);
    }

    protected function resolveOwner(Channel $channel): ?User
    {
        return $channel->owner_id ? User::find($channel->owner_id) : null;
    }
}
