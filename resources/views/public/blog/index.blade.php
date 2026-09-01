@php
    $wa = preg_replace('/^\+/', '', config('seo.company.phone_e164'));
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'blog-index', 'data' => ['articles' => $articles]]"
>
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 pb-12">
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 text-xs font-medium text-blue-200 bg-blue-500/10 border border-blue-500/20 rounded-full px-4 py-1.5 mb-6">
                <span>📚 Blog de Cloudstream</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight title-grad leading-tight mb-6">
                Guías y artículos sobre streaming de TV en Colombia
            </h1>
            <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto">
                Todo lo que necesitas saber para transmitir tu canal de TV por Internet: protocolos, costos, equipamiento, casos de uso y comparativas.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($articles as $a)
                @php
                    $slug = basename($a['path']);
                    $imgPath = '/images/blog/' . $slug . '.jpg';
                @endphp
                <a href="{{ url($a['path']) }}" class="glass-card rounded-2xl overflow-hidden hover:border-blue-500/40 transition flex flex-col">
                    <img src="{{ url($imgPath) }}" alt="{{ $a['title'] }}" width="1200" height="600" loading="lazy" class="w-full h-44 object-cover">
                    <div class="p-6 flex flex-col flex-1">
                        <div class="text-xs text-slate-500 mb-2">{{ \Carbon\Carbon::parse($a['date_published'])->format('d M Y') }}</div>
                        <h2 class="text-lg font-semibold text-white mb-2 leading-snug">{{ $a['title'] }}</h2>
                        <p class="text-sm text-slate-400 mb-4 flex-1">{{ $a['excerpt'] }}</p>
                        <span class="text-cyan-400 text-sm font-medium">Leer artículo →</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-16 glass-card rounded-2xl p-8 text-center">
            <h2 class="text-2xl font-bold title-grad mb-3">¿Listo para empezar?</h2>
            <p class="text-slate-400 mb-6">Contrata un plan de Cloudstream y emite tu canal en menos de 24 horas.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Hola, quiero información sobre los planes de Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">Hablar por WhatsApp</a>
                <a href="{{ url('/#planes') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Ver planes</a>
            </div>
        </div>
    </section>
</x-public-layout>
