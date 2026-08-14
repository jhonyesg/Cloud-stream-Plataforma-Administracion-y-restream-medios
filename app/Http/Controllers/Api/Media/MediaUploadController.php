<?php

namespace App\Http\Controllers\Api\Media;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\MediaItem;
use App\Models\User;
use App\Services\MediaStorageService;
use App\Services\MediaThumbnailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MediaUploadController extends Controller
{
    public function __construct(
        private readonly MediaStorageService $storage,
        private readonly MediaThumbnailService $thumbnails,
    ) {}

    public function upload(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        $this->ensureAccess($request, $channel);

        $request->validate([
            'file' => ['required', 'file', 'max:' . $this->maxKilobytes()],
        ], [], ['file' => 'archivo']);

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $this->ensureAllowedMime($mime);

        $filename = $this->sanitizeFilename($file->getClientOriginalName());
        if ($filename === '') {
            throw ValidationException::withMessages(['file' => 'Nombre de archivo inválido.']);
        }

        $size = (int) ($file->getSize() ?: 0);
        if (! $request->user()?->isAdmin()) {
            $this->ensureQuota($channel, $size);
        }

        try {
            $item = $this->storage->write($file, $channel, $filename);
        } catch (\Throwable $e) {
            Log::error('upload failed: ' . $e->getMessage());
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $this->markReadyOrFailed($item);

        return response()->json($this->decorate($item->fresh()), 201);
    }

    public function bulkUpload(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        $this->ensureAccess($request, $channel);

        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [$files];
        }

        $maxFiles = (int) config('cloudstream.media.bulk_max_files', 20);
        if (count($files) > $maxFiles) {
            throw ValidationException::withMessages(['files' => "Máximo {$maxFiles} archivos por envío."]);
        }

        $uploaded = [];
        $failed = [];
        $channel = $this->resolveChannel($request);
        $isAdmin = $request->user()?->isAdmin() ?? false;
        $currentUsed = (int) ($channel->used_bytes ?? 0);

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                $failed[] = ['filename' => $file?->getClientOriginalName() ?? '(vacío)', 'reason' => 'Archivo inválido.'];
                continue;
            }
            $mime = $file->getMimeType();
            if (! $this->isAllowedMime($mime)) {
                $failed[] = ['filename' => $file->getClientOriginalName(), 'reason' => 'Tipo de archivo no permitido.'];
                continue;
            }
            if ($file->getSize() > $this->maxBytes()) {
                $failed[] = ['filename' => $file->getClientOriginalName(), 'reason' => 'Archivo demasiado grande.'];
                continue;
            }
            $filename = $this->sanitizeFilename($file->getClientOriginalName());
            if ($filename === '') {
                $failed[] = ['filename' => $file->getClientOriginalName(), 'reason' => 'Nombre inválido.'];
                continue;
            }
            $size = (int) ($file->getSize() ?: 0);
            if (! $isAdmin && $channel->storage_limit_bytes !== null && ($currentUsed + $size) > (int) $channel->storage_limit_bytes) {
                $failed[] = ['filename' => $file->getClientOriginalName(), 'reason' => 'quota_exceeded'];
                continue;
            }
            try {
                $item = $this->storage->write($file, $channel, $filename);
                $written = (int) ($item->size_bytes ?? $size);
                $currentUsed += $written;
                $this->markReadyOrFailed($item);
                $uploaded[] = $this->decorate($item->fresh());
            } catch (\Throwable $e) {
                Log::warning('bulk upload item failed: ' . $e->getMessage());
                $failed[] = ['filename' => $file->getClientOriginalName(), 'reason' => $e->getMessage()];
            }
        }

        return response()->json(['uploaded' => $uploaded, 'failed' => $failed], 201);
    }

    protected function ensureQuota(Channel $channel, int $additionalBytes): void
    {
        if ($channel->storage_limit_bytes === null) {
            return;
        }
        if (((int) $channel->used_bytes + $additionalBytes) > (int) $channel->storage_limit_bytes) {
            $used = \App\Models\User::humanBytes((int) $channel->used_bytes);
            $limit = \App\Models\User::humanBytes((int) $channel->storage_limit_bytes);
            throw ValidationException::withMessages([
                'file' => "Has alcanzado el límite de almacenamiento del canal. Estás usando {$used} de {$limit}. Elimina archivos para liberar espacio.",
            ]);
        }
    }

    protected function resolveChannel(Request $request): Channel
    {
        $channelId = $request->input('channel_id');
        if (! $channelId) {
            throw ValidationException::withMessages(['channel_id' => 'channel_id es requerido.']);
        }
        $channel = Channel::find($channelId);
        if (! $channel) {
            throw ValidationException::withMessages(['channel_id' => 'Canal no encontrado.']);
        }
        if (! $channel->root_path) {
            throw ValidationException::withMessages(['channel_id' => 'El canal no tiene root_path configurado.']);
        }
        return $channel;
    }

    protected function ensureAccess(Request $request, Channel $channel): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if ($user->isAdmin()) {
            return;
        }
        if (! $user->canAccessChannel($channel)) {
            abort(403, 'No tienes acceso a este canal.');
        }
    }

    protected function ensureAllowedMime(?string $mime): void
    {
        if (! $this->isAllowedMime($mime)) {
            throw ValidationException::withMessages(['file' => 'Tipo de archivo no permitido.']);
        }
    }

    protected function isAllowedMime(?string $mime): bool
    {
        if (! $mime) {
            return false;
        }
        return in_array($mime, (array) config('cloudstream.media.allowed_mimes', []), true);
    }

    protected function sanitizeFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._\- ]+/', '_', $name);
        return trim($name);
    }

    protected function maxBytes(): int
    {
        return (int) config('cloudstream.media.max_upload_size', 524288000);
    }

    protected function maxKilobytes(): int
    {
        return (int) ceil($this->maxBytes() / 1024);
    }

    protected function decorate(MediaItem $item): array
    {
        $array = $item->toArray();
        $array['play_url'] = $item->playUrl();
        $array['thumb_url'] = $item->thumbUrl();
        return $array;
    }

    protected function markReadyOrFailed(MediaItem $item): void
    {
        try {
            $ok = $this->thumbnails->generateEager($item);
        } catch (\Throwable $e) {
            $ok = false;
            Log::warning("thumbnail generation threw for {$item->id}: " . $e->getMessage());
        }

        $item->refresh();

        if ($ok) {
            return;
        }

        $item->status = 'failed';
        $item->status_reason = 'thumbnail generation failed';
        $item->save();
    }
}