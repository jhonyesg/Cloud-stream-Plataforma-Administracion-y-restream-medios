<?php

namespace App\Console\Commands;

use App\Services\MigrationSnapshot;
use Illuminate\Console\Command;

class SafeMigrateCommand extends Command
{
    protected $signature = 'migrate:safe
                            {--no-backup : Skip the db:backup step (not recommended)}
                            {--no-verify : Skip post-migrate snapshot verification}';

    protected $description = 'Run db:backup, then migrate, then verify critical-table snapshots exist.';

    public function handle(): int
    {
        if (! $this->option('no-backup')) {
            $this->info('[1/3] Running db:backup ...');
            $exit = $this->call('db:backup');
            if ($exit !== 0) {
                $this->error('Backup failed. Aborting migrate.');

                return self::FAILURE;
            }
        } else {
            $this->warn('[1/3] Skipping db:backup (--no-backup).');
        }

        $this->info('[2/3] Running migrate ...');
        $exit = $this->call('migrate', [
            '--force' => true,
        ]);
        if ($exit !== 0) {
            $this->error('Migrate failed.');

            return self::FAILURE;
        }

        if ($this->option('no-verify')) {
            $this->warn('[3/3] Skipping verification (--no-verify).');

            return self::SUCCESS;
        }

        $this->info('[3/3] Verifying snapshots ...');
        /** @var MigrationSnapshot $snapshot */
        $snapshot = app(MigrationSnapshot::class);
        $list = $snapshot->listAvailable();

        $critical = (array) config('migration-safety.critical_tables', []);
        $missing = [];
        foreach ($critical as $table) {
            $found = false;
            foreach ($list as $entry) {
                if (isset($entry['tables']) && $entry['tables'] > 0 && file_exists($entry['path'].'/'.$table.'.json.gz')) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $missing[] = $table;
            }
        }

        if ($missing) {
            $this->warn('No snapshot found for critical tables: '.implode(', ', $missing));
            $this->warn('This is OK if the schema is fresh; otherwise run db:export-production-data.');
        } else {
            $this->info('All critical tables have snapshots available.');
        }

        return self::SUCCESS;
    }
}
