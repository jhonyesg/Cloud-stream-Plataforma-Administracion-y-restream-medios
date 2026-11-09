<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithPublicSeoHtml;
use Tests\TestCase;

class PublicSeoSchemaTest extends TestCase
{
    use InteractsWithPublicSeoHtml;

    public function test_home_graph_contains_all_required_entities(): void
    {
        $html = $this->get('/')->getContent();
        $graph = $this->jsonLdGraph($html);
        $types = array_column($graph, '@type');

        foreach (['Organization', 'WebSite', 'Service', 'Offer', 'FAQPage', 'BreadcrumbList', 'ContactPoint'] as $required) {
            $this->assertContains($required, $types, "Home JSON-LD must contain {$required}");
        }
    }

    public function test_home_offer_prices_match_config(): void
    {
        $html = $this->get('/')->getContent();
        $graph = $this->jsonLdGraph($html);

        $offers = array_values(array_filter($graph, fn ($e) => ($e['@type'] ?? null) === 'Offer'));

        $expected = [
            'plan1' => (string) config('seo.plans.plan1.price_cop'),
            'plan2' => (string) config('seo.plans.plan2.price_cop'),
            'plan3' => (string) config('seo.plans.plan3.price_cop'),
        ];

        $priceList = array_column($offers, 'price');
        foreach ($expected as $price) {
            $this->assertContains($price, $priceList, "Home JSON-LD must include Offer price {$price}");
        }
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
    public function test_landing_graph_contains_required_entities(string $slug): void
    {
        $html = $this->get('/' . $slug)->getContent();
        $graph = $this->jsonLdGraph($html);
        $types = array_column($graph, '@type');

        foreach (['Service', 'Offer', 'FAQPage', 'BreadcrumbList'] as $required) {
            $this->assertContains($required, $types, "{$slug} JSON-LD must contain {$required}");
        }
    }

    /**
     * @testWith ["cuanto-cuesta-transmitir-tv-internet"]
     *           ["como-transmitir-canal-tv-internet"]
     *           ["que-es-rtmp-hls"]
     *           ["alternativas-a-obs-para-transmitir-24-7"]
     *           ["como-elegir-servidor-de-streaming-en-colombia"]
     *           ["streaming-para-iglesias-como-transmitir-misas-y-eventos-en-vivo"]
     *           ["como-verificar-que-tu-pauta-publicitaria-se-emitio-en-tv"]
     *           ["rtmp-vs-hls-vs-srt-diferencias"]
     *           ["casos-de-uso-emisoras-colombianas-24-7"]
     *           ["como-empezar-a-transmitir-tu-canal-en-5-minutos"]
     */
    public function test_blog_article_graph_contains_article(string $slug): void
    {
        $html = $this->get('/blog/' . $slug)->getContent();
        $graph = $this->jsonLdGraph($html);
        $types = array_column($graph, '@type');

        $this->assertTrue(
            in_array('Article', $types, true) || in_array('BlogPosting', $types, true),
            "{$slug} JSON-LD must contain Article or BlogPosting"
        );
    }

    public function test_blog_index_emits_blog_and_itemlist_schema(): void
    {
        $html = $this->get('/blog')->getContent();
        $graph = $this->jsonLdGraph($html);
        $types = array_column($graph, '@type');

        $this->assertContains('Blog', $types, 'Blog index JSON-LD must contain Blog');
        $this->assertContains('ItemList', $types, 'Blog index JSON-LD must contain ItemList');
    }

    public function test_faq_page_has_at_least_fifteen_questions(): void
    {
        $html = $this->get('/preguntas-frecuentes')->getContent();
        $graph = $this->jsonLdGraph($html);

        $faq = null;
        foreach ($graph as $entity) {
            if (($entity['@type'] ?? null) === 'FAQPage') {
                $faq = $entity;
                break;
            }
        }

        $this->assertNotNull($faq, 'FAQPage entity not found');
        $mainEntity = $faq['mainEntity'] ?? [];
        $this->assertGreaterThanOrEqual(15, count($mainEntity), 'FAQPage must have ≥15 Question entries');

        foreach ($mainEntity as $question) {
            $this->assertEquals('Question', $question['@type'] ?? null);
            $this->assertNotEmpty($question['name'] ?? '');
            $this->assertNotEmpty($question['acceptedAnswer']['text'] ?? '');
        }
    }

    public function test_about_page_emits_aboutpage_with_org_reference(): void
    {
        $html = $this->get('/sobre-nosotros')->getContent();
        $graph = $this->jsonLdGraph($html);

        $about = null;
        $org = null;
        foreach ($graph as $entity) {
            if (($entity['@type'] ?? null) === 'AboutPage' && $about === null) {
                $about = $entity;
            }
            if (($entity['@type'] ?? null) === 'Organization' && isset($entity['@id'])) {
                $org = $entity;
            }
        }

        $this->assertNotNull($about, 'AboutPage entity not found');
        $this->assertNotNull($org, 'Organization entity not found');

        $expectedOrgId = rtrim(config('seo.site_url'), '/') . '/#organization';
        $this->assertEquals($expectedOrgId, $org['@id'] ?? null);
        $this->assertEquals($expectedOrgId, $about['about']['@id'] ?? null);
        $this->assertEquals('Media Clouding SAS', $org['name']);
    }
}