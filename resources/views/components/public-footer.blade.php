@php
    $company = config('seo.company');
    $wa = preg_replace('/^\+/', '', $company['phone_e164']);
@endphp

<footer class="border-t border-white/5 py-10 mt-16">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 text-sm">
            <div>
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="font-bold text-white text-base">Cloudstream</span>
                </div>
                <p class="text-slate-400 text-xs leading-relaxed">
                    Servicio de señal de TV en vivo por Internet en Colombia: streaming 24/7, programador de contenido, cuñas publicitarias, logo y restream a Facebook, YouTube y TikTok.
                </p>
            </div>
            <div>
                <h3 class="text-white font-semibold text-xs uppercase tracking-wider mb-3">Soluciones</h3>
                <ul class="space-y-2 text-slate-400 text-xs">
                    <li><a href="{{ url('/streaming-para-iglesias-colombia') }}" class="hover:text-white transition">Para iglesias</a></li>
                    <li><a href="{{ url('/streaming-para-emisoras-de-radio-online') }}" class="hover:text-white transition">Para emisoras online</a></li>
                    <li><a href="{{ url('/streaming-para-canales-de-tv-regionales') }}" class="hover:text-white transition">Para canales regionales</a></li>
                    <li><a href="{{ url('/streaming-para-universidades-y-educacion') }}" class="hover:text-white transition">Para universidades</a></li>
                    <li><a href="{{ url('/streaming-para-productoras-de-contenido') }}" class="hover:text-white transition">Para productoras</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-white font-semibold text-xs uppercase tracking-wider mb-3">Producto</h3>
                <ul class="space-y-2 text-slate-400 text-xs">
                    <li><a href="{{ url('/#planes') }}" class="hover:text-white transition">Planes y precios</a></li>
                    <li><a href="{{ url('/restream-facebook-youtube-tiktok') }}" class="hover:text-white transition">Restream</a></li>
                    <li><a href="{{ url('/servidor-rtmp-colombia') }}" class="hover:text-white transition">Servidor RTMP</a></li>
                    <li><a href="{{ url('/blog') }}" class="hover:text-white transition">Blog</a></li>
                    <li><a href="{{ url('/preguntas-frecuentes') }}" class="hover:text-white transition">Preguntas frecuentes</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-white font-semibold text-xs uppercase tracking-wider mb-3">Contacto</h3>
                <ul class="space-y-2 text-slate-400 text-xs">
                    <li>{{ $company['name'] }} &middot; {{ $company['city'] }}, {{ $company['country'] }}</li>
                    <li>NIT {{ $company['nit'] }}</li>
                    <li>{{ $company['phone_display'] }}</li>
                    <li>
                        <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero información sobre los planes de Cloudstream') }}" target="_blank" rel="noopener" class="hover:text-white transition">WhatsApp</a>
                    </li>
                    <li><a href="{{ url('/sobre-nosotros') }}" class="hover:text-white transition">Sobre nosotros</a></li>
                </ul>
            </div>
        </div>

        <div class="border-t border-white/5 mt-8 pt-6 text-center text-xs text-slate-500">
            <p class="mb-1">
                &copy; {{ date('Y') }} {{ $company['name'] }} &middot; v{{ config('app.version', '1.0') }} &middot; Streaming en la nube
            </p>
            <p>
                Producto y servicio de <strong class="text-slate-400">{{ $company['name'] }}</strong> &middot; Empresa legalmente constituida &middot; Factura electrónica vigente en Colombia
            </p>
        </div>
    </div>
</footer>
