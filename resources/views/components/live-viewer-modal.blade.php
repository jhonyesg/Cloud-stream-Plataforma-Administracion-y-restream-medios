@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
@endphp

<x-app-modal name="live-viewer" title="En vivo" subtitle="Previsualización de la salida HLS del canal" maxWidth="3xl" :icon="$icon" iconBg="bg-sky-100" iconColor="text-sky-600">
    <div
        x-data="liveViewer()"
        x-init="init()"
        class="flex flex-col flex-1 min-h-0"
    >
        {{-- Header status bar --}}
        <div class="px-6 pt-5 pb-3 flex items-center justify-between gap-3 border-b border-gray-100">
            <div class="flex items-center gap-2 min-w-0">
                <span x-show="status === 'live'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-green-100 text-green-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                    Al aire
                </span>
                <span x-show="status === 'starting'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-100 text-amber-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    Iniciando...
                </span>
                <span x-show="status === 'error'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-red-100 text-red-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                    Error
                </span>
                <span x-show="status === 'offline' || !status" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-gray-100 text-gray-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    Sin emisión
                </span>
                <span class="text-sm text-gray-500 truncate" x-text="channelName"></span>
            </div>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto p-6 space-y-4">
            {{-- Loading --}}
            <div x-show="loading" class="flex items-center justify-center py-16">
                <div class="flex flex-col items-center gap-3 text-gray-500">
                    <svg class="w-8 h-8 animate-spin text-sky-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    <span class="text-sm">Cargando reproductor...</span>
                </div>
            </div>

            {{-- No HLS configured --}}
            <div x-show="!loading && !hasHls" x-cloak class="text-center py-12">
                <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.071 19h13.858c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h4 class="text-base font-semibold text-gray-900">El canal aún no tiene URL pública HLS configurada</h4>
                <p class="text-sm text-gray-500 mt-1 max-w-sm mx-auto">Pega la URL <code class="px-1 py-0.5 bg-gray-100 rounded text-xs">.m3u8</code> que nginx-rtmp sirve en el formulario de edición del canal.</p>
                <div class="mt-4 max-w-md mx-auto text-left rounded-xl bg-gray-50 border border-gray-200 p-3">
                    <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Plantilla sugerida</div>
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            readonly
                            :value="suggestedUrl"
                            class="flex-1 px-3 py-2 text-xs font-mono bg-white border border-gray-200 rounded-lg text-gray-700 focus:outline-none"
                            @focus="$event.target.select()"
                        />
                        <button
                            type="button"
                            @click="copyUrl(suggestedUrl)"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition"
                            :class="copied ? 'text-green-600 border-green-300' : ''"
                        >
                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <svg x-show="copied" x-cloak class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span x-text="copied ? 'Copiado' : 'Copiar'"></span>
                        </button>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-500">Edita el canal y pega esta URL (ajustando <code class="px-1 py-0.5 bg-gray-100 rounded">{tu-dominio}</code> al público).</p>
                </div>
            </div>

            {{-- Video player (Plyr + HLS.js, same as channels cards) --}}
            <div x-show="!loading && hasHls" x-cloak class="space-y-3">
                <div class="rounded-xl ring-1 ring-black/5 shadow-lg overflow-hidden bg-black relative">
                    <video
                        x-ref="videoEl"
                        id="liveViewerVideo"
                        autoplay
                        muted
                        playsinline
                        class="block w-full h-auto"
                    ></video>
                    <div class="absolute top-2 left-2 z-10 flex items-center gap-1.5 px-2 py-1 rounded-md bg-black/70 text-white text-[10px] font-bold uppercase pointer-events-none">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                        Live
                    </div>
                </div>

                {{-- URL panel --}}
                <div class="rounded-xl bg-gray-50 border border-gray-200 p-3">
                    <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">URL pública</div>
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            readonly
                            :value="hlsUrl"
                            class="flex-1 px-3 py-2 text-xs font-mono bg-white border border-gray-200 rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-sky-500/30"
                            @focus="$event.target.select()"
                        />
                        <button
                            type="button"
                            @click="copyUrl()"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition"
                            :class="copied ? 'text-green-600 border-green-300' : ''"
                        >
                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <svg x-show="copied" x-cloak class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span x-text="copied ? 'Copiado' : 'Copiar'"></span>
                        </button>
                        <a
                            :href="hlsUrl"
                            target="_blank"
                            rel="noopener"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-sky-50 border border-sky-300 text-sky-700 hover:bg-sky-100 transition"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            Abrir en nueva pestaña
                        </a>
                    </div>
                    <div x-show="virtualScreenMeta" x-cloak class="mt-2 text-[11px] text-gray-500" x-text="virtualScreenMeta"></div>
                </div>
            </div>
        </div>
    </div>
</x-app-modal>

<script>
window.liveViewer = () => ({
    channelId: '',
    channelName: '',
    slug: '',
    status: '',
    hlsUrl: '',
    virtualScreenMeta: '',
    suggestedUrl: '',
    loading: true,
    hasHls: false,
    errorMsg: '',
    copied: false,
    _hls: null,
    _player: null,
    _loadToken: 0,

    init() {
        this.$watch('$store.modals.current', (val) => {
            if (val === 'live-viewer') {
                this.load();
            } else {
                this.teardown();
            }
        });
    },

    async load() {
        const token = ++this._loadToken;
        this.teardown();
        this.loading = true;
        this.hasHls = false;
        this.errorMsg = '';
        this.copied = false;

        const payload = this.$store.modals?.payload || {};
        const channelId = payload.channel_id || '';
        const channelName = payload.channel_name || '';
        const slug = payload.slug || '';
        const suggestedUrl = `https://tu-dominio/live/${slug || '{slug}'}.m3u8`;

        if (!channelId) {
            this.loading = false;
            this.errorMsg = 'No hay canal seleccionado.';
            return;
        }

        let hlsUrl = '';
        let virtualScreenMeta = '';
        let status = 'offline';

        try {
            const [vsRes, statusRes] = await Promise.all([
                fetch('/api/virtual-screens/' + channelId, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.csrfToken || '' } }),
                fetch('/api/channels/' + channelId + '/emission/status', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.csrfToken || '' } }),
            ]);

            if (statusRes.ok) {
                const s = await statusRes.json();
                status = s.status || 'offline';
            }
            if (vsRes.ok) {
                const vs = await vsRes.json();
                if (vs.channel_public_hls_url) {
                    hlsUrl = vs.channel_public_hls_url;
                } else if (vs.output_protocol === 'hls' && vs.output_url) {
                    hlsUrl = vs.output_url;
                }
                if (hlsUrl) {
                    const parts = [];
                    if (vs.resolution_width && vs.resolution_height) parts.push(vs.resolution_width + 'x' + vs.resolution_height);
                    if (vs.video_bitrate_kbps) parts.push(vs.video_bitrate_kbps + ' kbps');
                    if (vs.fps) parts.push(vs.fps + ' fps');
                    virtualScreenMeta = parts.length ? 'Salida configurada: ' + parts.join(' · ') : '';
                }
            }
        } catch (e) {
            status = 'offline';
        }

        if (token !== this._loadToken) return;

        this.channelId = channelId;
        this.channelName = channelName;
        this.slug = slug;
        this.suggestedUrl = suggestedUrl;
        this.status = status;
        this.hlsUrl = hlsUrl;
        this.virtualScreenMeta = virtualScreenMeta;
        this.hasHls = !!hlsUrl;
        this.loading = false;

        if (this.hasHls) {
            this.$nextTick(() => this.attachPlayer());
        }
    },

    attachPlayer() {
        const video = this.$refs.videoEl;
        if (!video || !this.hlsUrl) return;

        if (!window.Plyr) {
            this.errorMsg = 'Plyr no se cargó. Recarga la página.';
            return;
        }
        if (!window.Hls) {
            this.errorMsg = 'HLS.js no se cargó. Recarga la página.';
            return;
        }

        const player = new window.Plyr(video, {
            controls: ['play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
            autoplay: true,
            muted: true,
        });
        this._player = player;

        player.on('ready', () => {
            if (window.Hls.isSupported()) {
                const hls = new window.Hls({ liveSyncDurationCount: 3, liveMaxLatencyDurationCount: 8 });
                hls.on(window.Hls.Events.ERROR, (_evt, data) => {
                    if (data && data.fatal) {
                        this.errorMsg = 'Error HLS: ' + (data.details || data.type);
                    }
                });
                hls.loadSource(this.hlsUrl);
                hls.attachMedia(player.media);
                this._hls = hls;
            } else if (player.media.canPlayType('application/vnd.apple.mpegurl')) {
                player.media.src = this.hlsUrl;
            } else {
                this.errorMsg = 'Tu navegador no soporta HLS.';
            }
        });
    },

    async copyUrl(text) {
        const value = text !== undefined ? text : this.hlsUrl;
        if (!value) return;
        try {
            await navigator.clipboard.writeText(value);
            this.copied = true;
            setTimeout(() => { this.copied = false; }, 2000);
        } catch (e) {
            const input = document.createElement('input');
            input.value = value;
            document.body.appendChild(input);
            input.select();
            try { document.execCommand('copy'); this.copied = true; setTimeout(() => { this.copied = false; }, 2000); } catch (_) {}
            document.body.removeChild(input);
        }
    },

    teardown() {
        if (this._hls) {
            try { this._hls.destroy(); } catch (e) {}
            this._hls = null;
        }
        if (this._player) {
            try { this._player.destroy(); } catch (e) {}
            this._player = null;
        }
        const video = this.$refs.videoEl;
        if (video) {
            try { video.pause(); video.removeAttribute('src'); video.load(); } catch (e) {}
        }
    },
});
</script>