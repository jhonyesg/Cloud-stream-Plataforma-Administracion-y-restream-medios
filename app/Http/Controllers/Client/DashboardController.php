<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\MediaItem;
use App\Models\Playlist;
use App\Services\MediaserverMetricsAggregator;
use Illuminate\Http\Request;
use Throwable;

class DashboardController extends Controller
{
    public function index(Request $request, MediaserverMetricsAggregator $metrics)
    {
        $user = $request->user();

        $channelIds = $user->effectiveChannelIds();
        $channelIdsArray = $channelIds instanceof \Illuminate\Support\Collection ? $channelIds->all() : (array) $channelIds;
        $channels = Channel::with('playlists')
            ->whereIn('id', $channelIds)
            ->orderBy('display_name')
            ->get();

        $stats = [
            'channels' => $channels->count(),
            'media_items' => MediaItem::whereIn('channel_id', $channelIds)->count(),
            'playlists' => Playlist::whereIn('channel_id', $channelIds)->count(),
            'ads' => MediaItem::where('kind', 'ad')
                ->whereIn('channel_id', $channelIds)
                ->count(),
        ];

        $mediaserver = $this->mediaserverMetrics($metrics, $channelIdsArray);

        return view('client.dashboard', compact('channels', 'stats', 'mediaserver'));
    }

    private function mediaserverMetrics(MediaserverMetricsAggregator $metrics, array $channelIds): array
    {
        try {
            if (! $metrics->available()) {
                return ['available' => false, 'streams' => [], 'rules' => []];
            }
            $data = $metrics->forClient($channelIds);
            return [
                'available' => true,
                'streams' => $data['streams'],
                'rules' => $data['rules'],
            ];
        } catch (Throwable $e) {
            report($e);
            return ['available' => false, 'streams' => [], 'rules' => []];
        }
    }
}