<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\MediaItem;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use App\Models\User;
use App\Services\PlaylistCueInserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CueInterruptionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'username' => 'cue-owner',
            'email' => 'cue-owner@example.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    private function makeChannel(User $user, string $slug): Channel
    {
        return Channel::create([
            'slug' => $slug,
            'display_name' => $slug,
            'owner_id' => $user->id,
            'root_path' => '/tmp/cue-tests',
        ]);
    }

    private function makeMovie(Channel $channel, float $duration = 50_000): MediaItem
    {
        return MediaItem::create([
            'channel_id' => $channel->id,
            'filename' => 'movie.mp4',
            'kind' => 'video',
            'status' => 'ready',
            'duration_sec' => $duration,
        ]);
    }

    private function makeCue(Channel $channel, float $duration = 45): MediaItem
    {
        return MediaItem::create([
            'channel_id' => $channel->id,
            'filename' => 'ad.mp4',
            'kind' => 'ad',
            'status' => 'ready',
            'duration_sec' => $duration,
        ]);
    }

    public function test_cue_inserted_in_middle_orders_content_cue_tail(): void
    {
        $channel = $this->makeChannel($this->makeUser(), 'ch-middle');
        $movie = $this->makeMovie($channel);
        $cue = $this->makeCue($channel);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1]);

        $result = app(PlaylistCueInserter::class)->insertAt($playlist, 1000, $cue);

        $rows = $playlist->items()->with('mediaItem')->orderBy('position')->get();

        $this->assertSame(3, $rows->count());
        $this->assertSame(1, $rows[0]->position);
        $this->assertSame($movie->id, $rows[0]->media_item_id);
        $this->assertSame(1000.0, $rows[0]->cue_out_sec);

        $this->assertSame(2, $rows[1]->position);
        $this->assertSame($cue->id, $rows[1]->media_item_id);
        $this->assertSame(1000, $rows[1]->start_sec);

        $this->assertSame(3, $rows[2]->position);
        $this->assertSame($movie->id, $rows[2]->media_item_id);
        $this->assertSame(1000.0, $rows[2]->cue_in_sec);
        $this->assertSame(1045, $rows[2]->start_sec);
        $this->assertSame($rows[0]->id, $rows[2]->split_from_id);

        $this->assertSame($cue->id, $result['inserted']->media_item_id);
        $this->assertSame($movie->id, $result['split_from']->media_item_id);
    }

    public function test_cue_at_start_keeps_content_after_cue(): void
    {
        $channel = $this->makeChannel($this->makeUser(), 'ch-start');
        $movie = $this->makeMovie($channel, 3600);
        $cue = $this->makeCue($channel);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1]);

        app(PlaylistCueInserter::class)->insertAt($playlist, 0, $cue);

        $rows = $playlist->items()->with('mediaItem')->orderBy('position')->get();

        $this->assertSame($cue->id, $rows[0]->media_item_id);
        $this->assertSame(0, $rows[0]->start_sec);
        $this->assertSame($movie->id, $rows[1]->media_item_id);
        $this->assertSame(45, $rows[1]->start_sec);
    }

    public function test_cue_at_end_appends_without_tail(): void
    {
        $channel = $this->makeChannel($this->makeUser(), 'ch-end');
        $movie = $this->makeMovie($channel, 3600);
        $cue = $this->makeCue($channel);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1]);

        $result = app(PlaylistCueInserter::class)->insertAt($playlist, 3600, $cue);

        $this->assertNull($result['split_from']);
        $rows = $playlist->items()->orderBy('position')->get();
        $this->assertSame(2, $rows->count());
        $this->assertSame($cue->id, $rows[1]->media_item_id);
    }

    public function test_normalize_split_ordering_fixes_legacy_inverted_splits(): void
    {
        $channel = $this->makeChannel($this->makeUser(), 'ch-legacy');
        $movie = $this->makeMovie($channel, 3600);
        $cue = $this->makeCue($channel);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $parent = $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1, 'start_sec' => 0, 'cue_out_sec' => 1000]);
        $tail = $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 2, 'start_sec' => 1045, 'cue_in_sec' => 1000, 'split_from_id' => $parent->id]);
        $playlist->items()->create(['media_item_id' => $cue->id, 'position' => 3, 'start_sec' => 1000]);

        $fixed = Playlist::normalizeSplitOrdering($playlist);

        $this->assertSame(1, $fixed);
        $rows = $playlist->items()->orderBy('position')->with('mediaItem')->get();
        $this->assertSame($movie->id, $rows[0]->media_item_id);
        $this->assertSame($cue->id, $rows[1]->media_item_id);
        $this->assertSame($movie->id, $rows[2]->media_item_id);
        $this->assertSame($tail->id, $rows[2]->id);
    }

    public function test_timeline_builder_materializes_content_cue_content(): void
    {
        $channel = $this->makeChannel($this->makeUser(), 'ch-timeline');
        $movie = $this->makeMovie($channel, 50_000);
        $cue = $this->makeCue($channel);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1]);

        $template = ScheduleTemplate::create([
            'channel_id' => $channel->id,
            'name' => 'T',
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'active',
        ]);
        ScheduleBlock::create([
            'template_id' => $template->id,
            'day_of_month' => now()->day,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'kind' => 'program',
            'playlist_id' => $playlist->id,
        ]);

        app(PlaylistCueInserter::class)->insertAt($playlist, 2000, $cue);
        $result = app(\App\Services\TimelineBuilder::class)->buildForDay($template, now()->day);

        $rows = $result['items']->sortBy('starts_at_sec')->values();

        $this->assertSame(3, $rows->count());
        $this->assertSame('content', $rows[0]->kind);
        $this->assertSame('cue', $rows[1]->kind);
        $this->assertSame('content', $rows[2]->kind);

        $this->assertSame(2000, $rows[0]->ends_at_sec);
        $this->assertSame(2000, $rows[1]->starts_at_sec);
        $this->assertSame(2045, $rows[1]->ends_at_sec);
        $this->assertSame(2045, $rows[2]->starts_at_sec);
        $this->assertSame(2000.0, $rows[2]->cue_in_sec);
    }

    public function test_playlist_cues_api_returns_ordered_split(): void
    {
        $owner = $this->makeUser();
        $channel = $this->makeChannel($owner, 'ch-api');
        $movie = $this->makeMovie($channel);
        $cue = $this->makeCue($channel);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1]);

        $response = $this->actingAs($owner)->postJson('/api/playlists/' . $playlist->id . '/cues', [
            'media_item_id' => $cue->id,
            'at_sec' => 3000,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('inserted.media_item_id', $cue->id);
        $response->assertJsonPath('split_from.media_item_id', $movie->id);
        $response->assertJsonPath('split_from.cue_in_sec', 3000);

        $rows = $playlist->items()->orderBy('position')->get();
        $this->assertSame([$movie->id, $cue->id, $movie->id], $rows->pluck('media_item_id')->all());
    }

    public function test_cue_from_another_channel_is_rejected(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel($user, 'ch-own');
        $other = $this->makeChannel($user, 'ch-other');
        $movie = $this->makeMovie($channel);
        $foreignCue = $this->makeCue($other);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1]);

        $this->expectException(\RuntimeException::class);

        app(PlaylistCueInserter::class)->insertAt($playlist, 100, $foreignCue);
    }

    public function test_timeline_builder_reports_overflow_past_midnight(): void
    {
        $channel = $this->makeChannel($this->makeUser(), 'ch-overflow');
        $movie = $this->makeMovie($channel, 90_000);
        $cue = $this->makeCue($channel);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1]);

        $template = ScheduleTemplate::create([
            'channel_id' => $channel->id,
            'name' => 'T',
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'active',
        ]);
        ScheduleBlock::create([
            'template_id' => $template->id,
            'day_of_month' => now()->day,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'kind' => 'program',
            'playlist_id' => $playlist->id,
        ]);

        $result = app(\App\Services\TimelineBuilder::class)->buildForDay($template, now()->day);

        $this->assertSame(1, $result['items']->count());
        $this->assertGreaterThan(0, $result['overflow_sec']);
        $this->assertSame(0, $result['items']->first()->starts_at_sec);
    }

    public function test_timeline_insert_cue_rejects_past_second(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel($user, 'ch-past');
        $movie = $this->makeMovie($channel);
        $cue = $this->makeCue($channel);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1]);

        $template = ScheduleTemplate::create([
            'channel_id' => $channel->id,
            'name' => 'T',
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'active',
        ]);
        ScheduleBlock::create([
            'template_id' => $template->id,
            'day_of_month' => now()->day,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'kind' => 'program',
            'playlist_id' => $playlist->id,
        ]);

        $response = $this->actingAs($user)->postJson(
            '/api/schedule-templates/' . $template->id . '/days/' . now()->day . '/timeline/insert-cue',
            ['media_item_id' => $cue->id, 'at_sec' => 0]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Solo se puede insertar en el futuro', $response->json('message'));
    }

    public function test_client_without_channel_access_cannot_insert_cue(): void
    {
        $owner = $this->makeUser();
        $channel = $this->makeChannel($owner, 'ch-scope');
        $movie = $this->makeMovie($channel);
        $cue = $this->makeCue($channel);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1]);

        $client = User::create([
            'username' => 'cue-client',
            'email' => 'cue-client@example.com',
            'password' => 'password',
            'role' => 'client',
            'status' => 'active',
        ]);

        $response = $this->actingAs($client)->postJson('/api/playlists/' . $playlist->id . '/cues', [
            'media_item_id' => $cue->id,
            'at_sec' => 100,
        ]);

        $response->assertStatus(403);
    }

    public function test_timeline_insert_cue_records_audit_before_after(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel($user, 'ch-audit');
        $movie = $this->makeMovie($channel);
        $cue = $this->makeCue($channel);

        $playlist = Playlist::create(['channel_id' => $channel->id, 'name' => 'P']);
        $playlist->items()->create(['media_item_id' => $movie->id, 'position' => 1]);

        $template = ScheduleTemplate::create([
            'channel_id' => $channel->id,
            'name' => 'T',
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'active',
        ]);
        ScheduleBlock::create([
            'template_id' => $template->id,
            'day_of_month' => now()->day,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'kind' => 'program',
            'playlist_id' => $playlist->id,
        ]);

        $atSec = (int) now()->secondsSinceMidnight() + 60;

        $response = $this->actingAs($user)->postJson(
            '/api/schedule-templates/' . $template->id . '/days/' . now()->day . '/timeline/insert-cue',
            ['media_item_id' => $cue->id, 'at_sec' => $atSec]
        );

        $response->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'timeline.insert_cue',
            'entity_type' => \App\Models\ScheduleTemplate::class,
            'entity_id' => $template->id,
        ]);

        $log = \App\Models\AuditLog::where('action', 'timeline.insert_cue')->first();
        $this->assertNotNull($log);
        $this->assertSame($cue->id, $log->after['cue_media_item_id']);
        $this->assertSame($atSec, $log->after['at_sec']);
        $this->assertIsArray($log->before);
        $this->assertIsArray($log->after['after'] ?? $log->after);
    }
}
