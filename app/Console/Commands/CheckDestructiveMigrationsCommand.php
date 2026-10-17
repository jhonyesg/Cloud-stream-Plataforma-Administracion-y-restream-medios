<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckDestructiveMigrationsCommand extends Command
{
    protected $signature = 'check:destructive-migrations
                            {--path=database/migrations : Path to migrations directory}
                            {--strict-data : Also scan for data-level destruction (truncate / delete / DELETE FROM)}';

    protected $description = 'Scan migrations for destructive DDL in up() without the WithDataSafetySnapshot trait. Pass --strict-data to also catch data-level destruction (truncate / delete / DELETE FROM).';

    /** @var string[] */
    protected array $destructivePatterns = [
        '/Schema::dropIfExists\s*\(/',
        '/Schema::drop\s*\(/',
        '/->dropColumn\s*\(/',
        '/Schema::table\s*\([^,]+,\s*function[^}]*->dropColumn/s',
    ];

    /**
     * Data-level destruction patterns. Each entry: [regex, human description].
     * Catches DB::table('x')->truncate() / ->delete() / DB::statement('DELETE FROM…')
     * / DB::statement('TRUNCATE…') / raw DELETE / raw TRUNCATE inside the up() body.
     *
     * @var array<int, array{0:string, 1:string}>
     */
    protected array $destructiveDataPatterns = [
        ['/DB::table\s*\([^)]+\)\s*->truncate\s*\(\s*\)/', 'DB::table(...)->truncate()'],
        ['/DB::table\s*\([^)]+\)[^;]*?->delete\s*\(\s*\d+/', 'DB::table(...)->delete(<count>)'],
        ['/DB::table\s*\([^)]+\)[^;]*?->delete\s*\(\s*\)/', 'DB::table(...)->delete() (unbounded)'],
        ['/DB::statement\s*\(\s*[\'\"](?:DELETE\s+FROM|TRUNCATE\s+TABLE)/i', 'DB::statement(DELETE FROM|TRUNCATE TABLE)'],
        ['/(?<![A-Za-z_])DELETE\s+FROM\s+[\w\."]+/i', 'raw DELETE FROM'],
        ['/(?<![A-Za-z_])TRUNCATE\s+TABLE\s+[\w\."]+/i', 'raw TRUNCATE TABLE'],
    ];

    public function handle(): int
    {
        $path = (string) $this->option('path');
        // Allow absolute paths so tests and one-off invocations can point
        // at a temp directory; otherwise resolve relative to the project root.
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = base_path($path);
        }
        $strictData = (bool) $this->option('strict-data');

        $files = glob($path.'/*.php');
        if (! $files) {
            $this->error("No migration files found in {$path}");

            return self::FAILURE;
        }

        $violations = [];
        $scanned = 0;

        foreach ($files as $file) {
            $scanned++;
            $contents = file_get_contents($file);
            $upBody = $this->extractMethodBody($contents, 'up');

            $usesTrait = str_contains($contents, 'WithDataSafetySnapshot');
            $declaresCritical = preg_match('/\$criticalTables\s*=\s*\[/', $contents) === 1;
            $protectedByTrait = $usesTrait && $declaresCritical;

            // DDL check
            $hasDestructiveDdl = false;
            foreach ($this->destructivePatterns as $pattern) {
                if (preg_match($pattern, $upBody)) {
                    $hasDestructiveDdl = true;
                    break;
                }
            }

            if ($hasDestructiveDdl && ! $protectedByTrait) {
                $violations[] = [
                    'file' => basename($file),
                    'reason' => ! $usesTrait ? 'missing WithDataSafetySnapshot trait' : 'missing $criticalTables declaration',
                    'kind' => 'ddl',
                ];
                continue;
            }

            // Data-level check (only when --strict-data)
            if (! $strictData) {
                continue;
            }

            foreach ($this->destructiveDataPatterns as [$pattern, $description]) {
                if (! preg_match($pattern, $upBody)) {
                    continue;
                }
                if ($protectedByTrait) {
                    // Snapshotted → recovery exists, skip
                    continue;
                }
                $line = $this->firstMatchLine($upBody, $pattern);
                $violations[] = [
                    'file' => basename($file),
                    'reason' => $description,
                    'line' => $line,
                    'kind' => 'data',
                ];
                break; // one report per file is enough
            }
        }

        if (empty($violations)) {
            $mode = $strictData ? ' (DDL + data-level)' : ' (DDL only)';
            $this->info("OK — {$scanned} migration(s) scanned{$mode}, 0 violations found.");

            return self::SUCCESS;
        }

        $this->error('Destructive migrations missing data-safety declarations:');
        foreach ($violations as $v) {
            $line = isset($v['line']) ? " (line {$v['line']})" : '';
            $fix = $v['kind'] === 'data'
                ? ' — use WithDataSafetySnapshot + declare the affected table in $criticalTables'
                : '';
            $this->line("  - {$v['file']}: {$v['reason']}{$line}{$fix}");
        }

        return self::FAILURE;
    }

    /**
     * Extract the body of a method from a migration class. Returns empty
     * string if not found. Handles both named-class style
     * (`class Foo extends Migration { ... }`) and the anonymous-class
     * style Laravel ships by default in `database/migrations/`
     * (`return new class extends Migration { ... }`).
     */
    protected function extractMethodBody(string $contents, string $method): string
    {
        $pattern = '#function\s+'.preg_quote($method, '#').'\s*\([^)]*\)\s*:\s*\S+\s*\{#';
        if (! preg_match($pattern, $contents, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        $len = strlen($contents);
        for ($i = $start; $i < $len; $i++) {
            $ch = $contents[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($contents, $start, $i - $start);
                }
            }
        }

        return '';
    }

    /**
     * Return the 1-based line number of the first match of $pattern inside
     * $body. Returns null if not found.
     */
    protected function firstMatchLine(string $body, string $pattern): ?int
    {
        if (! preg_match($pattern, $body, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $offset = $m[0][1] ?? 0;

        return substr_count(substr($body, 0, $offset), "\n") + 1;
    }
}
