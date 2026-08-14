<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\MediaItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class MediaStorageService
{
    public function absolutePathFor(MediaItem $item): string
    {
        $root = rtrim((string) ($item->channel?->root_path ?? ''), '/');
        if ($root === '') {
            throw new RuntimeException('Channel has no root_path configured.');
        }
        return $root . '/' . $item->filename;
    }

    public function write(UploadedFile $file, Channel $channel, string $filename): MediaItem
    {
        if (! $channel->root_path) {
            throw new RuntimeException('Channel has no root_path configured.');
        }

        $root = rtrim($channel->root_path, '/');

        if (MediaItem::where('channel_id', $channel->id)->where('filename', $filename)->exists()) {
            throw new RuntimeException('A media item with that filename already exists in this channel.');
        }

        if (file_exists($root . '/' . $filename)) {
            throw new RuntimeException('File already exists at destination.');
        }

        if (! is_dir($root)) {
            @mkdir($root, 0755, true);
        }

        $size = $file->getSize() ?: null;
        $mime = $file->getMimeType() ?: null;

        $file->move($root, $filename);

        return MediaItem::create([
            'channel_id' => $channel->id,
            'filename' => $filename,
            'sha256' => null,
            'size_bytes' => $size,
            'mime_type' => $mime,
            'kind' => $this->kindFromMime($mime),
            'status' => 'pending',
            'status_reason' => null,
            'duration_sec' => null,
            'width' => null,
            'height' => null,
            'codec_video' => null,
            'codec_audio' => null,
            'bitrate_kbps' => null,
            'thumb_path' => null,
            'metadata' => [],
        ]);
    }

    public function rename(MediaItem $item, string $newFilename): MediaItem
    {
        if (! $this->isSafeFilename($newFilename)) {
            throw new RuntimeException('Invalid filename.');
        }

        if ($newFilename === $item->filename) {
            return $item;
        }

        if (MediaItem::where('channel_id', $item->channel_id)
            ->where('filename', $newFilename)
            ->where('id', '!=', $item->id)
            ->exists()) {
            throw new RuntimeException('Another item with that filename already exists in this channel.');
        }

        $oldAbs = $this->absolutePathFor($item);
        $newAbs = rtrim(dirname($oldAbs), '/') . '/' . $newFilename;

        if (! file_exists($oldAbs)) {
            throw new RuntimeException('Source file not found on disk.');
        }
        if (file_exists($newAbs)) {
            throw new RuntimeException('Destination file already exists.');
        }

        if (! @rename($oldAbs, $newAbs)) {
            throw new RuntimeException('Failed to rename file on disk.');
        }

        $item->filename = $newFilename;
        $item->save();

        return $item->fresh();
    }

    public function deleteFile(MediaItem $item): bool
    {
        try {
            $abs = $this->absolutePathFor($item);
            if (file_exists($abs)) {
                return @unlink($abs);
            }
            return true;
        } catch (\Throwable $e) {
            \Log::warning('media delete failed: ' . $e->getMessage());
            return false;
        }
    }

    public function exists(MediaItem $item): bool
    {
        return file_exists($this->absolutePathFor($item));
    }

    protected function kindFromMime(?string $mime): string
    {
        if (! $mime) {
            return 'other';
        }
        if (Str::startsWith($mime, 'video/')) {
            return 'video';
        }
        if (Str::startsWith($mime, 'image/')) {
            return 'image';
        }
        if (Str::startsWith($mime, 'audio/')) {
            return 'audio';
        }
        return 'other';
    }

    protected function isSafeFilename(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._\/\- ]+$/', $name) && ! str_contains($name, '..') && strlen($name) <= 512;
    }
}