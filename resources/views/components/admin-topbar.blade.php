@props([
    'accent' => 'border-indigo-500',
    'accentHex' => '#818cf8',
])

@auth
    @php
        $user = auth()->user();
        $initial = strtoupper(mb_substr($user->display_name ?? $user->username ?? $user->email ?? 'U', 0, 1));
        $avatarGradients = [
            'linear-gradient(135deg, #818cf8, #a855f7)',
            'linear-gradient(135deg, #34d399, #22d3ee)',
            'linear-gradient(135deg, #fb7185, #f59e0b)',
            'linear-gradient(135deg, #fbbf24, #f43f5e)',
            'linear-gradient(135deg, #38bdf8, #6366f1)',
            'linear-gradient(135deg, #a855f7, #ec4899)',
        ];
        $avatarGradient = $avatarGradients[crc32($user->id ?? $user->email ?? 'x') % count($avatarGradients)];
    @endphp

<header
    :class="sidebarCollapsed ? 'sm:left-16' : 'sm:left-64'"
    class="cs-chrome cs-chrome-inner is-topbar !fixed top-0 left-0 right-0 z-30 shrink-0 text-slate-100 border-b border-white/5 transition-[left] duration-200"
    style="--accent: {{ $accentHex }};"
>
    <x-tech-bg intensity="lite" :accent="$accentHex" scope="topbar" class="absolute inset-0 z-0 opacity-60" />

    <div class="relative z-10 h-16 flex items-center justify-between px-4 sm:px-6">
        <div class="flex items-center gap-3">
            <button
                @click="sidebarOpen = !sidebarOpen"
                class="sm:hidden p-2 -ml-2 text-slate-300 hover:text-white hover:bg-white/5 rounded-md"
                aria-label="Abrir menú"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <button
                type="button"
                @click="sidebarCollapsed = !sidebarCollapsed; localStorage.setItem('sb-collapsed', sidebarCollapsed ? '1' : '0')"
                class="hidden sm:flex p-2 -ml-2 text-slate-300 hover:text-white hover:bg-white/5 rounded-md"
                :title="sidebarCollapsed ? 'Expandir menú' : 'Colapsar menú'"
                aria-label="Colapsar/expandir menú"
            >
                <svg :class="sidebarCollapsed ? 'rotate-180' : ''" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
            </button>

            
        </div>

        <div class="flex items-center gap-3">
            <span
                class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-white/5 backdrop-blur-sm border border-white/10
                    {{ $user->isAdmin() ? 'text-purple-200' : 'text-sky-200' }}"
            >
                <span class="w-1.5 h-1.5 rounded-full {{ $user->isAdmin() ? 'bg-purple-400' : 'bg-sky-400' }}" style="box-shadow: 0 0 6px currentColor;"></span>
                {{ ucfirst($user->role) }}
            </span>

            <x-dropdown align="right" width="56">
                <x-slot name="trigger">
                    <button class="flex items-center gap-2 px-2 py-1 rounded-full hover:bg-white/5 focus:outline-none focus:bg-white/5 transition">
                        <span class="w-9 h-9 rounded-full text-white flex items-center justify-center font-semibold text-sm ring-2 ring-white/10" style="background: {{ $avatarGradient }};">
                            {{ $initial }}
                        </span>
                        <span class="hidden sm:flex flex-col items-start leading-tight">
                            <span class="text-sm font-medium text-white">{{ $user->display_name ?? $user->username }}</span>
                            <span class="text-xs text-slate-300/80">{{ $user->email }}</span>
                        </span>
                        <svg class="w-4 h-4 text-slate-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="px-4 py-2 border-b border-gray-100 sm:hidden">
                        <div class="text-sm font-medium text-gray-800">{{ $user->display_name ?? $user->username }}</div>
                        <div class="text-xs text-gray-500 truncate">{{ $user->email }}</div>
                        <div class="mt-1 text-xs">
                            <span class="px-2 py-0.5 rounded {{ $user->isAdmin() ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">{{ $user->role }}</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="$store.modals.open('profile')"
                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    >
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span class="flex flex-col items-start leading-tight">
                                <span class="font-medium">Mi perfil</span>
                                <span class="text-xs text-gray-500">Editar nombre, usuario, correo</span>
                            </span>
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="$store.modals.open('password')"
                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    >
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <span class="flex flex-col items-start leading-tight">
                                <span class="font-medium">Cambiar contraseña</span>
                                <span class="text-xs text-gray-500">Actualizar tu clave de acceso</span>
                            </span>
                        </span>
                    </button>
                    <div class="border-t border-gray-100"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            Cerrar sesión
                        </button>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>

    <div
        class="relative z-10 h-0.5 w-full"
        style="background-color: var(--accent); box-shadow: 0 0 12px var(--accent);"
    ></div>
</header>

<x-profile-modal />
<x-password-modal />
<x-fs-explorer-modal />
@endauth
