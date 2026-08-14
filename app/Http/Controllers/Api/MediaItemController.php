<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use App\Services\MediaStorageService;
use App\Services\MediaThumbnailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MediaItemController extends Controller
{
    public function __construct(
        private readonly MediaStorageService $storage,
        private readonly MediaThumbnailService $thumbnails,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = MediaItem::query();

        if ($user->isClient()) {
            $channelIds = $user->effectiveChannelIds();
            $query->whereIn('channel_id', $channelIds);
        }

        if ($request->filled('kind')) $query->where('kind', $request->string('kind'));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('channel_id')) $query->where('channel_id', $request->string('channel_id'));
        if ($request->filled('q')) {
            $term = '%' . $request->string('q') . '%';
            $query->where('filename', 'like', $term);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $page = $query->with(['channel:id,slug,display_name'])
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        $items = collect($page->items())->map(fn ($item) => $this->decorate($item));
        return response()->json([
            'data' => $items,
            'next_cursor' => $page->nextCursor()?->encode(),
            'prev_cursor' => $page->previousCursor()?->encode(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['required', 'uuid'],
            'filename' => ['required', 'string', 'max:512'],
            'kind' => ['required', 'in:video,ad,image,audio,other'],
        ]);

        $item = MediaItem::create($data + [
            'sha256' => null,
            'size_bytes' => null,
            'mime_type' => null,
            'status' => 'pending',
            'metadata' => [],
        ]);

        return response()->json($this->decorate($item), 201);
    }

    public function show(MediaItem $mediaItem): JsonResponse
    {
        Gate::authorize('view', $mediaItem);
        $mediaItem->load(['channel:id,slug,display_name']);
        return response()->json($this->decorate($mediaItem));
    }

    public function update(Request $request, MediaItem $mediaItem): JsonResponse
    {
        Gate::authorize('update', $mediaItem);

        $data = $request->validate([
            'filename' => ['sometimes', 'string', 'max:512'],
            'kind' => ['sometimes', 'in:video,ad,image,audio,other'],
            'metadata' => ['nullable', 'array'],
            'channel_id' => ['nullable', 'uuid'],
        ]);

        $oldFilename = $mediaItem->filename;

        if (isset($data['filename']) && $data['filename'] !== $oldFilename) {
            try {
                $this->storage->rename($mediaItem, $data['filename']);
                $this->thumbnails->clear($mediaItem);
            } catch (\Throwable $e) {
                Log::error('media rename failed: ' . $e->getMessage());
                return response()->json(['message' => $e->getMessage()], 422);
            }
            unset($data['filename']);
        }

        if (! empty($data)) {
            $mediaItem->update($data);
        }

        return response()->json($this->decorate($mediaItem->fresh()));
    }

    public function destroy(MediaItem $mediaItem): JsonResponse
    {
        Gate::authorize('delete', $mediaItem);

        $affectedPlaylistIds = \App\Models\PlaylistItem::where('media_item_id', $mediaItem->id)
            ->pluck('playlist_id')
            ->unique()
            ->toArray();

        $this->storage->deleteFile($mediaItem);
        $this->thumbnails->clear($mediaItem);
        $mediaItem->delete();

        $this->reorderPlaylists($affectedPlaylistIds);

        return response()->json(['deleted' => true]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        $deleted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($data['ids'] as $id) {
            $item = MediaItem::find($id);
            if (! $item) {
                $skipped++;
                continue;
            }
            try {
                Gate::authorize('delete', $item);
            } catch (\Throwable $e) {
                $skipped++;
                continue;
            }
            try {
                $affectedIds = \App\Models\PlaylistItem::where('media_item_id', $item->id)
                    ->pluck('playlist_id')->unique()->toArray();
                $this->storage->deleteFile($item);
                $this->thumbnails->clear($item);
                $item->delete();
                $this->reorderPlaylists($affectedIds);
                $deleted++;
            } catch (\Throwable $e) {
                Log::warning('bulk-delete failed for ' . $id . ': ' . $e->getMessage());
                $failed++;
            }
        }

        return response()->json([
            'deleted' => $deleted,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }

    public function bulkThumbnails(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
            'force' => ['nullable', 'boolean'],
        ]);

        $force = (bool) $request->input('force', true);

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($data['ids'] as $id) {
            $item = MediaItem::find($id);
            if (! $item) {
                $skipped++;
                continue;
            }
            try {
                Gate::authorize('view', $item);
            } catch (\Throwable $e) {
                $skipped++;
                continue;
            }
            try {
                $ok = $this->thumbnails->generateEager($item, $force);
                if ($ok) {
                    $processed++;
                } else {
                    // generateEager returned false: the regeneration attempt did not produce a real
                    // thumbnail (e.g. placeholder fallback for a video/image item). Tally as failed.
                    // $skipped is reserved for "item not found" or "not authorised".
                    $item->refresh();
                    $failed++;
                    Log::warning('bulk-thumbnails regeneration did not produce a real thumbnail for ' . $id . ': ' . ($item->status_reason ?? 'unknown'));
                }
            } catch (\Throwable $e) {
                Log::warning('bulk-thumbnails failed for ' . $id . ': ' . $e->getMessage());
                $failed++;
            }
        }

        return response()->json([
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }

    protected function decorate(MediaItem $item): array
    {
        $array = $item->toArray();
        $array['play_url'] = $item->playUrl();
        $array['thumb_url'] = $item->thumbUrl();
        return $array;
    }

    protected function reorderPlaylists(array $playlistIds): void
    {
        foreach ($playlistIds as $pid) {
            $playlist = \App\Models\Playlist::find($pid);
            if (! $playlist) continue;

            $items = \App\Models\PlaylistItem::where('playlist_id', $pid)
                ->orderBy('position')
                ->get();

            $offset = 1000000;
            foreach ($items as $i => $item) {
                \App\Models\PlaylistItem::where('id', $item->id)
                    ->update(['position' => $offset + $i + 1]);
            }
            foreach ($items as $i => $item) {
                \App\Models\PlaylistItem::where('id', $item->id)
                    ->update(['position' => $i + 1]);
            }

            \App\Models\Playlist::recalculateDuration($playlist->fresh());
        }
    }
}