@php
    $wa = preg_replace('/^\+/', '', config('seo.company.phone_e164'));
    $faqs = [
        ['q' => '¿RTMP está obsoleto en 2026?', 'a' => 'No. Aunque Adobe dejó de desarrollarlo en 2012, RTMP sigue siendo el protocolo estándar de la industria para ingest de video. Lo soportan OBS, vMix, Wirecast, YouTube, Facebook, Twitch, TikTok y prácticamente cualquier servidor streaming. La alternativa moderna SRT está ganando terreno, pero RTMP sigue siendo dominante.'],
        ['q' => '¿Cuándo conviene SRT sobre RTMP?', 'a' => 'SRT es mejor cuando transmites desde lugares con internet inestable: redes WiFi públicas, conexiones celulares 4G/5G con fluctuaciones, o cuando hay pérdida de paquetes. RTMP es suficiente si tu conexión es estable por cable.'],
        ['q' => '¿HLS sigue siendo el protocolo de entrega estándar?', 'a' => 'Sí. HLS es el protocolo universal para entrega de video en vivo a navegadores, apps móviles y Smart TVs. DASH es una alternativa usada por Netflix y algunas OTTs, pero HLS cubre el 95% de los casos. Para baja latencia existe LL-HLS.'],
    ];
@endphp

<x-public-layout :title="$meta['title']" :description="$meta['description']" :canonical="$meta['canonical']" :schema="['type' => 'blog', 'data' => ['title' => $meta['title'], 'description' => $meta['description'], 'date_published' => '2026-08-24', 'date_modified' => '2026-08-24', 'faqs' => $faqs]]">
    <article class="max-w-3xl mx-auto px-4 sm:px-6 pt-12 pb-16 prose-public">
        <div class="text-xs text-slate-500 mb-3">24 de agosto de 2026 · 5 min de lectura</div>
        <h1>RTMP vs HLS vs SRT — Diferencias y cuándo usar cada protocolo</h1>

        <figure class="my-6">
            <img src="{{ url('/images/blog/rtmp-vs-hls-vs-srt-diferencias.jpg') }}" alt="Comparativa visual entre los protocolos de streaming RTMP, HLS y SRT con sus características principales" width="1200" height="600" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">Tres protocolos, tres usos: RTMP para ingest, HLS para delivery, SRT para redes difíciles.</figcaption>
        </figure>

        <p>Cuando configuras una transmisión de video en vivo, te encuentras con tres siglas que aparecen en toda la documentación técnica: RTMP, HLS y SRT. Cada uno tiene su propósito, sus ventajas y sus limitaciones. Entender las diferencias te ayuda a elegir la combinación correcta para tu caso de uso.</p>

        <figure class="my-8">
            <img src="{{ url('/images/blog/diagram-protocols.jpg') }}" alt="Tabla visual comparando latencia, uso principal y características técnicas de RTMP, HLS y SRT para transmisión de TV" width="1200" height="450" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">Comparación directa: latencia, caso de uso principal y puerto de cada protocolo.</figcaption>
        </figure>

        <h2>Resumen rápido</h2>

        <ul>
            <li><strong>RTMP</strong>: protocolo de ingest. Lo usa tu software (OBS, vMix) para enviar la señal al servidor. Baja latencia.</li>
            <li><strong>HLS</strong>: protocolo de entrega. Lo usa el reproductor del televidente para recibir la señal. Compatible universalmente.</li>
            <li><strong>SRT</strong>: alternativa moderna a RTMP, más robusta ante redes inestables. Latencia comparable.</li>
        </ul>

        <h2>RTMP (Real-Time Messaging Protocol)</h2>

        <p>RTMP fue creado por Macromedia en 2002 y adoptado masivamente cuando Flash era el estándar de video en la web. Aunque Flash desapareció, RTMP sigue vivo como protocolo de ingest.</p>

        <p><strong>Ventajas</strong>:</p>
        <ul>
            <li>Baja latencia: 0.5 a 2 segundos entre emisión y servidor.</li>
            <li>Soportado por todo el ecosistema (OBS, vMix, Wirecast, YouTube, Facebook, Twitch).</li>
            <li>Simple de configurar: solo URL y clave de stream.</li>
        </ul>

        <p><strong>Desventajas</strong>:</p>
        <ul>
            <li>Usa puerto 1935, que puede ser bloqueado por firewalls corporativos.</li>
            <li>No es nativo de navegadores modernos (los televidentes no pueden ver RTMP directamente).</li>
            <li>Menos robusto ante pérdida de paquetes que SRT.</li>
        </ul>

        <h2>HLS (HTTP Live Streaming)</h2>

        <p>HLS fue creado por Apple en 2009 para soportar streaming en iPhone sin Flash. Hoy es el protocolo de entrega universal para video en vivo.</p>

        <p><strong>Ventajas</strong>:</p>
        <ul>
            <li>Compatible con cualquier navegador moderno, app móvil y Smart TV.</li>
            <li>Usa HTTP estándar (puerto 443), atraviesa firewalls sin problemas.</li>
            <li>Adaptativo (ABR): cambia de calidad según el ancho de banda del televidente.</li>
            <li>Funciona con CDN para escalar a millones de televidentes.</li>
        </ul>

        <p><strong>Desventajas</strong>:</p>
        <ul>
            <li>Mayor latencia: 5-15 segundos por defecto.</li>
            <li>LL-HLS (low latency) baja el delay a 2-3 segundos pero requiere más servidor.</li>
        </ul>

        <h2>SRT (Secure Reliable Transport)</h2>

        <p>SRT es un protocolo relativamente nuevo (2017) desarrollado por Haivision. Se diseñó para reemplazar RTMP en escenarios donde la red es impredecible.</p>

        <p><strong>Ventajas</strong>:</p>
        <ul>
            <li>Recuperación ante pérdida de paquetes: reenvía automáticamente los paquetes perdidos.</li>
            <li>Cifrado AES de extremo a extremo (más seguro que RTMP).</li>
            <li>Latencia comparable a RTMP (0.5-2 segundos).</li>
            <li>Excelente para transmisiones desde redes 4G/5G o WiFi inestable.</li>
        </ul>

        <p><strong>Desventajas</strong>:</p>
        <ul>
            <li>Menor soporte en software: OBS lo soporta desde la versión 26+, vMix desde 2020.</li>
            <li>No todas las plataformas lo aceptan para ingest.</li>
            <li>Requiere configuración más detallada (latencia objetivo, cifrado, buffer).</li>
        </ul>

        <h2>Cuándo usar cada uno</h2>

        <h3>RTMP: el estándar de la industria</h3>

        <p>Usa RTMP si:</p>

        <ul>
            <li>Emites desde un estudio con conexión a internet estable por cable.</li>
            <li>Usas OBS, vMix, Wirecast o cualquier software estándar.</li>
            <li>Quieres máxima compatibilidad con todas las plataformas.</li>
        </ul>

        <h3>HLS: para entregar a tu audiencia</h3>

        <p>Usa HLS para:</p>

        <ul>
            <li>Entregar la señal a los televidentes en navegadores, apps móviles y Smart TVs.</li>
            <li>Escalar a audiencias grandes vía CDN.</li>
            <li>Cuando la latencia de 5-15 segundos no es un problema (TV tradicional, canales 24/7).</li>
        </ul>

        <h3>SRT: para redes difíciles</h3>

        <p>Usa SRT si:</p>

        <ul>
            <li>Transmites desde exteriores, eventos en campo o lugares con internet inestable.</li>
            <li>Necesitas cifrado de extremo a extremo.</li>
            <li>Tu software y servidor lo soportan (OBS 26+, vMix, Larix, Wowza, Cloudstream bajo cotización).</li>
        </ul>

        <h2>La combinación más común</h2>

        <p>Para la mayoría de canales de TV por Internet en Colombia, la combinación ganadora es:</p>

        <ul>
            <li><strong>Ingest</strong>: RTMP desde OBS al servidor.</li>
            <li><strong>Entrega</strong>: HLS desde el servidor a los televidentes.</li>
        </ul>

        <p>Cloudstream usa exactamente esta combinación por defecto. Tú configuras OBS con la URL RTMP que te entregamos, y tu audiencia ve la señal en una URL HLS pública.</p>

        <h2>¿Y si necesito baja latencia?</h2>

        <p>Para escenarios interactivos (subastas en vivo, sorteos, Q&A con el público), la latencia de HLS (5-15 segundos) puede ser molesta. Opciones:</p>

        <ul>
            <li><strong>LL-HLS</strong>: latencia de 2-3 segundos. Requiere servidor configurado específicamente.</li>
            <li><strong>WebRTC</strong>: latencia sub-segundo. Para Q&A interactivo con chat.</li>
            <li><strong>RTMP delivery</strong>: pocos navegadores lo soportan nativamente, pero apps como VLC pueden.</li>
        </ul>

        <p>Cloudstream ofrece LL-HLS bajo cotización. Para WebRTC, necesitas un servidor especializado como mediasoup o Janus.</p>

        <h2>Conclusión</h2>

        <p>No necesitas saberlo todo sobre protocolos para transmitir tu canal. La regla práctica es: usa RTMP para subir, HLS para entregar. Si tu red es inestable, evalúa SRT. Si necesitas interactividad en tiempo real, considera LL-HLS o WebRTC. El resto, déjalo en manos de tu proveedor de servidor streaming.</p>

        <div class="mt-12 glass-card rounded-2xl p-8 text-center not-prose">
            <h2 class="text-2xl font-bold title-grad mb-3">RTMP + HLS automático</h2>
            <p class="text-slate-400 mb-6">Cloudstream maneja ambos protocolos por ti. Tú configuras OBS y tu audiencia ve la señal.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero información sobre el servidor de Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">Hablar por WhatsApp</a>
                <a href="{{ url('/servidor-rtmp-colombia') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Ver servidor RTMP</a>
            </div>
        </div>
    </article>
</x-public-layout>
