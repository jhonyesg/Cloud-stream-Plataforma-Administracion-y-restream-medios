<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\RestreamQuota;
use App\Models\RestreamTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestreamDaemonRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_restream_index_renders_with_new_statuses(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $channel = Channel::create([
            'slug' => 'render-a', 'display_name' => 'Render A', 'owner_id' => $user->id, 'root_path' => '/tmp/a',
        ]);

        RestreamQuota::create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'enabled' => true,
            'max_outputs' => 4,
        ]);

        RestreamTarget::create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'platform' => 'facebook',
            'name' => 'FB Test',
            'destination_url' => 'rtmp://example.com/live/',
            'stream_key' => 'secret-key-1234',
            'enabled' => true,
            'status' => 'live',
            'pipeline_pid' => 4242,
            'last_heartbeat_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/client/restream?channel_id=' . $channel->id);
        $response->assertOk();
        $response->assertSee('FB Test');
        $response->assertSee('live');
    }

    public function test_client_restream_index_marks_stale_heartbeat_as_error(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $channel = Channel::create([
            'slug' => 'render-b', 'display_name' => 'Render B', 'owner_id' => $user->id, 'root_path' => '/tmp/b',
        ]);

        RestreamQuota::create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'enabled' => true,
            'max_outputs' => 4,
        ]);

        RestreamTarget::create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'platform' => 'youtube',
            'name' => 'YT Stale',
            'destination_url' => 'rtmp://example.com/live/',
            'stream_key' => 'secret-key-5678',
            'enabled' => true,
            'status' => 'live',
            'pipeline_pid' => 9999,
            'last_heartbeat_at' => now()->subMinutes(5),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/client/restream?channel_id=' . $channel->id);
        $response->assertOk();
        $response->assertSee('YT Stale');
        $response->assertSee('error');
    }
}
