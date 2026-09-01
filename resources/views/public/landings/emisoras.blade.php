@php
    $heroH1 = 'Streaming para Emisoras de Radio Online en Colombia — Audio + Video en Vivo';
    $heroBadge = 'Baja latencia · Apps móviles · Monetización con cuñas';
    $heroIntro = 'Convierte tu <strong>emisora de radio online en un canal de TV por Internet</strong> sin cambiar tu operación actual. Con Cloudstream tu señal de audio se complementa con video en vivo — la cabina, los locutores, los invitados — y se transmite 24/7 con protocolos RTMP y HLS. Tu audiencia podrá verte en Facebook, YouTube, TikTok o en una página web propia, mientras sigues emitiendo audio en tus apps móviles y en tu dial digital. Planes desde $75.000/mes con programador de contenido, cuñas publicitarias y restream a redes sociales.';
    $heroCtaText = 'Hola, quiero información sobre el plan para emisoras de radio online de Cloudstream';
    $features = [
        ['icon' => '🎙️', 'title' => 'Video de la cabina en vivo', 'text' => 'Instala una cámara en tu cabina y transmite la imagen de tus locutores junto con el audio. El formato funciona especialmente bien para emisoras juveniles y musicales.'],
        ['icon' => '📱', 'title' => 'Sigue emitiendo en tus apps móviles', 'text' => 'Tu señal HLS funciona en apps como TuneIn, Radio Garden, Apple Music, Spotify Live y cualquier reproductor de radio por Internet. Tu audiencia no se pierde.'],
        ['icon' => '⏱️', 'title' => 'Baja latencia para programas en vivo', 'text' => 'Configuramos el servidor con latencia optimizada (5-10 segundos) para que tus oyentes-espectadores puedan interactuar en tiempo real por chat.'],
        ['icon' => '📢', 'title' => 'Cuñas publicitarias automáticas', 'text' => 'Inserta cuñas de tus clientes patrocinadores en bloques programados por hora. El sistema las reproduce solo, sin que tengas que pausar la programación.'],
        ['icon' => '🎵', 'title' => 'Música, entrevistas y podcasts en vivo', 'text' => 'Combina audio pregrabado (playlists, podcasts) con segmentos en vivo. El programador organiza todo y lo deja corriendo solo.'],
        ['icon' => '📊', 'title' => 'Métricas de audiencia', 'text' => 'Visualiza cuánta gente está conectada, desde qué ciudades y qué dispositivo usan. Datos útiles para vender pauta publicitaria.'],
    ];
    $faqs = [
        ['q' => '¿Puedo seguir emitiendo audio en mis apps de radio mientras transmito video?', 'a' => 'Sí. Tu señal de audio se mantiene igual que antes: HLS en tus apps móviles, TuneIn, Radio Garden y reproductores externos. Cloudstream añade la capa de video encima, sin reemplazar tu operación de audio.'],
        ['q' => '¿Qué tan baja es la latencia del video?', 'a' => 'Por defecto la latencia HLS está entre 8 y 15 segundos, suficiente para transmisión en vivo de cabina. Si necesitas latencia menor (3-5 segundos) podemos configurar el servidor en modo LL-HLS con un ajuste adicional.'],
        ['q' => '¿Necesito cambiar mi equipo de radio?', 'a' => 'No. Tu consola, micrófono, computador de cabina y encoders actuales siguen igual. Lo único que necesitas es una cámara (web USB, DSLR o celular con HDMI) conectada al mismo computador que emite.'],
        ['q' => '¿Cómo puedo monetizar con cuñas si ya tengo pauta contratada?', 'a' => 'Cargas tus cuñas en la plataforma, las organizas por cliente y horario, y el programador las reproduce automáticamente. Puedes tener diferentes tarifas para horario prime, madrugada y fines de semana.'],
    ];
    $ctaTitle = 'Convierte tu radio online en un canal de TV';
    $ctaSubtitle = 'Empieza a emitir video desde tu cabina esta semana. Tu audiencia ya está en Internet — dales una razón para verte.';
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'landing', 'data' => [
        'service_name' => 'Streaming para Emisoras de Radio Online en Colombia',
        'service_type' => 'Streaming de video para emisoras de radio online',
        'service_description' => 'Servicio de streaming 24/7 para emisoras de radio online en Colombia: video de cabina en vivo, baja latencia, apps móviles, monetización con cuñas publicitarias.',
        'faqs' => $faqs,
        'breadcrumbs' => [
            ['name' => 'Inicio', 'path' => '/'],
            ['name' => 'Streaming para Emisoras', 'path' => '/streaming-para-emisoras-de-radio-online'],
        ],
    ]]"
>
    @include('public.landings._layout')
</x-public-layout>
