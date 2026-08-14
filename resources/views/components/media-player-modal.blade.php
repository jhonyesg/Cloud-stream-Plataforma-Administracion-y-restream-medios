@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
@endphp

<x-app-modal name="play-media" title="Vista previa" subtitle="Visualización del archivo" maxWidth="3xl" :icon="$icon" iconBg="bg-sky-100" iconColor="text-sky-600">
    <div
        x-data="{
            playUrl: '',
            kind: 'video',
            filename: '',
            textContent: '',
            textLoading: false,
            init() {
                this.$watch('$store.modals.current', (val) => {
                    if (val === 'play-media') {
                        this.stopMedia();
                        const p = $store.modals.payload || {};
                        this.playUrl = p.play_url || '';
                        this.kind = p.kind || 'video';
                        this.filename = p.filename || '';
                        this.textContent = '';
                        this.textLoading = false;
                        if (this.isText) this.loadText();
                    } else {
                        this.stopMedia();
                    }
                });
            },
            stopMedia() {
                for (const ref of ['playerVideo', 'playerAudio']) {
                    const el = this.$refs[ref];
                    if (!el) continue;
                    try { el.pause(); } catch (e) {}
                    try { el.currentTime = 0; } catch (e) {}
                    try { el.removeAttribute('src'); el.load(); } catch (e) {}
                }
                this.playUrl = '';
                this.textContent = '';
            },
            get isText() {
                return this.kind === 'other' && this.filename.match(/\.(txt|py|js|json|xml|html|css|md|sh|bat|cfg|ini|conf|log|csv|sql|java|c|cpp|h|rb|go|rs|ts|yml|yaml)$/i);
            },
            get isImage() { return this.kind === 'image'; },
            get isVideo() { return this.kind === 'video' || this.kind === 'ad'; },
            get isAudio() { return this.kind === 'audio'; },
            async loadText() {
                this.textLoading = true;
                try {
                    const r = await fetch(this.playUrl);
                    if (r.ok) {
                        this.textContent = await r.text();
                    } else {
                        this.textContent = 'No se pudo cargar el archivo (HTTP ' + r.status + ')';
                    }
                } catch(e) {
                    this.textContent = 'Error al cargar el archivo.';
                } finally {
                    this.textLoading = false;
                }
            }
        }"
    >
        <div class="px-6 py-5">
            {{-- Video --}}
            <template x-if="isVideo">
                <div class="bg-black rounded-xl overflow-hidden flex items-center justify-center min-h-[200px] max-h-[70vh]">
                    <video x-ref="playerVideo" :src="playUrl" controls class="w-full max-h-[70vh]" autoplay></video>
                </div>
            </template>

            {{-- Audio --}}
            <template x-if="isAudio">
                <div class="bg-gradient-to-br from-amber-500/10 to-orange-500/5 rounded-xl p-8 flex flex-col items-center gap-4">
                    <div class="w-20 h-20 rounded-full bg-amber-100 flex items-center justify-center">
                        <svg class="w-10 h-10 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v12M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/></svg>
                    </div>
                    <audio x-ref="playerAudio" :src="playUrl" controls class="w-full" autoplay></audio>
                </div>
            </template>

            {{-- Image --}}
            <template x-if="isImage">
                <div class="rounded-xl overflow-hidden bg-gray-100 flex items-center justify-center max-h-[70vh]">
                    <img :src="playUrl" :alt="filename" class="max-w-full max-h-[70vh] object-contain">
                </div>
            </template>

            {{-- Text / code viewer --}}
            <template x-if="isText">
                <div class="rounded-xl overflow-hidden border border-gray-200">
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-100 border-b border-gray-200">
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide" x-text="filename"></span>
                        <button type="button" @click="loadText()"
                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                            Recargar
                        </button>
                    </div>
                    <div class="relative">
                        <div x-show="textLoading" class="p-8 text-center text-gray-400 text-sm">
                            <svg class="animate-spin w-5 h-5 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <p class="mt-2">Cargando…</p>
                        </div>
                        <pre x-show="!textLoading" x-text="textContent"
                             class="p-4 text-sm text-gray-800 font-mono whitespace-pre-wrap break-words max-h-[60vh] overflow-y-auto bg-white"></pre>
                    </div>
                </div>
            </template>

            {{-- Unsupported --}}
            <template x-if="!isVideo && !isAudio && !isImage && !isText">
                <div class="rounded-xl bg-gray-50 p-8 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gray-200 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <p class="text-gray-500 text-sm">No hay vista previa disponible para este tipo de archivo.</p>
                    <a :href="playUrl" download
                       class="inline-flex items-center gap-1.5 mt-3 px-3 py-1.5 rounded-lg bg-gray-200 text-gray-700 text-xs font-semibold hover:bg-gray-300 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descargar
                    </a>
                </div>
            </template>

            <p class="mt-3 text-sm text-gray-700 font-mono" x-text="filename"></p>
        </div>
    </div>
</x-app-modal>