<?php

namespace Tests\Concerns;

trait InteractsWithPublicSeoHtml
{
    protected function jsonLdGraph(string $html): array
    {
        if (! preg_match_all('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $html, $matches)) {
            return [];
        }

        $flat = [];
        foreach ($matches[1] as $body) {
            $decoded = json_decode($body, true);
            if (! is_array($decoded)) {
                continue;
            }
            $this->collectEntities($decoded, $flat);
        }

        return $flat;
    }

    private function collectEntities(array $node, array &$flat, bool $atRoot = true): void
    {
        if (isset($node['@type'])) {
            $flat[] = $node;
        }

        if ($atRoot && isset($node['@graph']) && is_array($node['@graph'])) {
            foreach ($node['@graph'] as $child) {
                if (is_array($child)) {
                    $this->collectEntities($child, $flat, false);
                }
            }
            return;
        }

        foreach ($node as $value) {
            if (is_array($value) && (! empty($value))) {
                $first = reset($value);
                if (is_array($first)) {
                    $this->collectEntities($value, $flat, false);
                } elseif ($this->isAssoc($value)) {
                    $this->collectEntities($value, $flat, false);
                }
            }
        }
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected function assertHasH1(string $html): void
    {
        preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/s', $html, $m);
        $this->assertCount(1, $m[0] ?? [], 'Expected exactly one <h1> on the page');
    }

    protected function assertH1Contains(string $html, string $needle): void
    {
        preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/s', $html, $m);
        $text = mb_strtolower(strip_tags($m[1][0] ?? ''));
        $this->assertStringContainsString(mb_strtolower($needle), $text, "H1 must contain '{$needle}'");
    }

    protected function assertMetaDescriptionInRange(string $html): void
    {
        preg_match('/<meta\s+name="description"\s+content="([^"]*)"/i', $html, $m);
        $this->assertNotEmpty($m[1] ?? '', 'Missing meta description');
        $len = mb_strlen($m[1]);
        $this->assertGreaterThanOrEqual(120, $len, 'Meta description shorter than 120 chars');
        $this->assertLessThanOrEqual(165, $len, 'Meta description longer than 165 chars');
    }

    protected function assertOgImageNotFavicon(string $html): void
    {
        preg_match('/<meta\s+property="og:image"\s+content="([^"]*)"/i', $html, $m);
        $this->assertNotEmpty($m[1] ?? '', 'Missing og:image');
        $this->assertStringNotContainsString('favicon.svg', $m[1], 'og:image must not be favicon.svg');
    }

    protected function assertHasCanonical(string $html, string $url): void
    {
        $this->assertStringContainsString('<link rel="canonical" href="' . $url . '">', $html);
    }

    protected function assertHasJsonLd(string $html): array
    {
        $graph = $this->jsonLdGraph($html);
        $this->assertNotEmpty($graph, 'Expected at least one JSON-LD @graph');
        return $graph;
    }
}