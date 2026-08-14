<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Cloudstream') }} — Iniciar sesión</title>
    <script src="{{ asset('js/tailwind.js') }}"></script>
    <style>
        :root {
            --c-bg-1: #050816;
            --c-bg-2: #0a0f2c;
            --c-accent: #6366f1;
            --c-accent-2: #a855f7;
            --c-cyan: #22d3ee;
        }
        * { -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: radial-gradient(ellipse at top, var(--c-bg-2) 0%, var(--c-bg-1) 60%, #02030a 100%);
            color: #e2e8f0;
            overflow: hidden;
        }

        /* ====== Fondo tecnológico: chispas, flujo, velocidad ====== */
        .tech-bg { position: fixed; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }

        /* Grilla sutil estilo circuito */
        .grid-lines {
            position: absolute; inset: -2px;
            background-image:
                linear-gradient(rgba(99,102,241,0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99,102,241,0.07) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
            animation: gridShift 24s linear infinite;
        }
        @keyframes gridShift {
            0% { background-position: 0 0, 0 0; }
            100% { background-position: 48px 48px, 48px 48px; }
        }

        /* Auroras de color */
        .aurora {
            position: absolute; border-radius: 50%;
            filter: blur(80px); opacity: .55;
            animation: floatA 14s ease-in-out infinite alternate;
        }
        .aurora.a1 { width: 520px; height: 520px; left: -120px; top: -160px;
            background: radial-gradient(circle, #6366f1 0%, transparent 70%); }
        .aurora.a2 { width: 480px; height: 480px; right: -120px; bottom: -160px;
            background: radial-gradient(circle, #a855f7 0%, transparent 70%); animation-delay: -4s; }
        .aurora.a3 { width: 380px; height: 380px; left: 40%; top: 50%;
            background: radial-gradient(circle, #22d3ee 0%, transparent 70%); opacity: .25; animation-delay: -8s; }
        @keyframes floatA {
            0%   { transform: translate(0,0) scale(1); }
            50%  { transform: translate(40px,-30px) scale(1.1); }
            100% { transform: translate(-30px,30px) scale(.95); }
        }

        /* Chispas (partículas) */
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

        /* Líneas de velocidad */
        .speed-line {
            position: absolute; height: 1px; width: 30%;
            background: linear-gradient(90deg, transparent, #818cf8, #c084fc, transparent);
            opacity: 0; filter: drop-shadow(0 0 6px rgba(168,85,247,.7));
            animation: streak linear infinite;
        }
        @keyframes streak {
            0%   { transform: translateX(-120%) scaleX(.5); opacity: 0; }
            20%  { opacity: .9; }
            100% { transform: translateX(420%) scaleX(1); opacity: 0; }
        }

        /* Rayo diagonal conectando esquinas */
        .bolt {
            position: absolute; height: 1px;
            background: linear-gradient(90deg, transparent, #22d3ee 50%, transparent);
            opacity: .4; transform-origin: left center;
            animation: pulseLine 6s ease-in-out infinite;
        }
        @keyframes pulseLine {
            0%, 100% { opacity: .15; transform: scaleX(.7); }
            50%      { opacity: .55; transform: scaleX(1); }
        }

        /* ====== Card / Logo ====== */
        .glass-card {
            background: linear-gradient(180deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.03) 100%);
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(22px) saturate(140%);
            -webkit-backdrop-filter: blur(22px) saturate(140%);
            box-shadow:
                0 30px 80px -20px rgba(0,0,0,0.6),
                inset 0 1px 0 rgba(255,255,255,0.08);
        }

        .logo-badge {
            position: relative;
            background: conic-gradient(from 140deg at 50% 50%, #6366f1, #a855f7, #22d3ee, #6366f1);
            animation: spin 12s linear infinite;
        }
        .logo-badge::before {
            content: ''; position: absolute; inset: 2px; border-radius: inherit;
            background: linear-gradient(135deg, #0b1230, #1e1b4b);
        }
        .logo-badge svg { position: relative; z-index: 1; }
        @keyframes spin { to { filter: hue-rotate(360deg); } }

        /* ====== Inputs ====== */
        .input-wrap {
            position: relative;
        }
        .input-wrap .leading-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; pointer-events: none;
        }
        .input-field {
            transition: all .25s ease;
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.18);
            color: #e2e8f0;
        }
        .input-field::placeholder { color: #64748b; }
        .input-field:hover { border-color: rgba(129, 140, 248, .4); }
        .input-field:focus {
            outline: none;
            border-color: #818cf8;
            box-shadow: 0 0 0 4px rgba(129, 140, 248, .18);
            background: rgba(15, 23, 42, 0.75);
        }
        .toggle-pass {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; padding: 6px; border-radius: 8px;
            transition: all .2s;
        }
        .toggle-pass:hover { color: #c7d2fe; background: rgba(99,102,241,.12); }

        /* ====== Botón primario ====== */
        .btn-primary {
            position: relative; overflow: hidden;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
            box-shadow:
                0 10px 30px -8px rgba(99,102,241,.55),
                0 0 0 1px rgba(255,255,255,0.06) inset;
            transition: transform .2s ease, box-shadow .25s ease, filter .25s ease;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow:
                0 16px 40px -8px rgba(168,85,247,.65),
                0 0 0 1px rgba(255,255,255,0.1) inset;
            filter: brightness(1.05);
        }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary::after {
            content: '';
            position: absolute; top: 0; left: -75%;
            width: 50%; height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255,255,255,.35), transparent);
            transform: skewX(-20deg);
            transition: left .8s ease;
        }
        .btn-primary:hover::after { left: 130%; }

        /* Checkbox */
        .check {
            appearance: none; -webkit-appearance: none;
            width: 18px; height: 18px; border-radius: 6px;
            border: 1.5px solid rgba(148,163,184,.4);
            background: rgba(15,23,42,.5);
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all .2s;
        }
        .check:checked {
            background: linear-gradient(135deg, #6366f1, #a855f7);
            border-color: transparent;
        }
        .check:checked::after {
            content: ''; width: 10px; height: 10px;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'/></svg>");
            background-size: contain; background-repeat: no-repeat;
        }

        /* Animación de aparición del card */
        .fade-up { animation: fadeUp .7s cubic-bezier(.2,.7,.2,1) both; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px) scale(.985); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .title-grad {
            background: linear-gradient(90deg, #c7d2fe, #f5d0fe, #a5f3fc);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-8">

    <div class="tech-bg" aria-hidden="true">
        <div class="grid-lines"></div>
        <div class="aurora a1"></div>
        <div class="aurora a2"></div>
        <div class="aurora a3"></div>

        <div class="bolt" style="top: 18%; left: -10%; width: 70%; transform: rotate(-12deg);"></div>
        <div class="bolt" style="top: 62%; left: 20%; width: 55%; transform: rotate(8deg); animation-delay: -2s;"></div>
        <div class="bolt" style="top: 82%; left: -5%; width: 50%; transform: rotate(-6deg); animation-delay: -4s;"></div>

        <div class="speed-line" style="top: 22%; left: 0; animation-duration: 4.5s; animation-delay: .3s;"></div>
        <div class="speed-line" style="top: 48%; left: 0; animation-duration: 5.5s; animation-delay: 1.2s; width: 22%;"></div>
        <div class="speed-line" style="top: 72%; left: 0; animation-duration: 6s;   animation-delay: 2.1s; width: 38%;"></div>
        <div class="speed-line" style="top: 88%; left: 0; animation-duration: 7s;   animation-delay: .8s; width: 18%;"></div>
    </div>

    <div class="relative z-10 w-full max-w-md fade-up">

        <div class="text-center mb-8">
            <div class="logo-badge inline-flex items-center justify-center w-20 h-20 rounded-2xl shadow-2xl mb-5">
                <svg class="w-10 h-10 text-white" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Cloudstream">
                    <defs>
                        <linearGradient id="cloudGrad" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#a5b4fc"/>
                            <stop offset="100%" stop-color="#22d3ee"/>
                        </linearGradient>
                        <linearGradient id="streamGrad" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="#f0abfc"/>
                            <stop offset="100%" stop-color="#a5f3fc"/>
                        </linearGradient>
                    </defs>
                    <path d="M20 42c-6.6 0-12-5.1-12-11.4 0-5.7 4.2-10.4 9.7-11.3C19.3 12 25.4 7 32.7 7c7.8 0 14.2 5.8 15 13.3.2 0 .4 0 .6 0 6.2 0 11.2 4.8 11.2 10.7S54.5 41.7 48.3 41.7H20z"
                          fill="url(#cloudGrad)" opacity="0.95"/>
                    <path d="M28 30 v18 l16 -9 z" fill="url(#streamGrad)" stroke="white" stroke-width="1.4" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold title-grad tracking-tight mb-1">Cloudstream</h1>
            <p class="text-indigo-200/80 text-sm">Panel de administración · Streaming en la nube</p>
        </div>

        <div class="glass-card rounded-2xl p-8">

            <div class="mb-6">
                <h2 class="text-lg font-semibold text-white">Bienvenido de vuelta</h2>
                <p class="text-sm text-slate-400">Ingresa tus credenciales para continuar.</p>
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-xl bg-red-500/10 border border-red-500/30 px-4 py-3">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-400 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <p class="text-sm text-red-200">
                            @if($errors->has('login'))
                                {{ $errors->first('login') }}
                            @else
                                Credenciales inválidas.
                            @endif
                        </p>
                    </div>
                </div>
            @endif

            @if (session('status'))
                <div class="mb-5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 px-4 py-3 text-sm text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="login" class="block text-sm font-medium text-slate-200 mb-1.5">Usuario o correo</label>
                    <div class="input-wrap">
                        <svg class="leading-icon w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input id="login" name="login" type="text" autocomplete="username" required autofocus
                               value="{{ old('login') }}"
                               placeholder="usuario o correo@ejemplo.com"
                               class="input-field w-full pl-11 pr-4 py-3 rounded-xl" />
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-medium text-slate-200">Contraseña</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-xs text-indigo-300 hover:text-indigo-200 transition">¿Olvidaste tu contraseña?</a>
                        @endif
                    </div>
                    <div class="input-wrap">
                        <svg class="leading-icon w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               placeholder="••••••••"
                               class="input-field w-full pl-11 pr-12 py-3 rounded-xl" />
                        <button type="button" id="togglePassword" aria-label="Mostrar u ocultar contraseña"
                                class="toggle-pass">
                            <svg id="eyeOff" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                            <svg id="eyeOn" class="w-5 h-5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center pt-1">
                    <input id="remember_me" name="remember" type="checkbox" class="check" />
                    <label for="remember_me" class="ml-2.5 text-sm text-slate-300 select-none cursor-pointer">Recordarme en este equipo</label>
                </div>

                <button type="submit" class="btn-primary w-full text-white font-semibold py-3.5 rounded-xl mt-2 flex items-center justify-center gap-2">
                    <span>Iniciar sesión</span>
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-indigo-200/60 mt-6">
            &copy; {{ date('Y') }} Cloudstream &middot; v{{ config('app.version', '1.0') }}
        </p>
    </div>

    <script>
        (function () {
            // Ojito: mostrar / ocultar contraseña
            const input = document.getElementById('password');
            const btn   = document.getElementById('togglePassword');
            const eyeOn = document.getElementById('eyeOn');
            const eyeOff= document.getElementById('eyeOff');

            btn.addEventListener('click', function () {
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                eyeOn.classList.toggle('hidden', showing);
                eyeOff.classList.toggle('hidden', !showing);
                btn.setAttribute('aria-pressed', String(!showing));
            });

            // Generar chispas dinámicamente
            const bg = document.querySelector('.tech-bg');
            const SPARK_COUNT = 28;
            for (let i = 0; i < SPARK_COUNT; i++) {
                const s = document.createElement('span');
                s.className = 'spark';
                const left  = Math.random() * 100;
                const size  = 2 + Math.random() * 4;
                const dur   = 6 + Math.random() * 8;
                const delay = -Math.random() * dur;
                const hue   = 200 + Math.random() * 80; // azul-violeta
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
        })();
    </script>
</body>
</html>