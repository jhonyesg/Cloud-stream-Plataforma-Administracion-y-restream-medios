<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithPublicSeoHtml;
use Tests\TestCase;

class PublicSeoHeadTest extends TestCase
{
    use InteractsWithPublicSeoHtml;

    public function test_home_head_contains_all_required_tags(): void
    {
        $html = $this->get('/')->getContent();

        preg_match('/<title>(.*?)<\/title>/s', $html, $titleMatch);
        $this->assertNotEmpty($titleMatch[1] ?? '', 'Missing <title>');
        $titleLen = mb_strlen(trim($titleMatch[1]));
        $this->assertGreaterThanOrEqual(30, $titleLen, 'Title too short');
        $this->assertLessThanOrEqual(70, $titleLen, 'Title too long');

        $this->assertMetaDescriptionInRange($html);
        $this->assertOgImageNotFavicon($html);
        $this->assertHasCanonical($html, rtrim(config('seo.site_url'), '/') . '/');
        $this->assertHasH1($html);
        $this->assertH1Contains($html, 'señal de TV en vivo');
        $this->assertH1Contains($html, 'Colombia');

        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta property="og:description"', $html);
        $this->assertStringContainsString('<meta property="og:url"', $html);
        $this->assertStringContainsString('<meta name="twitter:card"', $html);

        $graph = $this->assertHasJsonLd($html);
        $types = array_column($graph, '@type');
        $this->assertContains('Organization', $types, 'JSON-LD must contain Organization');
    }

    /**
     * @testWith ["streaming-para-iglesias-colombia"]
     *           ["streaming-para-emisoras-de-radio-online"]
     *           ["restream-facebook-youtube-tiktok"]
     *           ["servidor-rtmp-colombia"]
     */
    public function test_landing_pages_have_one_h1_and_json_ld(string $slug): void
    {
        $html = $this->get('/' . $slug)->getContent();

        $this->assertHasH1($html);
        $this->assertMetaDescriptionInRange($html);
        $this->assertOgImageNotFavicon($html);
        $this->assertHasCanonical($html, rtrim(config('seo.site_url'), '/') . '/' . $slug);
        $this->assertHasJsonLd($html);
    }

    /**
     * @testWith ["cuanto-cuesta-transmitir-tv-internet"]
     *           ["que-es-rtmp-hls"]
     *           ["como-empezar-a-transmitir-tu-canal-en-5-minutos"]
     */
    public function test_blog_articles_have_one_h1_and_json_ld(string $slug): void
    {
        $html = $this->get('/blog/' . $slug)->getContent();

        $this->assertHasH1($html);
        $this->assertMetaDescriptionInRange($html);
        $this->assertOgImageNotFavicon($html);
        $this->assertHasCanonical($html, rtrim(config('seo.site_url'), '/') . '/blog/' . $slug);
        $this->assertHasJsonLd($html);
    }

    public function test_faq_head_is_complete(): void
    {
        $html = $this->get('/preguntas-frecuentes')->getContent();

        preg_match('/<title>(.*?)<\/title>/s', $html, $m);
        $this->assertNotEmpty($m[1] ?? '', 'Missing <title>');

        $this->assertMetaDescriptionInRange($html);
        $this->assertOgImageNotFavicon($html);
        $this->assertHasCanonical($html, rtrim(config('seo.site_url'), '/') . '/preguntas-frecuentes');
        $this->assertHasH1($html);
        $this->assertHasJsonLd($html);
    }

    public function test_about_head_is_complete(): void
    {
        $html = $this->get('/sobre-nosotros')->getContent();

        preg_match('/<title>(.*?)<\/title>/s', $html, $m);
        $this->assertNotEmpty($m[1] ?? '', 'Missing <title>');

        $this->assertMetaDescriptionInRange($html);
        $this->assertOgImageNotFavicon($html);
        $this->assertHasCanonical($html, rtrim(config('seo.site_url'), '/') . '/sobre-nosotros');
        $this->assertHasH1($html);
        $this->assertHasJsonLd($html);
    }

    public function test_json_ld_body_is_valid_json(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches[1], 'Missing JSON-LD scripts');

        foreach ($matches[1] as $body) {
            $decoded = json_decode($body, true);
            $this->assertIsArray($decoded, 'JSON-LD body must parse as JSON');
        }
    }
}