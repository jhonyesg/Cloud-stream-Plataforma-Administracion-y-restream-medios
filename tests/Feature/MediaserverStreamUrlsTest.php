<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\User;
use App\Services\MediaserverApiException;
use App\Services\MediaserverApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaserverStreamUrlsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bloque `urls` tal como lo devuelve MediaServer Platform.
     */
    private function panelUrls(): array
    {
        return [
            'stream' => 'cinedios',
            'publish' => [
                'rtmp' => 'rtmp://15.204.8.139:1935/live/cinedios',
                'srt' => 'srt://15.204.8.139:10080?streamid=cinedios',
                'srt_full' => 'srt://15.204.8.139:10080?streamid=live/cinedios',
            ],
            'play' => [
                'hls' => 'http://15.204.8.139:8080/live/cinedios.m3u8',
                'flv' => 'http://15.204.8.139:8080/live/cinedios.flv',
                'srt' => 'srt://15.204.8.139:7386?streamid=read:cinedios',
                'webrtc_play' => 'https://canal.mediaserver.com.co/rtc/v1/play/?app=live&stream=cinedios',
                'mpegts' => 'http://15.204.8.139:1985/mpegts/cinedios',
                'via_proxy' => [
                    'https' => [
                        'hls' => 'https://canal.mediaserver.com.co/live/cinedios.m3u8',
                        'flv' => 'https://canal.mediaserver.com.co/live/cinedios.flv',
                        'webrtc_play' => 'https://canal.mediaserver.com.co/rtc/v1/play/?app=live&stream=cinedios',
                        'mpegts' => 'https://canal.mediaserver.com.co/mpegts/cinedios',
                    ],
                    'http' => [
                        'hls' => 'http://canal.mediaserver.com.co/live/cinedios.m3u8',
                    ],
                    'srt' => 'srt://canal.mediaserver.com.co:7386?streamid=read:cinedios',
                ],
            ],
        ];
    }

    private function channel(array $overrides = []): Channel
    {
        $owner = User::factory()->create(['role' => 'client', 'status' => 'active']);
        return Channel::create(array_merge([
            'owner_id' => $owner->id,
            'display_name' => 'Cine Dios',
            'slug' => 'cine-dios-' . uniqid(),
            'status' => 'active',
            'public_hls_url' => 'https://canal.mediaserver.com.co/live/cinedios.m3u8',
        ], $overrides));
    }

    private function mockPlatform(array $urls, bool $missing = false): void
    {
        $this->mock(MediaserverApiService::class, function ($mock) use ($urls, $missing) {
            $mock->shouldReceive('getStream')
                ->andReturnUsing(function () use ($urls, $missing) {
                    if ($missing) {
                        throw MediaserverApiException::panelError('/api/streams/cinedios', 404);
                    }
                    return [
                        'name' => 'cinedios',
                        'clients' => 12,
                        'publish' => ['active' => true],
                        'urls' => $urls,
                    ];
                });
        });
    }

    public function test_admin_receives_normalized_rows_for_a_channel(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $channel = $this->channel();
        $this->mockPlatform($this->panelUrls());

        $response = $this
            ->actingAs($admin)
            ->getJson("/api/channels/{$channel->id}/mediaserver/urls");

        $response->assertOk();
        $response->assertJsonPath('stream', 'cinedios');
        $response->assertJsonPath('available', true);
        $response->assertJsonPath('live', true);
        $response->assertJsonPath('clients', 12);

        $rows = collect($response->json('rows'));
        $urls = $rows->pluck('url');

        $this->assertTrue($urls->contains('rtmp://15.204.8.139:1935/live/cinedios'));
        $this->assertTrue($urls->contains('https://canal.mediaserver.com.co/live/cinedios.m3u8'));

        $this->assertEqualsCanonicalizing(
            ['publish', 'proxy'],
            $rows->pluck('group')->unique()->values()->all()
        );

        // Publicar: solo RTMP.
        $publish = $rows->where('group', 'publish');
        $this->assertCount(1, $publish);
        $this->assertSame('rtmp://15.204.8.139:1935/live/cinedios', $publish->first()['url']);

        // Reproducir: solo por dominio (HTTPS, HTTP y SRT de lectura).
        $proxy = $rows->where('group', 'proxy');
        $this->assertTrue($proxy->pluck('url')->contains('https://canal.mediaserver.com.co/live/cinedios.m3u8'));
        $this->assertTrue($proxy->pluck('url')->contains('http://canal.mediaserver.com.co/live/cinedios.m3u8'));
        $this->assertTrue($proxy->pluck('url')->contains('srt://canal.mediaserver.com.co:7386?streamid=read:cinedios'));

        // Las filas de reproducción no se abren en el navegador; la de publicación tampoco.
        $playRow = $rows->firstWhere('url', 'https://canal.mediaserver.com.co/live/cinedios.m3u8');
        $this->assertTrue($playRow['open']);
    }

    public function test_the_modal_never_exposes_the_direct_server_urls(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $channel = $this->channel();
        $this->mockPlatform($this->panelUrls());

        $response = $this
            ->actingAs($admin)
            ->getJson("/api/channels/{$channel->id}/mediaserver/urls");
        $response->assertOk();

        $urls = collect($response->json('rows'))->pluck('url');

        // Puertos directos del servidor (SRS HTTP, panel MPEG-TS y SRT de
        // lectura por IP): no se muestran al cliente.
        foreach ([
            'http://15.204.8.139:8080/live/cinedios.m3u8',
            'http://15.204.8.139:8080/live/cinedios.flv',
            'http://15.204.8.139:1985/mpegts/cinedios',
            'srt://15.204.8.139:7386?streamid=read:cinedios',
        ] as $direct) {
            $this->assertFalse($urls->contains($direct), "La URL directa no debe mostrarse: {$direct}");
        }

        // El SRT de publicación tampoco: en "Publicar" solo va el RTMP.
        $this->assertFalse($urls->contains('srt://15.204.8.139:10080?streamid=cinedios'));
        $this->assertFalse($urls->contains('srt://15.204.8.139:10080?streamid=live/cinedios'));
    }

    public function test_client_can_read_urls_of_its_own_channel(): void
    {
        $client = User::factory()->create(['role' => 'client', 'status' => 'active']);
        $channel = $this->channel(['owner_id' => $client->id]);
        $this->mockPlatform($this->panelUrls());

        $response = $this
            ->actingAs($client)
            ->getJson("/api/channels/{$channel->id}/mediaserver/urls");

        $response->assertOk();
        $response->assertJsonPath('available', true);
        $this->assertNotEmpty($response->json('rows'));
    }

    public function test_client_cannot_read_urls_of_a_channel_it_does_not_own(): void
    {
        $client = User::factory()->create(['role' => 'client', 'status' => 'active']);
        $channel = $this->channel();
        $this->mockPlatform($this->panelUrls());

        $response = $this
            ->actingAs($client)
            ->getJson("/api/channels/{$channel->id}/mediaserver/urls");

        $response->assertStatus(403);
        $this->assertNull($response->json('rows'));
    }

    public function test_channel_without_stream_degrades_to_the_configured_public_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $channel = $this->channel();
        $this->mockPlatform([], missing: true);

        $response = $this
            ->actingAs($admin)
            ->getJson("/api/channels/{$channel->id}/mediaserver/urls");

        $response->assertOk();
        $response->assertJsonPath('available', false);
        $this->assertNotEmpty($response->json('message'));

        $rows = collect($response->json('rows'));
        $this->assertCount(1, $rows);
        $this->assertSame('https://canal.mediaserver.com.co/live/cinedios.m3u8', $rows->first()['url']);
        $this->assertSame('channel', $rows->first()['group']);
    }

    public function test_guest_cannot_read_channel_urls(): void
    {
        $channel = $this->channel();

        $this->getJson("/api/channels/{$channel->id}/mediaserver/urls")
            ->assertStatus(401);
    }

    public function test_both_scheduler_views_expose_the_urls_button_and_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $client = User::factory()->create(['role' => 'client', 'status' => 'active']);
        $channel = $this->channel(['owner_id' => $client->id]);

        $adminHtml = $this->actingAs($admin)->get("/admin/scheduler?channel_id={$channel->id}")->assertOk()->getContent();
        $clientHtml = $this->actingAs($client)->get("/client/scheduler?channel_id={$channel->id}")->assertOk()->getContent();

        foreach (['admin' => $adminHtml, 'client' => $clientHtml] as $surface => $html) {
            $this->assertStringContainsString("modals.open('stream-urls'", $html, "Falta el botón de URLs en {$surface}");
            $this->assertStringContainsString('window.streamUrls = () =>', $html, "Falta el modal de URLs en {$surface}");
        }
    }

    public function test_every_row_group_has_a_section_in_the_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $channel = $this->channel();
        $this->mockPlatform($this->panelUrls());

        $response = $this
            ->actingAs($admin)
            ->getJson("/api/channels/{$channel->id}/mediaserver/urls");
        $response->assertOk();

        $modal = file_get_contents(resource_path('views/components/stream-urls-modal.blade.php'));
        preg_match_all("/key: '([a-z_]+)'/", $modal, $matches);
        $sections = $matches[1];

        foreach (collect($response->json('rows'))->pluck('group')->unique() as $group) {
            $this->assertContains($group, $sections, "El modal no tiene sección para el grupo '{$group}'");
        }

        // El modal debe consumir el mismo endpoint que expone el controller.
        $this->assertStringContainsString('/mediaserver/urls', $modal);
    }
}
