<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithPublicSeoHtml;
use Tests\TestCase;

class PublicSeoPriceConsistencyTest extends TestCase
{
    use InteractsWithPublicSeoHtml;

    public function test_home_plan1_price_consistent_across_html_whatsapp_and_jsonld(): void
    {
        $expected = number_format(config('seo.plans.plan1.price_cop'), 0, ',', '.');

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('$' . $expected, $html, 'Visible HTML must show plan1 price');

        preg_match_all('/href="(https:\/\/wa\.me\/[^"]+)"/i', $html, $matches);
        $waUrls = $matches[1] ?? [];
        $this->assertNotEmpty($waUrls, 'Expected WhatsApp anchor URLs on the home page');

        $foundInWa = false;
        foreach ($waUrls as $url) {
            $decoded = urldecode($url);
            if (str_contains($decoded, '$' . $expected) || str_contains($decoded, config('seo.plans.plan1.price_cop') . '/mes')) {
                $foundInWa = true;
                break;
            }
        }
        $this->assertTrue($foundInWa, 'Plan-1 price must be embedded in at least one WhatsApp URL');

        $graph = $this->jsonLdGraph($html);
        $offers = array_values(array_filter($graph, fn ($e) => ($e['@type'] ?? null) === 'Offer'));
        $priceList = array_column($offers, 'price');

        $this->assertContains(
            (string) config('seo.plans.plan1.price_cop'),
            $priceList,
            'JSON-LD must include Offer with plan1 price'
        );
    }

    public function test_home_plan2_price_consistent_across_html_whatsapp_and_jsonld(): void
    {
        $expected = number_format(config('seo.plans.plan2.price_cop'), 0, ',', '.');

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('$' . $expected, $html, 'Visible HTML must show plan2 price');

        preg_match_all('/href="(https:\/\/wa\.me\/[^"]+)"/i', $html, $matches);
        $waUrls = $matches[1] ?? [];
        $this->assertNotEmpty($waUrls, 'Expected WhatsApp anchor URLs on the home page');

        $foundInWa = false;
        foreach ($waUrls as $url) {
            $decoded = urldecode($url);
            if (str_contains($decoded, '$' . $expected)) {
                $foundInWa = true;
                break;
            }
        }
        $this->assertTrue($foundInWa, 'Plan-2 price must be embedded in at least one WhatsApp URL');

        $graph = $this->jsonLdGraph($html);
        $offers = array_values(array_filter($graph, fn ($e) => ($e['@type'] ?? null) === 'Offer'));
        $priceList = array_column($offers, 'price');

        $this->assertContains(
            (string) config('seo.plans.plan2.price_cop'),
            $priceList,
            'JSON-LD must include Offer with plan2 price'
        );
    }
}