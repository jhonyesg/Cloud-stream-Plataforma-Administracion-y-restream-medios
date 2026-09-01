<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\RestreamQuota;
use App\Models\RestreamTarget;
use App\Models\User;
use App\Services\Restream\RestreamQuotaException;
use App\Services\Restream\RestreamQuotaGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestreamQuotaPerChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_assert_can_enable_throws_when_no_quota_for_channel(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $owner = User::factory()->create(['role' => 'client']);
        $channel = Channel::create([
            'slug' => 'a', 'display_name' => 'A', 'owner_id' => $owner->id, 'root_path' => '/tmp/a',
        ]);

        $this->expectException(RestreamQuotaException::class);

        app(RestreamQuotaGuard::class)->assertCanEnable($user, $channel);
    }

    public function test_assert_can_enable_throws_when_channel_cap_reached(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $channel = Channel::create([
            'slug' => 'b', 'display_name' => 'B', 'owner_id' => $user->id, 'root_path' => '/tmp/b',
        ]);

        RestreamQuota::create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'enabled' => true,
            'max_outputs' => 2,
        ]);

        RestreamTarget::create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'platform' => 'facebook',
            'name' => 'T1',
            'destination_url' => 'rtmps://x/a',
            'stream_key' => 'k1',
            'enabled' => true,
            'status' => 'idle',
            'created_by' => $user->id,
        ]);
        RestreamTarget::create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'platform' => 'tiktok',
            'name' => 'T2',
            'destination_url' => 'rtmps://x/b',
            'stream_key' => 'k2',
            'enabled' => true,
            'status' => 'idle',
            'created_by' => $user->id,
        ]);

        $this->expectException(RestreamQuotaException::class);
        app(RestreamQuotaGuard::class)->assertCanEnable($user, $channel);
    }

    public function test_cap_on_one_channel_does_not_block_another(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $ch1 = Channel::create(['slug' => 'c1', 'display_name' => 'C1', 'owner_id' => $user->id, 'root_path' => '/tmp/c1']);
        $ch2 = Channel::create(['slug' => 'c2', 'display_name' => 'C2', 'owner_id' => $user->id, 'root_path' => '/tmp/c2']);

        RestreamQuota::create(['user_id' => $user->id, 'channel_id' => $ch1->id, 'enabled' => true, 'max_outputs' => 1]);
        RestreamQuota::create(['user_id' => $user->id, 'channel_id' => $ch2->id, 'enabled' => true, 'max_outputs' => 1]);

        RestreamTarget::create([
            'user_id' => $user->id, 'channel_id' => $ch1->id, 'platform' => 'youtube',
            'name' => 'ch1-full', 'destination_url' => 'rtmps://x/c1', 'stream_key' => 'k',
            'enabled' => true, 'status' => 'idle', 'created_by' => $user->id,
        ]);

        $guard = app(RestreamQuotaGuard::class);
        $guard->assertCanEnable($user, $ch2);

        $this->assertTrue(true);
    }
}
