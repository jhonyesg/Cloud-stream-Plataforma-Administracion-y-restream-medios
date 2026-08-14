<x-admin-layout active="channels">
    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Canales</h2>
    </x-slot:header>

    @php
        $allAssignableUsers = \App\Models\User::orderBy('display_name')->orderBy('username')->limit(500)->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->display_name ?? $u->username ?? $u->email,
                'label' => trim(($u->display_name ?? '') . ' · ' . ($u->username ?? '') . ' · ' . ($u->email ?? '')),
            ])->values();
    @endphp

    <script>window.allAssignableUsers = @json($allAssignableUsers);</script>

    <div x-data="{ viewMode: localStorage.getItem('admin-channels-view') || 'cards' }" class="space-y-4">
        <div class="flex justify-between items-center">
            <p class="text-sm text-gray-600">{{ $channels->total() }} canales</p>
            <div class="flex items-center gap-2">
                {{-- View mode toggle --}}
                <div class="inline-flex rounded-lg bg-gray-100 p-1" role="tablist">
                    <button
                        type="button"
                        @click="viewMode = 'cards'; localStorage.setItem('admin-channels-view', 'cards')"
                        :class="viewMode === 'cards' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md transition"
                        title="Vista tarjetas"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Tarjetas
                    </button>
                    <button
                        type="button"
                        @click="viewMode = 'table'; localStorage.setItem('admin-channels-view', 'table')"
                        :class="viewMode === 'table' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md transition"
                        title="Vista tabla"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        Tabla
                    </button>
                </div>
                <button
                    type="button"
                    @click="$store.modals.open('create-channel')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-md hover:bg-emerald-500 shadow-sm"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo canal
                </button>
            </div>
        </div>

        {{-- CARDS VIEW --}}
        <div x-show="viewMode === 'cards'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($channels as $ch)
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-200 overflow-hidden flex flex-col">
                    <div class="px-4 py-3 flex items-center justify-between gap-2 border-b border-gray-100">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded
                                @switch($ch->status)
                                    @case('active') bg-green-100 text-green-800 @break
                                    @case('draft') bg-gray-100 text-gray-800 @break
                                    @case('suspended') bg-red-100 text-red-800 @break
                                    @case('archived') bg-yellow-100 text-yellow-800 @break
                                @endswitch">{{ $ch->status }}</span>
                            <h3 class="font-semibold text-gray-900 truncate" title="{{ $ch->display_name }}">{{ $ch->display_name }}</h3>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button type="button" @click="$store.modals.open('edit-channel', { id: '{{ $ch->id }}' })" title="Editar canal" class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-400 to-indigo-600 shadow-sm flex items-center justify-center text-white hover:from-indigo-500 hover:to-indigo-700 hover:scale-110 active:scale-95 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button type="button" @click="$store.modals.open('virtual-screen-editor', { channel_id: '{{ $ch->id }}', channel_name: '{{ addslashes($ch->display_name) }}' })" title="Configurar pantalla virtual" class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-400 to-teal-600 shadow-sm flex items-center justify-center text-white hover:from-teal-500 hover:to-teal-700 hover:scale-110 active:scale-95 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </button>
                            <button type="button" @click="$store.modals.open('live-viewer', { channel_id: '{{ $ch->id }}', channel_name: '{{ addslashes($ch->display_name) }}', slug: '{{ $ch->slug }}' })" title="Ver emisión en vivo" class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-400 to-sky-600 shadow-sm flex items-center justify-center text-white hover:from-sky-500 hover:to-sky-700 hover:scale-110 active:scale-95 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                            @if($ch->status !== 'archived')
                                <button type="button" @click="$store.modals.open('confirm-delete', { action: '{{ route('admin.channels.destroy', $ch) }}', method: 'DELETE', title: 'Archivar canal', message: '¿Archivar {{ addslashes($ch->display_name) }}? El canal quedará como archivado y no se mostrará por defecto.' })" title="Archivar canal" class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-400 to-red-600 shadow-sm flex items-center justify-center text-white hover:from-red-500 hover:to-red-700 hover:scale-110 active:scale-95 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="bg-black aspect-video relative">
                        @if($ch->public_hls_url)
                            <video id="adminLiveCard-{{ $ch->id }}" data-hls-url="{{ $ch->public_hls_url }}" autoplay muted playsinline class="w-full h-full"></video>
                            <div class="absolute top-2 left-2 z-10 flex items-center gap-1.5 px-2 py-1 rounded-md bg-black/70 text-white text-[10px] font-bold uppercase pointer-events-none">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                Live
                            </div>
                        @else
                            <div class="absolute inset-0 flex items-center justify-center text-center text-gray-300 p-4">
                                <div>
                                    <svg class="w-10 h-10 mx-auto mb-2 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <p class="text-xs">Sin URL HLS</p>
                                    <p class="text-[10px] text-gray-400 mt-1">Edita el canal para configurarla</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="px-4 py-3 text-xs text-gray-500 space-y-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono truncate" title="{{ $ch->slug }}">{{ $ch->slug }}</span>
                            <span class="shrink-0 text-gray-400">{{ $ch->owner?->display_name ?? $ch->owner?->username ?? '—' }}</span>
                        </div>
                        @php $assigned = $ch->assignedUsers; @endphp
                        <div class="text-[11px] text-gray-400">
                            @if($assigned->isEmpty())
                                solo owner
                            @else
                                +{{ $assigned->count() }} asignado{{ $assigned->count() > 1 ? 's' : '' }}
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- TABLE VIEW --}}
        <div x-show="viewMode === 'table'" x-cloak class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Canal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruta</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resolución virtual</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asignados</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($channels as $ch)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $ch->display_name }}</div>
                                <div class="text-xs text-gray-500">{{ $ch->slug }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $ch->owner?->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-xs text-gray-600 font-mono truncate max-w-xs" title="{{ $ch->root_path ?? '' }}">{{ $ch->root_path ?: '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded
                                    @switch($ch->status)
                                        @case('active') bg-green-100 text-green-800 @break
                                        @case('draft') bg-gray-100 text-gray-800 @break
                                        @case('suspended') bg-red-100 text-red-800 @break
                                        @case('archived') bg-yellow-100 text-yellow-800 @break
                                    @endswitch">{{ $ch->status }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $ch->resolution }}</td>
                            <td class="px-6 py-4 text-xs text-gray-700">
                                @php $assigned = $ch->assignedUsers; @endphp
                                @if($assigned->isEmpty())
                                    <span class="text-gray-400">solo owner</span>
                                @else
                                    {{ $assigned->count() }} · {{ $assigned->take(2)->map(fn($u) => $u->display_name ?? $u->username)->join(', ') }}{{ $assigned->count() > 2 ? '…' : '' }}
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <div class="flex justify-end gap-1.5">
                                    <button type="button" @click="$store.modals.open('edit-channel', { id: '{{ $ch->id }}' })" title="Editar canal" class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-400 to-indigo-600 shadow-sm flex items-center justify-center text-white hover:from-indigo-500 hover:to-indigo-700 hover:scale-110 active:scale-95 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button type="button" @click="$store.modals.open('virtual-screen-editor', { channel_id: '{{ $ch->id }}', channel_name: '{{ addslashes($ch->display_name) }}' })" title="Configurar pantalla virtual" class="w-9 h-9 rounded-lg bg-gradient-to-br from-teal-400 to-teal-600 shadow-sm flex items-center justify-center text-white hover:from-teal-500 hover:to-teal-700 hover:scale-110 active:scale-95 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    </button>
                                    <button type="button" @click="$store.modals.open('live-viewer', { channel_id: '{{ $ch->id }}', channel_name: '{{ addslashes($ch->display_name) }}', slug: '{{ $ch->slug }}' })" title="Ver emisión en vivo" class="w-9 h-9 rounded-lg bg-gradient-to-br from-sky-400 to-sky-600 shadow-sm flex items-center justify-center text-white hover:from-sky-500 hover:to-sky-700 hover:scale-110 active:scale-95 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                    @if($ch->status !== 'archived')
                                        <button type="button" @click="$store.modals.open('confirm-delete', { action: '{{ route('admin.channels.destroy', $ch) }}', method: 'DELETE', title: 'Archivar canal', message: '¿Archivar {{ addslashes($ch->display_name) }}? El canal quedará como archivado y no se mostrará por defecto.' })" title="Archivar canal" class="w-9 h-9 rounded-lg bg-gradient-to-br from-red-400 to-red-600 shadow-sm flex items-center justify-center text-white hover:from-red-500 hover:to-red-700 hover:scale-110 active:scale-95 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                        </button>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 text-[10px] font-semibold rounded-md bg-gray-100 text-gray-500" title="Canal archivado">Archivado</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $channels->links() }}</div>
    </div>

    <x-channel-create-modal />
    <x-channel-edit-modal />
    <x-confirm-delete-modal />
    <x-virtual-screen-editor-modal />
    <x-virtual-screen-preview-viewer-modal />
    <x-live-viewer-modal />
</x-admin-layout>

<script>
(function() {
    if (!window.Plyr || !window.Hls) return;
    const players = [];
    document.querySelectorAll('video[data-hls-url]').forEach((video) => {
        const url = video.dataset.hlsUrl;
        if (!url) return;
        const player = new window.Plyr(video, {
            controls: ['play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
            autoplay: true,
            muted: true,
        });
        players.push(player);
        player.on('ready', () => {
            if (window.Hls.isSupported()) {
                const hls = new window.Hls({ liveSyncDurationCount: 3, liveMaxLatencyDurationCount: 8 });
                hls.loadSource(url);
                hls.attachMedia(player.media);
                player._hls = hls;
            } else if (player.media.canPlayType('application/vnd.apple.mpegurl')) {
                player.media.src = url;
            }
        });
    });
    window.addEventListener('beforeunload', () => {
        players.forEach(p => {
            if (p._hls) try { p._hls.destroy(); } catch (e) {}
            try { p.destroy(); } catch (e) {}
        });
    });
})();
</script>