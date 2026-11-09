<?php

namespace Tests\Feature\Services;

use App\Models\Channel;
use App\Models\EmissionState;
use App\Models\MediaItem;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use App\Models\User;
use App\Services\EmisionStartExceptionFactory;
use App\Services\Scheduler\TemplateActivator;
use App\Services\ScheduledPlaylistResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ScheduledPlaylistResolverHealTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): ScheduledPlaylistResolver
    {
        return new ScheduledPlaylistResolver(
            new EmisionStartExceptionFactory,
            new TemplateActivator,
        );
    }

    private function makeChannel(string $status = 'active'): Channel
    {
        $owner = User::factory()->create();
        return Channel::create([
            'owner_id' => $owner->id,
            'slug' => 'test-ch-'.uniqid(),
            'display_name' => 'Test Channel',
            'root_path' => '/tmp/test-root-'.uniqid(),
            'status' => $status,
        ]);
    }

    private function buildHealableChannel(Channel $channel, int $blockCount = 4): array
    {
        $template = ScheduleTemplate::create([
            'channel_id' => $channel->id,
            'name' => 'Programación',
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'draft',
        ]);
        $playlist = Playlist::create([
            'channel_id' => $channel->id,
            'name' => 'Test PL '.uniqid(),
        ]);
        for ($d = 1; $d <= $blockCount; $d++) {
            ScheduleBlock::create([
                'template_id' => $template->id,
                'day_of_month' => $d,
                'playlist_id' => $playlist->id,
            ]);
        }
        $today = now()->day;
        $block = ScheduleBlock::where('template_id', $template->id)
            ->where('day_of_month', $today)
            ->first();
        $media = MediaItem::create([
            'channel_id' => $channel->id,
            'filename' => 'test-'.uniqid().'.mp4',
            'kind' => 'video',
            'status' => 'ready',
            'duration_sec' => 60,
        ]);
        PlaylistItem::create([
            'playlist_id' => $playlist->id,
            'media_item_id' => $media->id,
            'position' => 1,
            'start_sec' => 0,
        ]);
        return [$template, $playlist, $block];
    }

    public function test_emission_resolves_draft_template_with_blocks_and_active_channel(): void
    {
        $channel = $this->makeChannel('active');
        [$template] = $this->buildHealableChannel($channel, 4);

        $resolved = $this->resolver()->resolveForNow($channel);

        $this->assertSame($template->id, $resolved['template']->id);
        $this->assertSame('active', ScheduleTemplate::find($template->id)->status);
    }

    public function test_emission_resolve_throws_for_suspended_channel_with_draft_blocks(): void
    {
        $channel = $this->makeChannel('suspended');
        [$template] = $this->buildHealableChannel($channel, 4);

        try {
            $this->resolver()->resolveForNow($channel);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No hay programación activa', $e->getMessage());
        }

        $this->assertSame('draft', ScheduleTemplate::find($template->id)->status);
    }

    public function test_emission_resolve_throws_for_empty_draft(): void
    {
        $channel = $this->makeChannel('active');
        ScheduleTemplate::create([
            'channel_id' => $channel->id,
            'name' => 'Empty',
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'draft',
        ]);

        try {
            $this->resolver()->resolveForNow($channel);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No hay programación activa', $e->getMessage());
        }
    }

    public function test_emission_resolve_throws_when_no_template_exists(): void
    {
        $channel = $this->makeChannel('active');

        try {
            $this->resolver()->resolveForNow($channel);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No hay programación activa', $e->getMessage());
        }
    }
}
