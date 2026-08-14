<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MigrationSnapshot
{
    protected string $basePath;

    protected int $chunkSize;

    /**
     * FK-aware insert order. Children after parents. The service will
     * additionally discover any critical table not listed here and append
     * it at the end (best-effort).
     */
    protected array $fkOrder;

    public function __construct(?string $basePath = null, ?int $chunkSize = null, ?array $fkOrder = null)
    {
        $this->basePath = $basePath ?? base_path(config('migration-safety.snapshot_path'));
        $this->chunkSize = $chunkSize ?? (int) config('migration-safety.chunk_size', 5000);
        $this->fkOrder = $fkOrder ?? (array) config('migration-safety.fk_order', []);

        if (! is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Capture the given tables into <basePath>/<migrationName>/<table>.json.gz.
     * Returns absolute paths written.
     *
     * @param  string  $migrationName  migration filename (e.g. "2026_07_15_072125_drop_X")
     * @param  array  $tables  list of table names to snapshot
     * @param  array  $columns  optional per-table column whitelist (default: all columns)
     * @return array{manifest: array, files: array<string,string>}
     */
    public function capture(string $migrationName, array $tables, array $columns = []): array
    {
        $dir = $this->basePath.'/'.$migrationName;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $manifest = [
            'migration' => $migrationName,
            'captured_at' => now()->toIso8601String(),
            'tables' => [],
        ];

        $files = [];

        foreach ($tables as $table) {
            if (! $this->tableExists($table)) {
                $manifest['tables'][$table] = ['skipped' => 'table_not_found'];

                continue;
            }

            $cols = $columns[$table] ?? null;
            $path = $dir.'/'.$table.'.json.gz';

            $count = $this->captureTable($table, $path, $cols);
            $manifest['tables'][$table] = [
                'rows' => $count,
                'file' => $path,
                'size_bytes' => file_exists($path) ? filesize($path) : 0,
                'columns' => $cols,
            ];
            $files[$table] = $path;
        }

        $manifestPath = $dir.'/manifest.json';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Log::info('MigrationSnapshot::capture', [
            'migration' => $migrationName,
            'tables' => array_keys($files),
            'dir' => $dir,
        ]);

        return ['manifest' => $manifest, 'files' => $files];
    }

    /**
     * Restore all tables captured for a migration. Returns array of restored tables.
     */
    public function restore(string $migrationName, array $tables = []): array
    {
        $dir = $this->basePath.'/'.$migrationName;
        if (! is_dir($dir)) {
            throw new RuntimeException("Snapshot directory not found: {$dir}");
        }

        $manifestPath = $dir.'/manifest.json';
        if (! file_exists($manifestPath)) {
            throw new RuntimeException("Snapshot manifest missing: {$manifestPath}");
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $available = array_keys($manifest['tables'] ?? []);
        $targets = empty($tables) ? $available : array_values(array_intersect($tables, $available));

        $ordered = $this->orderByForeignKeys($targets);

        $restored = [];
        foreach ($ordered as $table) {
            $info = $manifest['tables'][$table] ?? null;
            if (! $info || isset($info['skipped'])) {
                continue;
            }

            $file = $info['file'] ?? ($dir.'/'.$table.'.json.gz');
            if (! file_exists($file)) {
                Log::warning('MigrationSnapshot::restore missing file', ['file' => $file]);

                continue;
            }

            DB::transaction(function () use ($table, $file, &$restored) {
                $rows = $this->readJsonGz($file);
                if (! $this->tableExists($table)) {
                    Log::warning('MigrationSnapshot::restore target table missing', ['table' => $table]);

                    return;
                }
                DB::statement("TRUNCATE TABLE {$table} CASCADE");
                foreach (array_chunk($rows, $this->chunkSize) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
                $restored[$table] = count($rows);
            });
        }

        Log::info('MigrationSnapshot::restore', [
            'migration' => $migrationName,
            'restored' => $restored,
        ]);

        return $restored;
    }

    /**
     * List available snapshot directories.
     */
    public function listAvailable(): array
    {
        if (! is_dir($this->basePath)) {
            return [];
        }

        $out = [];
        foreach (glob($this->basePath.'/*/manifest.json') as $manifestPath) {
            $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
            $dir = dirname($manifestPath);
            $totalRows = 0;
            $totalSize = 0;
            foreach (($manifest['tables'] ?? []) as $info) {
                $totalRows += $info['rows'] ?? 0;
                $totalSize += $info['size_bytes'] ?? 0;
            }
            $out[] = [
                'migration' => $manifest['migration'] ?? basename($dir),
                'captured_at' => $manifest['captured_at'] ?? null,
                'tables' => count($manifest['tables'] ?? []),
                'total_rows' => $totalRows,
                'size_bytes' => $totalSize,
                'path' => $dir,
            ];
        }

        usort($out, fn ($a, $b) => strcmp($b['captured_at'] ?? '', $a['captured_at'] ?? ''));

        return $out;
    }

    /**
     * Read a single manifest.
     */
    public function manifest(string $migrationName): ?array
    {
        $path = $this->basePath.'/'.$migrationName.'/manifest.json';
        if (! file_exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * Path of the release snapshot file (may not exist yet).
     */
    public function releaseSnapshotPath(): string
    {
        $rel = (string) config('migration-safety.release_snapshot', 'release/production.json.gz');

        return $this->basePath.'/'.$rel;
    }

    public function releaseSnapshotExists(): bool
    {
        return file_exists($this->releaseSnapshotPath());
    }

    /**
     * Stream rows from a table into a gzipped JSON file.
     */
    protected function captureTable(string $table, string $path, ?array $columns = null): int
    {
        $count = 0;
        $fh = gzopen($path, 'w9');
        if ($fh === false) {
            throw new RuntimeException("Cannot open snapshot file for writing: {$path}");
        }

        gzwrite($fh, '[');
        $first = true;

        $query = DB::table($table);
        if ($columns) {
            $query = $query->select($columns);
        }
        $query->orderByRaw('1')->chunk($this->chunkSize, function ($rows) use (&$fh, &$first, &$count) {
            foreach ($rows as $row) {
                $payload = $this->normalizeRow((array) $row);
                if (! $first) {
                    gzwrite($fh, ',');
                }
                gzwrite($fh, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $first = false;
                $count++;
            }
        });

        gzwrite($fh, ']');
        gzclose($fh);

        return $count;
    }

    /**
     * Convert DB driver types into JSON-safe scalars. Postgres returns
     * numeric strings for some numeric columns; we leave them as-is since
     * Postgres will cast on insert.
     */
    protected function normalizeRow(array $row): array
    {
        foreach ($row as $k => $v) {
            if (is_resource($v)) {
                $row[$k] = stream_get_contents($v);
            }
        }

        return $row;
    }

    protected function readJsonGz(string $path): array
    {
        $raw = file_get_contents('compress.zlib://'.$path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    protected function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Order tables by FK dependency: parents before children. Tables not in
     * $fkOrder are appended in the order they appear in $tables.
     */
    protected function orderByForeignKeys(array $tables): array
    {
        $ordered = [];
        foreach ($this->fkOrder as $t) {
            if (in_array($t, $tables, true)) {
                $ordered[] = $t;
            }
        }
        foreach ($tables as $t) {
            if (! in_array($t, $ordered, true)) {
                $ordered[] = $t;
            }
        }

        return $ordered;
    }
}
