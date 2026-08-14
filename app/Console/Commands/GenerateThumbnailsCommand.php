<?php

namespace App\Console\Commands;

use App\Models\MediaItem;
use App\Services\MediaThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateThumbnailsCommand extends Command
{
    protected $signature = 'media:thumbs
        {--channel= : Scope to one channel (slug or id)}
        {--kind= : Restrict to one kind (video|image)}
        {--force : Regenerate even when a thumbnail already exists on disk}
        {--limit= : Cap items processed in one run}
        {--dry : Print what would be processed without writing}';

    protected $description = 'Generate thumbnails for existing media items (video/image) missing them.';

    public function handle(): int
    {
        $isDry = (bool) $this->option('dry');
        $isForce = (bool) $this->option('force');
        $kindOpt = $this->option('kind');
        $limitOpt = $this->option('limit');
        $channelOpt = $this->option('channel');
        $limit = $limitOpt !== null ? (int) $limitOpt : null;

        $allowedKinds = ['video', 'image', 'ad'];
        $kinds = $kindOpt ? [$kindOpt] : $allowedKinds;

        $query = MediaItem::query()->whereIn('kind', $kinds);
        if ($channelOpt) {
            $query->whereHas('channel', function ($q) use ($channelOpt) {
                $q->where('slug', $channelOpt)->orWhere('id', $channelOpt);
            });
        }

        $disk = Storage::disk(config('cloudstream.media.thumb_disk', 'public'));
        $items = $query->orderByDesc('created_at')->get();

        $candidates = $items->filter(function (MediaItem $item) use ($disk, $isForce) {
            if ($isForce) {
                return true;
            }
            if (! $item->thumb_path) {
                return true;
            }
            return ! $disk->exists($item->thumb_path);
        });

        if ($limit !== null) {
            $candidates = $candidates->take($limit);
        }

        $total = $candidates->count();

        if ($isDry) {
            $this->info("DRY: would process {$total} items.");
            foreach ($candidates as $item) {
                $this->line("  - [{$item->kind}] {$item->filename}");
            }
            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('No media items need thumbnails.');
            return self::SUCCESS;
        }

        $thumbnails = app(MediaThumbnailService::class);
        $processed = 0;
        $failed = 0;
        $skipped = 0;

        $this->info("Processing {$total} items...");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($candidates as $item) {
            $bar->advance();
            try {
                $ok = $thumbnails->generateEager($item);
                if ($ok) {
                    $processed++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("  Failed: {$item->filename} — {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Done. Processed: {$processed}, Failed: {$failed}, Skipped: {$skipped}");

        return self::SUCCESS;
    }
}