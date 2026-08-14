<div
    x-data="{
        modalName: 'virtual-screen-preview-viewer',
        blobUrl: null,
        width: 0,
        height: 0,
        channelName: '',
        generatedAt: null,
        loading: false,
        error: null,

        get isTop() {
            if (!$store.modals || !$store.modals.stack || $store.modals.stack.length === 0) return false;
            const top = $store.modals.stack[$store.modals.stack.length - 1];
            return top && top.name === this.modalName;
        },
        get isOpen() {
            if (!$store.modals || !$store.modals.stack) return false;
            return $store.modals.stack.some(e => e.name === this.modalName);
        },
        get zIndex() {
            if (!$store.modals || !$store.modals.stack) return 50;
            const idx = $store.modals.stack.findIndex(e => e.name === this.modalName);
            if (idx === -1) return 50;
            return 50 + idx * 10;
        },

        init() {
            this.$watch('$store.modals.current', (val) => {
                if (val === this.modalName) {
                    this.error = null;
                    const p = this.$store.modals.payload || {};
                    if (p.blobUrl) {
                        if (this.blobUrl) URL.revokeObjectURL(this.blobUrl);
                        this.blobUrl = p.blobUrl;
                        this.width = p.width || 0;
                        this.height = p.height || 0;
                        this.channelName = p.channelName || '';
                        this.generatedAt = p.generatedAt || null;
                    }
                }
            });
        },

        formatDate(d) {
            if (!d) return '';
            try { return new Date(d).toLocaleString(); } catch (e) { return ''; }
        },

        async regenerate() {
            if (!window.csrfToken) { this.error = 'CSRF token no disponible.'; return; }
            const payload = this.$store.modals.payload || {};
            const channelId = payload.channel_id;
            if (!channelId) { this.error = 'No hay canal seleccionado.'; return; }

            this.loading = true;
            this.error = null;
            try {
                const r = await fetch('/api/virtual-screens/' + channelId + '/test-preview', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.csrfToken,
                        'Accept': 'image/png',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!r.ok) {
                    const d = await r.json().catch(() => ({}));
                    this.error = d.message || ('HTTP ' + r.status);
                } else {
                    const blob = await r.blob();
                    if (this.blobUrl) URL.revokeObjectURL(this.blobUrl);
                    this.blobUrl = URL.createObjectURL(blob);
                    this.generatedAt = new Date().toISOString();
                    if (!this.width || !this.height) {
                        const img = new Image();
                        img.onload = () => { this.width = img.naturalWidth; this.height = img.naturalHeight; };
                        img.src = this.blobUrl;
                    }
                }
            } catch (e) {
                this.error = 'Error de red: ' + e.message;
            }
            this.loading = false;
        },

        closeViewer() {
            if (this.blobUrl) {
                URL.revokeObjectURL(this.blobUrl);
                this.blobUrl = null;
            }
            Alpine.store('modals').close();
        },
    }"
    x-show="isOpen"
    :style="{ zIndex: zIndex }"
    @keydown.escape.window="if (isTop) closeViewer()"
    class="fixed inset-0"
    x-cloak
>
    {{-- Backdrop --}}
    <div
        x-show="isTop"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 bg-black/95 z-0"
    ></div>

    {{-- Top bar: title + close --}}
    <div
        x-show="isTop"
        class="absolute top-0 inset-x-0 z-20 flex items-center justify-between px-6 py-4 text-white"
    >
        <div class="flex items-center gap-3 text-sm">
            <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <span class="font-semibold">Vista previa</span>
            <template x-if="channelName">
                <span class="opacity-60">· <span x-text="channelName"></span></span>
            </template>
        </div>
        <button
            type="button"
            @click="closeViewer()"
            class="p-2 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition"
            aria-label="Cerrar"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- Image stage --}}
    <div class="absolute inset-0 z-10 flex items-center justify-center px-6 pt-16 pb-24 pointer-events-none">
        <template x-if="blobUrl">
            <img
                :src="blobUrl"
                alt="Vista previa de la pantalla virtual"
                class="max-w-full max-h-full object-contain select-none"
                :style="'width: ' + width + 'px; height: ' + height + 'px; max-width: 95vw; max-height: calc(100vh - 160px);'"
            >
        </template>
        <template x-if="!blobUrl && loading">
            <div class="flex flex-col items-center gap-3 text-white/80 text-sm">
                <svg class="animate-spin w-8 h-8" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span>Regenerando…</span>
            </div>
        </template>
        <template x-if="!blobUrl && !loading && !error">
            <div class="text-center text-white/50 text-sm">No hay imagen para mostrar.</div>
        </template>
        <template x-if="error">
            <div class="text-center text-red-300 text-sm max-w-md">
                <pre x-text="error" class="p-4 rounded-lg bg-red-950/50 border border-red-800/40 font-mono whitespace-pre-wrap"></pre>
            </div>
        </template>
    </div>

    {{-- Bottom bar: metadata + actions --}}
    <div
        x-show="isTop"
        class="absolute bottom-0 inset-x-0 z-20 px-6 py-4"
    >
        <div class="mx-auto max-w-5xl rounded-xl bg-white/10 backdrop-blur-md border border-white/10 px-5 py-3 flex items-center justify-between gap-4 text-white">
            <div class="flex items-center gap-3 flex-wrap text-sm">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white/10 font-mono">
                    <span x-text="width + '×' + height"></span>
                </span>
                <template x-if="generatedAt">
                    <span class="opacity-70">
                        Generado <span x-text="formatDate(generatedAt)"></span>
                    </span>
                </template>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="regenerate()"
                    :disabled="loading"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-white/10 border border-white/20 rounded-lg hover:bg-white/20 disabled:opacity-50 transition"
                >
                    <svg class="w-4 h-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span x-text="loading ? 'Regenerando…' : 'Regenerar'"></span>
                </button>
                <a
                    :href="blobUrl || '#'"
                    download="preview.png"
                    :class="!blobUrl ? 'pointer-events-none opacity-50' : ''"
                    class="inline-flex items-center gap-2 px-5 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-500 transition shadow-sm"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descargar PNG
                </a>
            </div>
        </div>
    </div>
</div>

<style>[x-cloak] { display: none !important; }</style>