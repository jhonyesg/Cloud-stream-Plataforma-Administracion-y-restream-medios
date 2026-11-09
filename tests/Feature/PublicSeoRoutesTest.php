<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithPublicSeoHtml;
use Tests\TestCase;

class PublicSeoRoutesTest extends TestCase
{
    use InteractsWithPublicSeoHtml;

    public function test_home_returns_200_for_anonymous_visitor(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_blog_index_returns_200(): void
    {
        $this->get('/blog')->assertOk();
    }

    public function test_faq_returns_200(): void
    {
        $this->get('/preguntas-frecuentes')->assertOk();
    }

    public function test_about_returns_200(): void
    {
        $this->get('/sobre-nosotros')->assertOk();
    }

    public function test_sitemap_returns_200(): void
    {
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_robots_returns_200(): void
    {
        $this->get('/robots.txt')->assertOk();
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
    public function test_landing_routes_return_200(string $slug): void
    {
        $this->get('/' . $slug)->assertOk();
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
    public function test_blog_article_routes_return_200(string $slug): void
    {
        $this->get('/blog/' . $slug)->assertOk();
    }

    public function test_unknown_landing_returns_404(): void
    {
        $this->get('/streaming-para-inexistente')->assertNotFound();
    }

    public function test_unknown_blog_slug_returns_404(): void
    {
        $this->get('/blog/no-existe')->assertNotFound();
    }
}