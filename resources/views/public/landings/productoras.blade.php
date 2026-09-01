@php
    $heroH1 = 'Streaming para Productoras de Contenido en Colombia — Lanza tu OTT Propio';
    $heroBadge = 'Plataforma OTT multipantalla · Monetización · Branding personalizado';
    $heroIntro = 'Lanza tu <strong>plataforma OTT propia con Cloudstream</strong> y empieza a monetizar tu catálogo de contenido. Streaming en vivo para eventos y estrenos, video bajo demanda (VOD) para tu biblioteca, multipantalla para que tu audiencia vea desde celular, tablet, Smart TV o computador. Control total del branding: logo, colores, dominio personalizado. Monetiza con suscripción mensual, pago por evento o pauta publicitaria. Planes desde $125.000/mes con 30 GB de almacenamiento incluido.';
    $heroCtaText = 'Hola, quiero información sobre el plan para productoras de contenido de Cloudstream';
    $features = [
        ['icon' => '📺', 'title' => 'Plataforma OTT multipantalla', 'text' => 'Tu audiencia ve tu contenido desde Smart TV (Samsung, LG, Roku), celular Android y iPhone, tablet y computador. Sin apps que desarrollar — web responsive.'],
        ['icon' => '💰', 'title' => 'Monetización flexible', 'text' => 'Elige tu modelo: suscripción mensual, pago por evento único, acceso gratuito con anuncios, o freemium con contenido premium. Tú defines los precios.'],
        ['icon' => '🎨', 'title' => 'Branding 100% personalizado', 'text' => 'Tu logo, tus colores, tu dominio (contenido.tumarca.com). La plataforma se ve como tuya, no como un proveedor genérico de streaming.'],
        ['icon' => '🎬', 'title' => 'Video bajo demanda (VOD)', 'text' => 'Sube tu catálogo de películas, series, documentales o cursos. Organiza por categorías, temporadas y etiquetas. Tu audiencia busca y reproduce cuando quiere.'],
        ['icon' => '📡', 'title' => 'Streaming en vivo para estrenos', 'text' => 'Estrena contenido en vivo con interacción por chat. Ideal para premieres, eventos pagados, webinars de pago y transmisiones especiales.'],
        ['icon' => '📊', 'title' => 'Analítica de consumo', 'text' => 'Visualiza qué contenido ven más, en qué momento abandonan, qué dispositivos prefieren y cuánto tiempo permanecen. Datos para tomar decisiones de contenido.'],
    ];
    $faqs = [
        ['q' => '¿Puedo usar mi propio dominio para la plataforma?', 'a' => 'Sí. Configuramos tu plataforma en un subdominio tuyo (contenido.tumarca.com) o en tu dominio principal (tumarca.com/contenido). Necesitas acceso a la configuración DNS de tu dominio o que tu proveedor de hosting nos lo gestione.'],
        ['q' => '¿Cuánto almacenamiento en VOD incluye el plan?', 'a' => 'El plan Plataforma Completa incluye 30 GB de almacenamiento para tu biblioteca VOD. Para catálogos más grandes (películas en HD, series completas) podemos ampliar a 100 GB, 500 GB o 1 TB bajo cotización.'],
        ['q' => '¿Cómo cobro la suscripción a mis usuarios?', 'a' => 'Puedes integrar tu pasarela de pagos actual (Bold, PayU, Mercado Pago, ePayco, Wompi) para cobrar suscripciones recurrentes. Cloudstream entrega el streaming; la facturación y el recaudo lo manejas con tu plataforma de pagos.'],
        ['q' => '¿Puedo proteger el contenido con DRM?', 'a' => 'Sí. Para contenido premium (estrenos, series originales, cursos pagados) activamos cifrado HLS con tokens de acceso. Cada reproducción valida que el usuario tenga derecho a ver el contenido.'],
    ];
    $ctaTitle = 'Lanza tu plataforma OTT esta semana';
    $ctaSubtitle = 'Branding propio, monetización flexible, multipantalla. Sin desarrollar apps nativas.';
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'landing', 'data' => [
        'service_name' => 'Streaming para Productoras de Contenido en Colombia',
        'service_type' => 'Plataforma OTT personalizada con VOD y monetización',
        'service_description' => 'Servicio de streaming 24/7 para productoras de contenido en Colombia: plataforma OTT propia con multipantalla, video bajo demanda (VOD), monetización flexible y branding personalizado.',
        'faqs' => $faqs,
        'breadcrumbs' => [
            ['name' => 'Inicio', 'path' => '/'],
            ['name' => 'Streaming para Productoras', 'path' => '/streaming-para-productoras-de-contenido'],
        ],
    ]]"
>
    @include('public.landings._layout')
</x-public-layout>
