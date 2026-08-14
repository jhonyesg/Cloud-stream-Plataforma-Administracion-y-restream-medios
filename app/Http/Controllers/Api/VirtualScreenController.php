<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\MediaItem;
use App\Models\VirtualScreen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VirtualScreenController extends Controller
{
    public function show(Request $request, string $channelId): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $this->authorizeAccess($request, $channel);

        $screen = VirtualScreen::with('mediaItem:id,filename,thumb_path,kind')
            ->where('channel_id', $channelId)
            ->first();
        if (! $screen) {
            return response()->json(['message' => 'Pantalla virtual no configurada.'], 404);
        }

        return response()->json($this->enrichScreen($screen, $channel));
    }

    public function update(Request $request, string $channelId): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $this->authorizeAccess($request, $channel);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'width' => ['sometimes', 'integer', 'min:320', 'max:7680'],
            'height' => ['sometimes', 'integer', 'min:240', 'max:4320'],
            'layout' => ['nullable', 'array'],
            'theme' => ['nullable', 'string', 'max:60'],
            'output_protocol' => ['nullable', 'in:rtmp,srt,hls'],
            'output_url' => ['nullable', 'string', 'max:500'],
            'public_hls_url' => ['nullable', 'string', 'max:500', 'url'],
            'fps' => ['nullable', 'integer', 'in:24,25,30,50,60'],
            'video_bitrate_kbps' => ['nullable', 'integer', 'in:500,1000,1500,2000,2500,3000,4000,5000,6000,8000,10000,12000,15000,20000,25000,30000,40000,50000'],
            'audio_bitrate_kbps' => ['nullable', 'integer', 'in:32,64,96,128,160,192,224,256,320'],
            'codec_video' => ['nullable', 'string', 'in:libx264,libx265,libvpx-vp9,copy'],
            'codec_audio' => ['nullable', 'string', 'in:aac,libmp3lame,libopus,libvorbis,copy'],
            'video_preset' => ['nullable', 'string', 'in:ultrafast,superfast,veryfast,faster,fast,medium,slow,slower,veryslow'],
            'logo_media_item_id' => ['nullable', 'uuid'],
            'logo_x' => ['nullable', 'integer', 'min:0', 'max:7680'],
            'logo_y' => ['nullable', 'integer', 'min:0', 'max:4320'],
            'logo_w' => ['nullable', 'integer', 'min:16', 'max:4000'],
            'logo_h' => ['nullable', 'integer', 'min:16', 'max:4000'],
            'logo_opacity' => ['nullable', 'numeric', 'min:0.1', 'max:1.0'],
        ]);

        if (isset($data['logo_media_item_id'])) {
            $mediaExists = MediaItem::whereKey($data['logo_media_item_id'])->exists();
            if (! $mediaExists) {
                return response()->json(['errors' => ['logo_media_item_id' => ['Imagen no encontrada.']]], 422);
            }
        }

        $screen = VirtualScreen::updateOrCreate(['channel_id' => $channelId], $data);
        if (isset($data['public_hls_url'])) {
            $channel->update(['public_hls_url' => $data['public_hls_url']]);
        }
        $screen->load('mediaItem:id,filename,thumb_path,kind');
        return response()->json($this->enrichScreen($screen, $channel));
    }

    /**
     * Vista previa: genera una imagen PNG estática del canvas con el logo superpuesto.
     * Usa GD library de PHP (no requiere exec/proc_open). El archivo temporal
     * se elimina después de enviarse al cliente.
     */
    public function testPreview(Request $request, string $channelId): JsonResponse|BinaryFileResponse
    {
        $channel = Channel::findOrFail($channelId);
        $this->authorizeAccess($request, $channel);

        $screen = VirtualScreen::where('channel_id', $channelId)->first();
        if (! $screen) {
            return response()->json(['message' => 'Pantalla virtual no configurada.'], 404);
        }

        $sourceItem = MediaItem::where('channel_id', $channelId)->orderBy('created_at')->first();
        if (! $sourceItem) {
            return response()->json(['message' => 'No hay archivos multimedia en este canal para probar.'], 422);
        }

        $width = max(320, (int) ($screen->width ?: 1280));
        $height = max(240, (int) ($screen->height ?: 720));

        // Create canvas
        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            return response()->json(['message' => 'No se pudo crear el canvas (memoria insuficiente).'], 500);
        }
        $bgColor = imagecolorallocate($canvas, 0x0b, 0x12, 0x30);
        imagefill($canvas, 0, 0, $bgColor);

        // Composite background thumbnail if available
        $thumbSource = $this->resolveThumbnailPath($sourceItem, $channel->root_path);
        if ($thumbSource !== null) {
            $bg = $this->loadImage($thumbSource);
            if ($bg !== null) {
                $this->compositeCentered($bg, $canvas, $width, $height);
                imagedestroy($bg);
            }
        }

        // Composite logo if configured
        if ($screen->logo_media_item_id) {
            $logoItem = MediaItem::find($screen->logo_media_item_id);
            if ($logoItem) {
                $logoPath = $this->resolveFilePath($logoItem, $channel->root_path);
                if ($logoPath !== null) {
                    $logo = $this->loadImage($logoPath);
                    if ($logo !== null) {
                        $lw = max(16, (int) $screen->logo_w);
                        $lh = max(16, (int) $screen->logo_h);
                        $lx = max(0, (int) $screen->logo_x);
                        $ly = max(0, (int) $screen->logo_y);
                        $op = max(0.1, min(1.0, (float) $screen->logo_opacity));
                        $this->compositeLogo($logo, $canvas, $lw, $lh, $lx, $ly, $op);
                        imagedestroy($logo);
                    }
                }
            }
        }

        // Output to temp file
        $dir = storage_path('app/test-emissions');
        if (! is_dir($dir)) mkdir($dir, 0775, true);
        $ts = now()->format('Ymd_His');
        $out = $dir . "/preview-{$channelId}-{$ts}.png";

        $result = imagepng($canvas, $out, 6);
        imagedestroy($canvas);

        if (! $result || ! file_exists($out)) {
            return response()->json(['message' => 'No se pudo escribir la imagen de preview.'], 500);
        }

        return response()
            ->download($out, 'preview.png', ['Content-Type' => 'image/png'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Add computed URLs (play_url, thumb_url) for the logo media item
     * since those are accessors on the model, not real columns.
     */
    private function enrichScreen(VirtualScreen $screen, Channel $channel): array
    {
        $data = $screen->toArray();
        if ($screen->mediaItem) {
            $data['media_item'] = array_merge(
                $screen->mediaItem->only(['id', 'filename', 'thumb_path', 'kind']),
                [
                    'play_url' => $screen->mediaItem->playUrl(),
                    'thumb_url' => $screen->mediaItem->thumbUrl(),
                ]
            );
        } else {
            $data['media_item'] = null;
        }
        $data['channel_public_hls_url'] = $channel->public_hls_url;
        return $data;
    }

    /**
     * Resuelve la ruta absoluta del thumbnail de un media item.
     * Prueba múltiples ubicaciones: thumb_path literal, root_path + thumb_path,
     * storage público, y nombres adyacentes comunes.
     */
    private function resolveThumbnailPath(MediaItem $item, string $rootPath): ?string
    {
        $candidates = [];
        if (! empty($item->thumb_path)) {
            // Absolute path or already-resolved
            if (str_starts_with($item->thumb_path, '/') || preg_match('/^[A-Z]:\\\\/i', $item->thumb_path)) {
                $candidates[] = $item->thumb_path;
            }
            // Laravel public storage (most common location for thumbnails)
            $publicStorage = storage_path('app/public/' . ltrim($item->thumb_path, '/'));
            $candidates[] = $publicStorage;
            // root_path + thumb_path
            $candidates[] = $rootPath . '/' . ltrim($item->thumb_path, '/');
        }
        $baseName = pathinfo($item->filename, PATHINFO_FILENAME);
        if ($baseName) {
            foreach (['.jpg', '.jpeg', '.png', '.webp'] as $ext) {
                $candidates[] = $rootPath . '/' . $baseName . $ext;
                $candidates[] = storage_path('app/public/media/thumbs/' . $item->id . $ext);
            }
            $candidates[] = $rootPath . '/thumb_' . $baseName . '.jpg';
        }
        foreach ($candidates as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }
        return null;
    }

    private function resolveFilePath(MediaItem $item, string $rootPath): ?string
    {
        $candidates = [
            $rootPath . '/' . $item->filename,
            storage_path('app/public/' . ltrim($item->filename, '/')),
        ];
        foreach ($candidates as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Carga una imagen desde archivo. Soporta PNG/JPG.
     * Retorna GD image resource o null si falla.
     */
    private function loadImage(string $path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        try {
            if ($ext === 'png' || $ext === 'webp') {
                return @imagecreatefrompng($path);
            }
            if ($ext === 'jpg' || $ext === 'jpeg') {
                return @imagecreatefromjpeg($path);
            }
        } catch (\Throwable $e) {
            Log::warning('GD loadImage failed: ' . $path . ' — ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Compone una imagen de fondo centrada en el canvas (letterbox fit).
     */
    private function compositeCentered($bg, $canvas, int $canvasW, int $canvasH): void
    {
        $bgW = imagesx($bg);
        $bgH = imagesy($bg);
        if ($bgW <= 0 || $bgH <= 0) return;

        $scale = min($canvasW / $bgW, $canvasH / $bgH);
        $newW = (int) round($bgW * $scale);
        $newH = (int) round($bgH * $scale);
        $dstX = (int) (($canvasW - $newW) / 2);
        $dstY = (int) (($canvasH - $newH) / 2);

        imagecopyresampled(
            $canvas, $bg,
            $dstX, $dstY, 0, 0,
            $newW, $newH, $bgW, $bgH
        );
    }

    /**
     * Compone el logo sobre el canvas en (x, y) con tamaño (w, h) y opacidad alpha.
     */
    private function compositeLogo($logo, $canvas, int $w, int $h, int $x, int $y, float $opacity): void
    {
        // Resize the logo to the configured dimensions
        $logoResized = imagecreatetruecolor($w, $h);
        imagealphablending($logoResized, false);
        imagesavealpha($logoResized, true);
        $transparent = imagecolorallocatealpha($logoResized, 0, 0, 0, 127);
        imagefill($logoResized, 0, 0, $transparent);

        $logoW = imagesx($logo);
        $logoH = imagesy($logo);
        if ($logoW > 0 && $logoH > 0) {
            imagecopyresampled($logoResized, $logo, 0, 0, 0, 0, $w, $h, $logoW, $logoH);
        }

        // Apply opacity by scaling the alpha channel of each pixel
        if ($opacity < 1.0) {
            $alphaMul = (int) round((1 - $opacity) * 127);
            for ($py = 0; $py < $h; $py++) {
                for ($px = 0; $px < $w; $px++) {
                    $rgba = imagecolorat($logoResized, $px, $py);
                    $a = ($rgba & 0x7F000000) >> 24;
                    $newA = max(0, min(127, $a + $alphaMul));
                    $rgba = ($rgba & 0xFFFFFF) | ($newA << 24);
                    imagesetpixel($logoResized, $px, $py, $rgba);
                }
            }
        }

        // Composite onto canvas (use alpha blending)
        imagealphablending($canvas, true);
        imagecopy($canvas, $logoResized, $x, $y, 0, 0, $w, $h);
        imagealphablending($canvas, false);

        imagedestroy($logoResized);
    }

    private function authorizeAccess(Request $request, Channel $channel): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if ($user->isAdmin()) return;
        if (! $user->canAccessChannel($channel)) {
            abort(403, 'No tiene acceso a este canal.');
        }
    }
}