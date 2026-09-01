@php
    $company = config('seo.company');
    $wa = preg_replace('/^\+/', '', $company['phone_e164']);
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'about', 'data' => []]"
>
    <section class="max-w-3xl mx-auto px-4 sm:px-6 pt-16 pb-12">
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 text-xs font-medium text-blue-200 bg-blue-500/10 border border-blue-500/20 rounded-full px-4 py-1.5 mb-6">
                <span>Sobre nosotros</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight title-grad leading-tight mb-6">
                Conoce a {{ $company['name'] }}
            </h1>
            <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto">
                La empresa colombiana detrás de Cloudstream, el servicio de streaming de TV por Internet que opera desde un datacenter americano con uptime del 99%.
            </p>
        </div>

        <div class="prose-public">
            <h2>Quiénes somos</h2>

            <p>{{ $company['name'] }} es una empresa legalmente constituida en Colombia, dedicada a proveer servicios de infraestructura tecnológica para medios de comunicación. Operamos Cloudstream, nuestra plataforma de streaming de señal de TV por Internet, que actualmente da servicio a canales de TV regionales, emisoras online, iglesias, universidades y productoras de contenido en todo el país.</p>

            <p>Nuestro propósito es democratizar el acceso a infraestructura profesional de streaming en Colombia. Antes, montar un canal de TV por Internet requería invertir millones de pesos en equipos, servidores y personal técnico. Hoy, con Cloudstream, un canal pequeño puede estar al aire en 24 horas por menos de $100.000 pesos al mes.</p>

            <h2>Nuestra infraestructura</h2>

            <p>Nuestra plataforma opera desde un <strong>datacenter americano</strong> con certificación Tier III, redundancia eléctrica N+1, generadores diésel de respaldo, múltiples proveedores de conectividad y monitoreo 24/7. El SLA de uptime es del 99%, lo que se traduce en máximo 7 horas de caída al año.</p>

            <p>Para los servicios de emisión desde la nube, tenemos además una red de almacenamiento redundante con respaldo diario automático, de modo que tu contenido siempre esté disponible.</p>

            <h2>Datos de la empresa</h2>

            <ul>
                <li><strong>Razón social</strong>: {{ $company['name'] }}</li>
                <li><strong>NIT</strong>: {{ $company['nit'] }}</li>
                <li><strong>Domicilio</strong>: {{ $company['city'] }}, {{ $company['country'] }}</li>
                <li><strong>Actividad económica</strong>: Servicios de tecnología y comunicaciones</li>
                <li><strong>Facturación</strong>: Factura electrónica vigente y cuenta de cobro</li>
            </ul>

            <h2>Nuestro equipo</h2>

            <p>Somos un equipo multidisciplinary de ingenieros de sistemas, administradores de servidores, desarrolladores de software, diseñadores y especialistas en emisión audiovisual. Combinamos experiencia en infraestructura cloud (AWS, DigitalOcean, Hetzner) con conocimiento profundo del mercado audiovisual colombiano.</p>

            <p>Hablamos español colombiano, entendemos los retos de operar un canal de TV en una ciudad mediana, y sabemos lo que significa montar una iglesia con cámara y un equipo de emisión. Por eso nuestro soporte es cercano y directo, no un call center genérico.</p>

            <h2>Compromiso con nuestros clientes</h2>

            <p>Nuestra promesa operativa es simple:</p>

            <ul>
                <li><strong>Uptime del 99%</strong>: tu señal al aire, sin sorpresas.</li>
                <li><strong>Soporte en español</strong>: por WhatsApp, email y teléfono, en horario comercial colombiano.</li>
                <li><strong>Facturación local</strong>: factura electrónica válida para la DIAN.</li>
                <li><strong>Sin permanencia</strong>: mes a mes, sin cláusulas leoninas.</li>
                <li><strong>Mejora continua</strong>: nuevas funciones y actualizaciones frecuentes sin costo adicional.</li>
            </ul>

            <h2>Contacto</h2>

            <p>Si quieres hablar con nuestro equipo comercial, técnico o administrativo:</p>

            <ul>
                <li><strong>WhatsApp</strong>: <a href="https://wa.me/{{ $wa }}">{{ $company['phone_display'] }}</a></li>
                <li><strong>Teléfono</strong>: <a href="tel:{{ $company['phone_e164'] }}">{{ $company['phone_display'] }}</a></li>
                <li><strong>Email</strong>: <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a></li>
                <li><strong>Ubicación</strong>: {{ $company['city'] }}, {{ $company['country'] }}</li>
            </ul>
        </div>

        <div class="mt-12 glass-card rounded-2xl p-8 text-center">
            <h2 class="text-2xl font-bold title-grad mb-3">¿Listo para empezar?</h2>
            <p class="text-slate-400 mb-6">Contrata un plan de Cloudstream y emite tu canal en menos de 24 horas.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero información sobre los planes de Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">Hablar por WhatsApp</a>
                <a href="{{ url('/#planes') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Ver planes</a>
            </div>
        </div>
    </section>
</x-public-layout>
