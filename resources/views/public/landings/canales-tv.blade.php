@php
    $heroH1 = 'Streaming para Canales de TV Regionales en Colombia — Cobertura Nacional';
    $heroBadge = 'Señal HD · Compatibilidad con cableoperadores · Programador 24/7';
    $heroIntro = 'Lleva tu <strong>canal de TV regional a Internet con señal HD</strong> y mantén tu audiencia conectada en cualquier parte del país. Cloudstream te ofrece un servidor streaming profesional en datacenter americano, programador de contenido, inserción de cuñas publicitarias, logo y la posibilidad de compatibilizar tu señal con cableoperadores regionales. Atendemos canales comunitarios, canales universitarios con licencia, canales municipales y productoras regionales en toda Colombia.';
    $heroCtaText = 'Hola, quiero información sobre el plan para canales de TV regionales de Cloudstream';
    $features = [
        ['icon' => '📺', 'title' => 'Señal HD para cableoperadores', 'text' => 'Compatibilizamos tu señal con la infraestructura de cableoperadores regionales en Colombia. Ellos pueden tomar tu HLS y distribuirlo a su red de suscriptores.'],
        ['icon' => '🎬', 'title' => 'Calidad de emisión 1000 KBPS', 'text' => 'Resolución 720p con bitrate configurable. Soportamos H264, AAC y MP3 para máxima compatibilidad con decodificadores de cable y smart TVs.'],
        ['icon' => '📅', 'title' => 'Programador de programación 24/7', 'text' => 'Organiza noticieros, magazines, entretenimiento, deportes y franjas publicitarias con bloques horarios que se ejecutan automáticamente, todos los días del año.'],
        ['icon' => '🏷️', 'title' => 'Logo y marca del canal', 'text' => 'Inserta tu logo en la esquina superior de la señal, con cintillos de "EN VIVO" y barras de identificación del programa.'],
        ['icon' => '📢', 'title' => 'Venta de pauta publicitaria local', 'text' => 'Vende espacios publicitarios a comercios regionales. El sistema inserta las cuñas automáticamente en los horarios que negocies con cada anunciante.'],
        ['icon' => '📊', 'title' => 'Reportes de audiencia por ciudad', 'text' => 'Visualiza cuánta gente está viendo tu canal desde cada municipio. Información útil para vender pauta y para presentar a patrocinadores.'],
    ];
    $faqs = [
        ['q' => '¿Necesito licencia de ANTV para transmitir por Internet?', 'a' => 'Cloudstream te provee la infraestructura técnica (servidor, programador, codecs). La responsabilidad de la licencia de contenidos es tuya. Si tu canal ya tiene licencia para televisión abierta, la transmisión por Internet bajo el mismo contenido generalmente está cubierta, pero te recomendamos validar con tu asesor legal.'],
        ['q' => '¿Pueden los cableoperadores regionales tomar mi señal de Cloudstream?', 'a' => 'Sí. Les entregamos la URL HLS de tu señal y los datos técnicos para que la integren en su red. Es el mismo formato estándar que usan Netflix, YouTube y los OTT internacionales.'],
        ['q' => '¿Qué resolución y bitrate puedo emitir?', 'a' => 'El plan Señal Streaming soporta desde 320p hasta 720p con bitrate configurable entre 500 y 2000 KBPS. Para canales en HD real (1080p) podemos configurar el servidor bajo cotización especial.'],
        ['q' => '¿Cómo se factura la pauta publicitaria local?', 'a' => 'Cargas cada cuña con su anunciante, horario y tarifa. El sistema las reproduce automáticamente y tú llevas el control de cuántas veces se emitió cada una. Para facturación al anunciante puedes exportar reportes en CSV.'],
    ];
    $ctaTitle = 'Lleva tu canal regional a Internet y al cable';
    $ctaSubtitle = 'Señal profesional, programador de contenido y compatibilidad con cableoperadores en un solo plan.';
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'landing', 'data' => [
        'service_name' => 'Streaming para Canales de TV Regionales en Colombia',
        'service_type' => 'Streaming de canales de TV regionales con compatibilidad para cableoperadores',
        'service_description' => 'Servicio de streaming 24/7 para canales de TV regionales en Colombia: señal HD, cobertura nacional, compatibilidad con cableoperadores, programador de contenido y venta de pauta publicitaria local.',
        'faqs' => $faqs,
        'breadcrumbs' => [
            ['name' => 'Inicio', 'path' => '/'],
            ['name' => 'Streaming para Canales TV', 'path' => '/streaming-para-canales-de-tv-regionales'],
        ],
    ]]"
>
    @include('public.landings._layout')
</x-public-layout>
