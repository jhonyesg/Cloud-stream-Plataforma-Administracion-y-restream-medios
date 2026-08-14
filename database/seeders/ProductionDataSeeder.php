<?php

namespace Database\Seeders;

use App\Services\MigrationSnapshot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionDataSeeder extends Seeder
{
    public function run(): void
    {
        /** @var MigrationSnapshot $snapshot */
        $snapshot = app(MigrationSnapshot::class);

        if (! $snapshot->releaseSnapshotExists()) {
            Log::warning('ProductionDataSeeder: release snapshot not found', [
                'expected_path' => $snapshot->releaseSnapshotPath(),
            ]);
            $this->command?->warn("Release snapshot not found at {$snapshot->releaseSnapshotPath()}. Critical tables will remain empty.");

            return;
        }

        $path = $snapshot->releaseSnapshotPath();
        $tables = (array) config('migration-safety.critical_tables', []);
        $fkOrder = (array) config('migration-safety.fk_order', $tables);
        $ordered = [];
        foreach ($fkOrder as $t) {
            if (in_array($t, $tables, true)) {
                $ordered[] = $t;
            }
        }
        foreach ($tables as $t) {
            if (! in_array($t, $ordered, true)) {
                $ordered[] = $t;
            }
        }

        $raw = @file_get_contents('compress.zlib://'.$path);
        if ($raw === false) {
            Log::error('ProductionDataSeeder: cannot read snapshot', ['path' => $path]);
            $this->command?->error("Cannot read release snapshot: {$path}");

            return;
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            Log::error('ProductionDataSeeder: snapshot is not valid JSON', ['path' => $path]);
            $this->command?->error('Release snapshot is not valid JSON.');

            return;
        }

        $chunkSize = (int) config('migration-safety.chunk_size', 5000);

        foreach ($ordered as $table) {
            $rows = $payload[$table] ?? null;
            if (! is_array($rows)) {
                continue;
            }

            if (! DB::getSchemaBuilder()->hasTable($table)) {
                Log::warning('ProductionDataSeeder: target table missing', ['table' => $table]);

                continue;
            }

            DB::transaction(function () use ($table, $rows, $chunkSize) {
                DB::statement("TRUNCATE TABLE {$table} CASCADE");
                foreach (array_chunk($rows, $chunkSize) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            });

            $this->command?->info("Restored {$table}: ".count($rows).' rows');
        }
    }
}
