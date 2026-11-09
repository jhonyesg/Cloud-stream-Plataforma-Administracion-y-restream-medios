@props([
    'eyebrow' => 'Protocolos de emisión y entrega',
    'title' => 'Una señal. Todos los formatos.',
    'subtitle' => 'Tú emites una sola vez desde tu equipo con OBS, vMix o Wirecast. Nosotros convertimos la señal y la entregamos en HLS, FLV, WebRTC, MPEG-TS y SRT: por HTTPS con caché, por HTTP plano o directo por puerto, para que la consuma quien use el reproductor que prefiera.',
    'sectionId' => 'formatos',
])

@php
    $urls = app(\App\Services\StreamingUrlBuilder::class)->showcase();
    $slug = config('streaming_public.example_slug');
@endphp

<section @if($sectionId) id="{{ $sectionId }}" @endif class="max-w-6xl mx-auto scroll-mt-20 px-4 sm:px-6 py-16" x-data="deliveryUrls()">

    <div class="text-center mb-10">
        <div class="inline-flex items-center gap-2 rounded-full border border-cyan-500/20 bg-cyan-500/10 px-4 py-1.5 mb-4 text-xs font-medium text-cyan-200">
            <span aria-hidden="true">🔗</span>
            {{ $eyebrow }}
        </div>
        <h2 class="title-grad mb-3 text-3xl font-bold sm:text-4xl">{{ $title }}</h2>
        <p class="mx-auto max-w-3xl text-slate-400">{{ $subtitle }}</p>
    </div>

    {{-- Flujo de la señal --}}
    <div class="glass-card mb-8 rounded-2xl p-5 sm:p-6">
        <div class="grid grid-cols-1 items-center gap-4 text-center md:grid-cols-5">
            <div>
                <div class="mb-2 text-2xl" aria-hidden="true">📹</div>
                <p class="text-sm font-semibold text-white">Tu estudio</p>
                <p class="mt-1 text-xs text-slate-400">Emites una sola vez con OBS, vMix, Wirecast o desde el celular.</p>
            </div>
            <div class="flex items-center justify-center gap-3 text-cyan-300" aria-hidden="true">
                <span class="hidden text-xs text-slate-500 md:inline">RTMP / SRT</span>
                <span class="text-lg">→</span>
            </div>
            <div>
                <div class="mb-2 text-2xl" aria-hidden="true">☁️</div>
                <p class="text-sm font-semibold text-white">Datacenter en USA</p>
                <p class="mt-1 text-xs text-slate-400">Recibimos, convertimos y cacheamos tu señal.</p>
            </div>
            <div class="flex items-center justify-center gap-3 text-cyan-300" aria-hidden="true">
                <span class="hidden text-xs text-slate-500 md:inline">7 formatos</span>
                <span class="text-lg">→</span>
            </div>
            <div>
                <div class="mb-2 text-2xl" aria-hidden="true">📺</div>
                <p class="text-sm font-semibold text-white">Tu audiencia</p>
                <p class="mt-1 text-xs text-slate-400">Navegador, app, Smart TV, decodificador o troncal de cable.</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6">

        {{-- PUBLICAR --}}
        <div class="glass-card rounded-2xl p-6 sm:p-7" style="border-color: rgba(59,130,246,.28)">
            <div class="mb-1 flex items-center gap-2">
                <span aria-hidden="true">📤</span>
                <h3 class="text-lg font-semibold text-blue-300">PUBLICAR</h3>
            </div>
            <p class="mb-5 text-xs text-slate-400">OBS · FMLE · vMix · Wirecast</p>

            <div class="space-y-2">
                @foreach ($urls['publish'] as $row)
                    <x-delivery-url-row :protocol="$row['protocol']" :url="$row['url']" label-class="text-blue-300" />
                @endforeach
            </div>

            <p class="mt-4 text-xs leading-relaxed text-slate-500">
                El nombre del canal y tu clave de stream te los entregamos al activar el plan.
            </p>

            <div class="mt-5 space-y-2 border-t border-white/10 pt-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Cómo se emite</p>
                <p class="text-xs leading-relaxed text-slate-400">
                    <span class="font-semibold text-slate-300">OBS:</span> Configuración → Emisión → Servidor, con la URL RTMP y la clave de stream.
                </p>
                <p class="text-xs leading-relaxed text-slate-400">
                    <span class="font-semibold text-slate-300">vMix, Wirecast o FMLE:</span> salida RTMP con el mismo host, puerto y clave.
                </p>
                <p class="text-xs leading-relaxed text-slate-400">
                    <span class="font-semibold text-slate-300">Desde el celular:</span> Larix o Streamlabs OBS Mobile con la URL SRT, para redes inestables.
                </p>
            </div>
        </div>

        {{-- CONSUMIR --}}
        <div class="glass-card rounded-2xl p-6 sm:p-7" style="border-color: rgba(16,185,129,.28)">
            <div class="mb-1 flex items-center gap-2">
                <span aria-hidden="true">📥</span>
                <h3 class="text-lg font-semibold text-emerald-300">CONSUMIR</h3>
            </div>
            <p class="mb-5 text-xs text-slate-400">vMix · OBS · Astra · Lastra · FFplay · VLC</p>

            <div class="mb-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 font-mono text-xs text-emerald-300">
                ✓ Vía proxy inverso (nginx + caché): recomendado para distribución
            </div>
            <p class="mb-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500">🔒 HTTPS</p>
            <div class="space-y-2">
                @foreach ($urls['consume']['https'] as $row)
                    <x-delivery-url-row :protocol="$row['protocol']" :url="$row['url']" label-class="text-emerald-300" />
                @endforeach
            </div>

            <p class="mb-3 mt-5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">🔓 HTTP (sin TLS)</p>
            <div class="space-y-2">
                @foreach ($urls['consume']['http'] as $row)
                    <x-delivery-url-row :protocol="$row['protocol']" :url="$row['url']" label-class="text-emerald-300" />
                @endforeach
            </div>

            <p class="mb-3 mt-5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">⚡ SRT directo</p>
            <div class="space-y-2">
                @foreach ($urls['consume']['srt'] as $row)
                    <x-delivery-url-row :protocol="$row['protocol']" :url="$row['url']" label-class="text-emerald-300" />
                @endforeach
            </div>
        </div>
    </div>

    {{-- MPEG-TS --}}
    <div class="glass-card mt-8 rounded-2xl p-6 sm:p-8" style="border-color: rgba(251,191,36,.28)">
        <div class="flex items-start gap-3">
            <span class="mt-1 text-xl" aria-hidden="true">⭐</span>
            <div>
                <h3 class="text-lg font-semibold text-amber-200">MPEG-TS: el formato que hablan los decodificadores de TV</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-300">
                    Los decodificadores Astra, Lastra y Cesbo no reproducen HLS: consumen MPEG-TS, y vMix también puede entrar por esta vía. Es la entrega estándar para un operador de cable, una troncal de televisión por cable o una captura satelital, porque la señal viaja como flujo continuo MPEG-TS sobre HTTP, sin depender de SRT ni de plugins.
                </p>
            </div>
        </div>
    </div>

    <p class="mx-auto mt-6 max-w-3xl text-center text-xs leading-relaxed text-slate-500">
        URLs de ejemplo con el formato exacto que recibes. Sustituye <code class="rounded bg-white/5 px-1 py-0.5 font-mono text-slate-300">{{ $slug }}</code> por el nombre de tu canal; los host y las claves reales se entregan con la activación del plan.
    </p>

    <div x-show="visible"
        x-transition.opacity.duration.200ms
        role="status"
        aria-live="polite"
        x-text="toast"
        class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-xl border border-cyan-400/30 bg-slate-900/95 px-4 py-3 text-sm text-white shadow-2xl"></div>
</section>

@once
    <script>
        if (! window.deliveryUrls) {
            window.deliveryUrls = function () {
                return {
                    toast: '',
                    visible: false,
                    _timer: null,

                    notify(message) {
                        this.toast = message;
                        this.visible = true;
                        clearTimeout(this._timer);
                        this._timer = setTimeout(() => {
                            this.visible = false;
                        }, 2800);
                    },

                    fallbackCopy(url) {
                        const area = document.createElement('textarea');
                        area.value = url;
                        area.setAttribute('readonly', '');
                        area.style.position = 'fixed';
                        area.style.top = '-1000px';
                        area.style.opacity = '0';
                        document.body.appendChild(area);
                        area.select();
                        let ok = false;
                        try {
                            ok = document.execCommand('copy');
                        } catch (error) {
                            ok = false;
                        }
                        document.body.removeChild(area);
                        this.notify(ok
                            ? 'Copiado. Reemplaza tu-canal por el nombre de tu canal.'
                            : 'No se pudo copiar: selecciona la URL y cópiala a mano.');
                    },

                    copy(url) {
                        if (! url) {
                            return;
                        }
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(url)
                                .then(() => this.notify('Copiado. Reemplaza tu-canal por el nombre de tu canal.'))
                                .catch(() => this.fallbackCopy(url));
                            return;
                        }
                        this.fallbackCopy(url);
                    },
                };
            };
        }
    </script>
@endonce
