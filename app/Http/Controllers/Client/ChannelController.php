<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $ownedChannelIds = $user->channels()->pluck('channels.id')->toArray();
        $assignedIds = $user->assignedChannels()->pluck('channels.id')->toArray();
        $channelIds = collect($ownedChannelIds)->merge($assignedIds)->unique()->values();

        $channels = Channel::whereIn('id', $channelIds)
            ->where('status', '!=', 'archived')
            ->orderBy('display_name')
            ->paginate(25);

        return view('client.channels.index', [
            'channels' => $channels,
            'ownedChannelIds' => $ownedChannelIds,
        ]);
    }

    public function update(Request $request, Channel $channel)
    {
        $user = $request->user();
        if (! $user->canAccessChannel($channel)) {
            abort(403, 'No tienes acceso a este canal.');
        }

        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'root_path' => ['sometimes', 'string', 'max:1024'],
            'public_hls_url' => ['nullable', 'string', 'max:500', 'url'],
        ]);

        $channel->update($data);

        return redirect()->route('client.channels')->with('status', 'Canal actualizado correctamente.');
    }
}