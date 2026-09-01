@php
    $heroH1 = 'Servidor RTMP en Colombia — Ingest para OBS, vMix y Wirecast';
    $heroBadge = 'Redundancia · Bitrate configurable · Baja latencia';
    $heroIntro = 'Conecta tu <strong>OBS Studio, vMix, Wirecast o XSplit</strong> a nuestro servidor RTMP y emite tu canal de TV en vivo con baja latencia y redundancia. Cloudstream te entrega una URL de ingest estable, clave de stream privada, y la posibilidad de configurar bitrate, resolución y codecs según tu necesidad. Ideal para canales de TV, productoras, iglesias y emisoras que ya tienen su equipo de emisión y solo necesitan un servidor profesional que no se caiga. Planes desde $75.000/mes.';
    $heroCtaText = 'Hola, quiero información sobre el servidor RTMP de Cloudstream';
    $features = [
        ['icon' => '📡', 'title' => 'Ingest RTMP estándar', 'text' => 'Compatible con OBS Studio, vMix, Wirecast, XSplit, Streamlabs OBS, ffmpeg y cualquier software que soporte protocolo RTMP.'],
        ['icon' => '🔒', 'title' => 'Clave de stream privada', 'text' => 'Cada canal tiene su propia clave de stream única. Nadie más puede usar tu servidor ni interferir con tu señal.'],
        ['icon' => '⚡', 'title' => 'Baja latencia (2-5 segundos)', 'text' => 'Servidor configurado para minimizar el delay entre tu emisión y la salida al aire. Ideal para eventos en vivo, subastas, sorteos y noticias.'],
        ['icon' => '🔁', 'title' => 'Redundancia automática', 'text' => 'Si nuestro nodo principal tiene una falla, el tráfico se redirige automáticamente a un nodo secundario. Tu audiencia no nota la diferencia.'],
        ['icon' => '🎚️', 'title' => 'Bitrate y resolución configurables', 'text' => 'Desde 320p a 400 KBPS hasta 720p a 2000 KBPS. Configuramos el códec (H264) y el bitrate según tu audiencia y tu conexión de subida.'],
        ['icon' => '🌐', 'title' => 'Datacenter americano', 'text' => 'Servidores en datacenter de Estados Unidos con uptime del 99% SLA. Latencia desde Colombia de 80-120 ms.'],
    ];
    $faqs = [
        ['q' => '¿Qué diferencia hay entre RTMP y HLS?', 'a' => 'RTMP (Real-Time Messaging Protocol) es el protocolo de ingreso: lo usa tu software (OBS, vMix) para enviar la señal al servidor. HLS (HTTP Live Streaming) es el protocolo de salida: lo usa el reproductor del televidente para ver la señal. RTMP es de baja latencia, HLS es más compatible. Nuestro servidor recibe RTMP y lo entrega como HLS a los televidentes.'],
        ['q' => '¿Cuánto ancho de banda de subida necesito en mi estudio?', 'a' => 'Para emitir a 720p y 1000 KBPS necesitas al menos 2 Mbps de subida estables (recomendado 5 Mbps). Para 480p basta con 1.5 Mbps de subida. Si emites desde una casa con fibra óptica típica en Colombia (10-50 Mbps de bajada), el cuello de botella es la subida — verifica con tu ISP.'],
        ['q' => '¿Puedo emitir desde un celular?', 'a' => 'Sí. Apps como Larix Broadcaster, Streamlabs OBS Mobile y la app de GoPro permiten emitir RTMP desde un celular. Solo necesitas configurar la URL del servidor y la clave de stream que te entregamos. Útil para eventos en movimiento, transmisiones exteriores y reportería en campo.'],
        ['q' => '¿El servidor soporta SRT (más estable que RTMP)?', 'a' => 'Sí. Bajo cotización podemos configurar ingest SRT (Secure Reliable Transport) además de RTMP. SRT es más resistente a fluctuaciones de red y se recomienda para transmisiones desde lugares con internet inestable.'],
    ];
    $ctaTitle = 'Servidor RTMP profesional para tu canal';
    $ctaSubtitle = 'Ingest estable, baja latencia, redundancia automática. Compatible con OBS, vMix y Wirecast.';
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'landing', 'data' => [
        'service_name' => 'Servidor RTMP en Colombia',
        'service_type' => 'Servidor de ingest RTMP para software de emisión',
        'service_description' => 'Servidor RTMP en datacenter americano para ingest desde OBS, vMix, Wirecast y XSplit. Baja latencia, redundancia automática, clave de stream privada. Planes desde $75.000/mes.',
        'faqs' => $faqs,
        'breadcrumbs' => [
            ['name' => 'Inicio', 'path' => '/'],
            ['name' => 'Servidor RTMP', 'path' => '/servidor-rtmp-colombia'],
        ],
    ]]"
>
    @include('public.landings._layout')
</x-public-layout>
