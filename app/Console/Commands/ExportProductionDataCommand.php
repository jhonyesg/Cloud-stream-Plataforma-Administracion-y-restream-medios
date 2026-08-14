<?php

namespace App\Console\Commands;

use App\Services\MigrationSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExportProductionDataCommand extends Command
{
    protected $signature = 'db:export-production-data
                            {--output= : Override output path (defaults to config migration-safety.release_snapshot)}';

    protected $description = 'Capture the current production state into the release snapshot file.';

    public function handle(): int
    {
        /** @var MigrationSnapshot $snapshot */
        $snapshot = app(MigrationSnapshot::class);

        $tables = (array) config('migration-safety.critical_tables', []);
        if (empty($tables)) {
            $this->error('No critical_tables configured.');

            return self::FAILURE;
        }

        $output = $this->option('output') ?: $snapshot->releaseSnapshotPath();
        $outputDir = dirname($output);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'tables' => [],
        ];

        $this->info('Exporting '.count($tables)." critical tables to {$output}");

        foreach ($tables as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                $this->warn("  - {$table}: table not found, skipping");
                $payload['tables'][$table] = ['skipped' => 'table_not_found'];

                continue;
            }
            $rows = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
            $payload[$table] = $rows;
            $payload['tables'][$table] = ['rows' => count($rows)];
            $this->info("  - {$table}: ".count($rows).' rows');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $written = file_put_contents('compress.zlib://'.$output, $json);

        if ($written === false) {
            throw new RuntimeException("Failed to write snapshot to {$output}");
        }

        $size = filesize($output);
        $this->info("Done. {$output} (".self::humanBytes((int) $size).')');

        return self::SUCCESS;
    }

    protected static function humanBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
