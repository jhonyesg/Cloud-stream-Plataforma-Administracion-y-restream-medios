@props([])

<div
    x-data="virtualScreenEditor()"
    x-show="$store.modals && $store.modals.current === 'virtual-screen-editor'"
    x-cloak
    @keydown.escape.window="$store.modals.close()"
    class="fixed inset-0 z-50 overflow-y-auto"
>
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="$store.modals.close()"></div>

    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 flex flex-col max-h-[calc(100vh-2rem)] overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 shrink-0">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">
                        Pantalla Virtual
                        <span class="text-gray-400 font-normal" x-text="channelName ? '· ' + channelName : ''"></span>
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">Configura la resolución y el logo que se emiten por FFmpeg</p>
                </div>
                <button type="button" @click="$store.modals.close()" class="text-gray-400 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-5 py-5 space-y-5">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                    {{-- Left: config --}}
                    <div class="md:col-span-1 space-y-4">

                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Resolución</label>
                            <select x-model="resolutionPreset" @change="applyResolutionPreset()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                <option value="720p">720p (1280×720)</option>
                                <option value="1080p">1080p (1920×1080)</option>
                                <option value="custom">Personalizada</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Ancho</label>
                                <input type="number" x-model.number="width" min="320" max="7680" @input="updateRatio()" class="w-full px-2 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Alto</label>
                                <input type="number" x-model.number="height" min="240" max="4320" @input="updateRatio()" class="w-full px-2 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">FPS</label>
                            <select x-model.number="fps" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                <option :value="24">24</option>
                                <option :value="25">25</option>
                                <option :value="30">30</option>
                                <option :value="50">50</option>
                                <option :value="60">60</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Protocolo de salida</label>
                            <select x-model="outputProtocol" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                <option value="rtmp">RTMP</option>
                                <option value="srt">SRT</option>
                                <option value="hls">HLS</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">URL de salida</label>
                            <input type="text" x-model="outputUrl"
                                :placeholder="outputProtocol === 'srt' ? 'srt://127.0.0.1:10080?streamid=#!::r=live/canal,m=publish&pkt_size=1316&latency=120' : outputProtocol === 'rtmp' ? 'rtmp://127.0.0.1:1935/live/canal' : '/stream/canal.m3u8'"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs font-mono focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">URL pública HLS (player)</label>
                            <input type="url" x-model="publicHlsUrl"
                                placeholder="https://cdn.dominio.com/live/canal.m3u8"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs font-mono focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                            <p class="mt-1 text-[10px] text-gray-400">URL .m3u8 que consumen los reproductores (Replanet, etc.)</p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Video kbps</label>
                                <select x-model.number="videoBitrate" class="w-full px-2 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                    <option value="500">500</option>
                                    <option value="1000">1000</option>
                                    <option value="1500">1500</option>
                                    <option value="2000">2000</option>
                                    <option value="2500">2500</option>
                                    <option value="3000">3000</option>
                                    <option value="4000">4000</option>
                                    <option value="5000">5000</option>
                                    <option value="6000">6000</option>
                                    <option value="8000">8000</option>
                                    <option value="10000">10000</option>
                                    <option value="12000">12000</option>
                                    <option value="15000">15000</option>
                                    <option value="20000">20000</option>
                                    <option value="25000">25000</option>
                                    <option value="30000">30000</option>
                                    <option value="40000">40000</option>
                                    <option value="50000">50000</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Audio kbps</label>
                                <select x-model.number="audioBitrate" class="w-full px-2 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                    <option value="32">32</option>
                                    <option value="64">64</option>
                                    <option value="96">96</option>
                                    <option value="128">128</option>
                                    <option value="160">160</option>
                                    <option value="192">192</option>
                                    <option value="224">224</option>
                                    <option value="256">256</option>
                                    <option value="320">320</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Codec video</label>
                                <select x-model="codecVideo" class="w-full px-2 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                    <option value="libx264">libx264 (H.264)</option>
                                    <option value="libx265">libx265 (H.265)</option>
                                    <option value="libvpx-vp9">libvpx-vp9 (VP9)</option>
                                    <option value="copy">copy (sin reencode)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Codec audio</label>
                                <select x-model="codecAudio" class="w-full px-2 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                    <option value="aac">aac</option>
                                    <option value="libmp3lame">libmp3lame (MP3)</option>
                                    <option value="libopus">libopus (Opus)</option>
                                    <option value="libvorbis">libvorbis (Vorbis)</option>
                                    <option value="copy">copy (sin reencode)</option>
                                </select>
                            </div>
                        </div>

                        <div x-show="codecVideo !== 'copy'">
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Preset video (velocidad / calidad)</label>
                            <select x-model="videoPreset" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                <option value="ultrafast">ultrafast (más rápido, menos calidad)</option>
                                <option value="superfast">superfast</option>
                                <option value="veryfast">veryfast (recomendado)</option>
                                <option value="faster">faster</option>
                                <option value="fast">fast</option>
                                <option value="medium">medium</option>
                                <option value="slow">slow</option>
                                <option value="slower">slower</option>
                                <option value="veryslow">veryslow (más lento, mejor calidad)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Center: canvas --}}
                    <div class="md:col-span-2 space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider">Preview del canvas</label>
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <span x-text="width + '×' + height"></span>
                            </div>
                        </div>

                        {{-- Canvas --}}
                        <div class="bg-gray-900 rounded-xl p-4 flex items-center justify-center min-h-[280px]">
                            <div
                                class="relative bg-gray-800 rounded-lg overflow-hidden cursor-default select-none ring-1 ring-white/10"
                                :style="'aspect-ratio: ' + width + ' / ' + height + '; width: 100%; max-width: ' + canvasMaxWidth + 'px'"
                                x-ref="canvas"
                                @mousedown="startMove($event)"
                                @mousemove="onMove($event)"
                                @mouseup="endDrag()"
                                @mouseleave="endDrag()"
                            >
                                {{-- Placeholder grid pattern --}}
                                <div class="absolute inset-0 opacity-10" style="background-image: linear-gradient(45deg, #fff 25%, transparent 25%), linear-gradient(-45deg, #fff 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #fff 75%), linear-gradient(-45deg, transparent 75%, #fff 75%); background-size: 20px 20px; background-position: 0 0, 0 10px, 10px -10px, -10px 0px;"></div>

                                {{-- Logo element --}}
                                <template x-if="logoMediaItemId && logoUrl">
                                    <img
                                        :src="logoUrl"
                                        class="absolute pointer-events-none select-none"
                                        :style="'left: 0; top: 0; width: ' + logoDisplayW + 'px; height: ' + logoDisplayH + 'px; transform: translate(' + logoDisplayX + 'px, ' + logoDisplayY + 'px); opacity: ' + logoOpacity + '; border: 1px dashed rgba(255,255,255,0.3)'"
                                        draggable="false"
                                        x-ref="logoImg"
                                    >
                                </template>

                                {{-- Logo resize handles (only when logo present) --}}
                                <template x-if="logoMediaItemId && logoUrl">
                                    <div @mousedown.stop>
                                        <template x-for="h in ['nw','n','ne','e','se','s','sw','w']" :key="h">
                                            <div
                                                class="absolute w-3 h-3 bg-emerald-400 rounded-full border-2 border-white shadow cursor-pointer"
                                                :style="handleStyle(h)"
                                                @mousedown.stop="startResize($event, h)"
                                            ></div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Dashed placeholder when no logo --}}
                                <template x-if="!logoMediaItemId || !logoUrl">
                                    <div class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">
                                        <div class="border-2 border-dashed border-gray-600 rounded-xl px-6 py-4 text-center">
                                            <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <p>Sube o elige un logo<br>para verlo aquí</p>
                        </div>
                    </div>

                    {{-- 7.5 Fallback branch + 4K warning --}}
                    <div class="mt-4 p-3 rounded-lg bg-gray-50 ring-1 ring-gray-200 space-y-2">
                        <div class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Fuente de respaldo (fallback)</div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-400 uppercase mb-1">Tipo</label>
                                <select x-model="fallbackType" class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-xs bg-white">
                                    <option value="black">Negro (videotestsrc)</option>
                                    <option value="test_pattern">Patrón de prueba</option>
                                    <option value="image_loop">Imagen en loop</option>
                                    <option value="media">Media personalizada</option>
                                </select>
                            </div>
                            <div x-show="fallbackType === 'media'">
                                <label class="block text-[10px] font-semibold text-gray-400 uppercase mb-1">Media item (fallback)</label>
                                <select x-model="fallbackMediaItemId" class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-xs bg-white">
                                    <option value="">— elegir —</option>
                                    <template x-for="m in mediaItems.filter(i => i.kind === 'image' || i.kind === 'video' || i.kind === 'slate')" :key="m.id">
                                        <option :value="m.id" x-text="m.filename"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div x-show="width > 1920 || height > 1080"
                             class="mt-2 p-2 rounded-md bg-amber-50 border border-amber-200 text-[11px] text-amber-800">
                            ⚠ Resolución 4K en esta VPS puede saturar CPU con varios canales. Se recomienda 720p (1280×720) o 1080p (1920×1080) en libx264 veryfast.
                        </div>
                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Logo controls --}}
                        <div class="bg-gray-50 rounded-xl p-3 space-y-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-semibold text-gray-600 mr-1">Logo:</span>
                                <button type="button" @click="pickFromLibrary()" class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-200 rounded-lg hover:border-emerald-400 hover:bg-emerald-50 transition">
                                    📁 Desde biblioteca
                                </button>
                                <button type="button" @click="$store.modals.open('upload-media', { channel_id: channelId, onUploaded: 'virtual-screen-logo' })" class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-200 rounded-lg hover:border-emerald-400 hover:bg-emerald-50 transition">
                                    ⬆ Subir nuevo
                                </button>
                                <button type="button" x-show="logoMediaItemId" @click="clearLogo()" class="px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-gray-200 rounded-lg hover:bg-red-50 transition">
                                    Quitar
                                </button>
                                <span x-show="logoMediaItemId" class="text-xs text-gray-500 truncate max-w-[180px]" x-text="logoFilename || 'logo'"></span>
                            </div>

                            <div x-show="logoMediaItemId" class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Opacidad <span x-text="(logoOpacity * 100).toFixed(0) + '%'"></span></label>
                                    <input type="range" x-model.number="logoOpacity" min="0.1" max="1.0" step="0.05" class="w-full accent-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Ancho logo <span x-text="logoW + 'px'"></span></label>
                                    <input type="range" x-model.number="logoW" min="16" :max="width" step="1" class="w-full accent-emerald-500" @input="logoH = Math.round(logoW * logoAspect)">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- FFmpeg preview --}}
                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Comando FFmpeg (lo que el engine usará)</label>
                    <pre class="bg-gray-900 text-emerald-300 text-xs font-mono rounded-xl p-3 overflow-x-auto whitespace-pre-wrap" x-text="ffmpegCommand()"></pre>
                </div>

                {{-- Vista previa error (si falla la generación antes de abrir el visor) --}}
                <div x-show="testError" x-transition class="bg-red-50 rounded-xl p-3">
                    <label class="block text-[11px] font-bold text-red-600 uppercase tracking-wider mb-1.5">Error al generar la vista previa</label>
                    <pre x-text="testError" class="text-xs text-red-700 whitespace-pre-wrap font-mono"></pre>
                </div>

                {{-- Errors --}}
                <div x-show="Object.keys(errors).length > 0" class="bg-red-50 rounded-xl p-3">
                    <ul class="text-xs text-red-600 list-disc list-inside space-y-0.5">
                        <template x-for="(msg, key) in errors" :key="key">
                            <li x-text="key + ': ' + msg"></li>
                        </template>
                    </ul>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-t border-gray-100 shrink-0 bg-gray-50/50">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="testPreview()"
                        :disabled="testing || saving"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition"
                    >
                        <svg class="w-4 h-4" :class="testing ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span x-text="testing ? 'Generando…' : 'Vista previa'"></span>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="$store.modals.close()" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition">Cancelar</button>
                    <button
                        type="button"
                        @click="save()"
                        :disabled="saving"
                        class="inline-flex items-center gap-2 px-5 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-500 disabled:opacity-50 transition shadow-sm"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span x-text="saving ? 'Guardando…' : 'Guardar'"></span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    {{-- Hidden media picker modal --}}
    <div
        x-show="showPicker"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
    >
        <div class="fixed inset-0 bg-gray-900/70" @click="showPicker = false"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[80vh] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b">
                <h4 class="font-semibold text-gray-800">Elegir logo de la biblioteca</h4>
                <button @click="showPicker = false" class="text-gray-400 hover:text-gray-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <div x-show="pickerLoading" class="text-center text-gray-400 py-8 text-sm">Cargando imágenes…</div>
                <div x-show="!pickerLoading && pickerItems.length === 0" class="text-center text-gray-400 py-8 text-sm">No hay imágenes en este canal.</div>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                    <template x-for="item in pickerItems" :key="item.id">
                        <button
                            type="button"
                            @click="selectLogo(item)"
                            class="aspect-square rounded-lg overflow-hidden ring-2 ring-transparent hover:ring-emerald-400 transition"
                        >
                            <img :src="item.thumb_url" class="w-full h-full object-cover" :alt="item.filename">
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function virtualScreenEditor() {
        return {
            channelId: null,
            channelName: '',
            width: 1280,
            height: 720,
            fps: 30,
            outputProtocol: 'rtmp',
            outputUrl: '',
            publicHlsUrl: '',
            videoBitrate: 2500,
            audioBitrate: 128,
            codecVideo: 'libx264',
            codecAudio: 'aac',
            videoPreset: 'veryfast',
            fallbackType: 'black',
            fallbackMediaItemId: null,
            logoMediaItemId: null,
            logoUrl: null,
            logoFilename: null,
            logoX: 40,
            logoY: 40,
            logoW: 200,
            logoH: 80,
            logoOpacity: 0.85,
            logoAspect: 2.5,
            resolutionPreset: '720p',
            canvasMaxWidth: 520,
            errors: {},
            saving: false,
            testing: false,
            testError: null,
            dragMode: null,
            dragStart: null,
            dragOrig: null,
            showPicker: false,
            pickerLoading: false,
            pickerItems: [],

            init() {
                this.$watch('$store.modals.current', (val) => {
                    if (val === 'virtual-screen-editor') {
                        const p = this.$store.modals.payload || {};
                        this.channelId = p.channel_id || null;
                        this.channelName = p.channel_name || '';
                        this.testError = null;
                        this.errors = {};
                        if (this.channelId) this.load();
                    }
                });
            },

            get logoDisplayX() { return this.toScale(this.logoX); },
            get logoDisplayY() { return this.toScale(this.logoY); },
            get logoDisplayW() { return this.toScale(this.logoW); },
            get logoDisplayH() { return this.toScale(this.logoH); },

            toScale(v) {
                const canvas = this.$refs.canvas;
                if (!canvas) return v;
                const realW = canvas.offsetWidth || 1;
                return v * (realW / this.width);
            },
            toReal(v) {
                const canvas = this.$refs.canvas;
                if (!canvas) return v;
                const realW = canvas.offsetWidth || 1;
                return Math.round(v * (this.width / realW));
            },

            updateRatio() {
                if (this.width === 1280 && this.height === 720) this.resolutionPreset = '720p';
                else if (this.width === 1920 && this.height === 1080) this.resolutionPreset = '1080p';
                else this.resolutionPreset = 'custom';
            },
            applyResolutionPreset() {
                if (this.resolutionPreset === '720p') { this.width = 1280; this.height = 720; }
                else if (this.resolutionPreset === '1080p') { this.width = 1920; this.height = 1080; }
            },

            async load() {
                try {
                    const r = await fetch('/api/virtual-screens/' + this.channelId, { headers: { 'Accept': 'application/json' } });
                    if (r.status === 404) {
                    this.width = 1280; this.height = 720; this.fps = 30;
                    this.outputProtocol = 'rtmp'; this.outputUrl = '';
                    this.logoMediaItemId = null; this.logoUrl = null;
                    this.logoX = 40; this.logoY = 40; this.logoW = 200; this.logoH = 80;
                    this.logoOpacity = 0.85;
                    this.resolutionPreset = '720p';
                    this.videoPreset = 'veryfast';
                    return;
                    }
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    const d = await r.json();
                    this.width = d.width || 1280;
                    this.height = d.height || 720;
                    this.fps = d.fps || 30;
                    this.outputProtocol = d.output_protocol || 'rtmp';
                    this.outputUrl = d.output_url || '';
                    this.publicHlsUrl = d.channel_public_hls_url || '';
                    this.videoBitrate = d.video_bitrate_kbps || 2500;
                    this.audioBitrate = d.audio_bitrate_kbps || 128;
                    this.codecVideo = d.codec_video || 'libx264';
                    this.codecAudio = d.codec_audio || 'aac';
                    this.videoPreset = d.video_preset || 'veryfast';
                    this.logoMediaItemId = d.logo_media_item_id || null;
                    this.logoX = d.logo_x ?? 40;
                    this.logoY = d.logo_y ?? 40;
                    this.logoW = d.logo_w ?? 200;
                    this.logoH = d.logo_h ?? 80;
                    this.logoOpacity = d.logo_opacity ?? 0.85;
                    if (d.media_item) {
                        this.logoUrl = d.media_item.play_url || d.media_item.thumb_path || null;
                        this.logoFilename = d.media_item.filename || 'logo';
                        if (this.logoUrl) {
                            const img = new Image();
                            img.onload = () => { this.logoAspect = img.naturalWidth / Math.max(1, img.naturalHeight); };
                            img.src = this.logoUrl;
                        }
                    } else {
                        this.logoUrl = null; this.logoFilename = null;
                    }
                    this.updateRatio();
                } catch (e) { this.testError = 'No se pudo cargar la config: ' + e.message; }
            },

            startMove(e) {
                if (!this.logoMediaItemId || !this.logoUrl) return;
                if (e.target.classList.contains('rounded-full')) return;
                this.dragMode = 'move';
                this.dragStart = { x: e.clientX, y: e.clientY };
                this.dragOrig = { x: this.logoX, y: this.logoY };
            },
            startResize(e, handle) {
                e.preventDefault();
                this.dragMode = 'resize:' + handle;
                this.dragStart = { x: e.clientX, y: e.clientY };
                this.dragOrig = { x: this.logoX, y: this.logoY, w: this.logoW, h: this.logoH };
            },
            onMove(e) {
                if (!this.dragMode) return;
                const dx = this.toReal(e.clientX - this.dragStart.x);
                const dy = this.toReal(e.clientY - this.dragStart.y);
                if (this.dragMode === 'move') {
                    this.logoX = Math.max(0, Math.min(this.width - this.logoW, this.dragOrig.x + dx));
                    this.logoY = Math.max(0, Math.min(this.height - this.logoH, this.dragOrig.y + dy));
                } else {
                    const h = this.dragMode.split(':')[1];
                    let nx = this.dragOrig.x, ny = this.dragOrig.y, nw = this.dragOrig.w, nh = this.dragOrig.h;
                    if (h.includes('e')) nw = Math.max(16, this.dragOrig.w + dx);
                    if (h.includes('s')) nh = Math.max(16, this.dragOrig.h + dy);
                    if (h.includes('w')) { nw = Math.max(16, this.dragOrig.w - dx); nx = this.dragOrig.x + (this.dragOrig.w - nw); }
                    if (h.includes('n')) { nh = Math.max(16, this.dragOrig.h - dy); ny = this.dragOrig.y + (this.dragOrig.h - nh); }
                    if (h === 'nw' || h === 'ne' || h === 'sw' || h === 'se') {
                        const ar = this.dragOrig.w / Math.max(1, this.dragOrig.h);
                        if (Math.abs(dx) > Math.abs(dy)) { nh = Math.round(nw / ar); if (h.includes('n')) ny = this.dragOrig.y + (this.dragOrig.h - nh); }
                        else { nw = Math.round(nh * ar); if (h.includes('w')) nx = this.dragOrig.x + (this.dragOrig.w - nw); }
                    }
                    this.logoX = Math.max(0, nx); this.logoY = Math.max(0, ny);
                    this.logoW = Math.min(this.width, nw); this.logoH = Math.min(this.height, nh);
                }
            },
            endDrag() { this.dragMode = null; },

            handleStyle(h) {
                const px = this.logoDisplayX, py = this.logoDisplayY, pw = this.logoDisplayW, ph = this.logoDisplayH;
                const off = -6;
                const map = {
                    nw: [px + off, py + off], n: [px + pw/2 + off, py + off], ne: [px + pw + off, py + off],
                    e: [px + pw + off, py + ph/2 + off], se: [px + pw + off, py + ph + off],
                    s: [px + pw/2 + off, py + ph + off], sw: [px + off, py + ph + off], w: [px + off, py + ph/2 + off],
                };
                const [x, y] = map[h];
                return 'left:' + x + 'px;top:' + y + 'px;cursor:' + ({nw:'nwse-resize',ne:'nesw-resize',sw:'nesw-resize',se:'nwse-resize',n:'ns-resize',s:'ns-resize',e:'ew-resize',w:'ew-resize'}[h]);
            },

            async pickFromLibrary() {
                this.showPicker = true;
                this.pickerLoading = true;
                try {
                    const r = await fetch('/api/media-items?channel_id=' + this.channelId + '&kind=image&per_page=100', { headers: { 'Accept': 'application/json' } });
                    const d = await r.json();
                    this.pickerItems = d.data || d || [];
                } catch (e) { this.pickerItems = []; }
                this.pickerLoading = false;
            },
            selectLogo(item) {
                this.logoMediaItemId = item.id;
                this.logoUrl = item.play_url || item.thumb_url || null;
                this.logoFilename = item.filename || 'logo';
                if (this.logoUrl) {
                    const img = new Image();
                    img.onload = () => {
                        this.logoAspect = img.naturalWidth / Math.max(1, img.naturalHeight);
                        this.logoW = Math.min(this.logoW, this.width);
                        this.logoH = Math.round(this.logoW / this.logoAspect);
                    };
                    img.src = this.logoUrl;
                }
                this.showPicker = false;
            },
            clearLogo() {
                this.logoMediaItemId = null;
                this.logoUrl = null;
                this.logoFilename = null;
            },

ffmpegCommand() {
                const src = '<item.mp4>';
                let parts = ['ffmpeg -re -fflags +genpts -i ' + src];
                if (this.logoMediaItemId && this.logoUrl) {
                    parts.push('-i ' + (this.logoFilename || 'logo.png'));
                    const op = (this.logoOpacity || 1).toFixed(2);
                    const filter = '[1:v]scale=' + this.logoW + ':' + this.logoH + ',format=rgba,colorchannelmixer=aa=' + op + '[lg];[0:v]scale=' + this.width + ':' + this.height + ':force_original_aspect_ratio=decrease,pad=' + this.width + ':' + this.height + ':(ow-iw)/2:(oh-ih)/2[scaled];[scaled][lg]overlay=' + this.logoX + ':' + this.logoY + '[outv]';
                    parts.push('-filter_complex "' + filter + '"');
                    parts.push('-map [outv] -map 0:a?');
                } else {
                    parts.push('-map 0:v -map 0:a?');
                    parts.push('-vf "scale=' + this.width + ':' + this.height + ':force_original_aspect_ratio=decrease,pad=' + this.width + ':' + this.height + ':(ow-iw)/2:(oh-ih)/2"');
                }

                // Video codec
                if (this.codecVideo === 'copy') {
                    parts.push('-c:v copy');
                } else {
                    let videoLine = '-c:v ' + this.codecVideo;
                    if (this.codecVideo === 'libx264' || this.codecVideo === 'libx265') {
                        videoLine += ' -preset ' + (this.videoPreset || 'veryfast');
                    }
                    videoLine += ' -b:v ' + this.videoBitrate + 'k -r ' + this.fps;
                    parts.push(videoLine);
                }

                // Audio codec
                if (this.codecAudio === 'copy') {
                    parts.push('-c:a copy');
                } else {
                    parts.push('-c:a ' + this.codecAudio + ' -ar 44100 -b:a ' + this.audioBitrate + 'k');
                }

                if (this.outputProtocol === 'rtmp') {
                    parts.push('-f flv ' + (this.outputUrl || 'rtmp://127.0.0.1:1935/live/canal'));
                } else if (this.outputProtocol === 'srt') {
                    parts.push('-f mpegts ' + (this.outputUrl || 'srt://127.0.0.1:10080?streamid=#!::r=live/canal,m=publish&pkt_size=1316&latency=120'));
                } else {
                    parts.push('-f hls ' + (this.outputUrl || '/stream/canal.m3u8'));
                }
                return parts.join(' \\\n  ');
            },

            async testPreview() {
                this.testing = true; this.testError = null;
                try {
                    const r = await fetch('/api/virtual-screens/' + this.channelId + '/test-preview', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'image/png', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!r.ok) {
                        const d = await r.json().catch(() => ({}));
                        this.testError = d.message || ('HTTP ' + r.status);
                    } else {
                        const blob = await r.blob();
                        const blobUrl = URL.createObjectURL(blob);
                        Alpine.store('modals').open('virtual-screen-preview-viewer', {
                            blobUrl,
                            width: this.width,
                            height: this.height,
                            channelName: this.channelName,
                            generatedAt: new Date().toISOString(),
                            channel_id: this.channelId,
                        });
                    }
                } catch (e) { this.testError = 'Error de red: ' + e.message; }
                this.testing = false;
            },

            async save() {
                this.saving = true; this.errors = {};
                const body = {
                    name: this.channelName || 'Pantalla ' + (this.channelName || ''),
                    width: this.width, height: this.height, fps: this.fps,
                    output_protocol: this.outputProtocol, output_url: this.outputUrl,
                    public_hls_url: this.publicHlsUrl,
                    video_bitrate_kbps: this.videoBitrate, audio_bitrate_kbps: this.audioBitrate,
                    codec_video: this.codecVideo, codec_audio: this.codecAudio, video_preset: this.videoPreset,
                    logo_media_item_id: this.logoMediaItemId,
                    logo_x: this.logoX, logo_y: this.logoY,
                    logo_w: this.logoW, logo_h: this.logoH,
                    logo_opacity: this.logoOpacity,
                };
                try {
                    const r = await fetch('/api/virtual-screens/' + this.channelId, {
                        method: 'PUT',
                        headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(body),
                    });
                    if (r.ok) {
                        window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Pantalla virtual guardada.' } }));
                        this.$store.modals.close();
                    } else if (r.status === 422) {
                        const d = await r.json();
                        this.errors = d.errors || {};
                    } else {
                        const d = await r.json().catch(() => ({}));
                        this.errors = { general: [d.message || 'Error al guardar'] };
                    }
                } catch (e) { this.errors = { general: ['Error de red: ' + e.message] }; }
                this.saving = false;
            },
        };
    }
</script>

<style>[x-cloak] { display: none !important; }</style>