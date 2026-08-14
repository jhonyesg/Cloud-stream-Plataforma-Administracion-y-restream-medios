<?php

namespace App\Jobs;

use App\Models\MediaItem;
use App\Services\MediaProbeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProbeMediaItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public string $mediaItemId) {}

    public function handle(MediaProbeService $probe): void
    {
        $item = MediaItem::find($this->mediaItemId);
        if (! $item) return;
        $probe->probe($item);
    }
}
