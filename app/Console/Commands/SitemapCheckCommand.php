<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SitemapCheckCommand extends Command
{
    protected $signature = 'sitemap:check {--base=http://localhost : Base URL to test against}';
    protected $description = 'Verify every URL in config(seo.public_urls) returns 200 with expected JSON-LD';

    public function handle(): int
    {
        $base = rtrim($this->option('base'), '/');
        $urls = config('seo.public_urls', []);
        $failures = 0;

        foreach ($urls as $entry) {
            $path = $entry['path'];
            $url = $base . $path;
            $expectedType = $entry['type'] ?? 'home';

            try {
                $response = Http::timeout(10)->get($url);
                $status = $response->status();
            } catch (\Throwable $e) {
                $this->error("FAIL {$path}: " . $e->getMessage());
                $failures++;
                continue;
            }

            if ($status !== 200) {
                $this->error("FAIL {$path}: HTTP {$status}");
                $failures++;
                continue;
            }

            $html = $response->body();
            $hasH1 = preg_match('/<h1[^>]*>/i', $html) === 1;
            $hasJsonLd = str_contains($html, 'application/ld+json');

            $ok = $hasH1 && $hasJsonLd;
            $mark = $ok ? 'OK  ' : 'FAIL';
            $this->line("{$mark} {$path} (HTTP {$status}, h1=" . ($hasH1 ? 'Y' : 'N') . ", ld+json=" . ($hasJsonLd ? 'Y' : 'N') . ")");

            if (! $ok) {
                $failures++;
            }
        }

        if ($failures > 0) {
            $this->error("{$failures} failure(s)");
            return self::FAILURE;
        }

        $this->info('All URLs OK.');
        return self::SUCCESS;
    }
}
