<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $channelIds = $user->effectiveChannelIds();

        $channels = Channel::with('owner:id,display_name,username,email')
            ->whereIn('id', $channelIds)
            ->orderBy('display_name')
            ->get(['id', 'owner_id', 'slug', 'display_name', 'public_hls_url', 'description', 'logo_path', 'root_path', 'status']);

        return response()->json([
            'channels' => $channels,
        ]);
    }
}