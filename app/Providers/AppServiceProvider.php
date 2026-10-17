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
use Illuminate\Support\Facades\Log;
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
            $this->handleMigrateSafetyHook($event);
        });
    }

    /**
     * Pre-migrate lint hook. Public so feature tests can drive it directly
     * with a synthetic CommandStarting event without booting the full
     * artisan console. Runs the strict-data destructive-migrations lint
     * before any migrate* command executes; if the lint fails, logs a
     * warning + throws a RuntimeException to abort the command.
     */
    public function handleMigrateSafetyHook(CommandStarting $event): void
    {
        $command = $event->command;

        if (! in_array($command, self::PROTECTED_MIGRATE_COMMANDS, true)) {
            return;
        }

        if ($event->input->hasParameterOption('--allow-quarantined')) {
            return;
        }

        $exitCode = $this->callLintCommand();

        if ($exitCode !== 0) {
            // Capture the lint's plain-text output (file + line per violation)
            // AFTER the call so the warning payload has structured context
            // for post-mortem grep.
            $first = $this->extractFirstViolation(Artisan::output());

            Log::warning('[db-safety] blocked migrate', [
                'command' => $command,
                'file' => $first['file'] ?? null,
                'line' => $first['line'] ?? null,
            ]);

            throw new RuntimeException(
                "[db-safety] `php artisan {$command}` aborted: a migration with destructive data ops lacks the WithDataSafetySnapshot safety net. "
                .'Re-run with `--allow-quarantined` to bypass the lint for forensic investigation, '
                .'or fix the migration (use the trait + declare the affected table in $criticalTables) and try again.'
            );
        }
    }

    /**
     * Run the destructive-migrations lint with --strict-data and return the
     * exit code. Wrapped in a method so tests can subclass and point the
     * lint at a tmp directory instead of `database/migrations/`, keeping
     * test fixtures out of the production tree.
     *
     * @param  string|null  $migrationsPath  override the default scan path; null = `database/migrations/`.
     */
    protected function callLintCommand(?string $migrationsPath = null): int
    {
        $params = ['--strict-data' => true];
        if ($migrationsPath !== null) {
            $params['--path'] = $migrationsPath;
        }

        return Artisan::call(CheckDestructiveMigrationsCommand::class, $params);
    }

    /**
     * Parse the lint's plain-text output to extract the first violation's
     * file + line. Output format: `  - <file>: <reason> (line <n>)` or
     * `  - <file>: <reason>` (no line for the DDL check).
     *
     * @return array{file: ?string, line: ?int}
     */
    private function extractFirstViolation(string $lintOutput): array
    {
        $file = null;
        $line = null;

        if (preg_match('/^\s*-\s*(\S+\.php):\s*.*?\(line\s+(\d+)\)/m', $lintOutput, $m)) {
            $file = $m[1] ?? null;
            $line = isset($m[2]) ? (int) $m[2] : null;
        } elseif (preg_match('/^\s*-\s*(\S+\.php):/m', $lintOutput, $m)) {
            $file = $m[1] ?? null;
        }

        return ['file' => $file, 'line' => $line];
    }
}
