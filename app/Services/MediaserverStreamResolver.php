<?php

namespace App\Services;

use App\Models\Channel;

/**
 * Resuelve el nombre de stream en MediaServer Platform para un canal de
 * Cloudstream. La regla es única para toda la aplicación: el dashboard de
 * métricas y el modal de URLs de Programación deben mapear el canal al
 * MISMO stream, o las métricas y las URLs hablarían de streams distintos.
 */
class MediaserverStreamResolver
{
    public function streamNameForChannel(Channel $channel): string
    {
        $hls = (string) ($channel->public_hls_url ?? '');
        if ($hls !== '') {
            $path = (string) parse_url($hls, PHP_URL_PATH);
            $name = basename($path, '.m3u8');
            if ($name !== '' && $name !== $path) {
                return $name;
            }
        }

        $output = (string) ($channel->virtualScreen?->output_url ?? '');
        if ($output !== '' && preg_match('~^.*/live/([^/?#]+)~', $output, $m)) {
            return $m[1];
        }

        return (string) $channel->slug;
    }
}
