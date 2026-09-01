@php
    $wa = preg_replace('/^\+/', '', config('seo.company.phone_e164'));
    $faqs = [
        ['q' => '¿Qué protocolo usa YouTube para recibir transmisiones en vivo?', 'a' => 'YouTube usa RTMP para el ingest (recibir tu señal) y HLS para entregar la señal a los televidentes. Este es el patrón estándar en toda la industria: RTMP para subir, HLS para entregar.'],
        ['q' => '¿Por qué los televidentes ven la señal con unos segundos de retraso?', 'a' => 'El retraso es la suma del tiempo de codificación de OBS (~1s), el envío por RTMP al servidor (~0.5s), y la entrega HLS al televidente (~5-10s por el tamaño de los segmentos). Para reducir el delay se usa LL-HLS (low latency HLS), que baja el retraso a 2-3 segundos pero requiere más ancho de banda del servidor.'],
        ['q' => '¿RTMP ya está obsoleto?', 'a' => 'No. Aunque Adobe dejó de desarrollar RTMP en 2012, sigue siendo el protocolo estándar de la industria para ingest de video. Lo soportan OBS, vMix, Wirecast, XSplit, y todas las plataformas de streaming (YouTube, Facebook, Twitch, TikTok). La alternativa moderna es SRT, pero RTMP sigue siendo dominante.'],
    ];
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'blog', 'data' => ['title' => $meta['title'], 'description' => $meta['description'], 'date_published' => '2026-08-24', 'date_modified' => '2026-08-24', 'faqs' => $faqs]]"
>
    <article class="max-w-3xl mx-auto px-4 sm:px-6 pt-12 pb-16 prose-public">
        <div class="text-xs text-slate-500 mb-3">24 de agosto de 2026 · 5 min de lectura</div>
        <h1>Qué es RTMP y HLS — Protocolos de streaming de TV explicados</h1>

        <figure class="my-6">
            <img src="{{ url('/images/blog/que-es-rtmp-hls.jpg') }}" alt="Ilustración de los protocolos de streaming RTMP y HLS explicados para canales de TV en Colombia" width="1200" height="600" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">RTMP y HLS son los dos protocolos fundamentales del streaming de TV por Internet.</figcaption>
        </figure>

        <p>Si estás empezando en el mundo del streaming de TV, probablemente te has encontrado con dos siglas que aparecen en todos lados: <strong>RTMP</strong> y <strong>HLS</strong>. Entender qué son y en qué se diferencian es fundamental para tomar decisiones técnicas correctas sobre tu canal.</p>

        <figure class="my-8">
            <img src="{{ url('/images/blog/diagram-rtmp-flow.jpg') }}" alt="Diagrama del flujo de transmisión: cámara → OBS → servidor → televidentes mostrando cómo viaja la señal RTMP al servidor y se entrega como HLS a los viewers" width="1200" height="400" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">Flujo completo: tu cámara captura, OBS codifica en RTMP, el servidor Cloudstream procesa, y los televidentes reciben HLS en sus dispositivos.</figcaption>
        </figure>

        <h2>RTMP: el protocolo de subida</h2>

        <p>RTMP (Real-Time Messaging Protocol) fue creado por Macromedia en 2002 y luego mantenido por Adobe. Es el protocolo que usa tu software de emisión (OBS Studio, vMix, Wirecast) para enviar tu señal de video al servidor streaming.</p>

        <p>Características de RTMP:</p>

        <ul>
            <li><strong>Baja latencia</strong>: el retraso entre emisión y servidor es de 0.5 a 2 segundos.</li>
            <li><strong>Conexión persistente</strong>: una vez establecida, la conexión se mantiene abierta durante toda la transmisión.</li>
            <li><strong>Puerto 1935</strong>: usa TCP puerto 1935 (a veces también 443 para sortear firewalls).</li>
            <li><strong>Soportado universalmente</strong>: YouTube, Facebook, Twitch, TikTok, Instagram, y todos los servidores streaming lo aceptan.</li>
        </ul>

        <p>En la práctica, cuando configuras OBS y pegas la URL del servidor (ejemplo: rtmp://stream.midominio.com/live), estás usando RTMP para subir tu señal.</p>

        <h2>HLS: el protocolo de entrega</h2>

        <p>HLS (HTTP Live Streaming) fue creado por Apple en 2009. Es el protocolo que usa el reproductor del televidente (Chrome, Safari, Firefox, apps móviles, Smart TVs) para recibir la señal.</p>

        <p>Características de HLS:</p>

        <ul>
            <li><strong>Basado en HTTP</strong>: funciona sobre el mismo protocolo que las páginas web, lo que lo hace compatible con cualquier red y firewall.</li>
            <li><strong>Segmentos de video</strong>: el servidor corta el video en pedazos de 2-10 segundos y los entrega al reproductor.</li>
            <li><strong>Adaptativo (ABR)</strong>: el reproductor puede cambiar entre calidades según el ancho de banda disponible del televidente.</li>
            <li><strong>Latencia de 5-15 segundos</strong>: mayor que RTMP, pero más compatible y estable.</li>
        </ul>

        <h2>El flujo completo: de tu cámara al televidente</h2>

        <p>El proceso tiene cuatro etapas:</p>

        <ol>
            <li><strong>Captura</strong>: tu cámara graba el video y el micrófono captura el audio.</li>
            <li><strong>Codificación</strong>: OBS (o tu software) comprime el video en H264 y el audio en AAC, y lo empaqueta en RTMP.</li>
            <li><strong>Ingest</strong>: la señal viaja por RTMP al servidor streaming (en nuestro caso, el datacenter americano de Cloudstream).</li>
            <li><strong>Distribución</strong>: el servidor transcodifica la señal a múltiples calidades y la entrega como HLS a cada televidente.</li>
        </ol>

        <p>El televidente nunca ve RTMP. Su navegador o app abre una URL HLS y reproduce los segmentos de video.</p>

        <h2>¿Por qué se usan dos protocolos diferentes?</h2>

        <p>La razón es histórica y práctica. RTMP fue diseñado cuando Flash Player dominaba la web y necesitaba un protocolo eficiente para streaming. HLS surgió después, cuando Apple decidió que sus dispositivos (iPhone, iPad, Apple TV) necesitaban streaming nativo sin Flash.</p>

        <p>RTMP sigue siendo excelente para el ingest porque es de baja latencia y maneja bien las conexiones largas. HLS es mejor para la distribución porque atraviesa firewalls, se adapta al ancho de banda del televidente, y funciona en cualquier dispositivo moderno.</p>

        <h2>Cuándo usar cada uno</h2>

        <p>Como emisor de un canal de TV, tu única decisión técnica es:</p>

        <ul>
            <li><strong>Para subir tu señal</strong>: usa RTMP. Es lo que hace OBS por defecto. Tu servidor te dará una URL tipo rtmp://tuserver.com/live y una clave.</li>
            <li><strong>Para entregar a tu audiencia</strong>: usa HLS. Tu servidor convierte automáticamente el RTMP en HLS. Tu audiencia ve una URL tipo https://tuserver.com/stream.m3u8.</li>
        </ul>

        <p>Tú no tienes que hacer nada especial. El servidor streaming se encarga de la conversión. Tu trabajo es configurar OBS con la URL RTMP y la clave de stream.</p>

        <h2>Otros protocolos que quizá escuches</h2>

        <ul>
            <li><strong>SRT (Secure Reliable Transport)</strong>: protocolo más moderno que RTMP, con mejor recuperación ante pérdida de paquetes. Útil para transmisiones desde lugares con internet inestable.</li>
            <li><strong>WebRTC</strong>: latencia ultrabaja (sub-segundo), usado en videollamadas y transmisiones interactivas. No es práctico para canales 24/7.</li>
            <li><strong>DASH (MPEG-DASH)</strong>: similar a HLS pero estándar ISO. Usado por Netflix y algunas plataformas OTT. No es relevante para transmisión en vivo.</li>
        </ul>

        <h2>Resumen práctico</h2>

        <p>Si tu objetivo es transmitir un canal de TV por Internet en Colombia, lo único que necesitas saber es:</p>

        <ul>
            <li>Tu software (OBS, vMix) emite por RTMP al servidor.</li>
            <li>Tu servidor entrega la señal por HLS a los televidentes.</li>
            <li>El televidente abre una URL HTTPS en su navegador o app y ve el video.</li>
        </ul>

        <p>Cloudstream maneja ambos protocolos automáticamente. Tú configuras OBS con la URL RTMP que te entregamos, y tu audiencia entra a una URL HLS pública. Sin complicaciones adicionales.</p>

        <div class="mt-12 glass-card rounded-2xl p-8 text-center not-prose">
            <h2 class="text-2xl font-bold title-grad mb-3">Servidor RTMP + entrega HLS</h2>
            <p class="text-slate-400 mb-6">Cloudstream maneja ambos protocolos automáticamente. Configuras OBS una vez y tu audiencia ve tu señal.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero información sobre el servidor RTMP de Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">Hablar por WhatsApp</a>
                <a href="{{ url('/servidor-rtmp-colombia') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Ver servidor RTMP</a>
            </div>
        </div>
    </article>
</x-public-layout>
