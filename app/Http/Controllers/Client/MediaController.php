<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\MediaItem;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $ownedChannelIds = $user->channels()->pluck('channels.id')->toArray();
        $assignedIds = $user->assignedChannels()->pluck('channels.id')->toArray();
        $channelIds = collect($ownedChannelIds)->merge($assignedIds)->unique()->values();

        $query = MediaItem::whereIn('channel_id', $channelIds);

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->string('channel_id'));
        }
        if ($request->filled('kind')) {
            $query->where('kind', $request->string('kind'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $query->where('filename', 'like', '%' . $request->string('q') . '%');
        }

        $mediaItems = $query->with(['channel:id,slug,display_name'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->appends($request->only(['channel_id', 'kind', 'status', 'q']));

        $channels = Channel::whereIn('id', $channelIds)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'slug', 'owner_id', 'storage_limit_bytes', 'used_bytes']);

        $preselectedChannelId = $channelIds->count() === 1 ? $channelIds->first() : null;

        $channelStorage = $channels->mapWithKeys(function (Channel $channel) {
            $usedBytes = (int) $channel->used_bytes;
            $limitBytes = $channel->storage_limit_bytes !== null ? (int) $channel->storage_limit_bytes : null;
            $percent = $limitBytes && $limitBytes > 0 ? min(100, round(($usedBytes / $limitBytes) * 100, 1)) : 0.0;
            $remainingBytes = $limitBytes !== null ? max(0, $limitBytes - $usedBytes) : null;
            $isOver = $limitBytes !== null && $usedBytes >= $limitBytes;
            $barColor = $percent >= 90 ? 'bg-red-500' : ($percent >= 70 ? 'bg-yellow-500' : 'bg-green-500');
            $textColor = $isOver ? 'text-red-600' : 'text-gray-600';

            return [$channel->id => [
                'channel_id' => $channel->id,
                'channel_name' => $channel->display_name,
                'used_bytes' => $usedBytes,
                'limit_bytes' => $limitBytes,
                'used_human' => \App\Models\User::humanBytes($usedBytes),
                'limit_human' => $limitBytes !== null ? \App\Models\User::humanBytes($limitBytes) : 'Ilimitado',
                'remaining_human' => $remainingBytes !== null ? \App\Models\User::humanBytes($remainingBytes) : 'Ilimitado',
                'percent' => $percent,
                'is_over' => $isOver,
                'has_quota' => $limitBytes !== null,
                'bar_color' => $barColor,
                'text_color' => $textColor,
            ]];
        });

        $storage = $channelStorage->first();

        return view('client.media.index', [
            'mediaItems' => $mediaItems,
            'channels' => $channels,
            'accessibleChannels' => $channels,
            'ownedChannelIds' => $ownedChannelIds,
            'preselectedChannelId' => $preselectedChannelId,
            'currentChannel' => $request->string('channel_id')->toString(),
            'currentKind' => $request->string('kind')->toString(),
            'currentStatus' => $request->string('status')->toString(),
            'currentSearch' => $request->string('q')->toString(),
            'storage' => $storage,
            'channelStorage' => $channelStorage,
        ]);
    }
}