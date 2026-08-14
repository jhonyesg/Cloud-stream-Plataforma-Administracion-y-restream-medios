<?php

namespace App\Console\Commands;

use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use App\Services\TimelineBuilder;
use Illuminate\Console\Command;

class TimelineBackfillCommand extends Command
{
    protected $signature = 'timeline:backfill
        {--template= : Backfill a single schedule template id (default: all active templates)}
        {--day= : Comma-separated days to backfill (default: all days present in the template)}
        {--force : Rebuild even if timeline_items already exist for a day}';

    protected $description = 'Backfill program_timeline_items from existing schedule_blocks for active templates.';

    public function handle(TimelineBuilder $builder): int
    {
        $templateId = $this->option('template');
        $dayOpt = $this->option('day');
        $force = (bool) $this->option('force');

        $templates = ScheduleTemplate::query()
            ->when($templateId, fn ($q) => $q->where('id', $templateId))
            ->when(! $templateId, fn ($q) => $q->where('status', 'active'))
            ->orderBy('channel_id')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        if ($templates->isEmpty()) {
            $this->warn('No templates matched.');
            return self::SUCCESS;
        }

        $totalItems = 0;
        $totalOverflow = 0;
        $totalDays = 0;

        foreach ($templates as $template) {
            $blockQuery = ScheduleBlock::query()->where('template_id', $template->id);
            if ($dayOpt) {
                $days = collect(explode(',', $dayOpt))->map(fn ($d) => (int) trim($d))->filter()->all();
                $blockQuery->whereIn('day_of_month', $days);
            }
            $blocks = $blockQuery->orderBy('day_of_month')->get();
            if ($blocks->isEmpty()) {
                $this->line("template={$template->id} (channel {$template->channel_id}): no blocks to backfill");
                continue;
            }

            foreach ($blocks as $block) {
                if (! $force) {
                    $existing = \App\Models\ProgramTimelineItem::query()
                        ->where('template_id', $template->id)
                        ->where('day_of_month', $block->day_of_month)
                        ->count();
                    if ($existing > 0) {
                        $this->line("template={$template->id} day={$block->day_of_month}: skipped (already {$existing} timeline rows; use --force to rebuild)");
                        continue;
                    }
                }
                $result = $builder->buildForDay($template, (int) $block->day_of_month);
                $totalItems += $result['items']->count();
                $totalOverflow += $result['overflow_sec'];
                $totalDays++;
                $this->info(sprintf(
                    'template=%s day=%d -> %d items, overflow=%ds, version=%d',
                    $template->id,
                    $block->day_of_month,
                    $result['items']->count(),
                    $result['overflow_sec'],
                    $result['version'],
                ));
            }
        }

        $this->info("Backfill complete: {$totalDays} days, {$totalItems} items generated, {$totalOverflow}s overflow.");

        return self::SUCCESS;
    }
}
