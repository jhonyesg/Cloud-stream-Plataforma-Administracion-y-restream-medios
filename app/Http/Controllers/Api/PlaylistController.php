<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MediaItem;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Services\PlaylistCueInserter;
use App\Services\PlaylistDurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlaylistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Playlist::with('channel:id,slug,display_name');

        if ($user->isClient()) {
            $query->whereHas('channel', fn($q) => $q->where('owner_id', $user->effectiveOwnerId()));
        }

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->string('channel_id'));
        }

        $perPage = min((int) $request->input('per_page', 50), 200);

        return response()->json($query->orderBy('name')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'loop' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        if ($data['is_default'] ?? false) {
            Playlist::where('channel_id', $data['channel_id'])->where('is_default', true)->update(['is_default' => false]);
        }

        $playlist = Playlist::create($data);
        AuditLog::record('create.playlist', 'playlist', $playlist->id, null, $playlist->toArray(), $playlist->channel_id);

        return response()->json($playlist, 201);
    }

    public function show(Playlist $playlist): JsonResponse
    {
        $playlist->load(['channel:id,slug,display_name', 'items.mediaItem']);
        $payload = $playlist->toArray();
        $payload['items'] = $this->serializeItems($playlist->items);
        return response()->json($payload);
    }

    private function serializeItems($items): array
    {
        $out = [];
        foreach ($items as $it) {
            $row = $it->toArray();
            $row['duration_sec'] = (float) $it->effectiveDuration();
            $row['end_sec'] = $it->effectiveEndSec();
            $row['media_item'] = $it->mediaItem ? [
                'id' => $it->mediaItem->id,
                'filename' => $it->mediaItem->filename,
                'duration_sec' => (float) $it->mediaItem->duration_sec,
                'kind' => $it->mediaItem->kind,
                'thumb_path' => $it->mediaItem->thumb_path,
            ] : null;
            $out[] = $row;
        }
        return $out;
    }

    public function update(Request $request, Playlist $playlist): JsonResponse
    {
        $before = $playlist->toArray();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'loop' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        if (($data['is_default'] ?? false) && ! $playlist->is_default) {
            DB::transaction(function () use ($playlist, $data) {
                Playlist::where('channel_id', $playlist->channel_id)
                    ->where('is_default', true)
                    ->where('id', '!=', $playlist->id)
                    ->update(['is_default' => false]);
                $playlist->update($data);
            });
        } else {
            $playlist->update($data);
        }

        AuditLog::record('update.playlist', 'playlist', $playlist->id, $before, $playlist->toArray(), $playlist->channel_id);
        return response()->json($playlist);
    }

    public function destroy(Playlist $playlist): JsonResponse
    {
        $channelId = $playlist->channel_id;
        $emissionLive = \App\Models\EmissionState::where('channel_id', $channelId)->where('status', 'live')->exists();
        if ($playlist->is_default && $emissionLive) {
            return response()->json(['message' => 'No se puede eliminar la playlist default mientras hay emisión activa.'], 422);
        }
        $playlist->delete();
        AuditLog::record('delete.playlist', 'playlist', $playlist->id, null, null, $channelId);
        return response()->json(['deleted' => true]);
    }

    public function addItem(Request $request, Playlist $playlist, PlaylistDurationService $durations): JsonResponse
    {
        $data = $request->validate([
            'media_item_id' => ['required', 'uuid'],
            'cue_in_sec' => ['nullable', 'numeric', 'min:0'],
            'cue_out_sec' => ['nullable', 'numeric', 'min:0'],
            'transition_in' => ['nullable', 'in:cut,fade,slide_left,slide_right,dissolve'],
        ]);

        $media = MediaItem::findOrFail($data['media_item_id']);
        if ($media->channel_id !== $playlist->channel_id) {
            return response()->json(['message' => 'El media item no pertenece al canal de la playlist.'], 422);
        }
        $candidate = new PlaylistItem($data);
        $candidate->setRelation('mediaItem', $media);
        $durations->assertValid($candidate);

        $nextPos = ($playlist->items()->max('position') ?? 0) + 1;
        $item = $playlist->items()->create($data + ['position' => $nextPos]);
        Playlist::recalculateDuration($playlist);
        return response()->json($item, 201);
    }

    public function reorderItems(Request $request, Playlist $playlist): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['uuid'],
        ]);

        $validIds = $playlist->items()->pluck('id')->toArray();
        $orderIds = $data['order'];
        if (count($orderIds) !== count(array_unique($orderIds))) {
            return response()->json(['message' => 'La lista de orden tiene duplicados.'], 422);
        }
        foreach ($orderIds as $id) {
            if (!in_array($id, $validIds, true)) {
                return response()->json(['message' => 'Item no pertenece a la playlist.'], 422);
            }
        }

        DB::transaction(function () use ($playlist, $orderIds) {
            $offset = 1000000;
            foreach ($orderIds as $i => $id) {
                PlaylistItem::where('playlist_id', $playlist->id)
                    ->where('id', $id)
                    ->update(['position' => $offset + $i + 1]);
            }
            foreach ($orderIds as $i => $id) {
                PlaylistItem::where('playlist_id', $playlist->id)
                    ->where('id', $id)
                    ->update(['position' => $i + 1]);
            }
        });

        Playlist::recalculateDuration($playlist);
        return response()->json(['reordered' => true]);
    }

    public function removeItem(Playlist $playlist, PlaylistItem $item): JsonResponse
    {
        if ($item->playlist_id !== $playlist->id) {
            return response()->json(['message' => 'No encontrado.'], 404);
        }
        DB::transaction(function () use ($playlist, $item) {
            $this->mergeSplitSiblingsAround($playlist, $item);

            if ($item->start_sec !== null) {
                $dur = (int) round($item->effectiveDuration());
                PlaylistItem::where('playlist_id', $playlist->id)
                    ->where('id', '!=', $item->id)
                    ->whereNotNull('start_sec')
                    ->where('start_sec', '>', (int) $item->start_sec)
                    ->update(['start_sec' => DB::raw('GREATEST(0, start_sec - ' . (int) $dur . ')')]);
            }

            $item->delete();

            $items = $playlist->items()->orderBy('position')->get();
            $offset = 1000000;
            foreach ($items as $i => $it) {
                PlaylistItem::where('id', $it->id)
                    ->update(['position' => $offset + $i + 1]);
            }
            foreach ($items as $i => $it) {
                PlaylistItem::where('id', $it->id)
                    ->update(['position' => $i + 1]);
            }

            $this->cleanupOrphanSplits($playlist);
        });
        Playlist::recalculateDuration($playlist);
        return response()->json(['deleted' => true]);
    }

    public function cleanup(Request $request, Playlist $playlist): JsonResponse
    {
        $normalized = Playlist::normalizeSplitOrdering($playlist);
        $merged = Playlist::cleanupOrphanSplits($playlist);
        $shifted = Playlist::normalizeCueConflicts($playlist);

        $clamped = PlaylistItem::where('playlist_id', $playlist->id)
            ->whereNotNull('start_sec')
            ->where('start_sec', '<', 0)
            ->update(['start_sec' => 0]);

        Playlist::recalculateDuration($playlist);
        return response()->json(['normalized' => $normalized, 'merged' => $merged, 'clamped' => $clamped, 'shifted' => $shifted]);
    }

    public function resetToSequential(Request $request, Playlist $playlist): JsonResponse
    {
        $reset = PlaylistItem::where('playlist_items.playlist_id', $playlist->id)
            ->whereNull('playlist_items.cue_in_sec')
            ->whereNull('playlist_items.cue_out_sec')
            ->whereNull('playlist_items.split_from_id')
            ->whereNotNull('playlist_items.start_sec')
            ->join('media_items', 'media_items.id', '=', 'playlist_items.media_item_id')
            ->whereIn('media_items.kind', ['video', 'audio', 'image'])
            ->update(['playlist_items.start_sec' => null]);

        Playlist::cleanupOrphanSplits($playlist);
        Playlist::recalculateDuration($playlist);
        return response()->json(['reset' => $reset]);
    }

    private function mergeSplitSiblingsAround(Playlist $playlist, PlaylistItem $removed): void
    {
        $removedStart = $removed->start_sec;

        $before = PlaylistItem::where('playlist_id', $playlist->id)
            ->where('id', '!=', $removed->id)
            ->whereNotNull('cue_out_sec')
            ->when($removedStart !== null, fn ($q) => $q->where('start_sec', '<', $removedStart))
            ->orderByDesc('start_sec')
            ->first();

        $after = PlaylistItem::where('playlist_id', $playlist->id)
            ->where('id', '!=', $removed->id)
            ->whereNotNull('cue_in_sec')
            ->whereNotNull('split_from_id')
            ->when($removedStart !== null, fn ($q) => $q->where('start_sec', '>', $removedStart))
            ->orderBy('start_sec')
            ->first();

        if ($before && $after && $after->split_from_id === $before->id && $after->media_item_id === $before->media_item_id) {
            $before->cue_out_sec = null;
            $before->save();
            $after->delete();
        }
    }

    private function cleanupOrphanSplits(Playlist $playlist): void
    {
        $items = $playlist->items()->orderBy('position')->get();
        $deletedAny = true;
        while ($deletedAny) {
            $deletedAny = false;
            $items = $playlist->items()->orderBy('position')->get();
            foreach ($items as $tail) {
                if ($tail->cue_in_sec === null || $tail->split_from_id === null) continue;
                $parent = $items->firstWhere('id', $tail->split_from_id);
                if (!$parent || $parent->cue_out_sec === null) continue;
                if ($parent->media_item_id !== $tail->media_item_id) continue;

                $parentStart = $parent->start_sec ?? 0;
                $tailStart = $tail->start_sec ?? 0;

                $hasBetween = $items->contains(function ($it) use ($parent, $tail, $parentStart, $tailStart) {
                    if ($it->id === $parent->id || $it->id === $tail->id) return false;
                    $s = $it->start_sec;
                    if ($s === null) return false;
                    return $s > $parentStart && $s < $tailStart;
                });

                if (!$hasBetween) {
                    $parent->cue_out_sec = null;
                    $parent->save();
                    $tail->delete();
                    $deletedAny = true;
                    break;
                }
            }
        }
    }

    public function insertCue(Request $request, Playlist $playlist, PlaylistCueInserter $inserter): JsonResponse
    {
        if (! $request->user()->canAccessChannel($playlist->channel)) {
            abort(403, 'No tienes acceso a este canal.');
        }

        $data = $request->validate([
            'media_item_id' => ['required', 'uuid'],
            'at_sec' => ['required', 'integer', 'min:0', 'max:86400'],
        ]);

        $cue = MediaItem::findOrFail($data['media_item_id']);

        try {
            $result = $inserter->insertAt($playlist, (int) $data['at_sec'], $cue);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLog::record(
            'insert.cue.playlist',
            'playlist',
            $playlist->id,
            null,
            [
                'cue_media_item_id' => $cue->id,
                'at_sec' => (int) $data['at_sec'],
                'split_from_id' => $result['split_from']?->id,
                'cue_duration_sec' => $result['cue_duration_sec'],
            ],
            $playlist->channel_id
        );

        return response()->json([
            'inserted' => $result['inserted']->load('mediaItem'),
            'split_from' => $result['split_from']?->load('mediaItem'),
            'cue_duration_sec' => $result['cue_duration_sec'],
        ], 201);
    }
}
