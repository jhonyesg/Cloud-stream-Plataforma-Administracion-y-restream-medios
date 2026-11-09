<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSeoSitemapTest extends TestCase
{
    public function test_sitemap_returns_200_with_xml_content_type(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
    }

    public function test_sitemap_body_parses_as_xml(): void
    {
        $body = $this->get('/sitemap.xml')->getContent();

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($body);
        libxml_clear_errors();

        $this->assertTrue($loaded, 'Sitemap body must parse as valid XML');

        $urlset = $doc->getElementsByTagName('urlset')->item(0);
        $this->assertNotNull($urlset, 'Missing <urlset> root');
        $this->assertEquals('http://www.sitemaps.org/schemas/sitemap/0.9', $urlset->namespaceURI);
    }

    public function test_sitemap_lists_every_public_url(): void
    {
        $body = $this->get('/sitemap.xml')->getContent();
        $site = rtrim(config('seo.site_url'), '/');

        foreach (config('seo.public_urls', []) as $entry) {
            $loc = $site . $entry['path'];
            $this->assertStringContainsString('<loc>' . $loc . '</loc>', $body, "Sitemap missing {$loc}");
        }
    }

    public function test_sitemap_does_not_include_auth_gated_paths(): void
    {
        $body = $this->get('/sitemap.xml')->getContent();

        foreach (['/admin', '/client', '/profile', '/login', '/register', '/dashboard', '/api'] as $forbidden) {
            $this->assertDoesNotMatchRegularExpression(
                '/<loc>[^<]*' . preg_quote($forbidden, '/') . '/',
                $body,
                "Sitemap must not contain {$forbidden}"
            );
        }
    }

    public function test_home_has_highest_priority_and_weekly_changefreq(): void
    {
        $doc = $this->loadSitemap();
        $home = $this->findUrl($doc, '/');
        $this->assertNotNull($home, 'Home URL missing from sitemap');

        $this->assertEquals('1.0', $home->getElementsByTagName('priority')->item(0)->nodeValue);
        $this->assertEquals('weekly', $home->getElementsByTagName('changefreq')->item(0)->nodeValue);
    }

    /**
     * @testWith ["streaming-para-iglesias-colombia"]
     *           ["streaming-para-emisoras-de-radio-online"]
     *           ["streaming-para-canales-de-tv-regionales"]
     *           ["streaming-para-universidades-y-educacion"]
     *           ["streaming-para-productoras-de-contenido"]
     *           ["restream-facebook-youtube-tiktok"]
     *           ["servidor-rtmp-colombia"]
     */
    public function test_landing_pages_have_priority_0_9_monthly(string $slug): void
    {
        $doc = $this->loadSitemap();
        $url = $this->findUrl($doc, '/' . $slug);
        $this->assertNotNull($url);

        $this->assertEquals('0.9', $url->getElementsByTagName('priority')->item(0)->nodeValue);
        $this->assertEquals('monthly', $url->getElementsByTagName('changefreq')->item(0)->nodeValue);
    }

    /**
     * @testWith ["cuanto-cuesta-transmitir-tv-internet"]
     *           ["que-es-rtmp-hls"]
     *           ["como-empezar-a-transmitir-tu-canal-en-5-minutos"]
     */
    public function test_blog_posts_have_priority_0_7_monthly(string $slug): void
    {
        $doc = $this->loadSitemap();
        $url = $this->findUrl($doc, '/blog/' . $slug);
        $this->assertNotNull($url);

        $this->assertEquals('0.7', $url->getElementsByTagName('priority')->item(0)->nodeValue);
        $this->assertEquals('monthly', $url->getElementsByTagName('changefreq')->item(0)->nodeValue);
    }

    public function test_faq_and_about_have_priority_0_5(): void
    {
        $doc = $this->loadSitemap();
        $faq = $this->findUrl($doc, '/preguntas-frecuentes');
        $about = $this->findUrl($doc, '/sobre-nosotros');

        $this->assertNotNull($faq);
        $this->assertNotNull($about);

        $this->assertEquals('0.5', $faq->getElementsByTagName('priority')->item(0)->nodeValue);
        $this->assertEquals('0.5', $about->getElementsByTagName('priority')->item(0)->nodeValue);
    }

    public function test_every_url_has_lastmod_in_iso_8601_format(): void
    {
        $doc = $this->loadSitemap();
        $urls = $doc->getElementsByTagName('url');

        $this->assertGreaterThan(0, $urls->length, 'Sitemap must have at least one <url>');

        for ($i = 0; $i < $urls->length; $i++) {
            $lastmod = $urls->item($i)->getElementsByTagName('lastmod')->item(0);
            $this->assertNotNull($lastmod, 'Every <url> must contain <lastmod>');
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $lastmod->nodeValue);
        }
    }

    private function loadSitemap(): \DOMDocument
    {
        $body = $this->get('/sitemap.xml')->getContent();
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadXML($body);
        libxml_clear_errors();
        return $doc;
    }

    private function findUrl(\DOMDocument $doc, string $path): ?\DOMElement
    {
        $site = rtrim(config('seo.site_url'), '/');
        $target = $site . $path;
        $urls = $doc->getElementsByTagName('url');

        for ($i = 0; $i < $urls->length; $i++) {
            $loc = $urls->item($i)->getElementsByTagName('loc')->item(0)?->nodeValue;
            if ($loc === $target) {
                return $urls->item($i);
            }
        }

        return null;
    }
}