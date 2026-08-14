<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckDestructiveMigrationsCommand extends Command
{
    protected $signature = 'check:destructive-migrations {--path=database/migrations}';

    protected $description = 'Scan migrations for destructive DDL in up() without the WithDataSafetySnapshot trait.';

    /** @var string[] */
    protected array $destructivePatterns = [
        '/Schema::dropIfExists\s*\(/',
        '/Schema::drop\s*\(/',
        '/->dropColumn\s*\(/',
        '/Schema::table\s*\([^,]+,\s*function[^}]*->dropColumn/s',
    ];

    public function handle(): int
    {
        $path = base_path((string) $this->option('path'));
        $files = glob($path.'/*.php');
        if (! $files) {
            $this->error("No migration files found in {$path}");

            return self::FAILURE;
        }

        $violations = [];

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            $upBody = $this->extractMethodBody($contents, 'up');

            $hasUpDestructive = false;
            foreach ($this->destructivePatterns as $pattern) {
                if (preg_match($pattern, $upBody)) {
                    $hasUpDestructive = true;
                    break;
                }
            }

            if (! $hasUpDestructive) {
                continue;
            }

            $usesTrait = str_contains($contents, 'WithDataSafetySnapshot');
            $declaresCritical = preg_match('/\$criticalTables\s*=\s*\[/', $contents);

            if (! $usesTrait || ! $declaresCritical) {
                $violations[] = [
                    'file' => basename($file),
                    'reason' => ! $usesTrait ? 'missing WithDataSafetySnapshot trait' : 'missing $criticalTables declaration',
                ];
            }
        }

        if (empty($violations)) {
            $this->info('OK — all destructive up() migrations declare WithDataSafetySnapshot + $criticalTables.');

            return self::SUCCESS;
        }

        $this->error('Destructive migrations missing data-safety declarations:');
        foreach ($violations as $v) {
            $this->line("  - {$v['file']}: {$v['reason']}");
        }

        return self::FAILURE;
    }

    /**
     * Extract the body of a method from a migration class. Returns empty
     * string if not found.
     */
    protected function extractMethodBody(string $contents, string $method): string
    {
        if (! preg_match('/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)\s*:\s*\S+\s*\{(.*?)\n\s{4}\}/s', $contents, $m)) {
            return '';
        }

        return $m[1] ?? '';
    }
}
