<?php

namespace App\Services;

use App\Models\MediaItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaThumbnailService
{
    public function getOrGenerate(MediaItem $item): string
    {
        $disk = Storage::disk(config('cloudstream.media.thumb_disk', 'public'));
        $path = 'media/thumbs/' . $item->id . '.jpg';

        if ($disk->exists($path)) {
            return $disk->path($path);
        }

        try {
            if (str_starts_with((string) $item->mime_type, 'video/')) {
                $this->generateVideoThumb($item, $path, $disk);
            } elseif (str_starts_with((string) $item->mime_type, 'image/')) {
                $this->generateImageThumb($item, $path, $disk);
            } else {
                $this->generatePlaceholder($path, $disk, $this->kindForPlaceholder($item));
            }
        } catch (\Throwable $e) {
            Log::warning('thumbnail generation failed, using placeholder: ' . $e->getMessage());
            try {
                $this->generatePlaceholder($path, $disk, $this->kindForPlaceholder($item));
            } catch (\Throwable $e2) {
                Log::error('thumbnail placeholder failed: ' . $e2->getMessage());
            }
        }

        if ($disk->exists($path)) {
            if (in_array($item->kind, ['video', 'image', 'ad'], true) && $this->isPlaceholderThumb($disk, $path)) {
                $reason = "thumbnail generation fell back to placeholder for {$item->kind}";
                $this->failWithoutThumb($item, $path, $disk, $reason);
                return $disk->path($path);
            }

            if ($item->thumb_path !== $path) {
                $item->thumb_path = $path;
                if ($item->isDirty(['thumb_path'])) {
                    $item->save();
                }
            }
            $this->markReadyIfReady($item);
            return $disk->path($path);
        }

        return $this->placeholderPath($disk, 'other');
    }

    public function generateEager(MediaItem $item, bool $force = false): bool
    {
        $mime = (string) $item->mime_type;
        $disk = Storage::disk(config('cloudstream.media.thumb_disk', 'public'));
        $relPath = 'media/thumbs/' . $item->id . '.jpg';

        if ($disk->exists($relPath) && $item->thumb_path === $relPath) {
            if ($force || $this->isPlaceholderThumb($disk, $relPath)) {
                $disk->delete($relPath);
                Log::info("thumbnail eager: regenerating {$item->id} (force=" . ($force ? '1' : '0') . ", was-placeholder=" . ($this->isPlaceholderThumb($disk, $relPath) ? '1' : '0') . ')');
            } else {
                $this->markReadyIfReady($item);
                return true;
            }
        }
        if (! $force && $disk->exists($relPath) && $item->thumb_path === null) {
            $item->thumb_path = $relPath;
            $item->save();
            $this->markReadyIfReady($item);
            return true;
        }

        if (! str_starts_with($mime, 'video/') && ! str_starts_with($mime, 'image/')) {
            $this->generatePlaceholder($relPath, $disk, $this->kindForPlaceholder($item));
            if (! $disk->exists($relPath)) {
                return false;
            }
            $item->thumb_path = $relPath;
            $item->save();
            $this->markReadyIfReady($item);
            return true;
        }
        if (! in_array($item->kind, ['video', 'image', 'ad'], true)) {
            $this->generatePlaceholder($relPath, $disk, $this->kindForPlaceholder($item));
            if (! $disk->exists($relPath)) {
                return false;
            }
            $item->thumb_path = $relPath;
            $item->save();
            $this->markReadyIfReady($item);
            return true;
        }

        $source = $this->resolveSourcePath($item);
        if (! $source || ! file_exists($source)) {
            Log::warning("thumbnail eager: source missing for {$item->id} ({$item->filename})");
            return false;
        }

        try {
            if (str_starts_with($mime, 'video/')) {
                $this->generateVideoThumb($item, $relPath, $disk);
            } else {
                $this->generateImageThumb($item, $relPath, $disk);
            }
        } catch (\Throwable $e) {
            Log::warning("thumbnail eager failed for {$item->id}: " . $e->getMessage());
            return false;
        }

        if (! $disk->exists($relPath)) {
            Log::warning("thumbnail eager: no file produced for {$item->id}");
            return false;
        }

        if (in_array($item->kind, ['video', 'image', 'ad'], true) && $this->isPlaceholderThumb($disk, $relPath)) {
            $this->failWithoutThumb($item, $relPath, $disk, "thumbnail generation fell back to placeholder for {$item->kind}");
            return false;
        }

        $item->thumb_path = $relPath;
        $this->applyProbeMetadata($item, $source);
        $item->save();
        $this->markReadyIfReady($item);

        return true;
    }

    protected function applyProbeMetadata(MediaItem $item, string $source): void
    {
        $meta = $this->probeMetadata($source);
        if (empty($meta)) {
            return;
        }

        $dirty = false;
        if (empty($item->duration_sec) && isset($meta['duration_sec'])) {
            $item->duration_sec = $meta['duration_sec'];
            $dirty = true;
        }
        if (empty($item->width) && isset($meta['width'])) {
            $item->width = $meta['width'];
            $dirty = true;
        }
        if (empty($item->height) && isset($meta['height'])) {
            $item->height = $meta['height'];
            $dirty = true;
        }
        if (empty($item->codec_video) && isset($meta['codec_video'])) {
            $item->codec_video = $meta['codec_video'];
            $dirty = true;
        }
        if (empty($item->codec_audio) && isset($meta['codec_audio'])) {
            $item->codec_audio = $meta['codec_audio'];
            $dirty = true;
        }
        if (empty($item->bitrate_kbps) && isset($meta['bitrate_kbps'])) {
            $item->bitrate_kbps = $meta['bitrate_kbps'];
            $dirty = true;
        }

        if ($dirty) {
            // Persist alongside the thumb_path save done by the caller.
        }
    }

    public function probeMetadata(string $source): array
    {
        $ffprobe = trim((string) \shell_exec('command -v ffprobe 2>/dev/null'));
        if ($ffprobe === '') {
            return [];
        }

        $cmd = 'timeout 15 ' . escapeshellcmd($ffprobe)
            . ' -v quiet -print_format json -show_format -show_streams '
            . escapeshellarg($source)
            . ' 2>&1';

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        if ($exit !== 0) {
            return [];
        }

        $json = json_decode(implode("\n", $output), true);
        if (! is_array($json)) {
            return [];
        }

        $out = [];
        if (isset($json['format']['duration'])) {
            $out['duration_sec'] = (float) $json['format']['duration'];
        }
        if (isset($json['format']['bit_rate'])) {
            $out['bitrate_kbps'] = (int) round(((int) $json['format']['bit_rate']) / 1000);
        }

        $videoStream = null;
        $audioStream = null;
        foreach (($json['streams'] ?? []) as $stream) {
            if (($stream['codec_type'] ?? null) === 'video' && $videoStream === null) {
                $videoStream = $stream;
            } elseif (($stream['codec_type'] ?? null) === 'audio' && $audioStream === null) {
                $audioStream = $stream;
            }
        }
        if ($videoStream) {
            if (isset($videoStream['width']))  $out['width']  = (int) $videoStream['width'];
            if (isset($videoStream['height'])) $out['height'] = (int) $videoStream['height'];
            if (! empty($videoStream['codec_name'])) $out['codec_video'] = (string) $videoStream['codec_name'];
        }
        if ($audioStream && ! empty($audioStream['codec_name'])) {
            $out['codec_audio'] = (string) $audioStream['codec_name'];
        }

        return $out;
    }

    public function clear(MediaItem $item): void
    {
        $disk = Storage::disk(config('cloudstream.media.thumb_disk', 'public'));
        $path = 'media/thumbs/' . $item->id . '.jpg';
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    protected function kindForPlaceholder(MediaItem $item): string
    {
        if (str_starts_with((string) $item->mime_type, 'audio/')) return 'audio';
        if (str_starts_with((string) $item->mime_type, 'image/')) return 'image';
        if (str_starts_with((string) $item->mime_type, 'video/')) return 'video';
        return 'other';
    }

    protected function generateVideoThumb(MediaItem $item, string $path, $disk): void
    {
        $source = $this->resolveSourcePath($item);
        if (! $source || ! file_exists($source)) {
            $this->generatePlaceholder($path, $disk, 'video');
            return;
        }

        $candidates = $this->videoSeekCandidates($item, $source);

        foreach ($candidates as $seek) {
            $tmp = tempnam(sys_get_temp_dir(), 'thumb_') . '.jpg';
            $cmd = 'timeout 30 ffmpeg -y -ss ' . escapeshellarg((string) $seek)
                . ' -i ' . escapeshellarg($source)
                . ' -vframes 1 -q:v 2 '
                . escapeshellarg($tmp)
                . ' 2>&1';

            $output = [];
            $exit = 0;
            exec($cmd, $output, $exit);

            if ($exit === 0 && file_exists($tmp) && filesize($tmp) > 0) {
                $disk->put($path, file_get_contents($tmp));
                @unlink($tmp);
                return;
            }
            @unlink($tmp);
        }

        $this->generatePlaceholder($path, $disk, 'video');
    }

    protected function videoSeekCandidates(MediaItem $item, string $source): array
    {
        $duration = $item->duration_sec;
        if (! $duration) {
            $probed = $this->probeMetadata($source);
            if (isset($probed['duration_sec'])) {
                $duration = $probed['duration_sec'];
            }
        }

        if ($duration && $duration > 0) {
            $primary = max(1.0, (float) $duration * 0.10);
            $middle  = max(1.0, (float) $duration * 0.50);
            return [$primary, $middle, 1.0, 0.0];
        }

        return [1.0, 0.0];
    }

    protected function generateImageThumb(MediaItem $item, string $path, $disk): void
    {
        $source = $this->resolveSourcePath($item);
        if (! $source || ! file_exists($source)) {
            $this->generatePlaceholder($path, $disk, 'image');
            return;
        }

        if (! function_exists('imagecreatefromstring')) {
            $this->generateImageThumbImagick($item, $source, $path, $disk);
            return;
        }

        $contents = file_get_contents($source);
        $img = @imagecreatefromstring($contents);
        if (! $img) {
            $this->generateImageThumbImagick($item, $source, $path, $disk);
            return;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $max = 400;
        $scale = min(1.0, $max / max($w, $h));
        $nw = max(1, (int) ($w * $scale));
        $nh = max(1, (int) ($h * $scale));

        $thumb = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($thumb, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($img);
        imagedestroy($thumb);

        $disk->put($path, $jpeg);
    }

    protected function generateImageThumbImagick(MediaItem $item, string $source, string $path, $disk): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->generatePlaceholder($path, $disk, 'image');
            return;
        }

        try {
            $imagick = new \Imagick($source);
            $imagick->setImageFormat('jpeg');
            $imagick->thumbnailImage(400, 400, true);
            $disk->put($path, $imagick->getImageBlob());
            $imagick->clear();
        } catch (\Throwable $e) {
            Log::warning('Imagick thumbnail failed: ' . $e->getMessage());
            $this->generatePlaceholder($path, $disk, 'image');
        }
    }

    protected function generatePlaceholder(string $path, $disk, string $kind): void
    {
        $svg = $this->placeholderSvg($kind);
        $disk->put($path, $svg);
    }

    protected function placeholderPath($disk, string $kind): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ph_') . '.svg';
        file_put_contents($tmp, $this->placeholderSvg($kind));
        return $tmp;
    }

    protected function placeholderSvg(string $kind): string
    {
        $config = match ($kind) {
            'audio' => ['label' => 'AUDIO',  'color' => '#7c3aed', 'icon' => 'audio'],
            'image' => ['label' => 'IMAGE',  'color' => '#0891b2', 'icon' => 'image'],
            'video' => ['label' => 'VIDEO',  'color' => '#313030', 'icon' => 'play'],
            default => ['label' => 'ARCHIVO','color' => '#475569', 'icon' => 'file'],
        };

        $c = $config['color'];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="225" viewBox="0 0 400 225">';
        $svg .= '<rect width="400" height="225" fill="' . $c . '"/>';

        if ($config['icon'] === 'play') {
            $svg .= '<circle cx="200" cy="100" r="45" fill="white" opacity="0.9"/>';
            $svg .= '<polygon points="185,80 185,120 218,100" fill="' . $c . '"/>';
        } elseif ($config['icon'] === 'image') {
            $svg .= '<rect x="140" y="65" width="120" height="80" rx="6" fill="white" opacity="0.9"/>';
            $svg .= '<circle cx="170" cy="90" r="10" fill="' . $c . '"/>';
            $svg .= '<polygon points="155,135 185,105 215,135 235,120 255,135 255,145 145,145" fill="' . $c . '" opacity="0.6"/>';
        } elseif ($config['icon'] === 'audio') {
            $svg .= '<rect x="185" y="75" width="8" height="50" rx="4" fill="white" opacity="0.9"/>';
            $svg .= '<circle cx="189" cy="125" r="14" fill="white" opacity="0.9"/>';
            $svg .= '<path d="M199,125 Q199,90 215,85 L215,100 Q207,105 207,125" fill="white" opacity="0.7"/>';
        } else {
            $svg .= '<rect x="160" y="70" width="80" height="90" rx="8" fill="white" opacity="0.9"/>';
            $svg .= '<polygon points="220,70 240,90 220,90" fill="' . $c . '" opacity="0.3"/>';
            $svg .= '<rect x="170" y="100" width="60" height="4" rx="2" fill="' . $c . '" opacity="0.5"/>';
            $svg .= '<rect x="170" y="112" width="60" height="4" rx="2" fill="' . $c . '" opacity="0.5"/>';
            $svg .= '<rect x="170" y="124" width="40" height="4" rx="2" fill="' . $c . '" opacity="0.5"/>';
        }

        $svg .= '<text x="200" y="200" font-family="sans-serif" font-size="20" fill="white" text-anchor="middle" opacity="0.9">' . $config['label'] . '</text>';
        $svg .= '</svg>';
        return $svg;
    }

    protected function resolveSourcePath(MediaItem $item): ?string
    {
        $root = rtrim((string) ($item->channel?->root_path ?? ''), '/');
        if ($root === '') {
            return null;
        }
        return $root . '/' . $item->filename;
    }

    protected function markReadyIfReady(MediaItem $item): void
    {
        if ($item->status === 'ready') {
            return;
        }
        if (! $item->thumb_path) {
            return;
        }

        $disk = Storage::disk(config('cloudstream.media.thumb_disk', 'public'));
        if (! $disk->exists($item->thumb_path)) {
            return;
        }

        $item->status = 'ready';
        $item->status_reason = null;

        if ($item->isDirty(['status', 'status_reason'])) {
            $item->save();
        }
    }

    protected function isPlaceholderThumb($disk, string $relPath): bool
    {
        try {
            $contents = $disk->get($relPath);
        } catch (\Throwable $e) {
            return false;
        }
        if ($contents === null) {
            return false;
        }
        return str_starts_with(ltrim($contents), '<svg');
    }

    protected function failWithoutThumb(MediaItem $item, string $relPath, $disk, string $reason): void
    {
        if ($disk->exists($relPath)) {
            $disk->delete($relPath);
        }
        $item->thumb_path = null;
        $item->status = 'failed';
        $item->status_reason = $reason;
        if ($item->isDirty(['thumb_path', 'status', 'status_reason'])) {
            $item->save();
        }
        Log::warning("[thumbnail placeholder-fallback] {$item->id} ({$item->filename}): {$reason}");
    }
}