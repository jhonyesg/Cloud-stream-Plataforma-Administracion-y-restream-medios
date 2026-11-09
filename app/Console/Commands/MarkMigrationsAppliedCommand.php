<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MarkMigrationsAppliedCommand extends Command
{
    protected $signature = 'db:mark-migrations-applied
                            {migrations* : Migration file names (without .php) to mark as applied}
                            {--dry-run : Print what would be marked without mutating}
                            {--json : Emit a structured JSON summary}';

    protected $description = 'Record one or more migration files as already applied in the migrations table without executing their up() bodies. Use after restoring a PostgreSQL dump whose migrations ledger is older than the migration files on disk.';

    public function handle(): int
    {
        $names = $this->argument('migrations');
        $dryRun = (bool) $this->option('dry-run');
        $jsonOutput = (bool) $this->option('json');

        if (! is_array($names) || count($names) === 0) {
            $msg = 'Provide at least one migration file name (without .php).';
            if ($jsonOutput) {
                $this->line(json_encode(['ok' => false, 'error' => $msg]));
            } else {
                $this->error($msg);
            }
            return self::FAILURE;
        }

        $migrationsPath = database_path('migrations');
        $plan = [];
        $errors = [];

        foreach ($names as $name) {
            $name = preg_replace('/\.php$/', '', $name);
            $file = $migrationsPath . DIRECTORY_SEPARATOR . $name . '.php';

            if (! is_file($file)) {
                $errors[] = ['name' => $name, 'error' => "file not found: {$file}"];
                continue;
            }

            $contents = (string) file_get_contents($file);
            $objects = $this->extractObjectsFromMigration($contents);

            $tableObjects = array_values(array_filter(
                $objects,
                fn ($o) => $o['kind'] === 'table',
            ));

            $presentCount = 0;
            foreach ($tableObjects as $t) {
                if ($this->objectExists($t)) {
                    $presentCount++;
                }
            }

            if (count($tableObjects) > 0 && $presentCount === 0) {
                $missing = array_map(fn ($t) => $t['name'], $tableObjects);
                $errors[] = [
                    'name' => $name,
                    'error' => 'live DB is missing every table this migration creates (' . implode(', ', $missing) . ') — looks like the migration has never run here. Run its up() instead of marking it.',
                ];
            }

            $alreadyRecorded = (bool) DB::table('migrations')
                ->where('migration', $name)
                ->exists();

            $plan[] = [
                'name' => $name,
                'file' => $file,
                'objects' => $objects,
                'already_recorded' => $alreadyRecorded,
            ];
        }

        if (! empty($errors)) {
            if ($jsonOutput) {
                $this->line(json_encode(['ok' => false, 'errors' => $errors], JSON_PRETTY_PRINT));
            } else {
                foreach ($errors as $err) {
                    $this->error("Refusing: migration '{$err['name']}' — {$err['error']}");
                }
            }
            return self::FAILURE;
        }

        $newBatch = ((int) (DB::table('migrations')->max('batch') ?? 0)) + 1;
        $toInsert = [];
        foreach ($plan as $row) {
            if ($row['already_recorded']) {
                continue;
            }
            $toInsert[] = [
                'migration' => $row['name'],
                'batch' => $newBatch,
            ];
        }

        if ($dryRun) {
            $summary = [
                'ok' => true,
                'dry_run' => true,
                'would_mark_batch' => $newBatch,
                'would_mark' => array_values(array_filter(array_map(
                    fn ($r) => $r['already_recorded'] ? null : $r['name'],
                    $plan,
                ))),
                'already_recorded' => array_values(array_filter(array_map(
                    fn ($r) => $r['already_recorded'] ? $r['name'] : null,
                    $plan,
                ))),
            ];
            if ($jsonOutput) {
                $this->line(json_encode($summary, JSON_PRETTY_PRINT));
            } else {
                $this->info("[dry-run] batch that would be assigned: {$newBatch}");
                if (! empty($summary['would_mark'])) {
                    $this->line('Would mark as applied:');
                    foreach ($summary['would_mark'] as $n) {
                        $this->line("  + {$n}");
                    }
                }
                if (! empty($summary['already_recorded'])) {
                    $this->line('Already recorded (no-op):');
                    foreach ($summary['already_recorded'] as $n) {
                        $this->line("  = {$n}");
                    }
                }
            }
            return self::SUCCESS;
        }

        try {
            $auditAfter = DB::transaction(function () use ($toInsert, $plan, $newBatch) {
                foreach ($toInsert as $row) {
                    DB::table('migrations')->insertOrIgnore($row);
                }
                if (! empty($toInsert)) {
                    try {
                        DB::table('audit_logs')->insert([
                            'user_id' => null,
                            'channel_id' => null,
                            'action' => 'migrations.rebaseline',
                            'entity_type' => 'migrations',
                            'entity_id' => null,
                            'before' => null,
                            'after' => json_encode([
                                'migrations' => array_column($toInsert, 'migration'),
                                'batch' => $newBatch,
                            ]),
                            'ip' => null,
                            'user_agent' => 'artisan db:mark-migrations-applied',
                            'at' => now(),
                        ]);
                    } catch (Throwable) {
                        // audit_logs may not exist yet on a brand-new environment; do not fail the ledger update.
                    }
                }
                return [
                    'marked' => array_column($toInsert, 'migration'),
                    'already_recorded' => array_values(array_map(
                        fn ($r) => $r['name'],
                        array_filter($plan, fn ($r) => $r['already_recorded']),
                    )),
                ];
            });
        } catch (Throwable $e) {
            $msg = 'Transaction failed: ' . $e->getMessage();
            if ($jsonOutput) {
                $this->line(json_encode(['ok' => false, 'error' => $msg]));
            } else {
                $this->error($msg);
            }
            return self::FAILURE;
        }

        $summary = [
            'ok' => true,
            'batch' => $newBatch,
            'marked' => $auditAfter['marked'],
            'already_recorded' => $auditAfter['already_recorded'],
        ];

        if ($jsonOutput) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));
        } else {
            $this->info("Marked {$newBatch} migrations in batch {$summary['batch']}:");
            foreach ($summary['marked'] as $n) {
                $this->line("  + {$n}");
            }
            if (! empty($summary['already_recorded'])) {
                $this->line('Already recorded (no-op):');
                foreach ($summary['already_recorded'] as $n) {
                    $this->line("  = {$n}");
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Pull object names (tables, enums, indexes) from a migration file body.
     * This is a best-effort static scan over Schema::create / Schema::table /
     * DB::statement calls and CREATE INDEX / CREATE TYPE statements.
     *
     * @return array<int, array{kind:string,name:string}>
     */
    protected function extractObjectsFromMigration(string $contents): array
    {
        $objects = [];

        if (preg_match_all("/Schema::create\(\s*['\"]([^'\"]+)['\"]/", $contents, $m)) {
            foreach ($m[1] as $table) {
                $objects[] = ['kind' => 'table', 'name' => $table];
            }
        }

        if (preg_match_all("/Schema::table\(\s*['\"]([^'\"]+)['\"]/", $contents, $m)) {
            foreach ($m[1] as $table) {
                $objects[] = ['kind' => 'table', 'name' => $table];
            }
        }

        if (preg_match_all("/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([^\s(]+)/i", $contents, $m)) {
            foreach ($m[1] as $table) {
                $table = trim($table, '"');
                $objects[] = ['kind' => 'table', 'name' => $table];
            }
        }

        if (preg_match_all("/CREATE\s+TYPE\s+(?:IF\s+NOT\s+EXISTS\s+)?([^\s]+)/i", $contents, $m)) {
            foreach ($m[1] as $type) {
                $type = trim($type, '"');
                $objects[] = ['kind' => 'enum', 'name' => $type];
            }
        }

        if (preg_match_all("/CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?([^\s]+)\s+ON\s+/i", $contents, $m)) {
            foreach ($m[1] as $index) {
                $index = trim($index, '"');
                $objects[] = ['kind' => 'index', 'name' => $index];
            }
        }

        $seen = [];
        $dedup = [];
        foreach ($objects as $o) {
            $key = $o['kind'] . ':' . $o['name'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $dedup[] = $o;
        }
        return $dedup;
    }

    protected function objectExists(array $object): bool
    {
        $name = $object['name'];

        if ($object['kind'] === 'enum') {
            $row = DB::selectOne(
                "SELECT 1 AS x FROM pg_type t
                 JOIN pg_namespace n ON n.oid = t.typnamespace
                 WHERE t.typname = ? AND t.typtype = 'e' AND n.nspname = 'public'",
                [$name],
            );
            return $row !== null;
        }

        if ($object['kind'] === 'index') {
            $row = DB::selectOne(
                "SELECT 1 AS x FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE c.relkind = 'i' AND c.relname = ? AND n.nspname = 'public'",
                [$name],
            );
            return $row !== null;
        }

        try {
            return Schema::hasTable($name);
        } catch (Throwable) {
            return false;
        }
    }
}