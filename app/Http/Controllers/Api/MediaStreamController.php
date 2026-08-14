<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaStreamController extends Controller
{
    public function show(MediaItem $mediaItem): BinaryFileResponse
    {
        Gate::authorize('view', $mediaItem);

        $storage = app(\App\Services\MediaStorageService::class);
        $abs = $storage->absolutePathFor($mediaItem);

        if (! file_exists($abs)) {
            abort(404, 'File not found on disk');
        }

        $response = new BinaryFileResponse($abs);
        $response->setAutoLastModified(true);
        $response->headers->set('Content-Type', $mediaItem->mime_type ?? 'application/octet-stream');
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }
}