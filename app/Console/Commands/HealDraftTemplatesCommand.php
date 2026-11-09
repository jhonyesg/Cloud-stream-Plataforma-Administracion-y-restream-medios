<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\ScheduleTemplate;
use App\Services\Scheduler\TemplateActivator;
use Illuminate\Console\Command;

class HealDraftTemplatesCommand extends Command
{
    protected $signature = 'schedule-templates:heal-drafts
        {--channel=* : Limit to one or more channels (id; repeatable)}
        {--apply : Perform the heal (default is dry-run)}
        {--dry-run : List healable templates without modifying anything (default when --apply is not passed)}
        {--json : Emit structured JSON for piping}';

    protected $description = 'Promote draft schedule templates with blocks to active. Idempotent.';

    public function handle(TemplateActivator $activator): int
    {
        $channels = $this->option('channel') ?: [];
        $apply = (bool) $this->option('apply');
        $json = (bool) $this->option('json');

        $candidates = $this->findCandidates($channels);

        if ($candidates->isEmpty()) {
            if ($json) {
                $this->line(json_encode([
                    'mode' => $apply ? 'apply' : 'dry-run',
                    'healed' => [],
                    'skipped' => [],
                    'summary' => ['healed' => 0, 'skipped' => 0, 'errors' => 0],
                ], JSON_UNESCAPED_UNICODE));
            } else {
                $this->info('No healable draft templates found.');
            }
            return self::SUCCESS;
        }

        $healed = [];
        $skipped = [];

        if (! $apply) {
            foreach ($candidates as $tpl) {
                $row = $this->describeCandidate($tpl);
                $skipped[] = $row;
            }
            if ($json) {
                $this->line(json_encode([
                    'mode' => 'dry-run',
                    'healed' => [],
                    'skipped' => $skipped,
                    'summary' => ['healed' => 0, 'skipped' => count($skipped), 'errors' => 0],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->table(
                    ['template_id', 'channel_id', 'year', 'month', 'blocks_count', 'status', 'channel_status'],
                    collect($skipped)->map(fn ($r) => [
                        $r['template_id'],
                        $r['channel_id'],
                        $r['year'],
                        $r['month'],
                        $r['blocks_count'],
                        $r['status'],
                        $r['channel_status'],
                    ])->all()
                );
                $this->info('Dry-run: no changes applied. Re-run with --apply to heal.');
            }
            return self::SUCCESS;
        }

        $errors = 0;
        foreach ($candidates as $tpl) {
            $fresh = ScheduleTemplate::find($tpl->id);
            if (! $fresh) {
                $errors++;
                continue;
            }
            try {
                if ($activator->activateIfHealable($fresh)) {
                    $healed[] = $this->describeCandidate($fresh, ['status' => 'active']);
                } else {
                    $skipped[] = $this->describeCandidate($fresh);
                }
            } catch (\Throwable $e) {
                $errors++;
                $skipped[] = $this->describeCandidate($fresh) + ['error' => $e->getMessage()];
            }
        }

        if ($json) {
            $this->line(json_encode([
                'mode' => 'apply',
                'healed' => $healed,
                'skipped' => $skipped,
                'summary' => [
                    'healed' => count($healed),
                    'skipped' => count($skipped),
                    'errors' => $errors,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            if (count($healed) > 0) {
                $this->info("Healed: ".count($healed));
            }
            if (count($skipped) > 0) {
                $this->warn("Skipped: ".count($skipped));
            }
            if ($errors > 0) {
                $this->error("Errors: {$errors}");
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function findCandidates(array $channelIds)
    {
        $query = ScheduleTemplate::query()
            ->with('channel:id,display_name,status')
            ->where('status', 'draft')
            ->whereHas('blocks');

        if (! empty($channelIds)) {
            $query->whereIn('channel_id', $channelIds);
        }

        $templates = $query->get();

        return $templates->filter(function (ScheduleTemplate $tpl) {
            $status = $tpl->channel?->status;
            return $status !== null && ! in_array($status, ['suspended', 'archived'], true);
        })->values();
    }

    private function describeCandidate(ScheduleTemplate $tpl, array $overrides = []): array
    {
        return [
            'template_id' => $tpl->id,
            'channel_id' => $tpl->channel_id,
            'year' => (int) $tpl->year,
            'month' => (int) $tpl->month,
            'blocks_count' => $tpl->blocks()->count(),
            'status' => $overrides['status'] ?? $tpl->status,
            'channel_status' => $tpl->channel?->status,
        ];
    }
}
