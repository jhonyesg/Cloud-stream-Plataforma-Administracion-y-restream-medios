<?php

namespace App\Services\Scheduler;

use App\Models\AuditLog;
use App\Models\ScheduleTemplate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class TemplateActivator
{
    public const AUDIT_HEAL = 'update.schedule_template.heal';
    public const AUDIT_AUTO_ACTIVATE = 'update.schedule_template.auto_activate';

    public function activateIfHealable(
        ScheduleTemplate $template,
        string $auditAction = self::AUDIT_HEAL
    ): bool {
        if ($template->status !== 'draft') {
            return false;
        }

        if ($template->blocks()->count() === 0) {
            return false;
        }

        $channel = $template->channel;
        if ($channel === null) {
            return false;
        }

        if (in_array($channel->status, ['suspended', 'archived'], true)) {
            return false;
        }

        $before = $template->only(['status']);

        try {
            DB::transaction(function () use ($template) {
                $archivedIds = ScheduleTemplate::where('channel_id', $template->channel_id)
                    ->where('year', $template->year)
                    ->where('month', $template->month)
                    ->where('status', 'active')
                    ->where('id', '!=', $template->id)
                    ->pluck('id');

                if ($archivedIds->isNotEmpty()) {
                    ScheduleTemplate::whereIn('id', $archivedIds)->update(['status' => 'archived']);
                    foreach ($archivedIds as $tplId) {
                        AuditLog::record(
                            'update.schedule_template.auto_archive_on_promote',
                            'schedule_template',
                            $tplId,
                            ['status' => 'active'],
                            ['status' => 'archived'],
                            $template->channel_id
                        );
                    }
                }

                $template->update(['status' => 'active']);
            });
        } catch (UniqueConstraintViolationException $e) {
            return false;
        }

        AuditLog::record(
            $auditAction,
            'schedule_template',
            $template->id,
            $before,
            ['status' => 'active'],
            $template->channel_id
        );

        return true;
    }
}
