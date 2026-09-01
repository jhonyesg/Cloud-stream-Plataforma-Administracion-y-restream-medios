@php
    $wa = preg_replace('/^\+/', '', config('seo.company.phone_e164'));
    $faqs = [
        ['q' => '¿Qué equipo mínimo necesita una iglesia para transmitir?', 'a' => 'Para empezar basta con: una cámara (puede ser un celular moderno o una webcam Logitech C920), un trípode, un micrófono (lavalier o de mesa), un computador con OBS Studio, y conexión a internet con mínimo 5 Mbps de subida. Con Cloudstream el servidor lo proveemos nosotros.'],
        ['q' => '¿Cuánto cuesta transmitir misas por Internet en Colombia?', 'a' => 'Con Cloudstream desde $75.000/mes tienes servidor profesional con soporte local. Si sumas equipo (cámara, trípode, micrófono, computador), la inversión inicial puede estar entre $1.500.000 y $4.000.000 COP. A partir del segundo año, el costo recurrente es solo el plan mensual.'],
        ['q' => '¿Puedo transmitir desde el templo con internet lento?', 'a' => 'Sí, con ajustes. Si tu conexión de subida es de 1-2 Mbps, emite a 480p (calidad estándar) con bitrate de 600-800 Kbps. Para mejorar estabilidad, usa conexión por cable (no WiFi) y evita compartir el internet con otros usuarios durante la transmisión.'],
    ];
@endphp

<x-public-layout :title="$meta['title']" :description="$meta['description']" :canonical="$meta['canonical']" :schema="['type' => 'blog', 'data' => ['title' => $meta['title'], 'description' => $meta['description'], 'date_published' => '2026-08-24', 'date_modified' => '2026-08-24', 'faqs' => $faqs]]">
    <article class="max-w-3xl mx-auto px-4 sm:px-6 pt-12 pb-16 prose-public">
        <div class="text-xs text-slate-500 mb-3">24 de agosto de 2026 · 6 min de lectura</div>
        <h1>Streaming para iglesias: cómo transmitir misas y eventos en vivo</h1>

        <figure class="my-6">
            <img src="{{ url('/images/blog/streaming-para-iglesias-como-transmitir-misas-y-eventos-en-vivo.jpg') }}" alt="Ilustración de una iglesia transmitiendo misa en vivo por Internet con cámara, OBS y servidor de streaming en datacenter americano" width="1200" height="600" loading="lazy" class="w-full h-auto rounded-2xl">
            <figcaption class="text-xs text-slate-500 mt-2 text-center">Lleva tu iglesia a Internet con señal estable 24/7 desde datacenter americano.</figcaption>
        </figure>

        <p>La pandemia aceleró la digitalización de las iglesias, y muchas comunidades en Colombia ya esperan poder seguir la misa desde casa cuando no pueden asistir presencialmente. Transmitir una misa o un culto por Internet es técnicamente accesible y no requiere una inversión enorme. Esta guía cubre todo lo que necesitas saber.</p>

        <h2>¿Por qué transmitir tu iglesia?</h2>

        <p>Las razones más comunes que dan las iglesias colombianas para transmitir son:</p>

        <ul>
            <li><strong>Alcance</strong>: feligreses enfermos, adultos mayores o personas en otras ciudades pueden seguir la celebración.</li>
            <li><strong>Inclusión</strong>: personas con discapacidad o movilidad reducida participan desde casa.</li>
            <li><strong>Difusión</strong>: visitantes potenciales descubren tu iglesia antes de asistir presencialmente.</li>
            <li><strong>Comunidad</strong>: los jóvenes están en el celular; la señal en vivo es su formato.</li>
            <li><strong>Archivo</strong>: las celebraciones quedan grabadas para quienes no pudieron ver en vivo.</li>
        </ul>

        <h2>Equipo necesario para empezar</h2>

        <p>Para una iglesia pequeña o mediana, la inversión inicial puede ser modesta:</p>

        <h3>Equipo básico ($1.500.000 – $2.500.000 COP)</h3>

        <ul>
            <li><strong>Cámara</strong>: Logitech C920 o similar ($200.000 COP). Calidad 1080p, ideal para un altar o púlpito fijo.</li>
            <li><strong>Trípode</strong>: cualquier trípode fotográfico estándar ($80.000 COP).</li>
            <li><strong>Micrófono</strong>: de solapa (lavalier) inalámbrico para el celebrante ($250.000 COP).</li>
            <li><strong>Computador</strong>: usado o refurbished con 8 GB de RAM y disco SSD ($800.000 COP).</li>
            <li><strong>Cable HDMI</strong>: si conectas una cámara mejor al computador ($50.000 COP).</li>
            <li><strong>Internet</strong>: mínimo 5 Mbps de subida. Un plan de fibra óptica hogareño en Colombia basta.</li>
        </ul>

        <h3>Equipo intermedio ($3.000.000 – $6.000.000 COP)</h3>

        <ul>
            <li><strong>Cámara</strong>: Canon M50 o Sony a6400 con salida HDMI limpia ($2.500.000 COP).</li>
            <li><strong>Capturadora</strong>: Elgato Cam Link 4K para conectar la cámara por USB ($400.000 COP).</li>
            <li><strong>Mezclador de audio</strong>: para manejar múltiples micrófonos del coro, celebrantes, lecturas ($600.000 COP).</li>
            <li><strong>Iluminación</strong>: dos paneles LED suaves ($400.000 COP).</li>
        </ul>

        <h2>Software: OBS Studio es suficiente</h2>

        <p>Para una iglesia no necesitas vMix ni Wirecast. OBS Studio (gratis) cubre todo lo que necesitas:</p>

        <ul>
            <li><strong>Captura de cámara</strong>: agrega la cámara como fuente de video.</li>
            <li><strong>Captura de audio</strong>: el micrófono entra por la entrada de audio del computador.</li>
            <li><strong>Escenas</strong>: crea escenas para cada momento (misa, prédica, comunión, eventos especiales).</li>
            <li><strong>Overlays</strong>: agrega el logo de la iglesia, textos con la lectura del día, indicadores de "EN VIVO".</li>
            <li><strong>Grabación local</strong>: graba cada celebración para archivar o compartir después.</li>
        </ul>

        <h2>El servidor: Cloudstream como opción local</h2>

        <p>El servidor streaming es el componente que recibe tu señal de OBS y la entrega a tu audiencia. Cloudstream es la opción colombiana más práctica:</p>

        <ul>
            <li>Plan Señal Streaming: $75.000/mes (si emites desde tu propio equipo con OBS).</li>
            <li>Plan Plataforma Completa: $125.000/mes (si quieres emisión desde la nube con programador).</li>
            <li>Soporte en español por WhatsApp.</li>
            <li>Factura electrónica válida para la DIAN.</li>
        </ul>

        <h2>Cómo organizar la programación semanal</h2>

        <p>Si tu iglesia tiene celebraciones fijas, configura tu programador con:</p>

        <ul>
            <li><strong>Misa dominical</strong>: por ejemplo, sábado 6:00 PM y domingo 10:00 AM.</li>
            <li><strong>Misa diaria</strong>: si aplica, a una hora fija.</li>
            <li><strong>Cultos de semana</strong>: reuniones de oración, grupos juveniles, etc.</li>
            <li><strong>Eventos especiales</strong>: bodas, bautismos, primeras comuniones, retiros.</li>
        </ul>

        <p>El programador de Cloudstream ejecuta la parrilla automáticamente. Tu equipo solo configura la primera vez.</p>

        <h2>Cuñas y anuncios durante la transmisión</h2>

        <p>Durante la celebración puedes insertar:</p>

        <ul>
            <li><strong>Información de la parroquia</strong>: horarios de confesiones, grupos de oración, eventos próximos.</li>
            <li><strong>Donaciones</strong>: cuenta bancaria, Nequi, Daviplata o tu plataforma de ofrendas en línea.</li>
            <li><strong>Avisos comunitarios</strong>: defunciones, celebraciones especiales, actividades.</li>
            <li><strong>Himnos o música</strong>: pregrabados para ambientar momentos de silencio.</li>
        </ul>

        <h2>Restream a Facebook y YouTube</h2>

        <p>Si quieres que tu transmisión también aparezca en la página de Facebook de la parroquia o en un canal de YouTube, activa el restream en Cloudstream Plan 3 ($135.000/mes). La misma señal que sale al aire en tu página web se replica simultáneamente a esas redes.</p>

        <h2>Protección para niños y familias</h2>

        <p>Algunas familias prefieren que sus hijos vean la misa desde casa sin exposición a comentarios de YouTube o Facebook. Transmitir en tu propia página web (sin restream) evita ese problema. Si haces restream, modera los comentarios o desactívalos.</p>

        <h2>Costos totales para el primer año</h2>

        <p>Para una iglesia pequeña con equipo básico:</p>

        <ul>
            <li><strong>Inversión inicial en equipo</strong>: $1.500.000 – $2.500.000 COP (una sola vez).</li>
            <li><strong>Plan Cloudstream</strong>: $75.000 – $125.000 COP/mes = $900.000 – $1.500.000 COP/año.</li>
            <li><strong>Total primer año</strong>: entre $2.400.000 y $4.000.000 COP.</li>
        </ul>

        <p>Es una inversión accesible para la mayoría de parroquias, especialmente si se compara con el alcance que se logra.</p>

        <div class="mt-12 glass-card rounded-2xl p-8 text-center not-prose">
            <h2 class="text-2xl font-bold title-grad mb-3">Lleva tu iglesia a Internet</h2>
            <p class="text-slate-400 mb-6">Cloudstream te ayuda con la configuración inicial sin costo. Tu comunidad conectada contigo.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero información sobre el plan para iglesias de Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">Hablar por WhatsApp</a>
                <a href="{{ url('/streaming-para-iglesias-colombia') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Ver plan para iglesias</a>
            </div>
        </div>
    </article>
</x-public-layout>
