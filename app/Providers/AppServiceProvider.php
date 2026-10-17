<?php

namespace App\Providers;

use App\Console\Commands\CheckDestructiveMigrationsCommand;
use App\Models\MediaItem;
use App\Models\PlaylistItem;
use App\Observers\MediaItemObserver;
use App\Observers\PlaylistItemObserver;
use App\Services\EmissionLogRotator;
use App\Services\MediaserverApiService;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Commands that touch the database schema or data. We run the
     * strict-data destructive-migrations lint before any of them so a
     * migration that uses `->truncate()` / `->delete()` / `DELETE FROM`
     * without the `WithDataSafetySnapshot` safety net cannot be deployed
     * by accident. Escape hatch: pass `--allow-quarantined`.
     */
    private const PROTECTED_MIGRATE_COMMANDS = [
        'migrate',
        'migrate:fresh',
        'migrate:rollback',
        'migrate:reset',
        'migrate:refresh',
        'migrate:safe',
    ];

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

        $this->registerMigrateSafetyHook();
    }

    /**
     * Register the --allow-quarantined option on the migrate* commands so
     * Symfony accepts it as a recognized option, then install the
     * CommandStarting listener that runs the strict-data lint before any
     * migrate* command executes.
     */
    private function registerMigrateSafetyHook(): void
    {
        $events = $this->app['events'];

        // Add --allow-quarantined to the global Symfony definition so it's
        // recognised by every command (Symfony merges the application-level
        // definition with each command's own). Cheaper than mutating every
        // migrate* command individually and works whether the listener fires
        // before or after command registration.
        $events->listen(ArtisanStarting::class, function (ArtisanStarting $event) {
            $app = $event->artisan;
            $definition = $app->getDefinition();
            if (! $definition->hasOption('allow-quarantined')) {
                $definition->addOption(
                    new InputOption(
                        'allow-quarantined',
                        null,
                        InputOption::VALUE_NONE,
                        'Bypass the destructive-migrations safety lint (forensic investigation only).'
                    )
                );
            }
        });

        $events->listen(CommandStarting::class, function (CommandStarting $event) {
            $command = $event->command;

            if (! in_array($command, self::PROTECTED_MIGRATE_COMMANDS, true)) {
                return;
            }

            if ($event->input->hasParameterOption('--allow-quarantined')) {
                return;
            }

            $exitCode = Artisan::call(CheckDestructiveMigrationsCommand::class, [
                '--strict-data' => true,
            ]);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    "[db-safety] `php artisan {$command}` aborted: a migration with destructive data ops lacks the WithDataSafetySnapshot safety net. "
                    .'Re-run with `--allow-quarantined` to bypass the lint for forensic investigation, '
                    .'or fix the migration (use the trait + declare the affected table in $criticalTables) and try again.'
                );
            }
        });
    }
}
