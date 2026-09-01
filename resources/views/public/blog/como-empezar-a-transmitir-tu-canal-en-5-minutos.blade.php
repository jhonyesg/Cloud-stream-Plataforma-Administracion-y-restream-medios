@php
    $wa = preg_replace('/^\+/', '', config('seo.company.phone_e164'));
    $faqs = [
        ['q' => '¿Realmente puedo transmitir en 5 minutos?', 'a' => 'Sí, si ya tienes OBS instalado, una cámara conectada y el servidor de Cloudstream contratado. La configuración inicial toma entre 5 y 10 minutos: pegar URL, pegar clave, elegir resolución. La primera vez puede tomar 15 minutos si descargas OBS. Después de eso, cada transmisión toma literalmente 30 segundos (abrir OBS, hacer clic en "Iniciar transmisión").'],
        ['q' => '¿Qué necesito para empezar?', 'a' => 'Tres cosas: 1) Computador con Windows, Mac o Linux + OBS Studio (gratis). 2) Cámara (webcam, DSLR, o celular con app de cámara). 3) Servidor streaming contratado con Cloudstream. Si tienes todo eso, los 5 minutos son reales.'],
        ['q' => '¿Funciona con internet lento?', 'a' => 'Para 5 minutos de configuración inicial, sí. Después, la calidad de la transmisión depende de tu conexión de subida. Si tienes menos de 2 Mbps de subida, emite a 480p con 600-800 Kbps de bitrate. Si tienes 5+ Mbps, puedes emitir a 720p con 1500 Kbps.'],
    ];
@endphp

<x-public-layout :title="$meta['title']" :description="$meta['description']" :canonical="$meta['canonical']" :schema="['type' => 'blog', 'data' => ['title' => $meta['title'], 'description' => $meta['description'], 'date_published' => '2026-08-24', 'date_modified' => '2026-08-24', 'faqs' => $faqs]]">
    <article class="max-w-3xl mx-auto px-4 sm:px-6 pt-12 pb-16 prose-public">
        <div class="text-xs text-slate-500 mb-3">24 de agosto de 2026 · 3 min de lectura</div>
        <h1>Cómo empezar a transmitir tu canal de TV en 5 minutos</h1>

        <figure class="my-6">
            <img src="{{ url('/images/blog/como-empezar-a-transmitir-tu-canal-en-5-minutos.jpg') }}" alt="Ilustración de un cronómetro y checklist para transmitir un canal de TV en vivo por Internet en solo 5 minutos con OBS y Cloudstream" width="1200" height="600" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">5 minutos literal: checklist express para salir al aire hoy mismo.</figcaption>
        </figure>

        <p>¿Necesitas transmitir un evento urgente y no tienes tiempo para una configuración larga? Esta guía express te lleva de cero a al aire en 5 minutos, asumiendo que ya tienes OBS Studio instalado. Si no lo tienes, suma 10 minutos más para la descarga.</p>

        <h2>Antes de empezar: tu checklist de 30 segundos</h2>

        <ul>
            <li>✅ Computador con cámara y micrófono (puede ser laptop con webcam).</li>
            <li>✅ OBS Studio instalado y abierto.</li>
            <li>✅ Plan Cloudstream activo (recibiste un email con URL y clave de stream).</li>
            <li>✅ Internet con mínimo 2 Mbps de subida estables.</li>
        </ul>

        <figure class="my-6 not-prose">
            <img src="{{ url('/images/tools/obs-studio.jpg') }}" alt="Sitio oficial de OBS Studio, el software open source gratuito más usado del mundo para emisión en vivo, compatible con RTMP" width="1200" height="600" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">OBS Studio — la herramienta open source gratuita que vamos a usar.</figcaption>
        </figure>

        <h2>Minuto 1: configurar el servidor en OBS</h2>

        <ol>
            <li>En OBS, ve a <strong>Configuración → Emisión</strong>.</li>
            <li>En "Servicio", selecciona <strong>Personalizado</strong>.</li>
            <li>En "Servidor", pega la URL que te dio Cloudstream (formato: <code>rtmp://stream.tudominio.com/live</code>).</li>
            <li>En "Clave de transmisión", pega la clave secreta que te llegó por email.</li>
            <li>Haz clic en <strong>Aceptar</strong>.</li>
        </ol>

        <h2>Minuto 2: configurar resolución y bitrate</h2>

        <ol>
            <li>En OBS, ve a <strong>Configuración → Video</strong>.</li>
            <li>Resolución base: <strong>1920x1080</strong> (o la nativa de tu cámara).</li>
            <li>Resolución de salida: <strong>1280x720</strong> (HD listo para TVs).</li>
            <li>FPS: <strong>30</strong> (suficiente para TV; usa 60 solo si emites deportes).</li>
            <li>En <strong>Configuración → Salida</strong>, modo <strong>Avanzado</strong> → pestaña Emisión.</li>
            <li>Bitrate de video: <strong>1500</strong> Kbps.</li>
            <li>Bitrate de audio: <strong>128</strong> Kbps.</li>
            <li>Preset del codificador: si tienes tarjeta NVIDIA, usa <strong>NVENC</strong>. Si no, <strong>x264</strong> con preset <code>veryfast</code>.</li>
        </ol>

        <h2>Minuto 3: agregar la cámara como fuente</h2>

        <ol>
            <li>En el panel inferior de OBS, en "Fuentes", haz clic en <strong>+</strong>.</li>
            <li>Selecciona <strong>Dispositivo de captura de video</strong>.</li>
            <li>Asigna un nombre ("Cámara principal").</li>
            <li>En "Dispositivo", elige tu cámara de la lista.</li>
            <li>Haz clic en <strong>Aceptar</strong>. La cámara aparece en el preview.</li>
        </ol>

        <h2>Minuto 4: configurar el audio</h2>

        <ol>
            <li>En "Fuentes", haz clic en <strong>+</strong> nuevamente.</li>
            <li>Selecciona <strong>Captura de entrada de audio</strong>.</li>
            <li>Elige tu micrófono.</li>
            <li>En el panel "Mezclador de audio" a la derecha, ajusta el volumen del micrófono a un nivel saludable (la barra en verde, sin llegar a rojo).</li>
        </ol>

        <p>Si vas a incluir música de fondo, agrega otra fuente de audio (captura de salida de audio del sistema).</p>

        <h2>Minuto 5: salir al aire</h2>

        <ol>
            <li>Verifica en el preview de OBS que la cámara y el audio se ven y se escuchan correctamente.</li>
            <li>En el panel derecho de OBS, haz clic en <strong>Iniciar transmisión</strong>.</li>
            <li>Espera 3 segundos mientras OBS conecta con el servidor.</li>
            <li>En el indicador "Tiempo activo" empieza a contar: <strong>estás al aire</strong>.</li>
            <li>Comparte la URL pública de tu canal con tu audiencia (te la da Cloudstream).</li>
        </ol>

        <h2>¡Listo! Estás transmitiendo</h2>

        <p>En serio, esos son los 5 minutos. La configuración es tan simple porque:</p>

        <ul>
            <li>Cloudstream ya tiene el servidor configurado y listo para recibir tu señal.</li>
            <li>El protocolo RTMP es estándar: solo necesitas URL + clave.</li>
            <li>OBS es plug-and-play con cualquier cámara USB o built-in.</li>
        </ul>

        <h2>Si algo no funciona</h2>

        <p>Troubleshooting rápido:</p>

        <ul>
            <li><strong>"No se puede conectar al servidor"</strong>: verifica que la URL y la clave estén bien pegadas (sin espacios al inicio o final).</li>
            <li><strong>Video se ve pero audio no</strong>: revisa el mezclador de audio de OBS. La barra del micrófono debe estar en verde.</li>
            <li><strong>Video entrecortado</strong>: baja el bitrate a 800 Kbps o cambia de x264 a NVENC si tienes tarjeta NVIDIA.</li>
            <li><strong>Televidentes ven "loading" permanente</strong>: verifica que la URL pública esté bien escrita. Prueba en otro dispositivo o red.</li>
        </ul>

        <p>Si nada funciona, escríbenos por WhatsApp al +57 312 408 2557 y un técnico te ayuda en menos de 30 minutos.</p>

        <h2>Después de los 5 minutos: qué puedes agregar</h2>

        <p>Una vez que estés cómodo con la configuración básica, puedes mejorar tu transmisión:</p>

        <ul>
            <li><strong>Overlays</strong>: agrega tu logo, indicadores de "EN VIVO", cintillos de texto.</li>
            <li><strong>Múltiples escenas</strong>: una para el presentador solo, otra para pantalla compartida, otra para videos.</li>
            <li><strong>Restream a redes</strong>: si contratas Cloudstream Plan 3, tu señal se replica a Facebook, YouTube y TikTok automáticamente.</li>
            <li><strong>Programador de contenido</strong>: si contratas Plan 2, puedes programar videos para cuando no estés transmitiendo en vivo.</li>
        </ul>

        <p>Pero todo eso es opcional. Para tu primera transmisión, los 5 minutos de esta guía son suficientes.</p>

        <div class="mt-12 glass-card rounded-2xl p-8 text-center not-prose">
            <h2 class="text-2xl font-bold title-grad mb-3">Empieza en 5 minutos</h2>
            <p class="text-slate-400 mb-6">Cloudstream Plan 1 desde $75.000/mes. Te entregamos URL y clave, tú configuras OBS.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero contratar el plan Señal Streaming de Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">Contratar ahora</a>
                <a href="{{ url('/servidor-rtmp-colombia') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Ver servidor RTMP</a>
            </div>
        </div>
    </article>
</x-public-layout>
