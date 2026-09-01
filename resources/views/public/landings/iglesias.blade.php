@php
    $heroH1 = 'Streaming para Iglesias en Colombia — Misas y Eventos en Vivo 24/7';
    $heroBadge = 'Cobertura multi-sede · Programación de cultos · Donación en línea';
    $heroIntro = 'Lleva tu iglesia a Internet con <strong>señal de TV en vivo 24/7</strong> para misas, cultos, eventos especiales y actividades pastorales. Con Cloudstream tu comunidad puede seguir la celebración desde cualquier dispositivo, sin importar dónde esté. El plan Plataforma Completa te permite programar la parrilla de cultos por día y hora, insertar cuñas de la comunidad y mantener una señal estable desde un datacenter americano con uptime del 99%. Atendemos comunidades católicas, cristianas, evangélicas y de cualquier denominación en toda Colombia.';
    $heroCtaText = 'Hola, quiero información sobre el plan para iglesias de Cloudstream';
    $features = [
        ['icon' => '⛪', 'title' => 'Cobertura multi-sede', 'text' => 'Centraliza la transmisión de varias sedes de tu iglesia en una sola señal. Cada sede puede tener su propio horario y la programación se unifica en el aire.'],
        ['icon' => '📅', 'title' => 'Programación de cultos por día y hora', 'text' => 'Organiza misas dominicales, cultos de miércoles, reuniones de jóvenes y eventos especiales con un calendario semanal que se ejecuta automáticamente.'],
        ['icon' => '🎥', 'title' => 'Equipos simples para empezar', 'text' => 'Emite desde tu propia cámara con OBS, vMix o un celular con conexión HDMI. Nosotros te damos el servidor y las claves de transmisión.'],
        ['icon' => '📱', 'title' => 'Compatible con todos los dispositivos', 'text' => 'Tu señal llega a Smart TVs, celulares Android y iPhone, tablets y computadores. Tu comunidad puede seguir la misa desde casa o de viaje.'],
        ['icon' => '💰', 'title' => 'Donación y ofrenda en línea', 'text' => 'Inserta anuncios de la cuenta bancaria, Nequi, Daviplata o tu plataforma de donaciones en los momentos designados de la celebración.'],
        ['icon' => '🔁', 'title' => 'Comparte en Facebook y YouTube', 'text' => 'Con el plan Plataforma + Restream retransmite la misa en vivo a tu página de Facebook y a tu canal de YouTube al mismo tiempo, sin equipos adicionales.'],
    ];
    $faqs = [
        ['q' => '¿Puedo transmitir misas en horarios fijos todas las semanas?', 'a' => 'Sí. El plan Plataforma Completa incluye un programador de contenido que te permite configurar tu parrilla semanal: misa dominical a las 8:00 AM, culto de jóvenes los sábados a las 6:00 PM, etc. La señal se emite sola, sin que tengas que intervenir cada vez.'],
        ['q' => '¿Cómo funcionan los eventos especiales como bodas, bautismos o velaciones?', 'a' => 'Para eventos especiales puedes crear entradas puntuales en el programador con fecha y hora específicas. También puedes emitir en vivo desde un celular o cámara el día del evento. Tu audiencia recibirá la señal como cualquier otra celebración.'],
        ['q' => '¿Puedo recibir ofrendas y donaciones a través de la señal?', 'a' => 'Sí. Puedes insertar anuncios programados con la información de tu cuenta bancaria, Nequi, Daviplata o tu plataforma de donaciones (Bold, Donorbox, etc.) en los momentos que designes dentro de la celebración.'],
        ['q' => '¿Puedo transmitir desde varias sedes al mismo tiempo?', 'a' => 'Sí. Con Cloudstream puedes operar una señal principal por sede o unificar varias sedes en una sola señal con bloques de programación distintos. Cada sede puede tener su propio estudio y horario.'],
    ];
    $ctaTitle = 'Lleva tu iglesia a Internet esta semana';
    $ctaSubtitle = 'Emitimos tu señal desde un datacenter americano y te damos todo el soporte para que tu comunidad esté conectada contigo.';
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'landing', 'data' => [
        'service_name' => 'Streaming para Iglesias en Colombia',
        'service_type' => 'Streaming de misas, cultos y eventos religiosos en vivo',
        'service_description' => 'Servicio de streaming 24/7 para iglesias en Colombia: transmisión de misas, cultos, eventos especiales, programación semanal y cobertura multi-sede desde datacenter americano.',
        'faqs' => $faqs,
        'breadcrumbs' => [
            ['name' => 'Inicio', 'path' => '/'],
            ['name' => 'Streaming para Iglesias', 'path' => '/streaming-para-iglesias-colombia'],
        ],
    ]]"
>
    @include('public.landings._layout')
</x-public-layout>
