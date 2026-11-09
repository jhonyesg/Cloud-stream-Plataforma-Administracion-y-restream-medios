<?php

namespace Tests\Feature\Controllers;

use App\Models\Channel;
use App\Models\Playlist;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleControllerHealTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function clientUser(): User
    {
        $owner = User::factory()->create(['role' => 'client']);
        User::factory()->create(['role' => 'client']);
        return $owner;
    }

    private function makeChannel(User $owner, string $status = 'active'): Channel
    {
        return Channel::create([
            'owner_id' => $owner->id,
            'slug' => 'test-ch-'.uniqid(),
            'display_name' => 'Test Channel',
            'root_path' => '/tmp/test-root-'.uniqid(),
            'status' => $status,
        ]);
    }

    private function makeDraftTemplate(Channel $channel, int $blockCount): ScheduleTemplate
    {
        $template = ScheduleTemplate::create([
            'channel_id' => $channel->id,
            'name' => 'Programación',
            'year' => 2026,
            'month' => 9,
            'status' => 'draft',
        ]);
        if ($blockCount > 0) {
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
        }
        return $template;
    }

    public function test_admin_scheduler_load_promotes_draft_template_with_blocks(): void
    {
        $owner = $this->adminUser();
        $channel = $this->makeChannel($owner);
        $template = $this->makeDraftTemplate($channel, 4);

        $this->actingAs($owner)
            ->get('/admin/scheduler?channel_id='.$channel->id.'&year=2026&month=9')
            ->assertOk();

        $this->assertSame('active', ScheduleTemplate::find($template->id)->status);
    }

    public function test_admin_scheduler_load_does_not_promote_empty_draft(): void
    {
        $owner = $this->adminUser();
        $channel = $this->makeChannel($owner);
        $template = $this->makeDraftTemplate($channel, 0);

        $this->actingAs($owner)
            ->get('/admin/scheduler?channel_id='.$channel->id.'&year=2026&month=9')
            ->assertOk();

        $this->assertSame('draft', ScheduleTemplate::find($template->id)->status);
    }

    public function test_admin_scheduler_load_does_not_promote_on_suspended_channel(): void
    {
        $owner = $this->adminUser();
        $channel = $this->makeChannel($owner, 'suspended');
        $template = $this->makeDraftTemplate($channel, 4);

        $this->actingAs($owner)
            ->get('/admin/scheduler?channel_id='.$channel->id.'&year=2026&month=9')
            ->assertOk();

        $this->assertSame('draft', ScheduleTemplate::find($template->id)->status);
    }

    public function test_client_scheduler_load_promotes_draft_template_with_blocks(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $channel = $this->makeChannel($owner);
        $template = $this->makeDraftTemplate($channel, 4);

        $this->actingAs($owner)
            ->get('/client/scheduler?channel_id='.$channel->id.'&year=2026&month=9')
            ->assertOk();

        $this->assertSame('active', ScheduleTemplate::find($template->id)->status);
    }

    public function test_client_scheduler_load_does_not_promote_empty_draft(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $channel = $this->makeChannel($owner);
        $template = $this->makeDraftTemplate($channel, 0);

        $this->actingAs($owner)
            ->get('/client/scheduler?channel_id='.$channel->id.'&year=2026&month=9')
            ->assertOk();

        $this->assertSame('draft', ScheduleTemplate::find($template->id)->status);
    }

    public function test_client_scheduler_load_does_not_promote_on_suspended_channel(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $channel = $this->makeChannel($owner, 'suspended');
        $template = $this->makeDraftTemplate($channel, 4);

        $this->actingAs($owner)
            ->get('/client/scheduler?channel_id='.$channel->id.'&year=2026&month=9')
            ->assertOk();

        $this->assertSame('draft', ScheduleTemplate::find($template->id)->status);
    }
}
