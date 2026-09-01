@php
    $home = collect(config('seo.public_urls'))->firstWhere('path', '/');
    $schemaData = [
        'title' => $home['title'] ?? 'Cloudstream',
        'description' => $home['description'] ?? '',
        'lastmod' => $home['lastmod'] ?? '2026-08-24',
    ];
    $plan1 = config('seo.plans.plan1');
    $plan2 = config('seo.plans.plan2');
    $plan3 = config('seo.plans.plan3');
    $wa = preg_replace('/^\+/', '', config('seo.company.phone_e164'));
@endphp

<x-public-layout
    :title="$home['title']"
    :description="$home['description']"
    :canonical="config('seo.site_url') . '/'"
    :schema="['type' => 'home', 'data' => $schemaData]"
>
    <!-- ====== HERO ====== -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 pb-20 text-center fade-up">
        <div class="inline-flex items-center gap-2 text-xs font-medium text-blue-200 bg-blue-500/10 border border-blue-500/20 rounded-full px-4 py-1.5 mb-6">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            Emisión 24/7 · Uptime 99% · Datacenter americano
        </div>
        <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight title-grad leading-tight mb-6">
            Transmite tu canal de TV en vivo<br>por Internet en Colombia
        </h1>
        <p class="text-lg text-slate-300 max-w-2xl mx-auto mb-10">
            Lleva tu señal de televisión a Internet con streaming profesional 24/7, programación
            automatizada, cuñas publicitarias y restream a Facebook, YouTube, TikTok y más.
            Sin montar infraestructura: nosotros emitimos desde un datacenter americano.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="#planes" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">Ver planes y precios</a>
            <a href="#servicios" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Conoce los servicios</a>
        </div>
    </section>

    <!-- ====== SERVICIOS ====== -->
    <section id="servicios" class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
        <div class="text-center mb-12">
            <h2 class="text-3xl sm:text-4xl font-bold title-grad mb-3">¿Qué incluye tu señal de TV en vivo?</h2>
            <p class="text-slate-400 max-w-2xl mx-auto">Todo lo necesario para que tu canal esté al aire, sin preocuparte por la infraestructura.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="glass-card rounded-2xl p-6">
                <div class="text-3xl mb-4">📡</div>
                <h3 class="text-lg font-semibold text-white mb-2">Señal Streaming 24/7</h3>
                <p class="text-sm text-slate-400">Transmisión continua sin interrupciones, lista para tu audiencia en cualquier momento.</p>
            </div>
            <div class="glass-card rounded-2xl p-6">
                <div class="text-3xl mb-4">🎛️</div>
                <h3 class="text-lg font-semibold text-white mb-2">Programador de contenido</h3>
                <p class="text-sm text-slate-400">Organiza tu parrilla de programación por días y horas con total control.</p>
            </div>
            <div class="glass-card rounded-2xl p-6">
                <div class="text-3xl mb-4">📢</div>
                <h3 class="text-lg font-semibold text-white mb-2">Cuñas publicitarias</h3>
                <p class="text-sm text-slate-400">Inserta pautas comerciales en tu programación de forma automática.</p>
            </div>
            <div class="glass-card rounded-2xl p-6">
                <div class="text-3xl mb-4">🖼️</div>
                <h3 class="text-lg font-semibold text-white mb-2">Logo y marca</h3>
                <p class="text-sm text-slate-400">Personaliza tu canal con tu logo y la identidad visual de tu marca.</p>
            </div>
            <div class="glass-card rounded-2xl p-6">
                <div class="text-3xl mb-4">🌐</div>
                <h3 class="text-lg font-semibold text-white mb-2">Datacenter americano</h3>
                <p class="text-sm text-slate-400">Infraestructura de primer nivel con uptime del 99% y respaldo diario de tu información.</p>
            </div>
            <div class="glass-card rounded-2xl p-6">
                <div class="text-3xl mb-4">🔁</div>
                <h3 class="text-lg font-semibold text-white mb-2">Restream a plataformas</h3>
                <p class="text-sm text-slate-400">Transmite simultáneamente a Facebook, YouTube, TikTok y RTMP personalizado.</p>
            </div>
        </div>
    </section>

    <!-- ====== VALOR ====== -->
    <section id="valor" class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
        <div class="text-center mb-12">
            <h2 class="text-3xl sm:text-4xl font-bold title-grad mb-3">¿Por qué emitir desde la nube?</h2>
            <p class="text-slate-400 max-w-2xl mx-auto">Deja de preocuparte por la infraestructura. Con Cloudstream tu señal se emite desde un datacenter americano y evitas los dolores de cabeza de montar tu propio sistema.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
            <div class="glass-card rounded-2xl p-6 text-center">
                <div class="text-3xl mb-3">💻</div>
                <h3 class="text-sm font-semibold text-white mb-1">Evita comprar equipos</h3>
                <p class="text-xs text-slate-400">No necesitas servidores ni equipos de transmisión costosos.</p>
            </div>
            <div class="glass-card rounded-2xl p-6 text-center">
                <div class="text-3xl mb-3">💾</div>
                <h3 class="text-sm font-semibold text-white mb-1">Sin desgaste de discos</h3>
                <p class="text-xs text-slate-400">Tus discos duros no se desgastan emitiendo 24/7.</p>
            </div>
            <div class="glass-card rounded-2xl p-6 text-center">
                <div class="text-3xl mb-3">⚡</div>
                <h3 class="text-sm font-semibold text-white mb-1">Ahorro eléctrico</h3>
                <p class="text-xs text-slate-400">No pagas la energía de equipos encendidos todo el día.</p>
            </div>
            <div class="glass-card rounded-2xl p-6 text-center">
                <div class="text-3xl mb-3">🌐</div>
                <h3 class="text-sm font-semibold text-white mb-1">Ahorro de internet</h3>
                <p class="text-xs text-slate-400">Tu conexión no se satura subiendo la señal constantemente.</p>
            </div>
            <div class="glass-card rounded-2xl p-6 text-center">
                <div class="text-3xl mb-3">🛡️</div>
                <h3 class="text-sm font-semibold text-white mb-1">Estabilidad garantizada</h3>
                <p class="text-xs text-slate-400">Uptime del 99% en datacenter americano, sin cortes por luz ni fallas.</p>
            </div>
        </div>
    </section>

    <!-- ====== PLATAFORMA (capturas) ====== -->
    <section id="plataforma" class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
        <div class="text-center mb-12">
            <h2 class="text-3xl sm:text-4xl font-bold title-grad mb-3">Así se ve la plataforma por dentro</h2>
            <p class="text-slate-400 max-w-2xl mx-auto">Un panel simple y completo para administrar tu canal, tu contenido y tu programación en un solo lugar.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="glass-card rounded-2xl overflow-hidden">
                <img src="{{ asset('images/demo/dashboard.jpg') }}" alt="Panel de inicio de la plataforma Cloudstream con resumen de canales, medios y métricas en vivo" loading="lazy" class="w-full h-auto">
                <p class="px-5 py-4 text-sm text-slate-300">Panel de inicio con el resumen de tus canales, medios y métricas en vivo.</p>
            </div>
            <div class="glass-card rounded-2xl overflow-hidden">
                <img src="{{ asset('images/demo/media.jpg') }}" alt="Biblioteca multimedia de la plataforma Cloudstream con videos, imágenes y cuñas publicitarias" loading="lazy" class="w-full h-auto">
                <p class="px-5 py-4 text-sm text-slate-300">Biblioteca multimedia para subir y organizar tus videos, imágenes y cuñas.</p>
            </div>
            <div class="glass-card rounded-2xl overflow-hidden">
                <img src="{{ asset('images/demo/scheduler.jpg') }}" alt="Programador de contenido de la plataforma Cloudstream con calendario mensual de emisión" loading="lazy" class="w-full h-auto">
                <p class="px-5 py-4 text-sm text-slate-300">Programador de contenido con calendario mensual para organizar tu parrilla.</p>
            </div>
            <div class="glass-card rounded-2xl overflow-hidden">
                <img src="{{ asset('images/demo/restream.jpg') }}" alt="Panel de restream de la plataforma Cloudstream para transmitir a plataformas externas" loading="lazy" class="w-full h-auto">
                <p class="px-5 py-4 text-sm text-slate-300">Restream a Facebook, YouTube, TikTok y destinos RTMP personalizados.</p>
            </div>
        </div>
    </section>

    <!-- ====== PLANES ====== -->
    <section id="planes" class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
        <div class="text-center mb-12">
            <h2 class="text-3xl sm:text-4xl font-bold title-grad mb-3">Planes y precios</h2>
            <p class="text-slate-400 max-w-2xl mx-auto">Elige el plan que mejor se adapte a tu canal. Sin permanencia, sin sorpresas.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">

            <!-- PLAN 1: Señal Streaming -->
            <div class="glass-card rounded-2xl p-8 flex flex-col">
                <h3 class="text-lg font-semibold text-white mb-1">{{ $plan1['name'] }}</h3>
                <p class="text-sm text-slate-400 mb-6">Tú pones el equipo (OBS, vMix o tu app) y nosotros el servidor.</p>
                <div class="mb-2">
                    <span class="text-4xl font-extrabold text-white">${{ number_format($plan1['price_cop'], 0, ',', '.') }}</span>
                    <span class="text-slate-400 text-sm">/mes</span>
                </div>
                <p class="text-xs text-slate-500 mb-6">
                    <span class="strike">${{ number_format($plan1['price_original_cop'], 0, ',', '.') }}</span>
                    <span class="text-emerald-400 font-semibold ml-1">{{ $plan1['discount_percent'] }}% de descuento</span>
                </p>
                <ul class="space-y-3 text-sm text-slate-300 mb-8 flex-1">
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Emite con OBS, vMix o tu aplicación</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Te damos el servidor al que debes emitir</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> 1 señal streaming 24/7</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Codecs H264, MP3, AAC+</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Formato 320p a 720p</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Calidad de emisión 1000 KBPS</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Protocolos RTMP + HLS</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Hasta 300 televidentes</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Señal con SSL</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> 720 horas de emisión</li>
                </ul>
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero contratar el plan ' . $plan1['name'] . ' ($' . number_format($plan1['price_cop'], 0, ',', '.') . '/mes)') }}" target="_blank" rel="noopener" class="btn-ghost text-center text-white font-semibold py-3 rounded-xl">Elegir plan</a>
            </div>

            <!-- PLAN 2: Plataforma Completa -->
            <div class="glass-card plan-popular rounded-2xl p-8 flex flex-col relative">
                <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-gradient-to-r from-blue-600 to-cyan-500 text-white text-xs font-bold px-4 py-1 rounded-full">MÁS POPULAR</span>
                <h3 class="text-lg font-semibold text-white mb-1">{{ $plan2['name'] }}</h3>
                <p class="text-sm text-slate-400 mb-6">Emisión desde la nube, sin depender de tu equipo.</p>
                <div class="mb-2">
                    <span class="text-4xl font-extrabold text-white">${{ number_format($plan2['price_cop'], 0, ',', '.') }}</span>
                    <span class="text-slate-400 text-sm">/mes</span>
                </div>
                <p class="text-xs text-slate-500 mb-6">
                    <span class="strike">${{ number_format($plan2['price_original_cop'], 0, ',', '.') }}</span>
                    <span class="text-emerald-400 font-semibold ml-1">{{ $plan2['discount_percent'] }}% de descuento</span>
                </p>
                <ul class="space-y-3 text-sm text-slate-300 mb-8 flex-1">
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Todo lo del plan Señal Streaming</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Emisión desde la nube, sin tu equipo</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> 30 GB de almacenamiento en la nube</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Sistema de emisión en la nube</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Programador de contenido</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Inserción de cuñas publicitarias</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Logo y personalización de marca</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Datacenter americano</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Uptime del 99%</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Respaldo diario de información</li>
                </ul>
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero contratar el plan ' . $plan2['name'] . ' ($' . number_format($plan2['price_cop'], 0, ',', '.') . '/mes)') }}" target="_blank" rel="noopener" class="btn-primary text-center text-white font-semibold py-3 rounded-xl">Elegir plan</a>
            </div>

            <!-- PLAN 3: Plataforma + Restream -->
            <div class="glass-card rounded-2xl p-8 flex flex-col" x-data="restreamPlan()">
                <h3 class="text-lg font-semibold text-white mb-1">{{ $plan3['name'] }}</h3>
                <p class="text-sm text-slate-400 mb-6">Transmite a varias plataformas a la vez.</p>
                <div class="mb-4">
                    <span class="text-4xl font-extrabold text-white" x-text="formatPrice(total())"></span>
                    <span class="text-slate-400 text-sm">/mes</span>
                </div>
                <p class="text-xs text-slate-500 mb-6">
                    <span class="strike" x-text="formatPrice(original())"></span>
                    <span class="text-emerald-400 font-semibold ml-1">{{ $plan3['discount_percent'] }}% de descuento</span>
                </p>

                <div class="mb-6">
                    <p class="text-sm text-slate-300 mb-2">Conexiones simultáneas</p>
                    <div class="grid grid-cols-4 gap-2">
                        <template x-for="n in 4" :key="n">
                            <button type="button" class="conn-btn rounded-lg py-2 text-sm font-semibold text-slate-200" :class="{ 'active': conns === n }" @click="conns = n" x-text="n"></button>
                        </template>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">Máximo 4 salidas simultáneas · precio gradual por conexión</p>
                </div>

                <ul class="space-y-3 text-sm text-slate-300 mb-8 flex-1">
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Todo lo del plan Plataforma Completa</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Restream a Facebook Live</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Restream a YouTube Live</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Restream a TikTok Live</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Destino RTMP personalizado</li>
                    <li class="flex items-start gap-2"><span class="feature-check">✓</span> Hasta 4 salidas simultáneas</li>
                </ul>
                <a :href="waLink()" href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero contratar el plan ' . $plan3['name']) }}" target="_blank" rel="noopener" class="btn-ghost text-center text-white font-semibold py-3 rounded-xl">Elegir plan</a>
            </div>
        </div>
    </section>

    <!-- ====== FAQ ====== -->
    <section id="faq" class="max-w-3xl mx-auto px-4 sm:px-6 py-16">
        <div class="text-center mb-12">
            <h2 class="text-3xl sm:text-4xl font-bold title-grad mb-3">Preguntas frecuentes</h2>
            <p class="text-slate-400 max-w-2xl mx-auto">Resolvemos las dudas más comunes sobre el servicio de señal de TV en vivo por Internet.</p>
        </div>

        <div class="space-y-4" x-data="{ open: 0 }">
            <div class="glass-card rounded-2xl overflow-hidden">
                <button type="button" class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left" @click="open = open === 1 ? 0 : 1">
                    <span class="font-semibold text-white">¿Cómo transmito mi canal de TV en vivo por Internet en Colombia?</span>
                    <span class="text-cyan-400 text-xl shrink-0" x-text="open === 1 ? '−' : '+'"></span>
                </button>
                <div x-show="open === 1" x-transition.opacity.duration.200ms class="px-6 pb-5 text-sm text-slate-300">
                    Con Cloudstream tu señal se emite 24/7 desde un datacenter americano. Solo eliges tu plan y nosotros nos encargamos de la transmisión con protocolos RTMP y HLS, sin que tengas que montar tu propia infraestructura.
                </div>
            </div>

            <div class="glass-card rounded-2xl overflow-hidden">
                <button type="button" class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left" @click="open = open === 2 ? 0 : 2">
                    <span class="font-semibold text-white">¿Puedo emitir desde mi propio equipo?</span>
                    <span class="text-cyan-400 text-xl shrink-0" x-text="open === 2 ? '−' : '+'"></span>
                </button>
                <div x-show="open === 2" x-transition.opacity.duration.200ms class="px-6 pb-5 text-sm text-slate-300">
                    Sí, en el plan Señal Streaming. Tú pones tu equipo con OBS, vMix o tu aplicación de emisión, y nosotros te damos el servidor al que debes emitir. En los planes Plataforma Completa y Plataforma + Restream la emisión se hace desde la nube, sin depender de tu equipo.
                </div>
            </div>

            <div class="glass-card rounded-2xl overflow-hidden">
                <button type="button" class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left" @click="open = open === 3 ? 0 : 3">
                    <span class="font-semibold text-white">¿Cuál es la diferencia entre el plan Señal Streaming y la Plataforma Completa?</span>
                    <span class="text-cyan-400 text-xl shrink-0" x-text="open === 3 ? '−' : '+'"></span>
                </button>
                <div x-show="open === 3" x-transition.opacity.duration.200ms class="px-6 pb-5 text-sm text-slate-300">
                    En el plan Señal Streaming el cliente emite desde su propio equipo hacia nuestro servidor. En la Plataforma Completa la emisión se hace desde la nube: 30 GB de almacenamiento, sistema de emisión en la nube, programador de contenido, cuñas publicitarias, logo y respaldo diario.
                </div>
            </div>

            <div class="glass-card rounded-2xl overflow-hidden">
                <button type="button" class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left" @click="open = open === 4 ? 0 : 4">
                    <span class="font-semibold text-white">¿Qué incluye el servicio de streaming para canales de TV?</span>
                    <span class="text-cyan-400 text-xl shrink-0" x-text="open === 4 ? '−' : '+'"></span>
                </button>
                <div x-show="open === 4" x-transition.opacity.duration.200ms class="px-6 pb-5 text-sm text-slate-300">
                    El plan base incluye 1 señal streaming 24/7, codecs H264, MP3 y AAC+, formato de 320p a 720p, calidad de emisión de 1000 KBPS, protocolos RTMP + HLS, hasta 300 televidentes, señal con SSL y 720 horas de emisión al mes. Tú emites desde tu equipo con OBS, vMix o tu aplicación.
                </div>
            </div>

            <div class="glass-card rounded-2xl overflow-hidden">
                <button type="button" class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left" @click="open = open === 5 ? 0 : 5">
                    <span class="font-semibold text-white">¿Puedo transmitir mi canal a Facebook, YouTube y TikTok al mismo tiempo?</span>
                    <span class="text-cyan-400 text-xl shrink-0" x-text="open === 5 ? '−' : '+'"></span>
                </button>
                <div x-show="open === 5" x-transition.opacity.duration.200ms class="px-6 pb-5 text-sm text-slate-300">
                    Sí. Con el plan Plataforma + Restream puedes transmitir simultáneamente a Facebook Live, YouTube Live, TikTok Live y destinos RTMP personalizados, con hasta 4 salidas simultáneas.
                </div>
            </div>

            <div class="glass-card rounded-2xl overflow-hidden">
                <button type="button" class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left" @click="open = open === 6 ? 0 : 6">
                    <span class="font-semibold text-white">¿Puedo programar cuñas publicitarias en mi señal?</span>
                    <span class="text-cyan-400 text-xl shrink-0" x-text="open === 6 ? '−' : '+'"></span>
                </button>
                <div x-show="open === 6" x-transition.opacity.duration.200ms class="px-6 pb-5 text-sm text-slate-300">
                    Sí. El plan Plataforma Completa incluye un programador de contenido que te permite organizar tu parrilla de programación por días y horas, e insertar cuñas publicitarias de forma automática.
                </div>
            </div>

            <div class="glass-card rounded-2xl overflow-hidden">
                <button type="button" class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left" @click="open = open === 7 ? 0 : 7">
                    <span class="font-semibold text-white">¿Emiten factura electrónica?</span>
                    <span class="text-cyan-400 text-xl shrink-0" x-text="open === 7 ? '−' : '+'"></span>
                </button>
                <div x-show="open === 7" x-transition.opacity.duration.200ms class="px-6 pb-5 text-sm text-slate-300">
                    Sí. Emitimos factura electrónica vigente en Colombia y también cuenta de cobro. El servicio es prestado por Media Clouding SAS, empresa legalmente constituida.
                </div>
            </div>
        </div>
    </section>

    <!-- ====== CONTACTO ====== -->
    <section id="contacto" class="max-w-3xl mx-auto px-4 sm:px-6 py-16">
        <div class="glass-card rounded-2xl p-8 sm:p-10 text-center">
            <h2 class="text-3xl font-bold title-grad mb-3">¿Listo para llevar tu canal al aire?</h2>
            <p class="text-slate-400 mb-8">Contáctanos y en menos de 24 horas tu señal estará transmitiendo.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero información sobre los planes de Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Solicitar información por WhatsApp
                </a>
                <a href="{{ route('login') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Ya soy cliente · Iniciar sesión</a>
            </div>
            <div class="mt-8 pt-6 border-t border-white/5 flex flex-col sm:flex-row items-center justify-center gap-4 text-xs text-slate-400">
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Emitimos factura electrónica vigente en Colombia
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    También emitimos cuenta de cobro
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Servicio de Media Clouding SAS, empresa legalmente constituida
                </span>
            </div>
        </div>
    </section>

    <!-- ====== MODAL DE SALIDA (descuento de última oportunidad) ====== -->
    <div id="exitModal" class="exit-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="exitTitle">
        <div class="exit-modal-card relative p-8 sm:p-10 text-center">
            <button type="button" id="exitClose" class="exit-close" aria-label="Cerrar">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            <div class="exit-badge inline-flex items-center gap-1.5 text-white text-xs font-bold px-4 py-1.5 rounded-full mb-5">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                OFERTA DE ÚLTIMA OPORTUNIDAD
            </div>

            <h2 id="exitTitle" class="text-2xl sm:text-3xl font-extrabold title-grad mb-3">¡Espera! No te vayas sin tu descuento</h2>
            <p class="text-slate-300 text-sm mb-6">
                Por hoy, llévate el plan <strong class="text-white">{{ $plan1['name'] }}</strong> con un
                <strong class="text-emerald-400">descuento adicional de $5.000</strong>.
            </p>

            <div class="glass-card rounded-2xl p-5 mb-6">
                <p class="text-xs text-slate-400 mb-1">Precio normal</p>
                <p class="text-lg text-slate-300">${{ number_format($plan1['price_cop'], 0, ',', '.') }}/mes</p>
                <p class="text-xs text-slate-400 mt-3 mb-1">Hoy con tu descuento</p>
                <p class="text-4xl font-extrabold text-white">${{ number_format($plan1['price_cop'] - 5000, 0, ',', '.') }}<span class="text-base text-slate-400 font-normal">/mes</span></p>
            </div>

            <p class="text-xs text-amber-300/90 mb-5 flex items-center justify-center gap-1.5">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                La oferta expira en <span id="exitCountdown" class="exit-countdown font-bold">05:00</span>
            </p>

            <a id="exitWaLink" href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero aprovechar la oferta del plan ' . $plan1['name'] . ' con descuento ($' . number_format($plan1['price_cop'] - 5000, 0, ',', '.') . '/mes)') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold py-4 rounded-xl w-full flex items-center justify-center gap-2">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                Aprovechar oferta por WhatsApp
            </a>

            <button type="button" id="exitNoThanks" class="text-xs text-slate-500 hover:text-slate-300 transition mt-4">No, gracias, quiero ver los planes completos</button>
        </div>
    </div>

    <script>
        function restreamPlan() {
            return {
                conns: 1,
                finalPrices: [{{ $plan3['price_cop'] }}, 145000, 155000, 164000],
                discount: 0.30,
                total() {
                    return this.finalPrices[this.conns - 1];
                },
                original() {
                    return Math.round(this.finalPrices[this.conns - 1] / (1 - this.discount) / 1000) * 1000;
                },
                formatPrice(v) {
                    return '$' + v.toLocaleString('es-CO');
                },
                waLink() {
                    const conexiones = this.conns === 1 ? '1 conexión simultánea' : `${this.conns} conexiones simultáneas`;
                    const msg = `Hola, quiero contratar el plan {{ $plan3['name'] }} con ${conexiones} (${this.formatPrice(this.total())}/mes)`;
                    return 'https://wa.me/{{ $wa }}?text=' + encodeURIComponent(msg);
                }
            };
        }

        (function () {
            const bg = document.querySelector('.tech-bg');
            if (bg) {
                const SPARK_COUNT = 24;
                for (let i = 0; i < SPARK_COUNT; i++) {
                    const s = document.createElement('span');
                    s.className = 'spark';
                    const left  = Math.random() * 100;
                    const size  = 2 + Math.random() * 4;
                    const dur   = 6 + Math.random() * 8;
                    const delay = -Math.random() * dur;
                    const hue   = 200 + Math.random() * 80;
                    s.style.left = left + 'vw';
                    s.style.bottom = '-10px';
                    s.style.width = size + 'px';
                    s.style.height = size + 'px';
                    s.style.animationDuration = dur + 's';
                    s.style.animationDelay = delay + 's';
                    s.style.background = `hsl(${hue}, 90%, 85%)`;
                    s.style.boxShadow = `0 0 ${size*2}px ${size}px hsla(${hue}, 90%, 75%, .8)`;
                    bg.appendChild(s);
                }
            }

            const modal = document.getElementById('exitModal');
            const closeBtn = document.getElementById('exitClose');
            const noThanks = document.getElementById('exitNoThanks');
            const countdownEl = document.getElementById('exitCountdown');
            const shownKey = 'cloudstream_exit_modal_shown';

            if (!modal) return;

            let countdownTimer = null;
            let countdown = 300;

            function startCountdown() {
                if (countdownTimer) return;
                countdownTimer = setInterval(function () {
                    countdown--;
                    if (countdown <= 0) {
                        clearInterval(countdownTimer);
                        countdownTimer = null;
                        hideModal();
                        return;
                    }
                    const m = String(Math.floor(countdown / 60)).padStart(2, '0');
                    const s = String(countdown % 60).padStart(2, '0');
                    countdownEl.textContent = m + ':' + s;
                }, 1000);
            }

            function showModal() {
                if (sessionStorage.getItem(shownKey)) return;
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                startCountdown();
            }

            function hideModal() {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
            }

            function dismiss() {
                sessionStorage.setItem(shownKey, '1');
                hideModal();
            }

            closeBtn.addEventListener('click', dismiss);
            noThanks.addEventListener('click', function () {
                dismiss();
                const planes = document.getElementById('planes');
                if (planes) planes.scrollIntoView({ behavior: 'smooth' });
            });
            modal.addEventListener('click', function (e) {
                if (e.target === modal) dismiss();
            });

            let pageLoadTime = Date.now();
            let hasInteracted = false;

            function markInteraction() {
                hasInteracted = true;
            }
            document.addEventListener('scroll', markInteraction, { once: true, passive: true });
            document.addEventListener('click', markInteraction, { once: true });
            document.addEventListener('keydown', markInteraction, { once: true });

            document.addEventListener('mouseout', function (e) {
                if (!hasInteracted) return;
                if (Date.now() - pageLoadTime < 10000) return;
                if (e.relatedTarget === null && e.clientY <= 0) {
                    showModal();
                }
            });

            setTimeout(function () {
                if (!sessionStorage.getItem(shownKey) && hasInteracted) {
                    const planes = document.getElementById('planes');
                    if (planes) {
                        const rect = planes.getBoundingClientRect();
                        if (rect.top > window.innerHeight) {
                            showModal();
                        }
                    }
                }
            }, 120000);
        })();
    </script>

    <style>
        .exit-modal-overlay {
            position: fixed; inset: 0; z-index: 50;
            background: rgba(2, 3, 10, 0.8);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .exit-modal-card {
            max-width: 480px; width: 100%;
            background: linear-gradient(180deg, #0d1533 0%, #070b1e 100%);
            border: 1px solid rgba(14, 165, 233, 0.35);
            border-radius: 1.5rem;
            box-shadow: 0 40px 120px -20px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(14, 165, 233, 0.15) inset;
            animation: modalPop .35s cubic-bezier(.2, .7, .2, 1) both;
        }
        @keyframes modalPop {
            from { opacity: 0; transform: translateY(24px) scale(.96); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .exit-badge {
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            box-shadow: 0 10px 30px -8px rgba(37, 99, 235, .6);
        }
        .exit-close {
            position: absolute; top: 12px; right: 12px;
            width: 32px; height: 32px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #94a3b8; transition: all .2s;
        }
        .exit-close:hover { color: #fff; background: rgba(255, 255, 255, .08); }
        .exit-countdown {
            font-variant-numeric: tabular-nums;
        }
    </style>
</x-public-layout>
