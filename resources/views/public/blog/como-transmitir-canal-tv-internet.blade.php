@php
    $wa = preg_replace('/^\+/', '', config('seo.company.phone_e164'));
    $faqs = [
        ['q' => '¿Qué necesito para empezar a transmitir mi canal de TV por Internet?', 'a' => 'Necesitas tres cosas: 1) un computador con software de emisión (OBS Studio es gratuito y suficiente para empezar), 2) una cámara y un micrófono, 3) un servidor streaming que reciba tu señal y la entregue a los televidentes. Cloudstream te provee el servidor; tú pones el equipo de emisión.'],
        ['q' => '¿Puedo transmitir desde mi celular?', 'a' => 'Sí. Apps como Larix Broadcaster o Streamlabs OBS Mobile permiten emitir RTMP desde un celular Android o iPhone. La calidad es aceptable para transmisiones en vivo de eventos. Para emisión 24/7 es preferible un computador dedicado por estabilidad.'],
        ['q' => '¿Cuánto tarda la configuración inicial?', 'a' => 'Si ya tienes OBS instalado y tu servidor contratado, la configuración toma 15 minutos: pegar la URL del servidor en OBS, copiar la clave de stream, elegir la resolución y el bitrate. Estarás al aire inmediatamente.'],
        ['q' => '¿Necesito una IP fija o dominio propio?', 'a' => 'No. Cloudstream te entrega una URL pública (ejemplo: stream.midominio.com) y una clave de stream. Tu audiencia entra a esa URL y ve tu señal. No necesitas configurar DNS ni IP fija.'],
    ];
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'blog', 'data' => ['title' => $meta['title'], 'description' => $meta['description'], 'date_published' => '2026-08-24', 'date_modified' => '2026-08-24', 'faqs' => $faqs]]"
>
    <article class="max-w-3xl mx-auto px-4 sm:px-6 pt-12 pb-16 prose-public">
        <div class="text-xs text-slate-500 mb-3">24 de agosto de 2026 · 7 min de lectura</div>
        <h1>Cómo transmitir un canal de TV por Internet en Colombia — Guía paso a paso 2026</h1>

        <figure class="my-6">
            <img src="{{ url('/images/blog/como-transmitir-canal-tv-internet.jpg') }}" alt="Ilustración del paso a paso para transmitir un canal de TV por Internet en Colombia: configurar servidor, OBS, y emitir en vivo" width="1200" height="600" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">De cero a al aire en menos de 24 horas con Cloudstream.</figcaption>
        </figure>

        <p>Transmitir un canal de TV por Internet en Colombia en 2026 es técnicamente más simple de lo que parece. Con un computador con OBS Studio, una cámara y un servidor streaming profesional, puedes estar al aire en menos de una hora. Esta guía te lleva paso a paso desde cero.</p>

        <h2>Paso 1: Define tu modelo de emisión</h2>

        <p>Antes de instalar software o contratar un servidor, define qué tipo de canal vas a tener. Las opciones principales son:</p>

        <ul>
            <li><strong>Canal en vivo 24/7</strong>: señal continua, típica de canales de TV, emisoras o iglesias que emiten todo el día.</li>
            <li><strong>Eventos puntuales</strong>: bodas, conciertos, conferencias, partidos. Señal solo durante el evento.</li>
            <li><strong>Programación pre-grabada</strong>: contenido bajo demanda o reproducción automática de videos según horario.</li>
            <li><strong>Mixto</strong>: programación pre-grabada con bloques en vivo intercalados (típico de canales regionales).</li>
        </ul>

        <p>El modelo determina qué servidor necesitas. Cloudstream cubre los cuatro.</p>

        <figure class="my-8">
            <img src="{{ url('/images/blog/diagram-server-arch.jpg') }}" alt="Diagrama de la arquitectura del servidor de streaming Cloudstream: origen con OBS, ingest RTMP, datacenter americano, y distribución a navegadores, móviles y Smart TVs" width="1200" height="500" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">Arquitectura completa: tu estudio → ingest → datacenter → televidentes en cualquier dispositivo.</figcaption>
        </figure>

        <h2>Paso 2: Contrata un servidor de streaming</h2>

        <p>El servidor streaming es la pieza central: recibe la señal de tu estudio (ingest) y la entrega a tu audiencia (distribución). En Colombia tienes varias opciones:</p>

        <ul>
            <li><strong>Cloudstream</strong>: desde $75.000 COP/mes con datacenter americano, factura electrónica y soporte en español.</li>
            <li><strong>Dacast, Wowza, Vimeo</strong>: plataformas internacionales desde $35 USD/mes, facturación en dólares.</li>
            <li><strong>YouTube Live / Facebook Live</strong>: gratis, pero sin control sobre monetización ni programación.</li>
        </ul>

        <p>Para empezar te recomendamos Cloudstream Plan 1 ($75.000/mes) si vas a emitir desde tu propio equipo, o Plan 2 ($125.000/mes) si quieres emisión desde la nube con programador de contenido.</p>

        <h2>Paso 3: Configura tu estudio de emisión</h2>

        <p>El estudio es el lugar desde donde produces la señal. Para un canal pequeño basta con:</p>

        <ul>
            <li><strong>Computador</strong>: mínimo 8 GB de RAM, procesador i5 o equivalente, disco SSD. No necesitas tarjeta de captura si usas cámara USB o webcam.</li>
            <li><strong>Cámara</strong>: una webcam Logitech C920 ($200.000 COP) sirve para empezar. Para producción seria, una cámara mirrorless o una PTZ.</li>
            <li><strong>Micrófono</strong>: un micrófono de condensador USB como el Blue Yeti ($400.000 COP) o un lavalier para presentadores.</li>
            <li><strong>Iluminación</strong>: dos paneles LED suaves para eliminar sombras duras en el rostro.</li>
            <li><strong>Software OBS Studio</strong>: gratuito y open source. Funciona en Windows, Mac y Linux.</li>
        </ul>

        <h2>Paso 4: Instala y configura OBS Studio</h2>

        <figure class="my-6 not-prose">
            <img src="{{ url('/images/tools/obs-studio.jpg') }}" alt="Sitio oficial de OBS Studio, software open source gratuito para transmisión en vivo con soporte para RTMP, HLS, SRT y WebRTC" width="1200" height="600" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">OBS Studio — el software gratuito más usado para transmisión en vivo en el mundo.</figcaption>
        </figure>

        <p>OBS Studio es el software de emisión más usado del mundo. Descárgalo gratis desde obsproject.com y sigue estos pasos:</p>

        <ol>
            <li>Abre OBS y ve a <strong>Configuración → Emisión</strong>.</li>
            <li>Selecciona <strong>Personalizado</strong> como servicio.</li>
            <li>Pega la URL del servidor que te entregó Cloudstream (ejemplo: rtmp://stream.midominio.com/live).</li>
            <li>Pega la clave de stream que te llegó por email.</li>
            <li>En <strong>Configuración → Video</strong>, elige la resolución base (1920x1080) y la de salida (1280x720).</li>
            <li>En <strong>Configuración → Salida</strong>, pon bitrate de video en 1000-2000 Kbps y bitrate de audio en 128 Kbps.</li>
            <li>Agrega fuentes: cámara, micrófono, logo, gráficos.</li>
            <li>Haz clic en <strong>Iniciar transmisión</strong>. Estás al aire.</li>
        </ol>

        <h2>Paso 5: Configura el programador de contenido</h2>

        <p>Si elegiste el Plan 2 de Cloudstream, ahora puedes configurar tu parrilla de programación. El programador organiza qué contenido se emite en qué horario, todos los días:</p>

        <ul>
            <li><strong>Bloques de contenido</strong>: sube videos o define bloques en vivo para cada franja horaria.</li>
            <li><strong>Cuñas publicitarias</strong>: define en qué momentos se reproducen los anuncios de tus clientes.</li>
            <li><strong>Logo y barras</strong>: superpón tu marca sobre el contenido.</li>
            <li><strong>Repetición automática</strong>: el sistema repite tu programación en bucle sin que tengas que intervenir.</li>
        </ul>

        <h2>Paso 6: Comparte tu señal con tu audiencia</h2>

        <p>Tu audiencia puede ver tu señal de tres formas:</p>

        <ol>
            <li><strong>Web propia</strong>: incrusta el reproductor en tu página web con un iframe.</li>
            <li><strong>Redes sociales</strong>: si tienes el Plan 3 de restream, tu señal se retransmite automáticamente a Facebook, YouTube y TikTok.</li>
            <li><strong>Apps móviles</strong>: muchas Smart TVs y celulares reproducen HLS directamente. Para apps nativas, tu audiencia puede usar reproductores como VLC.</li>
        </ol>

        <h2>Paso 7: Monitorea y mejora</h2>

        <p>Una vez al aire, lo importante es medir y mejorar. Revisa semanalmente:</p>

        <ul>
            <li><strong>Calidad de la señal</strong>: frames por segundo, bitrate, errores de conexión en tu panel de Cloudstream.</li>
            <li><strong>Audiencia</strong>: cuántos televidentes simultáneos, desde qué ciudades, qué dispositivos.</li>
            <li><strong>Retención</strong>: cuánto tiempo permanecen viendo. Si la gente se va a los 5 minutos, algo en tu contenido no está funcionando.</li>
            <li><strong>Ingresos</strong>: si vendes pauta, cuántas cuñas se emitieron y cuánto facturar a cada anunciante.</li>
        </ul>

        <h2>Errores comunes que debes evitar</h2>

        <ul>
            <li><strong>Subir la señal por WiFi</strong>: usa siempre cable Ethernet. El WiFi introduce fluctuaciones que afectan la calidad.</li>
            <li><strong>No probar antes del evento</strong>: haz un ensayo 24 horas antes con la misma configuración que usarás en producción.</li>
            <li><strong>Ignorar el bitrate</strong>: subir un bitrate que no puedes mantener genera pausas y "buffering" para los televidentes.</li>
            <li><strong>No tener plan B</strong>: si tu computador falla, ¿qué haces? Ten un celular con Larix como respaldo.</li>
        </ul>

        <h2>Plan B: emitir desde tu celular</h2>

        <figure class="my-6 not-prose">
            <img src="{{ url('/images/tools/larix.jpg') }}" alt="Sitio oficial de Larix Broadcaster, aplicación móvil para Android y iPhone que permite emitir RTMP y SRT en vivo desde el celular" width="1200" height="600" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">Larix Broadcaster — app móvil gratuita para emitir RTMP/SRT desde Android y iOS.</figcaption>
        </figure>

        <p>Si necesitas emitir desde exteriores o como respaldo de emergencia, existen apps móviles profesionales que soportan RTMP y SRT con la misma calidad que OBS Studio en un computador. Las dos más usadas son:</p>

        <ul>
            <li><strong>Larix Broadcaster</strong> (Softvelum) — para Android e iOS, soporte para RTMP, SRT, RTSP y NDI; calidad hasta 1080p con HEVC y AAC.</li>
            <li><strong>Streamlabs OBS Mobile</strong> — fork móvil del popular OBS Studio, con interfaz amigable para streamers, alertas y overlays integrados.</li>
        </ul>

        <figure class="my-6 not-prose">
            <img src="{{ url('/images/tools/streamlabs.jpg') }}" alt="Sitio oficial de Streamlabs, plataforma para streamers en Twitch, YouTube y Facebook con alertas y overlays integrados para transmisión en vivo" width="1200" height="600" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">Streamlabs — plataforma popular entre streamers en redes sociales.</figcaption>
        </figure>

        <p>Configurar estas apps toma menos de 5 minutos: pegas la URL RTMP del servidor Cloudstream, la clave de stream, y empiezas a emitir. La calidad es suficiente para transmisiones en vivo de eventos y reportería en campo.</p>

        <p>Siguiendo estos pasos puedes estar transmitiendo tu canal en menos de 24 horas. Si necesitas ayuda, el equipo de Cloudstream te acompaña en la configuración inicial sin costo adicional.</p>

        <div class="mt-12 glass-card rounded-2xl p-8 text-center not-prose">
            <h2 class="text-2xl font-bold title-grad mb-3">Configuramos tu señal sin costo</h2>
            <p class="text-slate-400 mb-6">Contrata Cloudstream y un técnico te ayuda a configurar OBS y salir al aire en menos de 1 hora.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero contratar el plan Señal Streaming de Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">Contratar por WhatsApp</a>
                <a href="{{ url('/servidor-rtmp-colombia') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Ver servidor RTMP</a>
            </div>
        </div>
    </article>
</x-public-layout>
