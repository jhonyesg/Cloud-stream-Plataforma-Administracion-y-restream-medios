<?php

namespace App\Services;

use App\Models\MediaItem;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class MediaProbeService
{
    public function probe(MediaItem $item): bool
    {
        $root = rtrim((string) ($item->channel?->root_path ?? ''), '/');
        if ($root === '') {
            $item->update(['status' => 'failed', 'status_reason' => 'Channel has no root_path.']);
            return false;
        }

        $full = $root . '/' . $item->filename;
        if (! is_file($full)) {
            $item->update(['status' => 'failed', 'status_reason' => 'Archivo no encontrado.']);
            return false;
        }

        $ffprobe = trim((string) shell_exec('which ffprobe 2>/dev/null') ?? '');
        if ($ffprobe === '') {
            $item->update(['status' => 'failed', 'status_reason' => 'ffprobe no instalado en el servidor.']);
            return false;
        }

        $cmd = [$ffprobe, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $full];
        $process = new Process($cmd, null, null, null, 30);
        $process->run();

        if (! $process->isSuccessful()) {
            $item->update(['status' => 'failed', 'status_reason' => trim($process->getErrorOutput()) ?: 'ffprobe falló']);
            return false;
        }

        $payload = json_decode($process->getOutput(), true) ?? [];
        $format = $payload['format'] ?? [];
        $video = collect($payload['streams'] ?? [])->firstWhere('codec_type', 'video');
        $audio = collect($payload['streams'] ?? [])->firstWhere('codec_type', 'audio');

        $item->update([
            'status' => 'ready',
            'status_reason' => null,
            'size_bytes' => (int) ($format['size'] ?? filesize($full)),
            'mime_type' => 'video/' . pathinfo($full, PATHINFO_EXTENSION),
            'duration_sec' => isset($format['duration']) ? (float) $format['duration'] : null,
            'width' => $video['width'] ?? null,
            'height' => $video['height'] ?? null,
            'codec_video' => $video['codec_name'] ?? null,
            'codec_audio' => $audio['codec_name'] ?? null,
            'bitrate_kbps' => isset($format['bit_rate']) ? (int) round($format['bit_rate'] / 1000) : null,
            'metadata' => array_merge($item->metadata ?? [], ['probe' => $payload]),
        ]);

        Log::info("MediaItem probed", ['id' => $item->id, 'duration' => $item->duration_sec]);
        return true;
    }
}