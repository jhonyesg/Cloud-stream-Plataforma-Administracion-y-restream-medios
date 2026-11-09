<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSeoCrawlabilityTest extends TestCase
{
    public function test_robots_txt_returns_200(): void
    {
        $this->get('/robots.txt')->assertOk();
    }

    public function test_robots_txt_declares_sitemap(): void
    {
        $body = $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString(
            'Sitemap: ' . rtrim(config('seo.site_url'), '/') . '/sitemap.xml',
            $body
        );
    }

    public function test_robots_txt_disallows_auth_gated_paths(): void
    {
        $body = $this->get('/robots.txt')->getContent();

        foreach (['/admin', '/client', '/profile', '/login', '/register'] as $forbidden) {
            $this->assertStringContainsString(
                'Disallow: ' . $forbidden,
                $body,
                "robots.txt must contain Disallow: {$forbidden}"
            );
        }
    }

    public function test_robots_txt_does_not_disallow_public_pages(): void
    {
        $body = $this->get('/robots.txt')->getContent();
        $lines = preg_split('/\r?\n/', $body) ?: [];

        $publicPaths = [];
        foreach (config('seo.public_urls', []) as $entry) {
            $publicPaths[] = $entry['path'];
        }

        $disallowed = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*Disallow:\s*(\S+)/i', $line, $m)) {
                $disallowed[] = $m[1];
            }
        }

        foreach ($publicPaths as $path) {
            foreach ($disallowed as $rule) {
                $this->assertStringStartsNotWith(
                    $rule,
                    $path,
                    "Public path {$path} must not match Disallow rule {$rule}"
                );
            }
        }
    }
}