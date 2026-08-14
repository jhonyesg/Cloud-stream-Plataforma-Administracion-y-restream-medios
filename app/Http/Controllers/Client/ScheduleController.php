<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\MediaItem;
use App\Models\Playlist;
use App\Models\ScheduleTemplate;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $channelIds = $user->effectiveChannelIds();

        $channels = Channel::whereIn('id', $channelIds)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'slug']);

        $now = now();
        $channelId = $request->query('channel_id');
        $year = (int) $request->query('year', $now->year);
        $month = (int) $request->query('month', $now->month);

        $defaultDay = ($year === (int) $now->year && $month === (int) $now->month)
            ? (int) $now->day
            : 1;
        $selectedDay = (int) $request->query('day', $defaultDay);
        $selectedDay = max(1, min($selectedDay, (int) cal_days_in_month(CAL_GREGORIAN, $month, $year)));

        if (! $channelId && $channels->isNotEmpty()) {
            $channelId = $channels->first()->id;
        }

        $template = null;
        $blocksByDay = collect();
        $playlists = collect();
        $mediaItems = collect();

        if ($channelId && in_array($channelId, $channelIds->toArray())) {
            $template = ScheduleTemplate::where('channel_id', $channelId)
                ->where('year', $year)
                ->where('month', $month)
                ->with(['blocks' => fn ($q) => $q->orderBy('day_of_month'), 'blocks.playlist:id,name,total_duration_sec'])
                ->first();

            if ($template) {
                $blocksByDay = $template->blocks->keyBy('day_of_month');
            }

            $playlists = Playlist::where('channel_id', $channelId)
                ->withCount('items')
                ->orderBy('name')
                ->get(['id', 'name', 'total_duration_sec']);

            $mediaItems = MediaItem::where('channel_id', $channelId)
                ->whereIn('kind', ['video', 'ad'])
                ->orderBy('kind')
                ->orderBy('filename')
                ->get(['id', 'filename', 'kind', 'status', 'duration_sec']);
        }

        return view('client.scheduler.index', [
            'channels' => $channels,
            'currentChannel' => $channelId,
            'currentChannelSlug' => $channels->firstWhere('id', $channelId)?->slug ?? '',
            'currentChannelName' => $channels->firstWhere('id', $channelId)?->display_name ?? '',
            'year' => $year,
            'month' => $month,
            'selectedDay' => $selectedDay,
            'template' => $template,
            'blocksByDay' => $blocksByDay,
            'playlists' => $playlists,
            'mediaItems' => $mediaItems,
        ]);
    }
}
