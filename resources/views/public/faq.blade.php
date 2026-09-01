@php
    $wa = preg_replace('/^\+/', '', config('seo.company.phone_e164'));
    $faqs = [
        ['q' => '¿Cuánto cuesta el plan Señal Streaming?', 'a' => '$75.000 COP/mes. Incluye servidor en datacenter americano, 1 señal streaming 24/7, codecs H264, MP3 y AAC+, formato 320p a 720p, hasta 300 televidentes, protocolos RTMP + HLS y 720 horas de emisión al mes. Tú emites desde tu propio equipo con OBS, vMix o Wirecast.'],
        ['q' => '¿Qué diferencia hay entre Señal Streaming y Plataforma Completa?', 'a' => 'En el plan Señal Streaming tú emites desde tu propio computador con OBS. En la Plataforma Completa ($125.000 COP/mes) la emisión se hace desde la nube: tú subes tus videos y configuras la parrilla, y el servidor reproduce automáticamente. La Plataforma Completa incluye además 30 GB de almacenamiento, programador de contenido, inserción de cuñas publicitarias, logo y respaldo diario.'],
        ['q' => '¿Puedo cambiar de plan en cualquier momento?', 'a' => 'Sí. Puedes subir o bajar de plan cuando quieras. Si subes, el nuevo precio se prorratea desde el día del cambio. Si bajas, el cambio aplica al inicio del siguiente ciclo de facturación. Sin cláusulas de permanencia.'],
        ['q' => '¿El servicio incluye factura electrónica?', 'a' => 'Sí. Media Clouding SAS emite factura electrónica vigente en Colombia (válida para la DIAN) y también cuenta de cobro si la prefieres. El NIT aparece en todos los comprobantes.'],
        ['q' => '¿Qué ancho de banda de internet necesito para emitir?', 'a' => 'Mínimo 2 Mbps de subida estables para 480p, recomendado 5 Mbps para 720p HD. Si emites desde un estudio con fibra óptica típica en Colombia (10-50 Mbps de bajada), verifica que la subida también sea alta — los planes hogareños en Colombia suelen tener 5-10 Mbps de subida.'],
        ['q' => '¿Cuántos televidentes pueden ver mi señal al mismo tiempo?', 'a' => 'El plan Señal Streaming soporta hasta 300 televidentes simultáneos. Para más audiencia, podemos configurar el plan con mayor capacidad bajo cotización. Si necesitas escalar a miles, te entregamos la URL para conectar un CDN (Cloudflare, Akamai, AWS CloudFront).'],
        ['q' => '¿Puedo transmitir a Facebook, YouTube y TikTok al mismo tiempo?', 'a' => 'Sí. El plan Plataforma + Restream ($135.000 a $164.000 COP/mes según número de destinos) retransmite tu señal simultáneamente a Facebook Live, YouTube Live, TikTok Live y un destino RTMP personalizado adicional. Incluye failover automático si alguna conexión falla.'],
        ['q' => '¿Cómo funciona el programador de contenido?', 'a' => 'Cargas tus videos en la plataforma, defines bloques por día y hora (por ejemplo: noticiero a las 7 AM, entretenimiento de 9 AM a 12 PM, etc.), y el sistema reproduce automáticamente la parrilla en bucle 24/7. Tú puedes intervenir en vivo en cualquier momento si quieres transmitir en directo.'],
        ['q' => '¿Cómo funcionan las cuñas publicitarias?', 'a' => 'Subes los archivos de audio o video de cada cuña, las asignas a un cliente y defines en qué bloques horarios se reproducen. El sistema las inserta automáticamente en los momentos que configures. Al final del mes puedes exportar un reporte con cuántas veces se emitió cada cuña.'],
        ['q' => '¿Puedo proteger mi señal con contraseña?', 'a' => 'Sí. El plan Plataforma Completa permite proteger el acceso con usuario y contraseña. Útil para señales privadas, contenido premium o transmisiones corporativas. También podemos integrar el login con el sistema de tu organización (LDAP, SAML, OAuth).'],
        ['q' => '¿Qué pasa si mi servidor se cae?', 'a' => 'Nuestro datacenter tiene SLA de uptime del 99% y redundancia automática entre nodos. Si un nodo falla, el tráfico se redirige a otro en menos de 30 segundos. Tu audiencia no nota la interrupción. Además, monitorizamos los servicios 24/7 y respondemos incidentes en menos de 15 minutos.'],
        ['q' => '¿Ofrecen soporte técnico en español?', 'a' => 'Sí, soporte directo en español por WhatsApp, email y teléfono en horario comercial (8 AM a 6 PM hora Colombia). Para emergencias técnicas fuera de horario, tenemos guardia 24/7 disponible para clientes con plan de restream o señales críticas.'],
        ['q' => '¿Puedo emitir desde un celular?', 'a' => 'Sí. Apps como Larix Broadcaster, Streamlabs OBS Mobile o la app de GoPro permiten emitir RTMP desde un celular Android o iPhone. Solo necesitas configurar la URL del servidor y la clave de stream que te entregamos. Útil para transmisiones en exteriores, reportería en campo y eventos en movimiento.'],
        ['q' => '¿Cuánto tarda la activación del servicio?', 'a' => 'Una vez confirmado el pago, activamos tu servicio en menos de 2 horas hábiles. Te enviamos por email la URL del servidor, la clave de stream y las instrucciones de configuración. Si contrataste el plan Plataforma Completa, te ayudamos a configurar la primera parrilla sin costo adicional.'],
        ['q' => '¿Tienen contrato de permanencia?', 'a' => 'No. Todos nuestros planes son mes a mes. Puedes cancelar en cualquier momento sin penalidad. Si pagas trimestral o anual, obtienes un descuento pero puedes solicitar reembolso proporcional por los meses no usados.'],
    ];
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'faq', 'data' => ['faqs' => $faqs]]"
>
    <section class="max-w-3xl mx-auto px-4 sm:px-6 pt-16 pb-12">
        <div class="text-center mb-12">
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight title-grad leading-tight mb-6">
                Preguntas frecuentes sobre streaming de TV en Colombia
            </h1>
            <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto">
                Resolvemos las dudas más comunes sobre planes, precios, protocolos RTMP y HLS, restream a redes sociales, programador de contenido y soporte técnico.
            </p>
        </div>

        <div class="space-y-3" x-data="{ open: 0 }">
            @foreach($faqs as $i => $faq)
                <details class="glass-card rounded-2xl overflow-hidden group" {{ $loop->first ? '' : '' }}>
                    <summary class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left cursor-pointer list-none">
                        <span class="font-semibold text-white text-sm sm:text-base">{{ $faq['q'] }}</span>
                        <span class="text-cyan-400 text-xl shrink-0 group-open:rotate-45 transition-transform">+</span>
                    </summary>
                    <div class="px-6 pb-5 text-sm text-slate-300">
                        {{ $faq['a'] }}
                    </div>
                </details>
            @endforeach
        </div>

        <div class="mt-16 glass-card rounded-2xl p-8 text-center">
            <h2 class="text-2xl font-bold title-grad mb-3">¿Tienes otra pregunta?</h2>
            <p class="text-slate-400 mb-6">Escríbenos por WhatsApp y te respondemos en menos de 30 minutos.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, tengo una pregunta sobre los planes de Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">Preguntar por WhatsApp</a>
                <a href="tel:{{ config('seo.company.phone_e164') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Llamar {{ config('seo.company.phone_display') }}</a>
            </div>
        </div>
    </section>
</x-public-layout>
