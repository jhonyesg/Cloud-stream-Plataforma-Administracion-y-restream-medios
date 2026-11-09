<?php

namespace Tests\Feature;

use App\Services\StreamingUrlBuilder;
use Tests\Concerns\InteractsWithPublicSeoHtml;
use Tests\TestCase;

class LandingDeliveryUrlsTest extends TestCase
{
    use InteractsWithPublicSeoHtml;

    private const PRODUCTION_HOST = 'canal.mediaserver.com.co';

    private const PRODUCTION_IP = '15.204.8.139';

    private const REAL_CLIENT_STREAM = 'buenisimatv';

    private function home(): string
    {
        return $this->get('/')->getContent();
    }

    public function test_home_shows_the_delivery_section_between_plans_and_faq(): void
    {
        $html = $this->home();

        $posPlanes = strpos($html, 'id="planes"');
        $posFormatos = strpos($html, 'id="formatos"');
        $posFaq = strpos($html, 'id="faq"');

        $this->assertNotFalse($posPlanes, 'No se encontró la sección de planes');
        $this->assertNotFalse($posFormatos, 'No se encontró la sección de formatos');
        $this->assertNotFalse($posFaq, 'No se encontró la sección de preguntas frecuentes');

        $this->assertGreaterThan($posPlanes, $posFormatos, 'La sección de formatos debe ir después de los planes');
        $this->assertLessThan($posFaq, $posFormatos, 'La sección de formatos debe ir antes de las preguntas frecuentes');
    }

    public function test_home_exposes_the_formatos_anchor_and_the_nav_link(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('id="formatos"', $html);
        $this->assertStringContainsString('#formatos', $html, 'El nav público debe enlazar al ancla #formatos');
    }

    public function test_home_still_renders_a_single_h1(): void
    {
        $this->assertHasH1($this->home());
    }

    public function test_publish_block_lists_rtmp_and_srt_with_their_ports(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('PUBLICAR', $html);
        $this->assertStringContainsString('rtmp://streaming.mediaserver.com.co:1935/live/tu-canal', $html);
        $this->assertStringContainsString('srt://streaming.mediaserver.com.co:10080?streamid=tu-canal', $html);

        foreach (['OBS', 'FMLE', 'vMix', 'Wirecast'] as $encoder) {
            $this->assertStringContainsString($encoder, $html, "Falta el encoder {$encoder}");
        }
    }

    public function test_consume_block_lists_every_delivery_format_over_https_and_http(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('CONSUMIR', $html);

        foreach (['HLS', 'FLV', 'WebRTC', 'MPEG-TS'] as $protocol) {
            $this->assertStringContainsString($protocol, $html, "Falta el formato {$protocol}");
        }

        $this->assertStringContainsString('https://streaming.mediaserver.com.co/live/tu-canal.m3u8', $html);
        $this->assertStringContainsString('https://streaming.mediaserver.com.co/live/tu-canal.flv', $html);
        $this->assertStringContainsString('https://streaming.mediaserver.com.co/rtc/v1/play/?app=live&amp;stream=tu-canal', $html);
        $this->assertStringContainsString('https://streaming.mediaserver.com.co/mpegts/tu-canal', $html);

        $this->assertStringContainsString('http://streaming.mediaserver.com.co/live/tu-canal.m3u8', $html);
        $this->assertStringContainsString('http://streaming.mediaserver.com.co/live/tu-canal.flv', $html);
        $this->assertStringContainsString('http://streaming.mediaserver.com.co/rtc/v1/play/?app=live&amp;stream=tu-canal', $html);
        $this->assertStringContainsString('http://streaming.mediaserver.com.co/mpegts/tu-canal', $html);
    }

    public function test_consume_block_lists_the_srt_playback_url(): void
    {
        $this->assertStringContainsString(
            'srt://streaming.mediaserver.com.co:7386?streamid=read:tu-canal',
            $this->home()
        );
    }

    public function test_consume_block_recommends_the_https_proxy_variant(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('proxy inverso', $html);
        $this->assertStringContainsString('HTTPS', $html);
        $this->assertStringContainsString('HTTP (sin TLS)', $html);

        foreach (['vMix', 'OBS', 'Astra', 'Lastra', 'FFplay', 'VLC'] as $player) {
            $this->assertStringContainsString($player, $html, "Falta el reproductor {$player}");
        }
    }

    public function test_every_protocol_row_has_a_labelled_copy_control(): void
    {
        $html = $this->home();

        foreach (['RTMP', 'SRT', 'HLS', 'FLV', 'WebRTC', 'MPEG-TS'] as $protocol) {
            $this->assertStringContainsString(
                'aria-label="Copiar la URL '.$protocol.'"',
                $html,
                "Falta el botón de copiar con etiqueta para {$protocol}"
            );
        }

        $this->assertGreaterThanOrEqual(
            11,
            substr_count($html, 'aria-label="Copiar la URL'),
            'Debe existir un botón de copiar por cada fila de protocolo'
        );

        $this->assertStringContainsString('role="status"', $html, 'El aviso de copiado debe anunciarse con role="status"');
        $this->assertStringContainsString('Reemplaza tu-canal', $html, 'El aviso debe indicar que hay que reemplazar tu-canal');
    }

    public function test_published_urls_use_the_configured_showcase_host_and_channel(): void
    {
        $html = $this->home();

        $this->assertStringContainsString(config('streaming_public.display_host'), $html);
        $this->assertStringContainsString(config('streaming_public.example_slug'), $html);
    }

    public function test_published_urls_follow_the_configuration(): void
    {
        config([
            'streaming_public.display_host' => 'host-de-prueba.ejemplo',
            'streaming_public.example_slug' => 'canal-de-prueba',
        ]);

        $html = $this->home();

        $this->assertStringContainsString('rtmp://host-de-prueba.ejemplo:1935/live/canal-de-prueba', $html);
        $this->assertStringContainsString('https://host-de-prueba.ejemplo/live/canal-de-prueba.m3u8', $html);
        $this->assertStringContainsString('srt://host-de-prueba.ejemplo:7386?streamid=read:canal-de-prueba', $html);
        $this->assertStringNotContainsString('streaming.mediaserver.com.co', $html);
    }

    public function test_section_explains_the_mpegts_path_for_decoders_and_cable_trunks(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('Los decodificadores Astra, Lastra y Cesbo', $html);
        $this->assertStringContainsString('vMix también puede entrar por esta vía', $html);
        $this->assertStringNotContainsString('Bemis', $html);
        $this->assertStringContainsString('operador de cable', $html);
        $this->assertStringContainsString('troncal de televisión', $html);
        $this->assertStringContainsString('captura satelital', $html);
        $this->assertStringContainsString('sin depender de SRT ni de plugins', $html);
    }

    public function test_section_explains_the_signal_flow(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('Tu estudio', $html);
        $this->assertStringContainsString('Datacenter en USA', $html);
        $this->assertStringContainsString('Tu audiencia', $html);
        $this->assertStringContainsString('RTMP / SRT', $html);
    }

    public function test_section_states_that_the_urls_are_templates(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('URLs de ejemplo con el formato exacto que recibes', $html);
        $this->assertStringContainsString('los host y las claves reales se entregan con la activación del plan', $html);
    }

    public function test_published_home_publishes_no_production_endpoint(): void
    {
        $html = $this->home();

        $this->assertStringNotContainsString(self::PRODUCTION_IP, $html, 'La IP del servidor no se puede publicar');
        $this->assertStringNotContainsString(self::PRODUCTION_HOST, $html, 'El host de streaming real no se puede publicar');
        $this->assertStringNotContainsString(self::REAL_CLIENT_STREAM, $html, 'No se puede publicar el nombre de un canal real');
        $this->assertStringNotContainsString('MEDIASERVER_API_URL', $html);
    }

    public function test_showcase_host_is_not_the_production_host(): void
    {
        $this->assertNotSame(
            self::PRODUCTION_HOST,
            config('streaming_public.display_host'),
            'El host de exhibición no puede ser el host de producción'
        );
        $this->assertNotSame(
            self::PRODUCTION_IP,
            config('streaming_public.display_host'),
            'El host de exhibición no puede ser la IP del servidor'
        );
    }

    public function test_component_renders_standalone(): void
    {
        $html = $this->blade('<x-public-delivery-urls />');

        $this->assertStringContainsString('PUBLICAR', $html);
        $this->assertStringContainsString('CONSUMIR', $html);
        $this->assertStringContainsString('rtmp://', $html);
        $this->assertStringContainsString('srt://', $html);
        $this->assertStringContainsString('.m3u8', $html);
        $this->assertStringContainsString('.flv', $html);
        $this->assertStringContainsString('/rtc/v1/play/', $html);
        $this->assertStringContainsString('/mpegts/', $html);
    }

    public function test_builder_returns_the_whole_matrix(): void
    {
        $matrix = app(StreamingUrlBuilder::class)->showcase();

        $this->assertCount(2, $matrix['publish']);
        $this->assertCount(4, $matrix['consume']['https']);
        $this->assertCount(4, $matrix['consume']['http']);
        $this->assertCount(1, $matrix['consume']['srt']);

        $this->assertSame(['RTMP', 'SRT'], array_column($matrix['publish'], 'protocol'));
        $this->assertSame(['HLS', 'FLV', 'WebRTC', 'MPEG-TS'], array_column($matrix['consume']['https'], 'protocol'));
        $this->assertSame(['HLS', 'FLV', 'WebRTC', 'MPEG-TS'], array_column($matrix['consume']['http'], 'protocol'));
        $this->assertSame(['SRT'], array_column($matrix['consume']['srt'], 'protocol'));
    }
}
