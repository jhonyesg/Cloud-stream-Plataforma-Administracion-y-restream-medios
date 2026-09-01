<?php

namespace App\Providers;

use App\Models\MediaItem;
use App\Models\PlaylistItem;
use App\Observers\MediaItemObserver;
use App\Observers\PlaylistItemObserver;
use App\Services\EmissionLogRotator;
use App\Services\MediaserverApiService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            MediaserverApiService::class,
            fn () => MediaserverApiService::fromConfig(),
        );

        $this->app->singleton(
            EmissionLogRotator::class,
            fn () => new EmissionLogRotator(
                maxBytes: (int) config('emission.log.max_bytes', 50 * 1024 * 1024),
                retentionDays: (int) config('emission.log.retention_days', 7),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceScheme('https');

        MediaItem::observe(MediaItemObserver::class);
        PlaylistItem::observe(PlaylistItemObserver::class);
    }
}
