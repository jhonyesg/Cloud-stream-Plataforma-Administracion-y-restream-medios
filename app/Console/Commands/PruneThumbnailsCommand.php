<?php

namespace App\Console\Commands;

use App\Models\MediaItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneThumbnailsCommand extends Command
{
    protected $signature = 'media:thumbs:prune
        {--dry : Print what would be deleted without removing anything}';

    protected $description = 'Remove thumbnail files on disk that no longer correspond to a live MediaItem.';

    public function handle(): int
    {
        $isDry = (bool) $this->option('dry');

        $disk = Storage::disk(config('cloudstream.media.thumb_disk', 'public'));
        $dir = 'media/thumbs';

        if (! $disk->exists($dir)) {
            $this->info("Nothing to scan: {$dir} does not exist.");
            return self::SUCCESS;
        }

        $files = $disk->files($dir);
        $this->info('Scanning ' . count($files) . " file(s) in {$dir}…");

        $removed = 0;
        $kept = 0;
        $orphans = [];

        foreach ($files as $relPath) {
            $basename = basename($relPath);
            $id = pathinfo($basename, PATHINFO_FILENAME);

            $item = MediaItem::where('id', $id)->first();

            if ($item === null) {
                $orphans[] = ['path' => $relPath, 'reason' => 'no MediaItem in DB'];
            } else {
                $kept++;
            }
        }

        if (empty($orphans)) {
            $this->info('No orphan thumbnails found.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(sprintf('Found %d orphan thumbnail(s):', count($orphans)));
        foreach ($orphans as $o) {
            $this->line("  - {$o['path']}  ({$o['reason']})");
        }

        if ($isDry) {
            $totalSize = 0;
            foreach ($orphans as $o) {
                $totalSize += $disk->size($o['path']);
            }
            $this->newLine();
            $this->info(sprintf(
                'DRY: would remove %d file(s), freeing %s',
                count($orphans),
                $this->formatSize($totalSize)
            ));
            return self::SUCCESS;
        }

        foreach ($orphans as $o) {
            if ($disk->delete($o['path'])) {
                $removed++;
            }
        }

        $this->newLine();
        $this->info("Removed {$removed} orphan thumbnail(s). Kept {$kept} live.");

        return self::SUCCESS;
    }

    protected function formatSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1024 / 1024, 1) . ' MB';
    }
}