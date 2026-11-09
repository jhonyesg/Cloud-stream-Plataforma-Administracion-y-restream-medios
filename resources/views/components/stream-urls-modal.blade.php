@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>';
@endphp

<x-app-modal name="stream-urls"
             title="URLs del canal"
             subtitle="Publicación y reproducción — copiá la que necesites y pegala en tu encoder o player"
             maxWidth="3xl"
             :icon="$icon"
             iconBg="bg-teal-100"
             iconColor="text-teal-600">
    <div
        x-data="streamUrls()"
        x-init="init()"
        class="flex flex-col flex-1 min-h-0"
    >
        {{-- Status bar --}}
        <div class="px-6 pt-5 pb-3 flex items-center justify-between gap-3 border-b border-gray-100">
            <div class="flex items-center gap-2 min-w-0">
                <span x-show="live" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-green-100 text-green-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                    Al aire
                </span>
                <span x-show="!live && !loading && available" x-cloak class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-gray-100 text-gray-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    Sin emisión
                </span>
                <span class="text-sm font-semibold text-gray-900 truncate" x-text="channelName"></span>
                <span class="text-xs font-mono text-gray-400 truncate" x-text="stream ? 'stream: ' + stream : ''"></span>
            </div>
            <span x-show="clients > 0" x-cloak class="shrink-0 text-xs font-semibold text-gray-500" x-text="clients + (clients === 1 ? ' viewer' : ' viewers')"></span>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto p-6 space-y-5">
            {{-- Loading --}}
            <div x-show="loading" class="flex items-center justify-center py-16">
                <div class="flex flex-col items-center gap-3 text-gray-500">
                    <svg class="w-8 h-8 animate-spin text-teal-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    <span class="text-sm">Consultando MediaServer Platform...</span>
                </div>
            </div>

            {{-- Error (API caída o permiso) --}}
            <div x-show="!loading && errorMsg" x-cloak class="rounded-xl border border-red-200 bg-red-50 p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.858c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <p class="text-sm text-red-800" x-text="errorMsg"></p>
                </div>
            </div>

            {{-- Aviso de estado degradado --}}
            <div x-show="!loading && !errorMsg && message" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.858c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <p class="text-sm text-amber-800" x-text="message"></p>
                </div>
            </div>

            {{-- Grupos de URLs --}}
            <template x-for="group in groups" :key="group.key">
                <div x-show="rowsFor(group.key).length > 0" class="rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 flex items-baseline justify-between gap-3">
                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider" x-text="group.title"></span>
                        <span class="text-[11px] text-gray-400 truncate" x-text="group.hint"></span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <template x-for="row in rowsFor(group.key)" :key="row.url">
                            <div class="flex items-center gap-3 px-4 py-2.5">
                                <div class="w-44 shrink-0 text-xs font-semibold text-gray-700" x-text="row.label"></div>
                                <code class="flex-1 min-w-0 truncate text-xs font-mono text-gray-600 bg-gray-50 rounded-md px-2 py-1"
                                      :title="row.url"
                                      x-text="row.url"></code>
                                <button type="button"
                                        @click="copy(row.url)"
                                        class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition"
                                        :class="copiedUrl === row.url ? 'text-green-600 border-green-300 bg-green-50' : ''">
                                    <svg x-show="copiedUrl !== row.url" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <svg x-show="copiedUrl === row.url" x-cloak class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span x-text="copiedUrl === row.url ? 'Copiado' : 'Copiar'"></span>
                                </button>
                                <a x-show="row.open"
                                   :href="row.url"
                                   target="_blank"
                                   rel="noopener"
                                   class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-teal-50 border border-teal-200 text-teal-700 hover:bg-teal-100 transition"
                                   title="Abrir en una pestaña nueva">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    Abrir
                                </a>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Sin URLs --}}
            <div x-show="!loading && !errorMsg && rows.length === 0" x-cloak class="text-center py-12">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>
                </div>
                <h4 class="text-base font-semibold text-gray-900">Sin URLs disponibles</h4>
                <p class="text-sm text-gray-500 mt-1">MediaServer Platform no reportó URLs para este canal.</p>
            </div>
        </div>
    </div>
</x-app-modal>

<script>
    window.streamUrls = () => ({
        channelId: '',
        channelName: '',
        stream: '',
        available: false,
        live: false,
        clients: 0,
        rows: [],
        message: '',
        errorMsg: '',
        loading: true,
        copiedUrl: '',
        _loadToken: 0,

        groups: [
            { key: 'publish', title: 'Publicar', hint: 'En tu encoder: OBS, vMix, Wirecast' },
            { key: 'proxy', title: 'Reproducir (dominio)', hint: 'HTTPS con TLS y caché — recomendado' },
            { key: 'channel', title: 'En Cloudstream', hint: 'URL pública configurada en el canal' },
        ],

        init() {
            this.$watch('$store.modals.current', (val) => {
                if (val === 'stream-urls') {
                    this.load();
                }
            });
        },

        rowsFor(key) {
            return this.rows.filter((r) => r.group === key);
        },

        async load() {
            const token = ++this._loadToken;
            this.loading = true;
            this.errorMsg = '';
            this.message = '';
            this.rows = [];
            this.copiedUrl = '';

            const payload = this.$store.modals?.payload || {};
            this.channelId = payload.channel_id || '';
            this.channelName = payload.channel_name || '';

            if (!this.channelId) {
                this.loading = false;
                this.errorMsg = 'No hay canal seleccionado.';
                return;
            }

            try {
                const res = await fetch('/api/channels/' + this.channelId + '/mediaserver/urls', {
                    headers: { 'Accept': 'application/json' },
                });

                if (res.status === 403) {
                    this.loading = false;
                    this.errorMsg = 'No tenés acceso a este canal.';
                    return;
                }
                if (!res.ok) {
                    this.loading = false;
                    this.errorMsg = 'No se pudieron consultar las URLs del canal (HTTP ' + res.status + ').';
                    return;
                }

                const data = await res.json();
                if (token !== this._loadToken) return;

                this.channelName = data?.channel?.name || this.channelName;
                this.stream = data?.stream || '';
                this.available = !!data?.available;
                this.live = !!data?.live;
                this.clients = Number(data?.clients || 0);
                this.rows = Array.isArray(data?.rows) ? data.rows : [];
                this.message = data?.message || '';
                this.loading = false;
            } catch (e) {
                if (token !== this._loadToken) return;
                this.loading = false;
                this.errorMsg = 'No se pudo conectar con el servidor para consultar las URLs.';
            }
        },

        async copy(url) {
            if (!url) return;
            try {
                await navigator.clipboard.writeText(url);
                this.copiedUrl = url;
            } catch (e) {
                const input = document.createElement('textarea');
                input.value = url;
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                try {
                    document.execCommand('copy');
                    this.copiedUrl = url;
                } catch (_) {}
                document.body.removeChild(input);
            }
            setTimeout(() => {
                if (this.copiedUrl === url) this.copiedUrl = '';
            }, 2000);
        },
    });
</script>
