@php
    $wa = preg_replace('/^\+/', '', config('seo.company.phone_e164'));
    $plan1 = config('seo.plans.plan1');
@endphp

<!-- HERO -->
<section class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 pb-12 text-center fade-up">
    <div class="inline-flex items-center gap-2 text-xs font-medium text-blue-200 bg-blue-500/10 border border-blue-500/20 rounded-full px-4 py-1.5 mb-6">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        {{ $heroBadge ?? 'Emisión 24/7 · Uptime 99% · Datacenter americano' }}
    </div>
    <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight title-grad leading-tight mb-6">
        {{ $heroH1 }}
    </h1>
    <p class="text-base sm:text-lg text-slate-300 max-w-3xl mx-auto mb-8 leading-relaxed">
        {!! $heroIntro !!}
    </p>
    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
        <a href="https://wa.me/{{ $wa }}?text={{ urlencode($heroCtaText ?? 'Hola, quiero información sobre el plan Señal Streaming de Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">{{ $heroCtaLabel ?? 'Contratar por WhatsApp' }}</a>
        <a href="{{ url('/#planes') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Ver planes y precios</a>
        <a href="tel:{{ config('seo.company.phone_e164') }}" class="btn-ghost text-slate-200 font-medium px-6 py-4 rounded-xl">{{ config('seo.company.phone_display') }}</a>
    </div>
</section>

<!-- FEATURES -->
@if(!empty($features))
<section class="max-w-6xl mx-auto px-4 sm:px-6 py-12">
    <div class="text-center mb-10">
        <h2 class="text-2xl sm:text-3xl font-bold title-grad mb-3">{{ $featuresTitle ?? '¿Qué incluye?' }}</h2>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($features as $f)
            <div class="glass-card rounded-2xl p-6">
                <div class="text-3xl mb-3">{{ $f['icon'] ?? '✅' }}</div>
                <h3 class="text-base font-semibold text-white mb-2">{{ $f['title'] }}</h3>
                <p class="text-sm text-slate-400">{{ $f['text'] }}</p>
            </div>
        @endforeach
    </div>
</section>
@endif

<!-- BODY CONTENT -->
@if(!empty($bodyHtml))
<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8 prose-public">
    {!! $bodyHtml !!}
</section>
@endif

<!-- FAQ -->
@if(!empty($faqs))
<section class="max-w-3xl mx-auto px-4 sm:px-6 py-12">
    <div class="text-center mb-10">
        <h2 class="text-2xl sm:text-3xl font-bold title-grad mb-3">Preguntas frecuentes</h2>
    </div>
    <div class="space-y-4" x-data="{ open: 0 }">
        @foreach($faqs as $i => $faq)
            <div class="glass-card rounded-2xl overflow-hidden">
                <button type="button" class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left" @click="open = open === {{ $i + 1 }} ? 0 : {{ $i + 1 }}">
                    <span class="font-semibold text-white text-sm sm:text-base">{{ $faq['q'] }}</span>
                    <span class="text-cyan-400 text-xl shrink-0" x-text="open === {{ $i + 1 }} ? '−' : '+'"></span>
                </button>
                <div x-show="open === {{ $i + 1 }}" x-transition.opacity.duration.200ms class="px-6 pb-5 text-sm text-slate-300">
                    {{ $faq['a'] }}
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

<!-- CTA -->
<section class="max-w-3xl mx-auto px-4 sm:px-6 py-12">
    <div class="glass-card rounded-2xl p-8 sm:p-10 text-center">
        <h2 class="text-2xl sm:text-3xl font-bold title-grad mb-3">{{ $ctaTitle ?? '¿Listo para empezar?' }}</h2>
        <p class="text-slate-400 mb-6">{{ $ctaSubtitle ?? 'Contrata hoy y en menos de 24 horas tu señal estará al aire.' }}</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="https://wa.me/{{ $wa }}?text={{ urlencode($heroCtaText ?? 'Hola, quiero información sobre Cloudstream') }}" target="_blank" rel="noopener" class="btn-primary text-white font-semibold px-8 py-4 rounded-xl">Hablar por WhatsApp</a>
            <a href="tel:{{ config('seo.company.phone_e164') }}" class="btn-ghost text-slate-200 font-medium px-8 py-4 rounded-xl">Llamar {{ config('seo.company.phone_display') }}</a>
        </div>
    </div>
</section>
