<?php

namespace Tests\Feature\Commands;

use App\Models\Channel;
use App\Models\Playlist;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealDraftTemplatesCommandTest extends TestCase
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

    private function makeDraftTemplate(Channel $channel, int $blockCount, int $year, int $month): ScheduleTemplate
    {
        $template = ScheduleTemplate::create([
            'channel_id' => $channel->id,
            'name' => 'Programación',
            'year' => $year,
            'month' => $month,
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

    public function test_dry_run_lists_candidates_without_modifying(): void
    {
        $channel = $this->makeChannel();
        $template = $this->makeDraftTemplate($channel, 4, 2026, 9);

        $this->artisan('schedule-templates:heal-drafts')
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $this->assertSame('draft', ScheduleTemplate::find($template->id)->status);
    }

    public function test_apply_performs_heal(): void
    {
        $channel = $this->makeChannel();
        $template = $this->makeDraftTemplate($channel, 4, 2026, 9);

        $this->artisan('schedule-templates:heal-drafts', ['--apply' => true])
            ->expectsOutputToContain('Healed: 1')
            ->assertExitCode(0);

        $this->assertSame('active', ScheduleTemplate::find($template->id)->status);
    }

    public function test_apply_is_idempotent_on_second_run(): void
    {
        $channel = $this->makeChannel();
        $template = $this->makeDraftTemplate($channel, 4, 2026, 9);

        $this->artisan('schedule-templates:heal-drafts', ['--apply' => true])
            ->assertExitCode(0);

        $this->artisan('schedule-templates:heal-drafts')
            ->expectsOutputToContain('No healable draft templates found.')
            ->assertExitCode(0);

        $this->assertSame('active', ScheduleTemplate::find($template->id)->status);
    }

    public function test_json_dry_run_emits_valid_json(): void
    {
        $channel = $this->makeChannel();
        $this->makeDraftTemplate($channel, 4, 2026, 9);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('schedule-templates:heal-drafts', ['--json' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame(0, $exitCode);

        $payload = json_decode($output, true);
        $this->assertIsArray($payload);
        $this->assertSame('dry-run', $payload['mode']);
        $this->assertCount(1, $payload['skipped']);
        $this->assertSame('draft', $payload['skipped'][0]['status']);
    }

    public function test_channel_filter_limits_scope(): void
    {
        $channelA = $this->makeChannel();
        $channelB = $this->makeChannel();
        $templateA = $this->makeDraftTemplate($channelA, 4, 2026, 9);
        $templateB = $this->makeDraftTemplate($channelB, 4, 2026, 9);

        $this->artisan('schedule-templates:heal-drafts', [
            '--apply' => true,
            '--channel' => [$channelA->id],
        ])->assertExitCode(0);

        $this->assertSame('active', ScheduleTemplate::find($templateA->id)->status);
        $this->assertSame('draft', ScheduleTemplate::find($templateB->id)->status);
    }

    public function test_suspended_channel_excluded(): void
    {
        $channel = $this->makeChannel('suspended');
        $template = $this->makeDraftTemplate($channel, 4, 2026, 9);

        $this->artisan('schedule-templates:heal-drafts', ['--apply' => true])
            ->expectsOutputToContain('No healable draft templates found.')
            ->assertExitCode(0);

        $this->assertSame('draft', ScheduleTemplate::find($template->id)->status);
    }
}
