<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\MediaItem;
use App\Services\Media\MediaFileClassifier;
use App\Services\MediaThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ScanMediaFoldersCommand extends Command
{
    protected $signature = 'media:scan {--channel= : Scan only this channel (slug or id)} {--dry : Show what would be imported without writing}';
    protected $description = 'Scans channel folders on disk and imports files not yet registered as MediaItem.';

    public function handle(): int
    {
        $isDry = (bool) $this->option('dry');
        $channelFilter = $this->option('channel');
        $thumbnails = app(MediaThumbnailService::class);

        $query = Channel::query()
            ->whereNotNull('root_path')
            ->where('root_path', '!=', '')
            ->whereNull('deleted_at');

        if ($channelFilter) {
            if (Str::isUuid($channelFilter)) {
                $query->where('id', $channelFilter);
            } else {
                $query->where('slug', $channelFilter);
            }
        }
        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->warn('No channels with root_path found.');
            return self::SUCCESS;
        }

        $totalImported = 0;
        $totalSkipped = 0;
        $totalRejected = 0;

        foreach ($channels as $channel) {
            $rootPath = rtrim($channel->root_path, '/');
            if (! is_dir($rootPath)) {
                $this->warn("  [{$channel->display_name}] root_path does not exist: {$rootPath}");
                continue;
            }

            $existingFilenames = MediaItem::query()
                ->where('channel_id', $channel->id)
                ->pluck('filename')
                ->flip();

            $files = scandir($rootPath);
            $imported = 0;
            $skipped = 0;
            $rejected = 0;

            foreach ($files as $filename) {
                if ($filename === '.' || $filename === '..') continue;

                $fullPath = $rootPath . '/' . $filename;
                if (! is_file($fullPath)) continue;

                if (! MediaFileClassifier::isAccepted($filename)) {
                    $rejected++;
                    continue;
                }

                if ($existingFilenames->has($filename)) {
                    $skipped++;
                    continue;
                }

                $kind = MediaFileClassifier::classify($filename) ?? MediaFileClassifier::KIND_OTHER;
                $mime = mime_content_type($fullPath) ?: null;
                $size = filesize($fullPath) ?: null;

                if ($isDry) {
                    $this->line("    [DRY] Would import: {$filename} ({$kind}, " . $this->formatSize($size) . ")");
                    $imported++;
                    continue;
                }

                $item = MediaItem::create([
                    'channel_id' => $channel->id,
                    'filename' => $filename,
                    'sha256' => null,
                    'size_bytes' => $size,
                    'mime_type' => $mime,
                    'kind' => $kind,
                    'status' => 'ready',
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
                $imported++;
                $thumbnails->generateEager($item);
                $this->line("    thumb: {$filename}");
            }

            $this->info("  [{$channel->display_name}] Imported: {$imported}, Already registered: {$skipped}, Rejected by filter: {$rejected}");
            $totalImported += $imported;
            $totalSkipped += $skipped;
            $totalRejected += $rejected;
        }

        $this->newLine();
        $this->info("Done. Total imported: {$totalImported}, already registered: {$totalSkipped}, rejected: {$totalRejected}");

        return self::SUCCESS;
    }

    protected function formatSize(?int $bytes): string
    {
        if (!$bytes) return '0 B';
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}