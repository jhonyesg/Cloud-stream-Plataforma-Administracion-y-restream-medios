<?php

namespace Tests\Feature\Admin;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelStorageQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function channel(array $overrides = []): Channel
    {
        $owner = User::factory()->create(['role' => 'client', 'status' => 'active']);
        return Channel::create(array_merge([
            'owner_id' => $owner->id,
            'display_name' => 'Test Channel',
            'slug' => 'test-channel-' . uniqid(),
            'status' => 'active',
            'storage_limit_bytes' => 107374182400,
            'used_bytes' => 5368709120,
        ], $overrides));
    }

    public function test_admin_can_set_storage_limit_on_existing_channel(): void
    {
        $admin = $this->admin();
        $channel = $this->channel();

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/channels/{$channel->id}", [
                'display_name' => $channel->display_name,
                'slug' => $channel->slug,
                'owner_id' => $channel->owner_id,
                'status' => 'active',
                'description' => null,
                'root_path' => '/test',
                'storage_limit_gb' => 50,
            ]);

        $response->assertOk();

        $channel->refresh();
        $this->assertSame(50 * 1024 * 1024 * 1024, (int) $channel->storage_limit_bytes);
    }

    public function test_admin_can_remove_storage_limit_by_sending_empty(): void
    {
        $admin = $this->admin();
        $channel = $this->channel();

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/channels/{$channel->id}", [
                'display_name' => $channel->display_name,
                'slug' => $channel->slug,
                'owner_id' => $channel->owner_id,
                'status' => 'active',
                'description' => null,
                'root_path' => '/test',
                'storage_limit_gb' => '',
            ]);

        $response->assertOk();

        $channel->refresh();
        $this->assertNull($channel->storage_limit_bytes);
    }

    public function test_admin_invalid_storage_limit_rejected(): void
    {
        $admin = $this->admin();
        $channel = $this->channel();

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/channels/{$channel->id}", [
                'display_name' => $channel->display_name,
                'slug' => $channel->slug,
                'owner_id' => $channel->owner_id,
                'status' => 'active',
                'description' => null,
                'root_path' => '/test',
                'storage_limit_gb' => -5,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('storage_limit_gb');

        $channel->refresh();
        $this->assertSame(107374182400, (int) $channel->storage_limit_bytes);
    }

    public function test_channel_show_returns_storage_fields(): void
    {
        $admin = $this->admin();
        $channel = $this->channel([
            'storage_limit_bytes' => 21474836480,
            'used_bytes' => 1073741824,
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson("/admin/channels/{$channel->id}");

        $response->assertOk();
        $response->assertJsonPath('channel.storage_limit_bytes', 21474836480);
        $response->assertJsonPath('channel.used_bytes', 1073741824);
    }
}
