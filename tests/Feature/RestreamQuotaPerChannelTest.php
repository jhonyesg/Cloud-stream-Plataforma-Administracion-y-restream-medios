<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\RestreamQuota;
use App\Models\RestreamTarget;
use App\Models\User;
use App\Services\Restream\RestreamQuotaException;
use App\Services\Restream\RestreamQuotaGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
            'name' => 'ch1-full', 'destination_url' => 'rtmps://x/c1', 'stream_key' => 'sk-test-key',
            'enabled' => true, 'status' => 'idle', 'created_by' => $user->id,
        ]);

        $guard = app(RestreamQuotaGuard::class);
        $guard->assertCanEnable($user, $ch2);

        $this->assertTrue(true);
    }

    /**
     * Helper: POST /client/restream/channels/{c}/restream-targets and return the response.
     * Mirrors the payload the modal submit() handler sends (post-fix).
     */
    protected function postTarget(User $user, Channel $channel, array $payload): TestResponse
    {
        return $this->actingAs($user)->postJson(
            "/client/restream/channels/{$channel->id}/restream-targets",
            $payload
        );
    }

    /**
     * Regression for the bug where the modal sent `enabled=0` regardless of the
     * checkbox state (the JS compared against 'on' while the input declared
     * value="1"). The fix is in the modal's submit handler — this test pins the
     * backend contract that the modal must satisfy.
     */
    public function test_post_with_enabled_true_consumes_a_slot(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $channel = Channel::create([
            'slug' => 'enabled-true', 'display_name' => 'EnabledTrue', 'owner_id' => $user->id, 'root_path' => '/tmp/enabled-true',
        ]);
        RestreamQuota::create(['user_id' => $user->id, 'channel_id' => $channel->id, 'enabled' => true, 'max_outputs' => 2]);

        $this->assertSame(0, $user->restreamUsedOutputsFor($channel->id));

        $r = $this->postTarget($user, $channel, [
            'platform' => 'youtube',
            'name' => 'YT principal',
            'destination_url' => 'rtmps://a.youtube.com/live2',
            'stream_key' => 'sk-abc',
            'enabled' => 1,
        ]);

        $r->assertCreated();
        $row = RestreamTarget::query()->where('user_id', $user->id)->where('channel_id', $channel->id)->firstOrFail();
        $this->assertTrue((bool) $row->enabled, 'Row must be persisted with enabled=true when payload says enabled=1.');
        $this->assertSame(1, $user->fresh()->restreamUsedOutputsFor($channel->id), 'used_outputs must increment after creating an enabled target.');
    }

    /**
     * Sibling of the above: sending enabled=0 must NOT consume a slot, and the
     * cap must not be invoked (so users can create unlimited disabled drafts
     * within their free-tier exploration, but the cap is the ONLY limit on
     * active restreams).
     */
    public function test_post_with_enabled_false_does_not_consume_a_slot(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $channel = Channel::create([
            'slug' => 'enabled-false', 'display_name' => 'EnabledFalse', 'owner_id' => $user->id, 'root_path' => '/tmp/enabled-false',
        ]);
        RestreamQuota::create(['user_id' => $user->id, 'channel_id' => $channel->id, 'enabled' => true, 'max_outputs' => 1]);

        $r = $this->postTarget($user, $channel, [
            'platform' => 'tiktok',
            'name' => 'TT draft',
            'destination_url' => 'rtmps://pull-flv.tiktok.com/live',
            'stream_key' => 'sk-xyz',
            'enabled' => 0,
        ]);

        $r->assertCreated();
        $row = RestreamTarget::query()->where('user_id', $user->id)->where('channel_id', $channel->id)->firstOrFail();
        $this->assertFalse((bool) $row->enabled);
        $this->assertSame(0, $user->fresh()->restreamUsedOutputsFor($channel->id));
    }

    /**
     * Smoke test for the cap path: after the fix, the modal actually invokes
     * assertCanEnable() when the checkbox is marked, so a third enabled
     * target against a max_outputs=2 cap must be rejected with 422.
     */
    public function test_cap_blocks_third_enabled_target_with_422(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $channel = Channel::create([
            'slug' => 'cap-blocks', 'display_name' => 'CapBlocks', 'owner_id' => $user->id, 'root_path' => '/tmp/cap-blocks',
        ]);
        RestreamQuota::create(['user_id' => $user->id, 'channel_id' => $channel->id, 'enabled' => true, 'max_outputs' => 2]);

        $this->postTarget($user, $channel, [
            'platform' => 'youtube', 'name' => 'Y1', 'destination_url' => 'rtmps://a/live', 'stream_key' => 'sk-test-key', 'enabled' => 1,
        ])->assertCreated();
        $this->postTarget($user, $channel, [
            'platform' => 'tiktok', 'name' => 'T1', 'destination_url' => 'rtmps://b/live', 'stream_key' => 'sk-test-key', 'enabled' => 1,
        ])->assertCreated();

        $r = $this->postTarget($user, $channel, [
            'platform' => 'facebook', 'name' => 'F1', 'destination_url' => 'rtmps://c/live', 'stream_key' => 'sk-test-key', 'enabled' => 1,
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['restream']);
        $this->assertSame(2, $user->fresh()->restreamUsedOutputsFor($channel->id), 'Cap must reject without incrementing the count.');
    }
}
