<?php

return [

    'company' => [
        'name' => 'Media Clouding SAS',
        'brand' => 'Cloudstream',
        'nit' => '900.000.000-1',
        'city' => 'Bogotá',
        'country' => 'Colombia',
        'country_code' => 'CO',
        'phone_e164' => '+573124082557',
        'phone_display' => '+57 312 408 2557',
        'email' => 'contacto@cloudstream.mediaserver.com.co',
        'address' => 'Bogotá D.C., Colombia',
        'founding_year' => 2020,
        'founder' => 'Media Clouding SAS',
        'social' => [
            'https://www.facebook.com/cloudstream.co',
            'https://www.instagram.com/cloudstream.co',
            'https://www.linkedin.com/company/cloudstream-co',
        ],
        'awards' => [
            'Empresa legalmente constituida en Colombia',
            'Factura electrónica vigente (DIAN)',
        ],
    ],

    'site_url' => 'https://cloudstream.mediaserver.com.co',

    'og_image' => '/og/home-1200x630.jpg',

    'plans' => [
        'plan1' => [
            'name' => 'Señal Streaming',
            'price_cop' => 75000,
            'price_original_cop' => 150000,
            'discount_percent' => 50,
            'description' => 'El cliente emite desde su propio equipo con OBS, vMix o su aplicación hacia nuestro servidor streaming.',
        ],
        'plan2' => [
            'name' => 'Plataforma Completa',
            'price_cop' => 125000,
            'price_original_cop' => 312500,
            'discount_percent' => 60,
            'description' => 'Emisión desde la nube sin depender del equipo del cliente. Incluye 30 GB de almacenamiento, programador, cuñas, logo y respaldo diario.',
        ],
        'plan3' => [
            'name' => 'Plataforma + Restream',
            'price_cop' => 135000,
            'price_original_cop' => 192857,
            'discount_percent' => 30,
            'description' => 'Transmisión simultánea a Facebook Live, YouTube Live, TikTok Live y destinos RTMP personalizados, hasta 4 salidas.',
        ],
    ],

    'public_urls' => [
        ['path' => '/', 'type' => 'home', 'lastmod' => '2026-08-24', 'title' => 'Señal de TV en vivo por Internet en Colombia | Streaming 24/7 y Restream | Cloudstream', 'description' => 'Transmite tu canal de TV en vivo por Internet en Colombia. Streaming 24/7, programador, cuñas y restream a Facebook, YouTube, TikTok. Desde $75.000/mes con datacenter americano 99% uptime.', 'schema_data' => []],
        ['path' => '/streaming-para-iglesias-colombia', 'type' => 'landing', 'lastmod' => '2026-08-24', 'title' => 'Streaming para Iglesias en Colombia — Transmite Misas y Eventos en Vivo | Cloudstream', 'description' => 'Lleva tu iglesia a Internet con streaming 24/7 de misas, cultos y eventos especiales. Cobertura multi-sede, programación de cultos y donación en línea. Planes desde $75.000/mes.', 'schema_data' => []],
        ['path' => '/streaming-para-emisoras-de-radio-online', 'type' => 'landing', 'lastmod' => '2026-08-24', 'title' => 'Streaming para Emisoras de Radio Online en Colombia — Audio + Video | Cloudstream', 'description' => 'Convierte tu emisora online en un canal de TV por Internet. Streaming de baja latencia, apps móviles, monetización con cuñas publicitarias. Planes desde $75.000/mes.', 'schema_data' => []],
        ['path' => '/streaming-para-canales-de-tv-regionales', 'type' => 'landing', 'lastmod' => '2026-08-24', 'title' => 'Streaming para Canales de TV Regionales en Colombia — Cobertura Nacional | Cloudstream', 'description' => 'Lleva tu canal regional a Internet con señal HD, cobertura nacional y compatibilidad con cableoperadores. Streaming 24/7 con programador de contenido. Planes desde $75.000/mes.', 'schema_data' => []],
        ['path' => '/streaming-para-universidades-y-educacion', 'type' => 'landing', 'lastmod' => '2026-08-24', 'title' => 'Streaming para Universidades y Educación en Colombia — Clases Híbridas | Cloudstream', 'description' => 'Transmite clases, conferencias y eventos académicos en vivo o bajo demanda. Grabación automática, protección con login e integración con LMS. Planes desde $75.000/mes.', 'schema_data' => []],
        ['path' => '/streaming-para-productoras-de-contenido', 'type' => 'landing', 'lastmod' => '2026-08-24', 'title' => 'Streaming para Productoras de Contenido en Colombia — OTT Propio | Cloudstream', 'description' => 'Lanza tu plataforma OTT propia con multipantalla, monetización y branding personalizado. Streaming 24/7 para productoras de contenido en Colombia. Planes desde $75.000/mes.', 'schema_data' => []],
        ['path' => '/restream-facebook-youtube-tiktok', 'type' => 'landing', 'lastmod' => '2026-08-24', 'title' => 'Restream a Facebook, YouTube y TikTok al Mismo Tiempo — Colombia | Cloudstream', 'description' => 'Transmite tu canal simultáneamente a Facebook Live, YouTube Live y TikTok Live. Hasta 4 destinos RTMP personalizados con failover y monitoreo desde panel. Planes desde $135.000/mes.', 'schema_data' => []],
        ['path' => '/servidor-rtmp-colombia', 'type' => 'landing', 'lastmod' => '2026-08-24', 'title' => 'Servidor RTMP en Colombia — Ingest para OBS, vMix y Wirecast | Cloudstream', 'description' => 'Servidor RTMP con ingest desde OBS, vMix y Wirecast, redundancia y bitrate configurable. Ideal para canales de TV y productores en Colombia. Planes desde $75.000/mes.', 'schema_data' => []],
        ['path' => '/preguntas-frecuentes', 'type' => 'faq', 'lastmod' => '2026-08-24', 'title' => 'Preguntas Frecuentes sobre Streaming de TV en Colombia | Cloudstream', 'description' => 'Resuelve las dudas más comunes sobre streaming de señal de TV en Colombia: planes, precios, protocolos RTMP y HLS, restream a redes sociales, programador de contenido y soporte técnico.', 'schema_data' => []],
        ['path' => '/sobre-nosotros', 'type' => 'about', 'lastmod' => '2026-08-24', 'title' => 'Sobre Nosotros — Media Clouding SAS, Cloudstream Colombia', 'description' => 'Conoce a Media Clouding SAS, la empresa colombiana detrás de Cloudstream: NIT, datacenter americano, historia, equipo y datos de contacto.', 'schema_data' => []],

        ['path' => '/blog', 'type' => 'blog-index', 'lastmod' => '2026-08-24', 'title' => 'Blog de Streaming y Televisión en Colombia | Cloudstream', 'description' => 'Guías, comparativas y artículos sobre streaming de TV en Colombia: RTMP, HLS, OBS, restream a redes sociales, costos y casos de uso para iglesias, emisoras y canales regionales.', 'schema_data' => []],

        ['path' => '/blog/cuanto-cuesta-transmitir-tv-internet', 'type' => 'blog', 'lastmod' => '2026-08-24', 'title' => 'Cuánto Cuesta Transmitir un Canal de TV en Vivo por Internet en Colombia (2026)', 'description' => 'Comparativa de precios y planes para transmitir un canal de TV en vivo por Internet en Colombia: desde soluciones gratis con OBS hasta plataformas profesionales en datacenter americano.', 'schema_data' => []],
        ['path' => '/blog/como-transmitir-canal-tv-internet', 'type' => 'blog', 'lastmod' => '2026-08-24', 'title' => 'Cómo Transmitir un Canal de TV por Internet en Colombia — Guía 2026', 'description' => 'Guía paso a paso para transmitir tu canal de TV por Internet en Colombia: desde la elección del servidor RTMP hasta la configuración del programador de contenido.', 'schema_data' => []],
        ['path' => '/blog/que-es-rtmp-hls', 'type' => 'blog', 'lastmod' => '2026-08-24', 'title' => 'Qué es RTMP y HLS — Protocolos de Streaming de TV Explicados', 'description' => 'Aprende qué son los protocolos RTMP y HLS, en qué se diferencian y cuándo usar cada uno para transmitir tu canal de TV por Internet.', 'schema_data' => []],
        ['path' => '/blog/alternativas-a-obs-para-transmitir-24-7', 'type' => 'blog', 'lastmod' => '2026-08-24', 'title' => '5 Alternativas a OBS para Transmitir tu Canal 24/7', 'description' => 'Comparativa de las mejores alternativas a OBS Studio para emitir tu canal de TV 24/7 sin depender de un equipo encendido: Cloudstream, Wowza, Dacast, AWS IVS y vMix.', 'schema_data' => []],
        ['path' => '/blog/como-elegir-servidor-de-streaming-en-colombia', 'type' => 'blog', 'lastmod' => '2026-08-24', 'title' => 'Cómo Elegir un Servidor de Streaming en Colombia — 7 Criterios', 'description' => '7 criterios para elegir un servidor de streaming en Colombia: ubicación del datacenter, protocolos, codecs, soporte, facturación local y redundancia.', 'schema_data' => []],
        ['path' => '/blog/streaming-para-iglesias-como-transmitir-misas-y-eventos-en-vivo', 'type' => 'blog', 'lastmod' => '2026-08-24', 'title' => 'Streaming para Iglesias: Cómo Transmitir Misas y Eventos en Vivo', 'description' => 'Guía práctica para iglesias que quieren transmitir misas, cultos y eventos especiales en vivo por Internet: equipos, costos, programación y donación en línea.', 'schema_data' => []],
        ['path' => '/blog/como-verificar-que-tu-pauta-publicitaria-se-emitio-en-tv', 'type' => 'blog', 'lastmod' => '2026-08-24', 'title' => 'Cómo Verificar que tu Pauta Publicitaria se Emitió en TV', 'description' => 'Aprende a verificar que tu pauta publicitaria realmente se emitió en TV: registros del programador, reportes de emisión y auditoría de cuñas.', 'schema_data' => []],
        ['path' => '/blog/rtmp-vs-hls-vs-srt-diferencias', 'type' => 'blog', 'lastmod' => '2026-08-24', 'title' => 'RTMP vs HLS vs SRT — Diferencias y Cuándo Usar Cada uno', 'description' => 'Comparativa técnica entre RTMP, HLS y SRT: latencia, compatibilidad, casos de uso y cuándo conviene cada protocolo para transmitir TV por Internet.', 'schema_data' => []],
        ['path' => '/blog/casos-de-uso-emisoras-colombianas-24-7', 'type' => 'blog', 'lastmod' => '2026-08-24', 'title' => 'Casos de Uso: Cómo las Emisoras Colombianas Transmiten 24/7', 'description' => 'Cómo emisoras online colombianas pasaron de solo audio a transmitir video 24/7 por Internet: casos de uso, costos, programadores y monetización con cuñas.', 'schema_data' => []],
        ['path' => '/blog/como-empezar-a-transmitir-tu-canal-en-5-minutos', 'type' => 'blog', 'lastmod' => '2026-08-24', 'title' => 'Cómo Empezar a Transmitir tu Canal de TV en 5 Minutos', 'description' => 'Mini-guía express: en 5 minutos puedes estar transmitiendo tu canal de TV por Internet. Checklist de OBS, servidor RTMP y configuración inicial.', 'schema_data' => []],
    ],

    'landing_slugs' => [
        'streaming-para-iglesias-colombia',
        'streaming-para-emisoras-de-radio-online',
        'streaming-para-canales-de-tv-regionales',
        'streaming-para-universidades-y-educacion',
        'streaming-para-productoras-de-contenido',
        'restream-facebook-youtube-tiktok',
        'servidor-rtmp-colombia',
    ],

    'blog_slugs' => [
        'cuanto-cuesta-transmitir-tv-internet',
        'como-transmitir-canal-tv-internet',
        'que-es-rtmp-hls',
        'alternativas-a-obs-para-transmitir-24-7',
        'como-elegir-servidor-de-streaming-en-colombia',
        'streaming-para-iglesias-como-transmitir-misas-y-eventos-en-vivo',
        'como-verificar-que-tu-pauta-publicitaria-se-emitio-en-tv',
        'rtmp-vs-hls-vs-srt-diferencias',
        'casos-de-uso-emisoras-colombianas-24-7',
        'como-empezar-a-transmitir-tu-canal-en-5-minutos',
    ],

];
