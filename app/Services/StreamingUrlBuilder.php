<?php

namespace App\Services;

/**
 * Construye la matriz de URLs de emisión y entrega que la web pública
 * publica como plantilla (home, landings verticales, FAQ).
 *
 * Todos los valores salen de `config/streaming_public.php`:
 *  - `display_host` es un host DE EXHIBICIÓN: no tiene DNS y no resuelve a
 *    ningún servidor. Las URLs publicadas son plantillas del formato que
 *    recibe el cliente, nunca accesos reales.
 *  - `example_slug` es un nombre de canal de ejemplo, nunca el de un
 *    cliente real.
 *
 * El host real de streaming y la IP del servidor viven en el MediaServer
 * Platform y en las variables de entorno; no se referencian aquí.
 */
class StreamingUrlBuilder
{
    /**
     * @return array{publish: array<int, array{protocol: string, url: string, hint: string}>, consume: array<string, array<int, array{protocol: string, url: string}>>}
     */
    public function showcase(): array
    {
        $host = trim((string) config('streaming_public.display_host'));
        $slug = trim((string) config('streaming_public.example_slug'));
        $ports = (array) config('streaming_public.ports');

        return [
            // Emisión: el encoder del cliente publica hacia nosotros.
            'publish' => [
                [
                    'protocol' => 'RTMP',
                    'url' => sprintf('rtmp://%s:%d/live/%s', $host, (int) $ports['rtmp'], $slug),
                    'hint' => 'OBS, FMLE, vMix y Wirecast',
                ],
                [
                    'protocol' => 'SRT',
                    'url' => sprintf('srt://%s:%d?streamid=%s', $host, (int) $ports['srt_publish'], $slug),
                    'hint' => 'Para redes inestables o emisión desde celular',
                ],
            ],

            // Entrega: la audiencia recibe la señal ya convertida.
            'consume' => [
                'https' => [
                    ['protocol' => 'HLS', 'url' => sprintf('https://%s/live/%s.m3u8', $host, $slug)],
                    ['protocol' => 'FLV', 'url' => sprintf('https://%s/live/%s.flv', $host, $slug)],
                    ['protocol' => 'WebRTC', 'url' => sprintf('https://%s/rtc/v1/play/?app=live&stream=%s', $host, $slug)],
                    ['protocol' => 'MPEG-TS', 'url' => sprintf('https://%s/mpegts/%s', $host, $slug)],
                ],
                'http' => [
                    ['protocol' => 'HLS', 'url' => sprintf('http://%s/live/%s.m3u8', $host, $slug)],
                    ['protocol' => 'FLV', 'url' => sprintf('http://%s/live/%s.flv', $host, $slug)],
                    ['protocol' => 'WebRTC', 'url' => sprintf('http://%s/rtc/v1/play/?app=live&stream=%s', $host, $slug)],
                    ['protocol' => 'MPEG-TS', 'url' => sprintf('http://%s/mpegts/%s', $host, $slug)],
                ],
                'srt' => [
                    ['protocol' => 'SRT', 'url' => sprintf('srt://%s:%d?streamid=read:%s', $host, (int) $ports['srt_play'], $slug)],
                ],
            ],
        ];
    }
}
