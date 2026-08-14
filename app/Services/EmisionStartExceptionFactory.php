<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Playlist;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use RuntimeException;

class EmisionStartExceptionFactory
{
    public function noActiveTemplate(Channel $channel, int $year, int $month): RuntimeException
    {
        return new RuntimeException(sprintf(
            'No hay programación activa para %s en %d-%02d. Activala desde el módulo Programación.',
            $channel->display_name,
            $year,
            $month,
        ));
    }

    public function noBlockForDay(Channel $channel, ScheduleTemplate $template, int $day): RuntimeException
    {
        return new RuntimeException(sprintf(
            'El día %d no tiene playlist asignada en la programación de %s. Asigná una antes de iniciar la emisión.',
            $day,
            $channel->display_name,
        ));
    }

    public function noPlaylistOnBlock(Channel $channel, ScheduleBlock $block): RuntimeException
    {
        return new RuntimeException(sprintf(
            'El bloque del día %d no tiene playlist válida.',
            $block->day_of_month,
        ));
    }

    public function noReadyItems(Channel $channel, Playlist $playlist): RuntimeException
    {
        return new RuntimeException(sprintf(
            'La playlist "%s" del día no tiene items válidos (todos están fallidos o sin media asociado).',
            $playlist->name,
        ));
    }
}
