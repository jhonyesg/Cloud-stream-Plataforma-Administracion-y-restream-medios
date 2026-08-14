<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\MediaItem;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $query = MediaItem::query();

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

        $allowedPerPage = [25, 50, 100];
        $perPage = (int) $request->input('per_page', 50);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 50;
        }

        $mediaItems = $query->with(['channel:id,slug,display_name'])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->only(['channel_id', 'kind', 'status', 'q', 'per_page']));

        $channels = Channel::orderBy('display_name')->get(['id', 'display_name', 'slug']);
        $availableChannels = $channels;

        return view('admin.media.index', [
            'mediaItems' => $mediaItems,
            'channels' => $channels,
            'availableChannels' => $availableChannels,
            'currentChannel' => $request->string('channel_id')->toString(),
            'currentKind' => $request->string('kind')->toString(),
            'currentStatus' => $request->string('status')->toString(),
            'currentSearch' => $request->string('q')->toString(),
            'currentPerPage' => $perPage,
            'allowedPerPage' => $allowedPerPage,
        ]);
    }
}