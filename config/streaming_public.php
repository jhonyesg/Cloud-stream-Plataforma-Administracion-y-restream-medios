<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración pública de los formatos de emisión y entrega
    |--------------------------------------------------------------------------
    |
    | Esta es la ÚNICA fuente de verdad de las URLs que la web pública
    | publica (home, landings verticales, FAQ). No contiene secretos.
    |
    | `display_host` es un host DE EXHIBICIÓN: no tiene registro DNS y no
    | resuelve a ningún servidor. Las URLs que se publican son PLANTILLAS
    | del formato que recibe el cliente, nunca links de acceso reales.
    |
    | Prohibido usar aquí (ni en cualquier vista pública) el host real de
    | streaming (`canal.mediaserver.com.co`), la IP del servidor
    | (`15.204.8.139`), el panel interno o `config('mediaserver.public_host')`.
    | Esos valores son internos: viven en el MediaServer Platform y en las
    | variables de entorno, nunca en el HTML público.
    |
    | Ver openspec/changes/landing-delivery-urls-section/ (sección pública
    | de formatos de entrega).
    |
    */

    'display_host' => 'streaming.mediaserver.com.co',

    // Nombre de canal de ejemplo que aparece en las URLs publicadas.
    // Nunca es el nombre real de un canal de un cliente.
    'example_slug' => 'tu-canal',

    'ports' => [
        // Ingest RTMP (OBS, FMLE, vMix, Wirecast).
        'rtmp' => 1935,
        // Ingest SRT (emisor con/libsrt, apps móviles).
        'srt_publish' => 10080,
        // Playback SRT (lectura directa, no pasa por el proxy inverso).
        'srt_play' => 7386,
    ],

];
