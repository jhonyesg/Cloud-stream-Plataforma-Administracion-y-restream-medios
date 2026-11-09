<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithPublicSeoHtml;
use Tests\TestCase;

class LandingYouTubeWalkthroughTest extends TestCase
{
    use InteractsWithPublicSeoHtml;

    /** @return array<int, array<string, mixed>> */
    private function steps(string $html): array
    {
        preg_match_all('/data-start="([^"]*)"[^>]*data-image="([^"]*)"/', $html, $matches, PREG_SET_ORDER);

        return array_map(fn ($m) => [
            'start' => (float) $m[1],
            'image' => $m[2],
        ], $matches);
    }

    public function test_home_renders_a_single_first_party_video_with_a_poster(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertSame(1, substr_count($html, '<video'), 'La home debe tener un solo elemento <video>');

        preg_match('/<video[^>]*>/s', $html, $m);
        $this->assertNotEmpty($m, 'No se encontró el elemento <video>');
        $video = $m[0];

        $this->assertStringContainsString('src="/videos/youtube-walkthrough/', $video);
        $this->assertStringContainsString('poster="/images/demo/youtube-walkthrough/', $video);
        $this->assertStringContainsString('preload="none"', $video);
        $this->assertStringContainsString('muted', $video);
        $this->assertStringContainsString('playsinline', $video);
        $this->assertStringContainsString('controls', $video);
        $this->assertStringContainsString('aria-label="', $video);
    }

    public function test_home_renders_the_six_steps_with_their_own_screenshot(): void
    {
        $html = $this->get('/')->getContent();
        $steps = $this->steps($html);

        $this->assertCount(6, $steps, 'La sección debe tener exactamente 6 pasos con su captura');

        $expected = [
            'el-panel.jpg',
            'conectar-cuenta.jpg',
            'crear-destino.jpg',
            'programar-ventana.jpg',
            'varias-ventanas.jpg',
            'encender-compartir.jpg',
        ];

        foreach ($steps as $index => $step) {
            $this->assertSame('/images/demo/youtube-walkthrough/' . $expected[$index], $step['image']);
            $this->assertGreaterThanOrEqual(0, $step['start']);
        }

        // Los capítulos avanzan en el tiempo: cada paso apunta más adelante
        // que el anterior dentro del video continuo.
        $starts = array_column($steps, 'start');
        $sorted = $starts;
        sort($sorted);
        $this->assertSame($sorted, $starts, 'Los capítulos deben ir en orden temporal');
        $this->assertSame($starts[0], 0.0, 'El primer capítulo arranca en 0');
    }

    public function test_home_publishes_no_screenshot_with_third_party_personal_data(): void
    {
        $html = $this->get('/')->getContent();

        // Las capturas del walkthrough están sanificadas: no publican el
        // nombre de la persona ni su correo.
        $this->assertStringNotContainsString('Efrain Suarez', $html);
        $this->assertStringNotContainsString('jhonyesg@gmail.com', $html);
        $this->assertStringNotContainsString('pruebas@pruebas.com', $html);
    }

    public function test_step_text_describes_the_real_flow(): void
    {
        $html = $this->get('/')->getContent();
        $text = mb_strtolower(strip_tags($html));

        $this->assertStringContainsString('google oauth', $text);
        $this->assertStringContainsString('usar mi cuenta', $text);
        $this->assertStringContainsString('slot', $text);
        $this->assertStringContainsString('privacidad', $text);
        $this->assertStringContainsString('automáticamente', $text);
        $this->assertStringContainsString('en vivo en youtube', $text);
    }

    public function test_home_json_ld_contains_a_first_party_video_object(): void
    {
        $html = $this->get('/')->getContent();
        $graph = $this->jsonLdGraph($html);

        $videos = array_values(array_filter($graph, fn ($e) => ($e['@type'] ?? null) === 'VideoObject'));
        $this->assertCount(1, $videos, 'El JSON-LD de la home debe tener exactamente un VideoObject');

        $video = $videos[0];
        $this->assertStringStartsWith('https://cloudstream.mediaserver.com.co/videos/', $video['contentUrl']);
        $this->assertStringStartsWith('https://cloudstream.mediaserver.com.co/images/', $video['thumbnailUrl']);
        $this->assertNotEmpty($video['name']);
        $this->assertNotEmpty($video['description']);
        $this->assertNotEmpty($video['uploadDate']);
        $this->assertNotEmpty($video['duration']);

        // El video del schema debe ser el mismo que referencia la página.
        $this->assertStringContainsString($video['contentUrl'], $html);
        $this->assertStringContainsString($video['thumbnailUrl'], $html);
    }

    public function test_home_advertises_the_video_in_social_metadata(): void
    {
        $html = $this->get('/')->getContent();

        foreach (['og:video', 'og:video:type', 'og:video:poster'] as $property) {
            $this->assertMatchesRegularExpression(
                '/<meta property="' . preg_quote($property, '/') . '" content="[^"]+">/',
                $html,
                "Falta la meta {$property}"
            );
        }
    }

    public function test_published_media_exists_and_stays_within_the_budget(): void
    {
        $html = $this->get('/')->getContent();
        $steps = $this->steps($html);

        preg_match_all('/(?:src|poster|data-image)="(\/(?:videos|images)\/[^"]+)"/', $html, $matches);

        $paths = array_unique(array_merge(
            array_column($steps, 'image'),
            $matches[1]
        ));

        $this->assertNotEmpty($paths);

        $total = 0;
        foreach ($paths as $path) {
            $file = public_path(ltrim($path, '/'));
            $this->assertFileExists($file, "Falta el medio publicado: {$path}");
            $total += filesize($file);
        }

        $this->assertLessThanOrEqual(
            6 * 1024 * 1024,
            $total,
            'El peso total de los medios del walkthrough no puede superar 6 MB'
        );
    }
}
