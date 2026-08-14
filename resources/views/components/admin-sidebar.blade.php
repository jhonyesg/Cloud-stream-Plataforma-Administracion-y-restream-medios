@props(['active' => null])

@php
    $user = auth()->user();
    $isAdmin = $user && $user->isAdmin();

    $mediaIcon = 'M9 19V6l12-3v12M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z';

    $schedulerIcon = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z';

    $items = $isAdmin ? [
        ['key' => 'dashboard', 'route' => 'admin.dashboard', 'label' => 'Inicio', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['key' => 'users', 'route' => 'admin.users', 'label' => 'Usuarios', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-2a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z'],
        ['key' => 'channels', 'route' => 'admin.channels', 'label' => 'Canales', 'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
        ['key' => 'media', 'route' => 'admin.media', 'label' => 'Multimedia', 'icon' => $mediaIcon],
        ['key' => 'scheduler', 'route' => 'admin.scheduler', 'label' => 'Programación', 'icon' => $schedulerIcon],
    ] : [
        ['key' => 'dashboard', 'route' => 'client.dashboard', 'label' => 'Inicio', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['key' => 'channels', 'route' => 'client.channels', 'label' => 'Canales', 'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
        ['key' => 'media', 'route' => 'client.media', 'label' => 'Multimedia', 'icon' => $mediaIcon],
        ['key' => 'scheduler', 'route' => 'client.scheduler', 'label' => 'Programación', 'icon' => $schedulerIcon],
    ];

    $themes = [
        'dashboard' => ['accent_hex' => '#818cf8'],
        'users'     => ['accent_hex' => '#38bdf8'],
        'channels'  => ['accent_hex' => '#34d399'],
        'media'     => ['accent_hex' => '#fbbf24'],
        'scheduler' => ['accent_hex' => '#2dd4bf'],
    ];
    $theme = $themes[$active] ?? $themes['dashboard'];
    $themeAccent = $theme['accent_hex'];
@endphp

<aside
    :class="[
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        sidebarCollapsed ? 'sm:w-16' : 'sm:w-64'
    ]"
    class="cs-chrome fixed inset-y-0 left-0 z-40 w-64 flex flex-col transform transition-all duration-200 ease-in-out sm:translate-x-0 border-r border-white/5"
    style="--accent: {{ $themeAccent }};"
>
    <div class="cs-chrome-inner relative flex-1 flex flex-col overflow-hidden">
        <x-tech-bg intensity="lite" :accent="$themeAccent" scope="sidebar" class="absolute inset-0 z-0" />

        <div class="relative z-10 h-16 px-4 flex items-center justify-between border-b border-white/5 shrink-0">
            <a href="{{ route('home') }}" class="flex items-center gap-2 overflow-hidden">
                <x-cloud-logo size="w-8 h-8" class="rounded-lg" />
                <span
                    :class="sidebarCollapsed ? 'sm:hidden' : ''"
                    class="font-bold text-white whitespace-nowrap bg-gradient-to-r from-indigo-200 via-fuchsia-200 to-cyan-200 bg-clip-text text-transparent"
                >Cloudstream</span>
            </a>
            <button
                type="button"
                @click="sidebarOpen = false"
                class="sm:hidden text-slate-400 hover:text-white"
                aria-label="Cerrar menú"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <nav class="relative z-10 flex-1 px-2 py-4 space-y-1 overflow-y-auto overflow-x-hidden">
            @foreach($items as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a
                    href="{{ route($item['route']) }}"
                    :title="sidebarCollapsed ? '{{ $item['label'] }}' : null"
                    @click="sidebarOpen = false"
                    @if($isActive) aria-current="page" @endif
                    class="cs-nav group relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200
                        {{ $isActive
                            ? 'cs-nav-active'
                            : 'text-slate-300 cs-nav-hover' }}"
                >
                    @if($isActive)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-r" style="background-color: var(--accent); box-shadow: 0 0 8px var(--accent);"></span>
                    @endif
                    <span class="shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                        </svg>
                    </span>
                    <span
                        :class="sidebarCollapsed ? 'sm:hidden' : ''"
                        class="whitespace-nowrap"
                    >{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        @auth
            <div class="relative z-10 px-3 py-3 border-t border-white/5 shrink-0 backdrop-blur-sm bg-white/[0.02]">
                <div class="flex items-center gap-2">
                    <span
                        class="w-2 h-2 rounded-full shrink-0 {{ $isAdmin ? 'bg-purple-400' : 'bg-sky-400' }}"
                        style="box-shadow: 0 0 8px currentColor;"
                    ></span>
                    <div
                        :class="sidebarCollapsed ? 'sm:hidden' : ''"
                        class="text-xs leading-tight min-w-0"
                    >
                        <div class="text-slate-100 font-medium truncate">{{ $user->display_name ?? $user->username }}</div>
                        <div class="text-slate-400 uppercase tracking-wide">{{ $user->role }}</div>
                    </div>
                    <button
                        type="button"
                        @click="sidebarCollapsed = !sidebarCollapsed; localStorage.setItem('sb-collapsed', sidebarCollapsed ? '1' : '0')"
                        class="ml-auto p-1.5 rounded text-slate-400 hover:text-white hover:bg-white/5 shrink-0 hidden sm:block"
                        :title="sidebarCollapsed ? 'Expandir menú' : 'Colapsar menú'"
                        aria-label="Colapsar/expandir menú"
                    >
                        <svg :class="sidebarCollapsed ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endauth
    </div>
</aside>
