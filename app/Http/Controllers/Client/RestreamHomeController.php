<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\RestreamPlatformAccount;
use App\Models\RestreamTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestreamHomeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $channelIds = $user->effectiveChannelIds();

        $channels = Channel::whereIn('id', $channelIds)->orderBy('display_name')->get(['id', 'display_name']);

        $requested = $request->query('channel_id');
        $currentChannelId = ($requested && $channels->contains('id', $requested))
            ? $requested
            : ($channels->first()?->id);

        $currentChannel = $currentChannelId ? $channels->firstWhere('id', $currentChannelId) : null;

        $query = RestreamTarget::with(['channel'])
            ->where('user_id', $user->id)
            ->where('channel_id', $currentChannelId);

        if ($platform = $request->query('platform')) {
            $query->where('platform', $platform);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $targets = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        $usedOutputs = $currentChannelId ? $user->restreamUsedOutputsFor($currentChannelId) : 0;
        $maxOutputs = $currentChannelId ? $user->restreamMaxOutputsFor($currentChannelId) : 0;
        $remainingSlots = $currentChannelId ? $user->restreamRemainingSlotsFor($currentChannelId) : 0;

        $targetsArray = collect($targets->getCollection()->map(function ($t) {
            $status = $t->status;
            if ($status === RestreamTarget::STATUS_ACTIVE && ! $t->isHeartbeatFresh()) {
                $status = RestreamTarget::STATUS_ERROR;
            }
            return [
                'id' => $t->id,
                'channel_id' => $t->channel_id,
                'channel' => ['display_name' => optional($t->channel)->display_name],
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
                'platform_account_id' => $t->platform_account_id,
                'title' => $t->title,
                'description' => $t->description,
                'scheduled_start_at' => $t->scheduled_start_at ? $t->scheduled_start_at->format('Y-m-d\TH:i') : null,
            ];
        }))->values();

        $connectedAccounts = RestreamPlatformAccount::where('user_id', $user->id)->get()
            ->map(fn (RestreamPlatformAccount $a) => [
                'id' => $a->id,
                'platform' => $a->platform,
                'display_name' => $a->display_name,
                'needs_reconnect' => $a->needsReconnect(),
            ])->values();

        if ($request->wantsJson()) {
            return response()->json([
                'targets' => $targetsArray,
                'total' => $targets->total(),
                'usedOutputs' => $usedOutputs,
                'maxOutputs' => $maxOutputs,
                'remainingSlots' => $remainingSlots,
            ]);
        }

        return view('client.restream.index', [
            'targets' => $targets,
            'targetsJson' => $targetsArray->toJson(),
            'channelsJson' => $channels->map(fn($c) => ['id' => $c->id, 'display_name' => $c->display_name])->values()->toJson(),
            'connectedAccountsJson' => $connectedAccounts->toJson(),
            'channels' => $channels,
            'currentChannel' => $currentChannel,
            'currentChannelId' => $currentChannelId,
            'filters' => $request->only(['platform', 'status']),
            'usedOutputs' => $usedOutputs,
            'maxOutputs' => $maxOutputs,
            'remainingSlots' => $remainingSlots,
        ]);
    }
}
