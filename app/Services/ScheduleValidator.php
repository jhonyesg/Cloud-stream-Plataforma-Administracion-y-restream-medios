<?php

namespace App\Services;

use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;

class ScheduleValidator
{
    public function validate(ScheduleTemplate $template): array
    {
        $errors = [];
        $warnings = [];

        foreach ($template->blocks()->get() as $b) {
            if ($b->end_time <= $b->start_time) {
                $errors[] = "Bloque {$b->id}: rango inválido ({$b->start_time} - {$b->end_time}).";
            }
            if ($b->kind === 'ad_break' && $b->items()->count() === 0) {
                $errors[] = "Bloque ad_break {$b->id} en {$b->start_time} sin items.";
            }
        }

        $hasFullDay = $template->blocks()->whereNull('day_of_month')->whereNull('weekday_mask')->exists();
        if (! $hasFullDay) {
            $warnings[] = 'No hay cobertura 24/7 (falta un bloque sin day_of_month ni weekday_mask).';
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'ok' => empty($errors)];
    }
}
