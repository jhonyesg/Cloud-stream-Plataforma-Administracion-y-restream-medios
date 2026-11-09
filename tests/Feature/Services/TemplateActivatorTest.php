<?php

namespace Tests\Feature\Services;

use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\Playlist;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use App\Models\User;
use App\Services\Scheduler\TemplateActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateActivatorTest extends TestCase
{
    use RefreshDatabase;

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

    private function makePlaylistFor(Channel $channel): Playlist
    {
        return Playlist::create([
            'channel_id' => $channel->id,
            'name' => 'Test Playlist '.uniqid(),
        ]);
    }

    private function makeTemplate(Channel $channel, string $status, int $year, int $month, int $blockCount = 0): ScheduleTemplate
    {
        $template = ScheduleTemplate::create([
            'channel_id' => $channel->id,
            'name' => "Programación $year-$month",
            'year' => $year,
            'month' => $month,
            'status' => $status,
        ]);
        $playlist = $this->makePlaylistFor($channel);
        for ($d = 1; $d <= $blockCount; $d++) {
            ScheduleBlock::create([
                'template_id' => $template->id,
                'day_of_month' => $d,
                'playlist_id' => $playlist->id,
            ]);
        }
        return $template;
    }

    public function test_draft_template_with_blocks_and_active_channel_gets_promoted(): void
    {
        $channel = $this->makeChannel('active');
        $template = $this->makeTemplate($channel, 'draft', 2026, 9, 5);
        $beforeId = $template->id;

        $result = (new TemplateActivator)->activateIfHealable($template->fresh());

        $this->assertTrue($result);
        $this->assertSame('active', ScheduleTemplate::find($beforeId)->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update.schedule_template.heal',
            'entity_type' => 'schedule_template',
            'entity_id' => $beforeId,
        ]);
    }

    public function test_empty_draft_template_is_not_promoted(): void
    {
        $channel = $this->makeChannel('active');
        $template = $this->makeTemplate($channel, 'draft', 2026, 9, 0);
        $beforeId = $template->id;

        $result = (new TemplateActivator)->activateIfHealable($template->fresh());

        $this->assertFalse($result);
        $this->assertSame('draft', ScheduleTemplate::find($beforeId)->status);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'update.schedule_template.heal',
            'entity_id' => $beforeId,
        ]);
    }

    public function test_draft_template_on_suspended_channel_is_not_promoted(): void
    {
        $channel = $this->makeChannel('suspended');
        $template = $this->makeTemplate($channel, 'draft', 2026, 9, 4);
        $beforeId = $template->id;

        $result = (new TemplateActivator)->activateIfHealable($template->fresh());

        $this->assertFalse($result);
        $this->assertSame('draft', ScheduleTemplate::find($beforeId)->status);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'update.schedule_template.heal',
            'entity_id' => $beforeId,
        ]);
    }

    public function test_already_active_template_is_noop(): void
    {
        $channel = $this->makeChannel('active');
        $template = $this->makeTemplate($channel, 'active', 2026, 9, 3);
        $beforeId = $template->id;

        $result = (new TemplateActivator)->activateIfHealable($template->fresh());

        $this->assertFalse($result);
        $this->assertSame('active', ScheduleTemplate::find($beforeId)->status);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'update.schedule_template.heal',
            'entity_id' => $beforeId,
        ]);
    }

    public function test_archived_template_is_noop(): void
    {
        $channel = $this->makeChannel('active');
        $template = $this->makeTemplate($channel, 'archived', 2026, 9, 2);
        $beforeId = $template->id;

        $result = (new TemplateActivator)->activateIfHealable($template->fresh());

        $this->assertFalse($result);
        $this->assertSame('archived', ScheduleTemplate::find($beforeId)->status);
    }

    public function test_heal_archives_competing_active_template_in_same_month(): void
    {
        $channel = $this->makeChannel('active');
        $t1 = $this->makeTemplate($channel, 'active', 2026, 9, 3);
        $t2 = $this->makeTemplate($channel, 'draft', 2026, 9, 4);

        $result = (new TemplateActivator)->activateIfHealable(ScheduleTemplate::find($t2->id));

        $this->assertTrue($result);
        $this->assertSame('archived', ScheduleTemplate::find($t1->id)->status);
        $this->assertSame('active', ScheduleTemplate::find($t2->id)->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update.schedule_template.heal',
            'entity_id' => $t2->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update.schedule_template.auto_archive_on_promote',
            'entity_id' => $t1->id,
        ]);
    }
}
