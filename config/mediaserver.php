<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MediaServer Platform (SRS) integration
    |--------------------------------------------------------------------------
    |
    | Configuración del panel MediaServer Platform que Cloudstream consume
    | para mostrar métricas en vivo y permitir matar clientes conectados.
    |
    | Si `api_url` está vacío, MediaserverApiService::isReachable() devuelve
    | false y la UI muestra el banner "MediaServer Platform no responde".
    |
    */

    'api_url'       => env('MEDIASERVER_API_URL', ''),
    'api_user'      => env('MEDIASERVER_API_USER', ''),
    'api_password'  => env('MEDIASERVER_API_PASSWORD', ''),

    // Host público (sin esquema) usado para construir las URLs de publish/play
    // que se muestran al operador y el iframe del player.
    'public_host'   => env('MEDIASERVER_PUBLIC_HOST', ''),

    // Timeout HTTP en segundos para llamadas al panel.
    'http_timeout'  => (int) env('MEDIASERVER_HTTP_TIMEOUT', 5),
];
