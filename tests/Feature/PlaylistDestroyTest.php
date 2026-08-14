<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\EmissionState;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_playlist_with_live_emission_cannot_be_deleted_and_message_is_surfaced(): void
    {
        $owner = User::factory()->create();
        $channel = Channel::create([
            'slug' => 'test-ch',
            'display_name' => 'Test',
            'owner_id' => $owner->id,
            'root_path' => '/tmp/test-root',
        ]);
        $playlist = Playlist::create([
            'channel_id' => $channel->id,
            'name' => 'Default PL',
            'is_default' => true,
        ]);
        EmissionState::create([
            'channel_id' => $channel->id,
            'status' => 'live',
        ]);

        $response = $this
            ->actingAs($owner)
            ->deleteJson('/api/playlists/' . $playlist->id);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'No se puede eliminar la playlist default mientras hay emisión activa.',
        ]);

        $this->assertDatabaseHas('playlists', ['id' => $playlist->id]);
    }

    public function test_non_default_playlist_can_be_deleted(): void
    {
        $owner = User::factory()->create();
        $channel = Channel::create([
            'slug' => 'test-ch-2',
            'display_name' => 'Test 2',
            'owner_id' => $owner->id,
            'root_path' => '/tmp/test-root-2',
        ]);
        $playlist = Playlist::create([
            'channel_id' => $channel->id,
            'name' => 'Disposable PL',
            'is_default' => false,
        ]);

        $response = $this
            ->actingAs($owner)
            ->deleteJson('/api/playlists/' . $playlist->id);

        $response->assertOk();
        $response->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('playlists', ['id' => $playlist->id]);
    }
}
