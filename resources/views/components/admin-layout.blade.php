<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Cloudstream') }}</title>

    @include('partials._favicons')

    @php
        $themes = [
            'dashboard' => ['name' => 'Indigo',  'top' => 'border-indigo-500',  'page' => 'bg-indigo-50/40',  'btn' => 'bg-indigo-600 hover:bg-indigo-500',  'pill' => 'bg-indigo-100 text-indigo-800',  'text' => 'text-indigo-700',  'accent_hex' => '#818cf8'],
            'users'     => ['name' => 'Sky',     'top' => 'border-sky-500',     'page' => 'bg-sky-50/40',     'btn' => 'bg-sky-600 hover:bg-sky-500',      'pill' => 'bg-sky-100 text-sky-800',         'text' => 'text-sky-700',     'accent_hex' => '#38bdf8'],
            'channels'  => ['name' => 'Emerald', 'top' => 'border-emerald-500', 'page' => 'bg-emerald-50/40', 'btn' => 'bg-emerald-600 hover:bg-emerald-500', 'pill' => 'bg-emerald-100 text-emerald-800', 'text' => 'text-emerald-700', 'accent_hex' => '#34d399'],
            'media'     => ['name' => 'Amber',   'top' => 'border-amber-500',   'page' => 'bg-amber-50/40',   'btn' => 'bg-amber-600 hover:bg-amber-500',   'pill' => 'bg-amber-100 text-amber-800',    'text' => 'text-amber-700',   'accent_hex' => '#fbbf24'],
            'scheduler' => ['name' => 'Teal',    'top' => 'border-teal-500',    'page' => 'bg-teal-50/40',    'btn' => 'bg-teal-600 hover:bg-teal-500',     'pill' => 'bg-teal-100 text-teal-800',      'text' => 'text-teal-700',    'accent_hex' => '#2dd4bf'],
        ];
        $theme = $themes[$active ?? 'dashboard'] ?? $themes['dashboard'];
    @endphp

    <script src="{{ asset('js/tailwind.js') }}"></script>
    {{-- Plyr.io + HLS.js for the live-viewer modal --}}
    <link rel="stylesheet" href="https://cdn.plyr.io/3.6.8/plyr.css">
    <script src="https://cdn.plyr.io/3.6.8/plyr.polyfilled.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script>
        window.csrfToken = @json(csrf_token());
        window.appTheme = @json($theme);
        @include('partials._alpine-stores')

        window.mediaCard = (config) => ({
            data: config.data,
            play() {
                Alpine.store('modals').open('play-media', {
                    play_url: this.data.play_url,
                    kind: this.data.kind,
                    filename: this.data.filename,
                });
            },
            rename() {
                Alpine.store('modals').open('rename-media', {
                    id: this.data.id,
                    filename: this.data.filename,
                    kind: this.data.kind,
                });
            },
            del() {
                Alpine.store('modals').open('confirm-delete', {
                    action: '/api/media-items/' + this.data.id,
                    method: 'DELETE',
                    title: 'Eliminar archivo',
                    message: '\u00bfEliminar ' + this.data.filename + '? El archivo se borrar\u00e1 del disco y las playlists mostrar\u00e1n "no disponible".',
                });
            },
        });
    </script>
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
</head>
<body
    class="font-sans antialiased {{ $theme['page'] }}"
    x-data="{
        sidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('sb-collapsed') === '1'
    }"
>
    <div @keydown.escape.window="sidebarOpen = false">
        <div class="flex min-h-screen relative">
            <div
                x-show="sidebarOpen"
                x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="sidebarOpen = false"
                class="fixed inset-0 bg-gray-900/50 z-30 sm:hidden"
                style="display: none;"
            ></div>

            <x-admin-sidebar :active="$active ?? null" />

            <div class="flex-1 flex flex-col min-w-0 transition-all duration-200"
     :class="sidebarCollapsed ? 'sm:ml-16' : 'sm:ml-64'">
                <x-admin-topbar :accent="$theme['top']" :accent-hex="$theme['accent_hex']" />

                @isset($header)
                    <div class="bg-white border-b border-gray-200">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                            {{ $header }}
                        </div>
                    </div>
                @endisset

                <main class="flex-1 px-4 sm:px-6 lg:px-8 pt-20 pb-6 max-w-7xl w-full mx-auto">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <div
            x-data="{ message: '', ok: false }"
            x-on:crud-error.window="ok = false; message = ($event.detail && ($event.detail.message || (($event.detail.errors) ? Object.values($event.detail.errors).flat()[0] : 'Operación no completada'))) || 'Operación no completada'; setTimeout(() => message = '', 5000)"
            x-on:crud-success.window="ok = true; message = $event.detail?.message || 'Operación completada'; setTimeout(() => message = '', 4000)"
            x-show="message"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            class="fixed bottom-6 right-6 z-50 max-w-sm shadow-2xl rounded-xl ring-1 ring-black/5 overflow-hidden"
            style="display: none;"
        >
            <div
                :class="ok ? 'bg-emerald-600' : 'bg-red-600'"
                class="text-white px-4 py-3 flex items-start gap-3"
            >
                <div :class="ok ? 'bg-emerald-500' : 'bg-red-500'" class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center -ml-1">
                    <svg x-show="ok" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="!ok" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M5.071 19h13.858c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm" x-text="ok ? 'Éxito' : 'Error'"></div>
                    <div class="text-sm opacity-95 mt-0.5" x-text="message"></div>
                </div>
                <button @click="message = ''" class="shrink-0 -mr-1 -mt-1 p-1 rounded hover:bg-white/20 transition" aria-label="Cerrar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="h-1 bg-white/20">
                <div
                    :class="ok ? 'bg-white/40' : 'bg-white/40'"
                    class="h-full toast-bar"
                    x-init="$watch('message', (m) => { if (m) { const bar = $el; bar.style.transition = 'none'; bar.style.width = '100%'; requestAnimationFrame(() => { bar.style.transition = 'width 4s linear'; bar.style.width = '0%'; }); } })"
                ></div>
            </div>
        </div>

        <style>
            .toast-bar { width: 100%; }

            /* === Chrome dark tokens === */
            .cs-chrome {
                --chrome-bg-1: #050816;
                --chrome-bg-2: #0a0f2c;
                --chrome-bg-3: #0b1230;
            }
            .cs-chrome-inner {
                position: relative;
                background: linear-gradient(180deg, var(--chrome-bg-1) 0%, var(--chrome-bg-2) 100%);
            }
            .cs-chrome-inner.is-topbar {
                background: linear-gradient(90deg, var(--chrome-bg-1) 0%, var(--chrome-bg-2) 100%);
            }
            .cs-accent-glow {
                box-shadow: 0 0 12px var(--accent, #818cf8);
            }
            .cs-nav-active {
                background: color-mix(in srgb, var(--accent, #818cf8) 8%, transparent) !important;
                color: #fff !important;
            }
            @supports not (color: color-mix(in srgb, black, white)) {
                .cs-nav-active { background: rgba(255,255,255,0.06) !important; }
            }
            .cs-nav-hover:hover {
                background: rgba(255,255,255,0.05);
                color: #fff;
                box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--accent, #818cf8) 25%, transparent);
            }
        </style>

        <script>
            (function () {
                if (window.__csTechBgInit) return;
                window.__csTechBgInit = true;

                var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (reduce) return;

                var layers = document.querySelectorAll('[data-tech-bg]');
                layers.forEach(function (layer) {
                    var cap = parseInt(layer.getAttribute('data-cap') || '0', 10);
                    if (cap <= 0) return;
                    var container = layer.querySelector('.sparks');
                    if (!container) return;
                    for (var i = 0; i < cap; i++) {
                        var s = document.createElement('span');
                        s.className = 'spark';
                        var left = Math.random() * 100;
                        var size = 2 + Math.random() * 3;
                        var dur  = 8 + Math.random() * 10;
                        var delay = -Math.random() * dur;
                        s.style.left = left + '%';
                        s.style.bottom = '-10px';
                        s.style.width = size + 'px';
                        s.style.height = size + 'px';
                        s.style.animationDuration = dur + 's';
                        s.style.animationDelay = delay + 's';
                        container.appendChild(s);
                    }
                });

                window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', function (e) {
                    document.querySelectorAll('[data-tech-bg]').forEach(function (l) {
                        l.style.display = e.matches ? 'none' : '';
                    });
                });
            })();
        </script>
    </div>

    <x-mobile-menu-fab />
</body>
</html>