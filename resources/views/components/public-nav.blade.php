<div class="tech-bg" aria-hidden="true">
    <div class="grid-lines"></div>
    <div class="aurora a1"></div>
    <div class="aurora a2"></div>
    <div class="aurora a3"></div>
</div>

<header class="sticky top-0 z-20 backdrop-blur-xl bg-[#050816]/70 border-b border-white/5">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5" aria-label="Cloudstream — Inicio">
            <div class="logo-badge inline-flex items-center justify-center w-9 h-9 rounded-lg">
                <svg class="w-5 h-5 text-white" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Cloudstream">
                    <defs>
                        <linearGradient id="cloudGrad" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#93c5fd"/>
                            <stop offset="100%" stop-color="#22d3ee"/>
                        </linearGradient>
                        <linearGradient id="streamGrad" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="#7dd3fc"/>
                            <stop offset="100%" stop-color="#a5f3fc"/>
                        </linearGradient>
                    </defs>
                    <path d="M20 42c-6.6 0-12-5.1-12-11.4 0-5.7 4.2-10.4 9.7-11.3C19.3 12 25.4 7 32.7 7c7.8 0 14.2 5.8 15 13.3.2 0 .4 0 .6 0 6.2 0 11.2 4.8 11.2 10.7S54.5 41.7 48.3 41.7H20z" fill="url(#cloudGrad)" opacity="0.95"/>
                    <path d="M28 30 v18 l16 -9 z" fill="url(#streamGrad)" stroke="white" stroke-width="1.4" stroke-linejoin="round"/>
                </svg>
            </div>
            <span class="font-bold text-lg tracking-tight text-white">Cloudstream</span>
        </a>

        <nav class="hidden md:flex items-center gap-6 text-sm text-slate-300" aria-label="Navegación principal">
            <a href="{{ url('/') }}#servicios" class="hover:text-white transition">Servicios</a>
            <a href="{{ url('/') }}#planes" class="hover:text-white transition">Planes</a>
            <a href="{{ url('/') }}#contacto" class="hover:text-white transition">Contacto</a>
            <a href="{{ url('/blog') }}" class="hover:text-white transition">Blog</a>
            <a href="{{ url('/preguntas-frecuentes') }}" class="hover:text-white transition">FAQ</a>
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ route('login') }}" class="btn-ghost text-sm font-medium text-slate-200 px-4 py-2 rounded-lg">Iniciar sesión</a>
        </div>
    </div>
</header>

<style>
    :root {
        --c-bg-1: #050816;
        --c-bg-2: #0a0f2c;
        --c-accent: #2563eb;
        --c-accent-2: #0ea5e9;
        --c-cyan: #22d3ee;
    }
    * { -webkit-tap-highlight-color: transparent; }
    html { scroll-behavior: smooth; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background: radial-gradient(ellipse at top, var(--c-bg-2) 0%, var(--c-bg-1) 60%, #02030a 100%);
        color: #e2e8f0;
    }
    .tech-bg { position: fixed; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
    .grid-lines {
        position: absolute; inset: -2px;
        background-image:
            linear-gradient(rgba(99,102,241,0.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(99,102,241,0.06) 1px, transparent 1px);
        background-size: 48px 48px;
        mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
        -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
        animation: gridShift 24s linear infinite;
    }
    @keyframes gridShift {
        0% { background-position: 0 0, 0 0; }
        100% { background-position: 48px 48px, 48px 48px; }
    }
    .aurora {
        position: absolute; border-radius: 50%;
        filter: blur(80px); opacity: .5;
        animation: floatA 14s ease-in-out infinite alternate;
    }
    .aurora.a1 { width: 520px; height: 520px; left: -120px; top: -160px; background: radial-gradient(circle, #2563eb 0%, transparent 70%); }
    .aurora.a2 { width: 480px; height: 480px; right: -120px; bottom: -160px; background: radial-gradient(circle, #0ea5e9 0%, transparent 70%); animation-delay: -4s; }
    .aurora.a3 { width: 380px; height: 380px; left: 40%; top: 50%; background: radial-gradient(circle, #22d3ee 0%, transparent 70%); opacity: .25; animation-delay: -8s; }
    @keyframes floatA {
        0%   { transform: translate(0,0) scale(1); }
        50%  { transform: translate(40px,-30px) scale(1.1); }
        100% { transform: translate(-30px,30px) scale(.95); }
    }
    .spark {
        position: absolute; width: 4px; height: 4px; border-radius: 50%;
        background: #c7d2fe;
        box-shadow: 0 0 8px 2px rgba(199,210,254,.9), 0 0 14px 4px rgba(99,102,241,.45);
        opacity: 0;
        animation: rise linear infinite;
    }
    @keyframes rise {
        0%   { transform: translateY(0) translateX(0) scale(.6); opacity: 0; }
        10%  { opacity: 1; }
        50%  { transform: translateY(-50vh) translateX(20px) scale(1); }
        90%  { opacity: 1; }
        100% { transform: translateY(-110vh) translateX(-20px) scale(.4); opacity: 0; }
    }
    .glass-card {
        background: linear-gradient(180deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.03) 100%);
        border: 1px solid rgba(255,255,255,0.08);
        backdrop-filter: blur(22px) saturate(140%);
        -webkit-backdrop-filter: blur(22px) saturate(140%);
        box-shadow: 0 30px 80px -20px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.08);
    }
    .logo-badge {
        position: relative;
        background: conic-gradient(from 140deg at 50% 50%, #2563eb, #0ea5e9, #22d3ee, #2563eb);
        animation: spin 12s linear infinite;
    }
    .logo-badge::before {
        content: ''; position: absolute; inset: 2px; border-radius: inherit;
        background: linear-gradient(135deg, #0b1230, #0a1a3a);
    }
    .logo-badge svg { position: relative; z-index: 1; }
    @keyframes spin { to { filter: hue-rotate(360deg); } }
    .title-grad {
        background: linear-gradient(90deg, #bfdbfe, #7dd3fc, #a5f3fc);
        -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .fade-up { animation: fadeUp .7s cubic-bezier(.2,.7,.2,1) both; }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(18px) scale(.985); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .btn-primary {
        position: relative; overflow: hidden;
        background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 50%, #22d3ee 100%);
        box-shadow: 0 10px 30px -8px rgba(37,99,235,.55), 0 0 0 1px rgba(255,255,255,0.06) inset;
        transition: transform .2s ease, box-shadow .25s ease, filter .25s ease;
    }
    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 40px -8px rgba(14,165,233,.65), 0 0 0 1px rgba(255,255,255,0.1) inset;
        filter: brightness(1.05);
    }
    .btn-primary:active { transform: translateY(0); }
    .btn-primary::after {
        content: ''; position: absolute; top: 0; left: -75%;
        width: 50%; height: 100%;
        background: linear-gradient(120deg, transparent, rgba(255,255,255,.35), transparent);
        transform: skewX(-20deg);
        transition: left .8s ease;
    }
    .btn-primary:hover::after { left: 130%; }
    .btn-ghost {
        border: 1px solid rgba(148,163,184,.25);
        transition: all .2s ease;
    }
    .btn-ghost:hover { border-color: rgba(129,140,248,.5); background: rgba(99,102,241,.1); }
    .feature-check { color: #34d399; }
    .plan-popular {
        border: 1px solid rgba(14,165,233,.55);
        box-shadow: 0 0 0 1px rgba(14,165,233,.2), 0 30px 80px -20px rgba(14,165,233,.35);
    }
    .conn-btn {
        transition: all .2s ease;
        border: 1px solid rgba(148,163,184,.2);
        background: rgba(15,23,42,.5);
    }
    .conn-btn:hover { border-color: rgba(129,140,248,.5); }
    .conn-btn.active {
        background: linear-gradient(135deg, #2563eb, #0ea5e9);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 8px 20px -6px rgba(37,99,235,.6);
    }
    .strike { text-decoration: line-through; color: #64748b; }
    .prose-public h2 { font-size: 1.75rem; font-weight: 700; margin-top: 2rem; margin-bottom: .75rem; color: #fff; }
    .prose-public h3 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: .5rem; color: #e2e8f0; }
    .prose-public p { color: #cbd5e1; line-height: 1.75; margin-bottom: 1rem; }
    .prose-public ul { color: #cbd5e1; line-height: 1.75; margin-bottom: 1rem; padding-left: 1.5rem; list-style: disc; }
    .prose-public li { margin-bottom: .35rem; }
    .prose-public strong { color: #fff; }
    .prose-public a { color: #7dd3fc; text-decoration: underline; text-underline-offset: 2px; }
    .prose-public a:hover { color: #a5f3fc; }
</style>
