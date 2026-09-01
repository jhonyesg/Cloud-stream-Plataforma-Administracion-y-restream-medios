<x-client-layout active="channels">
    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mis Canales</h2>
    </x-slot:header>

    <div
        x-data="{
            editOpen: false,
            editId: null,
            editName: '',
            editDesc: '',
            editPath: '',
            editHlsUrl: '',
            editBusy: false,
            editErrors: {},
            openEditChannel(id, ch) {
                this.editId = id;
                this.editName = ch.display_name || '';
                this.editDesc = ch.description || '';
                this.editPath = ch.root_path || '';
                this.editHlsUrl = ch.public_hls_url || '';
                this.editErrors = {};
                this.editOpen = true;
            },
            submitForm(ev) {
                if (!this.editId) { ev.preventDefault(); return; }
                this.editBusy = true;
                this.editErrors = {};
                const form = ev.target;
                const data = new FormData(form);
                fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': data.get('_token') },
                    body: data,
                }).then(async (r) => {
                    this.editBusy = false;
                    if (r.ok) {
                        this.editOpen = false;
                        window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Canal actualizado correctamente.' } }));
                        setTimeout(() => window.location.reload(), 600);
                    } else if (r.status === 422) {
                        const d = await r.json();
                        this.editErrors = d.errors || {};
                    } else if (r.status === 403) {
                        this.editErrors = { general: ['No tienes permiso para editar este canal.'] };
                    } else {
                        window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'No se pudo actualizar el canal.' } }));
                    }
                }).catch(() => {
                    this.editBusy = false;
                    window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'Error de red.' } }));
                });
            }
        }"
    >

    <div class="mb-4">
        <p class="text-sm text-gray-600">{{ $channels->total() }} canales</p>
    </div>

    @if($channels->isEmpty())
        <div class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm p-12 text-center">
            <p class="text-gray-700 font-medium">No tienes canales asignados</p>
            <p class="text-gray-500 text-sm mt-1">Contacta al administrador para que te asigne un canal.</p>
        </div>
    @else
        <div x-data="{ viewMode: localStorage.getItem('client-channels-view') || 'cards' }" class="space-y-4">
            <div class="flex justify-end">
                <div class="inline-flex rounded-lg bg-gray-100 p-1" role="tablist">
                    <button type="button" @click="viewMode = 'cards'; localStorage.setItem('client-channels-view', 'cards')" :class="viewMode === 'cards' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md transition" title="Vista tarjetas">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Tarjetas
                    </button>
                    <button type="button" @click="viewMode = 'table'; localStorage.setItem('client-channels-view', 'table')" :class="viewMode === 'table' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md transition" title="Vista tabla">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        Tabla
                    </button>
                </div>
            </div>

            <div x-show="viewMode === 'cards'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($channels as $ch)
                    @php
                        $isOwner = in_array($ch->id, $ownedChannelIds);
                        $chJson = json_encode($ch->only(['id','display_name','description','root_path','public_hls_url']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    @endphp
                    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-200 overflow-hidden flex flex-col">
                        {{-- Header: name + status + buttons --}}
                        <div class="px-4 py-3 flex items-center justify-between gap-2 border-b border-gray-100">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded
                                    @switch($ch->status)
                                        @case('active') bg-green-100 text-green-800 @break
                                        @case('draft') bg-gray-100 text-gray-800 @break
                                        @case('suspended') bg-red-100 text-red-800 @break
                                        @default bg-gray-100 text-gray-800
                                    @endswitch">{{ $ch->status }}</span>
                                <h3 class="font-semibold text-gray-900 truncate" title="{{ $ch->display_name }}">{{ $ch->display_name }}</h3>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                @if($isOwner)
                                    <button
                                        type="button"
                                        data-channel-id="{{ $ch->id }}"
                                        data-channel="{{ $chJson }}"
                                        @click="openEditChannel($event.currentTarget.dataset.channelId, JSON.parse($event.currentTarget.dataset.channel))"
                                        title="Editar canal"
                                        class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-sm flex items-center justify-center text-white hover:from-emerald-500 hover:to-emerald-700 hover:scale-110 active:scale-95 transition"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="$store.modals.open('virtual-screen-editor', { channel_id: '{{ $ch->id }}', channel_name: '{{ addslashes($ch->display_name) }}' })"
                                        title="Configurar pantalla virtual"
                                        class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-400 to-teal-600 shadow-sm flex items-center justify-center text-white hover:from-teal-500 hover:to-teal-700 hover:scale-110 active:scale-95 transition"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    @click="$store.modals.open('live-viewer', { channel_id: '{{ $ch->id }}', channel_name: '{{ addslashes($ch->display_name) }}', slug: '{{ $ch->slug }}' })"
                                    title="Ver emisión en vivo"
                                    class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-400 to-sky-600 shadow-sm flex items-center justify-center text-white hover:from-sky-500 hover:to-sky-700 hover:scale-110 active:scale-95 transition"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Live preview player (Plyr + HLS.js, autoplay only when card is in viewport) --}}
                        <div class="bg-black aspect-video relative" data-live-card>
                            @if($ch->public_hls_url)
                                <video
                                    id="liveCard-{{ $ch->id }}"
                                    data-hls-url="{{ $ch->public_hls_url }}"
                                    data-live-video
                                    muted
                                    playsinline
                                    preload="none"
                                    class="w-full h-full"
                                ></video>
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

                        {{-- Footer: slug + path --}}
                        <div class="px-4 py-3 text-xs text-gray-500 flex items-center justify-between gap-2">
                            <span class="font-mono truncate" title="{{ $ch->slug }}">{{ $ch->slug }}</span>
                            @if($ch->root_path)
                                <span class="font-mono truncate text-gray-400" title="{{ $ch->root_path }}">{{ $ch->root_path }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div x-show="viewMode === 'table'" x-cloak class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Canal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($channels as $ch)
                            @php
                                $isOwner = in_array($ch->id, $ownedChannelIds);
                                $chJson = json_encode($ch->only(['id','display_name','description','root_path','public_hls_url']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            @endphp
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ $ch->display_name }}</div>
                                    @if($ch->root_path)
                                        <div class="text-xs text-gray-500 font-mono truncate max-w-xs" title="{{ $ch->root_path }}">{{ $ch->root_path }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500 font-mono">{{ $ch->slug }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <div class="flex justify-end gap-1.5">
                                        @if($isOwner)
                                            <button type="button" data-channel-id="{{ $ch->id }}" data-channel="{{ $chJson }}" @click="openEditChannel($event.currentTarget.dataset.channelId, JSON.parse($event.currentTarget.dataset.channel))" title="Editar canal" class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-sm flex items-center justify-center text-white hover:from-emerald-500 hover:to-emerald-700 hover:scale-110 active:scale-95 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button type="button" @click="$store.modals.open('virtual-screen-editor', { channel_id: '{{ $ch->id }}', channel_name: '{{ addslashes($ch->display_name) }}' })" title="Configurar pantalla virtual" class="w-9 h-9 rounded-lg bg-gradient-to-br from-teal-400 to-teal-600 shadow-sm flex items-center justify-center text-white hover:from-teal-500 hover:to-teal-700 hover:scale-110 active:scale-95 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            </button>
                                        @endif
                                        <button type="button" @click="$store.modals.open('live-viewer', { channel_id: '{{ $ch->id }}', channel_name: '{{ addslashes($ch->display_name) }}', slug: '{{ $ch->slug }}' })" title="Ver emisión en vivo" class="w-9 h-9 rounded-lg bg-gradient-to-br from-sky-400 to-sky-600 shadow-sm flex items-center justify-center text-white hover:from-sky-500 hover:to-sky-700 hover:scale-110 active:scale-95 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $channels->links() }}</div>
        </div>
    @endif

    {{-- Inline edit modal for client-owned channels --}}
    <div>
        <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="editOpen = false"></div>
            <div class="relative min-h-screen flex items-center justify-center p-4">
                <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 flex flex-col max-h-[calc(100vh-2rem)] overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 shrink-0">
                        <h3 class="text-lg font-semibold text-gray-800">Editar canal</h3>
                        <button type="button" @click="editOpen = false" class="text-gray-400 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form
                        :action="'/client/channels/' + editId"
                        method="POST"
                        @submit.prevent="submitForm($event)"
                        class="flex flex-col flex-1 min-h-0"
                    >
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="_method" value="PUT">
                        <div class="flex-1 overflow-y-auto px-5 py-5 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre</label>
                                <input type="text" name="display_name" x-model="editName" maxlength="120"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition">
                                <p x-show="editErrors.display_name" class="mt-1 text-sm text-red-600" x-text="editErrors.display_name?.[0] || ''"></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Descripción</label>
                                <textarea name="description" x-model="editDesc" rows="2" maxlength="2000"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ruta</label>
                                <input type="text" name="root_path" x-model="editPath" maxlength="1024"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 font-mono text-sm transition">
                                <p class="mt-1 text-xs text-gray-500">Ruta absoluta del servidor. Ej: <code class="px-1 py-0.5 bg-gray-100 rounded">/mnt/multimedia/cine-dios</code></p>
                                <p x-show="editErrors.root_path" class="mt-1 text-sm text-red-600" x-text="editErrors.root_path?.[0] || ''"></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">URL pública HLS (player)</label>
                                <input type="url" name="public_hls_url" x-model="editHlsUrl" maxlength="500"
                                    placeholder="https://canal.tudominio.com/live/cinedios.m3u8"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 font-mono text-sm transition">
                                <p class="mt-1 text-xs text-gray-500">URL <code class="px-1 py-0.5 bg-gray-100 rounded">.m3u8</code> que nginx-rtmp sirve y consumen los espectadores. Formato típico: <code class="px-1 py-0.5 bg-gray-100 rounded">https://tu-dominio/live/{slug}.m3u8</code>. Requerida para previsualizar el canal desde el botón "Ver vivo".</p>
                                <p x-show="editErrors.public_hls_url" class="mt-1 text-sm text-red-600" x-text="editErrors.public_hls_url?.[0] || ''"></p>
                            </div>
                            <p x-show="editErrors.general" class="text-sm text-red-600" x-text="editErrors.general?.[0] || ''"></p>
                        </div>
                        <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100 shrink-0 bg-gray-50/50">
                            <button type="button" @click="editOpen = false" :disabled="editBusy"
                                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition">Cancelar</button>
                            <button type="submit" :disabled="editBusy"
                                class="inline-flex items-center gap-2 px-5 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-500 disabled:opacity-50 transition shadow-sm">
                                <svg x-show="!editBusy" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <svg x-show="editBusy" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span x-text="editBusy ? 'Guardando…' : 'Guardar'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    </div>

    <x-virtual-screen-editor-modal />
    <x-virtual-screen-preview-viewer-modal />
    <x-live-viewer-modal />
</x-client-layout>

<script>
(function() {
    if (!window.Plyr || !window.Hls) return;
    const players = [];
    const cards = new Map();

    document.querySelectorAll('video[data-live-video]').forEach((video) => {
        const url = video.dataset.hlsUrl;
        if (!url) return;

        const card = video.closest('[data-live-card]') || video.parentElement;
        cards.set(video, card);

        const player = new window.Plyr(video, {
            controls: ['play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
            autoplay: false,
            muted: true,
        });
        player._initialized = false;
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
            player._initialized = true;
        });
    });

    const isVisible = (el) => el && el.offsetParent !== null;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const video = entry.target;
            const player = players.find((p) => p.media === video);
            if (!player || !player._initialized) return;
            if (entry.isIntersecting && isVisible(video.closest('[data-live-card]'))) {
                player.play().catch(() => {});
            } else {
                player.pause();
            }
        });
    }, { threshold: 0.25 });

    cards.forEach((_card, video) => observer.observe(video));

    window.addEventListener('beforeunload', () => {
        observer.disconnect();
        players.forEach((p) => {
            if (p._hls) try { p._hls.destroy(); } catch (e) {}
            try { p.destroy(); } catch (e) {}
        });
    });
})();
</script>