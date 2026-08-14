<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use App\Services\MediaThumbnailService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class MediaThumbnailController extends Controller
{
    public function __construct(private readonly MediaThumbnailService $thumbnails) {}

    public function show(MediaItem $mediaItem): Response
    {
        Gate::authorize('view', $mediaItem);

        $disk = Storage::disk(config('cloudstream.media.thumb_disk', 'public'));
        $relative = 'media/thumbs/' . $mediaItem->id . '.jpg';

        try {
            $path = $this->thumbnails->getOrGenerate($mediaItem);
        } catch (\Throwable $e) {
            \Log::error('thumbnail generation error: ' . $e->getMessage());
            return $this->svgPlaceholder($mediaItem);
        }

        if (! $disk->exists($relative)) {
            return $this->svgPlaceholder($mediaItem);
        }

        $localPath = $disk->path($relative);
        $contents = @file_get_contents($localPath);

        if ($contents === false) {
            return $this->svgPlaceholder($mediaItem);
        }

        if (str_starts_with(trim($contents), '<svg')) {
            return response($contents, 200, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        $response = new BinaryFileResponse($localPath);
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        return $response;
    }

    public function generate(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'channel_id' => ['nullable', 'string'],
            'force' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $user = $request->user();
        $force = (bool) $request->input('force', false);
        $limit = (int) $request->input('limit', 50);
        $channelId = $request->input('channel_id');

        $query = MediaItem::query()->whereIn('kind', ['video', 'image', 'ad']);

        if (! $user->isAdmin()) {
            $accessible = $user->effectiveChannelIds();
            if ($accessible->isEmpty()) {
                return response()->json([
                    'processed' => 0, 'failed' => 0, 'skipped' => 0, 'remaining' => 0,
                ]);
            }
            $query->whereIn('channel_id', $accessible->all());
            if ($channelId && ! $accessible->contains($channelId)) {
                return response()->json([
                    'processed' => 0, 'failed' => 0, 'skipped' => 0, 'remaining' => 0,
                ]);
            }
        }

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        $disk = \Illuminate\Support\Facades\Storage::disk(config('cloudstream.media.thumb_disk', 'public'));

        $candidateQuery = clone $query;
        if (! $force) {
            $candidateQuery->where(function ($q) {
                $q->whereNull('thumb_path')->orWhere('thumb_path', '');
            });
        }

        $candidates = $candidateQuery->orderByDesc('created_at')->limit($limit)->get();

        $processed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($candidates as $item) {
            if (! $force && $item->thumb_path && $disk->exists($item->thumb_path)) {
                $skipped++;
                continue;
            }
            try {
                $ok = $this->thumbnails->generateEager($item, $force);
                if ($ok) {
                    $processed++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                \Log::warning("admin bulk-thumb failed for {$item->id}: " . $e->getMessage());
            }
        }

        $remainingQuery = clone $query;
        if (! $force) {
            $remainingQuery->where(function ($q) {
                $q->whereNull('thumb_path')->orWhere('thumb_path', '');
            });
        }
        $remaining = $remainingQuery->count();

        return response()->json([
            'processed' => $processed,
            'failed' => $failed,
            'skipped' => $skipped,
            'remaining' => max(0, $remaining - $processed),
        ]);
    }

    protected function svgPlaceholder(MediaItem $item): Response
    {
        $mime = (string) $item->mime_type;
        $kind = 'other';
        if (str_starts_with($mime, 'video/')) $kind = 'video';
        elseif (str_starts_with($mime, 'image/')) $kind = 'image';
        elseif (str_starts_with($mime, 'audio/')) $kind = 'audio';

        $config = match ($kind) {
            'audio' => ['label' => 'AUDIO',  'color' => '#7c3aed'],
            'image' => ['label' => 'IMAGE',  'color' => '#0891b2'],
            'video' => ['label' => 'VIDEO',  'color' => '#313030'],
            default => ['label' => 'ARCHIVO','color' => '#475569'],
        };

        $c = $config['color'];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="225" viewBox="0 0 400 225">';
        $svg .= '<rect width="400" height="225" fill="' . $c . '"/>';
        $svg .= '<text x="200" y="125" font-family="sans-serif" font-size="20" fill="white" text-anchor="middle" opacity="0.9">' . $config['label'] . '</text>';
        $svg .= '</svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-cache',
        ]);
    }
}