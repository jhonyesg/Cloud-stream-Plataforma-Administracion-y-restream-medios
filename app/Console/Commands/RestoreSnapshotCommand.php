<?php

namespace App\Console\Commands;

use App\Services\MigrationSnapshot;
use Illuminate\Console\Command;

class RestoreSnapshotCommand extends Command
{
    protected $signature = 'db:restore-snapshot
                            {name : Migration name (e.g. 2026_07_15_072125_drop_media_providers_and_folders)}
                            {--table=* : Restore only these tables (repeatable)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Restore rows from a migration snapshot into the live database.';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $tables = (array) $this->option('table');

        /** @var MigrationSnapshot $snapshot */
        $snapshot = app(MigrationSnapshot::class);

        if (! $snapshot->manifest($name)) {
            $this->error("Snapshot not found: {$name}");

            return self::FAILURE;
        }

        $manifest = $snapshot->manifest($name);
        $available = array_keys($manifest['tables'] ?? []);
        $targets = empty($tables) ? $available : array_values(array_intersect($tables, $available));

        if (! $this->option('force')) {
            $this->warn('About to TRUNCATE and restore the following tables:');
            foreach ($targets as $t) {
                $info = $manifest['tables'][$t] ?? [];
                $this->line("  - {$t} ({$info['rows']} rows)");
            }
            if (! $this->confirm('Proceed?')) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $restored = $snapshot->restore($name, $targets);

        foreach ($restored as $table => $count) {
            $this->info("  ✓ {$table}: {$count} rows restored");
        }

        return self::SUCCESS;
    }
}
