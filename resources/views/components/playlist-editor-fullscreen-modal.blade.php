@include('components.playlist-editor-modal-js')
@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v12M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/></svg>';
@endphp

<div
    x-show="$store.modals.current === 'playlist-editor'"
    x-cloak
    class="fixed inset-0 z-50 bg-gray-900/70 backdrop-blur-sm overflow-y-auto"
    @keydown.escape.window="$store.modals.current === 'playlist-editor' && $store.modals.close()"
>
    <div
        x-data="playlistEditor()"
        x-init="init($dispatch)"
        class="bg-white min-h-screen flex flex-col"
    >
        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-teal-50 to-cyan-50 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center text-teal-600">
                {!! $icon !!}
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <input type="text" x-model="name" @blur="rename()" @keydown.enter="rename()"
                           class="text-lg font-semibold text-gray-900 bg-transparent border-b border-transparent hover:border-gray-300 focus:border-teal-500 focus:outline-none px-1 -ml-1"
                           placeholder="Nombre de playlist">
                    <button type="button" @click="rename()" title="Guardar nombre" class="p-1.5 rounded text-teal-600 hover:bg-teal-100 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </div>
                <p class="text-xs text-gray-500" x-text="statsText"></p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <div class="px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-center">
                    <div class="text-[10px] text-gray-400 uppercase tracking-wider">Total</div>
                    <div class="font-bold text-gray-900" x-text="formatDur(totalDuration)"></div>
                </div>
                <div class="px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-center">
                    <div class="text-[10px] text-gray-400 uppercase tracking-wider">Loops/día</div>
                    <div class="font-bold text-teal-600" x-text="'~' + loopCount.toFixed(1)"></div>
                </div>
                <div class="px-3 py-1.5 rounded-lg bg-white border text-center"
                     :class="exceeds24h ? 'border-red-300 bg-red-50' : 'border-gray-200'">
                    <div class="text-[10px] uppercase tracking-wider" :class="exceeds24h ? 'text-red-600' : 'text-gray-400'" x-text="exceeds24h ? 'Excede 24h' : 'Termina a las'"></div>
                    <div class="font-bold font-mono text-xs" :class="exceeds24h ? 'text-red-700' : 'text-gray-900'" x-text="formatTotalClock(totalDuration)"></div>
                </div>
                <div class="px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-center">
                    <div class="text-[10px] text-gray-400 uppercase tracking-wider">Items</div>
                    <div class="font-bold text-gray-900" x-text="items.length"></div>
                </div>
            </div>
            <button type="button" @click="save()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-500 active:scale-95 shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Guardar
            </button>
            <button type="button" @click="cancel()" title="Cerrar"
                    class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <button type="button" @click="cleanupOrphanSplits()"
                    title="Volver a unir las mitades de películas divididas cuando no haya cuña entre ellas"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-indigo-300 text-indigo-700 text-xs font-semibold rounded-lg hover:bg-indigo-50 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Limpiar splits
            </button>
            <button type="button" @click="resetToSequential()"
                    title="Borrar start_sec de los videos que no formen parte de splits (útil cuando datos heredados rompen el orden)"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-amber-300 text-amber-700 text-xs font-semibold rounded-lg hover:bg-amber-50 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h11M9 21V3l-6 7 6 7m11-10h-2m2 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Reset secuencial
            </button>
        </div>

        {{-- Body: 2 columns + timeline --}}
        <div class="flex-1 flex flex-col overflow-hidden">
            {{-- TIMELINE (top) --}}
            <div class="border-b border-gray-200 bg-white">
                <div class="px-4 py-2 flex items-center justify-between border-b border-gray-100">
                    <h3 class="text-xs font-bold text-gray-600 uppercase tracking-wider">Timeline del día (00:00 — 24:00)</h3>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="togglePlay()" class="p-2 rounded-lg transition"
                                :class="isPlaying ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-green-100 text-green-700 hover:bg-green-200'">
                            <svg x-show="!isPlaying" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            <svg x-show="isPlaying" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>
                        </button>
                        <button type="button" @click="stop()" class="p-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12"/></svg>
                        </button>
                        <select x-model.number="speed" class="px-2 py-1.5 border border-gray-200 rounded-lg text-xs">
                            <option value="1">1x</option>
                            <option value="2">2x</option>
                            <option value="4">4x</option>
                            <option value="8">8x</option>
                            <option value="30">30x</option>
                        </select>
                        <span class="px-3 py-1.5 rounded-lg bg-gray-100 font-mono text-xs font-bold" x-text="formatClock(playheadPosition)"></span>
                    </div>
                </div>

                {{-- Timeline track --}}
                <div class="relative h-24 bg-gray-50 border-t border-gray-200 overflow-hidden cursor-pointer" x-ref="timelineEl"
                     @click="seekTimelineFromEvent($event)">
                    {{-- Hour markers --}}
                    <div class="absolute inset-0 pointer-events-none">
                        @for($h = 0; $h <= 24; $h += 6)
                            <div class="absolute top-0 bottom-0 border-l border-gray-300 text-[10px] text-gray-400 font-mono pl-1 pt-1"
                                 style="left: {{ ($h / 24) * 100 }}%">
                                {{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00
                            </div>
                        @endfor
                    </div>

                    {{-- Item blocks (rendered by Alpine) --}}
                    <div class="absolute inset-0 pt-3" x-html="timelineBlocksHtml"></div>

                    {{-- Playhead --}}
                    <div class="absolute top-0 bottom-0 w-0.5 bg-teal-500 shadow-[0_0_8px_rgba(20,184,166,0.6)] pointer-events-none"
                         :style="'left: ' + playheadPositionPercent + '%'"></div>
                </div>
            </div>

            {{-- PREVIEW PLAYER --}}
            <div class="border-b border-gray-200 bg-gradient-to-b from-gray-900 to-gray-800 px-4 py-3">
                <div class="flex items-center gap-4">
                    <div x-ref="previewContainer" class="relative w-72 aspect-video bg-black rounded-lg overflow-hidden shrink-0 ring-1 ring-gray-700 shadow-lg">
                        <video x-ref="previewVideoA"
                               class="absolute inset-0 w-full h-full object-contain bg-black transition-opacity duration-300"
                               :class="activePlayer === 'A' ? 'opacity-100 z-10' : 'opacity-0 z-0'"
                               preload="metadata"
                               @loadedmetadata="onPreviewLoaded()"
                               @timeupdate="onPreviewTimeUpdate()"
                               @ended="onPreviewEnded()"
                               @waiting="previewLoading = true"
                               @playing="previewLoading = false"
                               @canplay="previewLoading = false"></video>
                        <video x-ref="previewVideoB"
                               class="absolute inset-0 w-full h-full object-contain bg-black transition-opacity duration-300"
                               :class="activePlayer === 'B' ? 'opacity-100 z-10' : 'opacity-0 z-0'"
                               preload="metadata"
                               @loadedmetadata="onPreviewLoaded()"
                               @timeupdate="onPreviewTimeUpdate()"
                               @ended="onPreviewEnded()"
                               @waiting="previewLoading = true"
                               @playing="previewLoading = false"
                               @canplay="previewLoading = false"></video>
                        <img x-show="showLogoOverlay && virtualScreen && virtualScreen.logo_url"
                             x-cloak
                             :src="virtualScreen?.logo_url"
                             alt="Logo"
                             class="absolute z-20 pointer-events-none select-none"
                             :style="virtualScreen ? `left: ${(virtualScreen.logo_x / virtualScreen.width) * 100}%; top: ${(virtualScreen.logo_y / virtualScreen.height) * 100}%; width: ${(virtualScreen.logo_w / virtualScreen.width) * 100}%; opacity: ${virtualScreen.logo_opacity};` : ''">
                        <div x-show="!previewItem" x-cloak class="absolute inset-0 flex items-center justify-center text-gray-400 text-[11px] text-center px-3 bg-gray-900 z-20">
                            <div>
                                <svg class="w-8 h-8 mx-auto mb-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p>Selecciona un item</p>
                            </div>
                        </div>
                        <div x-show="previewLoading" x-cloak class="absolute inset-0 flex items-center justify-center bg-black/60 z-20">
                            <svg class="animate-spin w-6 h-6 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </div>
                        <div x-show="previewItem && previewItem.kind === 'ad'" x-cloak class="absolute top-1.5 left-1.5 px-1.5 py-0.5 bg-orange-500 text-white text-[9px] font-bold rounded uppercase shadow z-20">Cuña</div>
                        <div x-show="previewItem" x-cloak class="absolute bottom-1.5 right-1.5 px-1.5 py-0.5 bg-black/70 text-white text-[10px] font-mono rounded z-20">
                            <span x-text="(_lastActiveIdx + 1)"></span>/<span x-text="items.length"></span>
                        </div>
                        <div x-show="virtualScreen" x-cloak class="absolute top-1.5 right-1.5 z-30">
                            <button type="button" @click="showLogoOverlay = !showLogoOverlay"
                                    :title="showLogoOverlay ? 'Ocultar logo de la pantalla virtual' : 'Mostrar logo de la pantalla virtual'"
                                    class="w-7 h-7 rounded-md bg-black/60 hover:bg-black/80 text-white flex items-center justify-center transition">
                                <svg x-show="showLogoOverlay" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                <svg x-show="!showLogoOverlay" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                        <div class="absolute bottom-1.5 left-1.5 z-30">
                            <button type="button" @click="toggleFullscreen()" title="Pantalla completa"
                                    class="w-7 h-7 rounded-md bg-black/60 hover:bg-black/80 text-white flex items-center justify-center transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0 flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-teal-400 uppercase tracking-wider">Reproduciendo playlist</span>
                            <span class="text-xs text-gray-300 font-medium truncate" x-text="previewItem ? previewItem.filename : '—'"></span>
                            <button type="button" @click="openInsertCuePicker()" title="Insertar cuña a una hora específica (puede partir un item)"
                                    class="ml-auto inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-orange-500 hover:bg-orange-400 text-white text-[11px] font-semibold transition shadow">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Insertar cuña
                            </button>
                            <span class="text-[11px] text-gray-400 font-mono tabular-nums">
                                <span x-text="formatClock(previewCurrentTime)"></span>
                                <span class="text-gray-600"> / </span>
                                <span x-text="formatClock(previewItemDuration || 0)"></span>
                                <span class="text-gray-600 ml-2">·</span>
                                <span class="text-teal-400 ml-1" x-text="formatClock(playheadPosition)"></span>
                                <span class="text-gray-600"> total</span>
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="prevPlaylistItem()" :disabled="!previewItem || _lastActiveIdx <= 0" title="Item anterior"
                                    class="w-9 h-9 rounded-lg bg-white/10 hover:bg-white/20 text-white disabled:opacity-30 disabled:cursor-not-allowed transition flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20L9 12l10-8v16z"/><rect x="5" y="4" width="2" height="16" fill="currentColor"/></svg>
                            </button>
                            <button type="button" @click="seekPreview(-10)" :disabled="!previewItem" title="Retroceder 10s"
                                    class="w-9 h-9 rounded-lg bg-white/10 hover:bg-white/20 text-white disabled:opacity-30 disabled:cursor-not-allowed transition flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                            </button>
                            <button type="button" @click="togglePreviewPlay()" :disabled="!previewItem"
                                    class="w-10 h-10 rounded-lg bg-teal-500 hover:bg-teal-400 text-white disabled:opacity-30 disabled:cursor-not-allowed transition flex items-center justify-center shadow"
                                    :title="isPreviewPlaying ? 'Pausar' : 'Reproducir playlist'">
                                <svg x-show="!isPreviewPlaying" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                <svg x-show="isPreviewPlaying" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>
                            </button>
                            <button type="button" @click="stopPreview()" :disabled="!previewItem" title="Detener"
                                    class="w-9 h-9 rounded-lg bg-white/10 hover:bg-white/20 text-white disabled:opacity-30 disabled:cursor-not-allowed transition flex items-center justify-center">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12"/></svg>
                            </button>
                            <button type="button" @click="seekPreview(10)" :disabled="!previewItem" title="Avanzar 10s"
                                    class="w-9 h-9 rounded-lg bg-white/10 hover:bg-white/20 text-white disabled:opacity-30 disabled:cursor-not-allowed transition flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7m-8-14l7 7-7 7"/></svg>
                            </button>
                            <button type="button" @click="nextPlaylistItem()" :disabled="!previewItem || _lastActiveIdx >= items.length - 1" title="Siguiente item"
                                    class="w-9 h-9 rounded-lg bg-white/10 hover:bg-white/20 text-white disabled:opacity-30 disabled:cursor-not-allowed transition flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4l10 8-10 8V4z"/><rect x="17" y="4" width="2" height="16" fill="currentColor"/></svg>
                            </button>
                            <input type="range" min="0" :max="Math.max(1, parseInt(previewItemDuration || 0))" step="0.1"
                                   :value="previewCurrentTime"
                                   @input="seekPreviewTo(parseFloat($event.target.value))"
                                   :disabled="!previewItem"
                                   class="flex-1 h-1.5 accent-teal-500 cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed">
                            <button type="button" @click="togglePreviewMute()" :disabled="!previewItem" :title="previewMuted ? 'Activar sonido' : 'Silenciar'"
                                    class="w-9 h-9 rounded-lg bg-white/10 hover:bg-white/20 text-white disabled:opacity-30 disabled:cursor-not-allowed transition flex items-center justify-center">
                                <svg x-show="!previewMuted" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M11 5L6 9H2v6h4l5 4V5z"/></svg>
                                <svg x-show="previewMuted" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                            </button>
                            <input type="range" min="0" max="1" step="0.05" x-model.number="previewVolume"
                                   @input="onVolumeChange()"
                                   class="w-16 h-1.5 accent-teal-500 cursor-pointer">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 grid grid-cols-1 lg:grid-cols-5 gap-0 overflow-hidden">
                {{-- LEFT: playlist items --}}
                <div class="lg:col-span-3 border-r border-gray-200 flex flex-col bg-gray-50/30">
                    <div class="px-4 py-2 border-b border-gray-200 bg-white flex items-center justify-between">
                        <h3 class="text-xs font-bold text-gray-600 uppercase tracking-wider">Items en la playlist</h3>
                        <span class="text-[11px] text-gray-400" x-text="items.length + ' items'"></span>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3 space-y-1.5">
                        <template x-if="items.length === 0">
                            <div class="text-center text-sm text-gray-400 py-16 border-2 border-dashed border-gray-200 rounded-xl">
                                <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
                                Arrastra medios desde la derecha para agregar
                            </div>
                        </template>
                        <template x-for="(item, idx) in items" :key="item.id">
                            <div draggable="true"
                                 @dragstart="dragStart(item, $event)"
                                 @dragover.prevent="dragOver(idx, $event)"
                                 @dragleave="dragLeave(idx)"
                                 @drop.prevent="dropFromLibrary(idx, $event)"
                                 @dragend="dragEnd"
class="flex items-center gap-2 p-2 rounded-lg border-2 cursor-grab active:cursor-grabbing transition relative"
                                 :class="[
                                    itemOverflows(idx) ? 'bg-red-50 border-red-300 ring-2 ring-red-200' : (item.kind === 'ad' ? 'bg-orange-50 border-orange-200' : 'bg-blue-50 border-blue-200'),
                                    dropTargetIdx === idx ? 'ring-2 ring-teal-500 ring-offset-1' : '',
                                    activeItemIndex() === idx ? 'ring-2 ring-teal-500 shadow-md' : ''
                                  ]"
                                 @click="previewItemByIndex(idx)">
                                <span class="text-xs font-mono w-7 text-right shrink-0" :class="itemOverflows(idx) ? 'text-red-700 font-bold' : 'text-gray-400'" x-text="(idx + 1) + '.'"></span>
                                <div class="shrink-0 text-center" style="width: 76px;">
                                    <div class="text-[10px] font-mono font-bold leading-tight" :class="itemOverflows(idx) ? 'text-red-700' : 'text-teal-700'" x-text="formatTotalClock(cumulativeStart(idx))"></div>
                                    <div class="text-[10px] font-mono leading-tight" :class="itemOverflows(idx) ? 'text-red-400' : 'text-gray-400'">↓</div>
                                    <div class="text-[10px] font-mono font-bold leading-tight" :class="itemOverflows(idx) ? 'text-red-700' : 'text-teal-700'" x-text="formatTotalClock(cumulativeEnd(idx))"></div>
                                </div>
                                <div x-show="item.split_from_id" class="px-1 py-0.5 rounded text-[8px] font-bold uppercase bg-indigo-100 text-indigo-700 border border-indigo-200" title="Parte de una película dividida">Split</div>
                                <img :src="'/api/media-items/' + item.media_item_id + '/thumb'" :alt="item.filename" loading="lazy"
                                     class="w-16 h-10 object-cover rounded shrink-0 bg-gray-200"
                                     onerror="this.style.display='none'">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate" :class="itemOverflows(idx) ? 'text-red-700 line-through' : (item.kind === 'ad' ? 'text-orange-700' : 'text-blue-700')" x-text="item.filename"></p>
                                    <p class="text-[11px] flex items-center gap-1" :class="itemOverflows(idx) ? 'text-red-600' : 'text-gray-500'">
                                        <span x-text="formatDur(item.duration_sec) + ' · ' + (item.kind === 'ad' ? 'Cuña' : 'Video')"></span>
                                        <span x-show="itemOverflows(idx)" class="px-1.5 py-0.5 bg-red-500 text-white text-[9px] font-bold rounded uppercase">Excede 24h</span>
                                    </p>
                                </div>
                                <button type="button" @click.stop="moveItem(idx, -1)" :disabled="idx === 0" class="p-1.5 rounded disabled:opacity-30 transition" :class="itemOverflows(idx) ? 'text-red-400 hover:bg-red-100' : 'text-gray-400 hover:bg-white/80'">▲</button>
                                <button type="button" @click.stop="moveItem(idx, 1)" :disabled="idx === items.length - 1" class="p-1.5 rounded disabled:opacity-30 transition" :class="itemOverflows(idx) ? 'text-red-400 hover:bg-red-100' : 'text-gray-400 hover:bg-white/80'">▼</button>
                                <button type="button" @click.stop="confirmRemove(item, idx)" class="p-1.5 rounded text-red-600 hover:bg-red-100 transition" title="Eliminar">✕</button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- RIGHT: library --}}
                <div class="lg:col-span-2 flex flex-col bg-white">
                    <div class="px-4 py-2 border-b border-gray-200">
                        <h3 class="text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Biblioteca del canal</h3>
                        <input type="text" x-model="search" placeholder="Buscar..."
                               class="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm mb-2 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20">
                        <div class="flex bg-gray-100 rounded-lg p-0.5">
                            <button type="button" @click="filter = 'all'"
                                    :class="filter === 'all' ? 'bg-white shadow-sm text-teal-700' : 'text-gray-600 hover:text-gray-800'"
                                    class="flex-1 px-3 py-1 rounded-md text-xs font-semibold transition">
                                Todos
                            </button>
                            <button type="button" @click="filter = 'video'"
                                    :class="filter === 'video' ? 'bg-white shadow-sm text-blue-700' : 'text-gray-600 hover:text-gray-800'"
                                    class="flex-1 px-3 py-1 rounded-md text-xs font-semibold transition">
                                Videos
                            </button>
                            <button type="button" @click="filter = 'ad'"
                                    :class="filter === 'ad' ? 'bg-white shadow-sm text-orange-700' : 'text-gray-600 hover:text-gray-800'"
                                    class="flex-1 px-3 py-1 rounded-md text-xs font-semibold transition">
                                Cuñas
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <template x-for="m in filteredLibrary" :key="m.id">
                                <button type="button" draggable="true"
                                        @dragstart="libraryDragStart(m, $event)"
                                        @dragend="libraryDragEnd"
                                        @click="toggleItem(m)"
                                        class="text-left rounded-lg border-2 overflow-hidden transition hover:shadow-md hover:scale-[1.02] cursor-grab active:cursor-grabbing relative"
                                        :class="[
                                            itemInPlaylist(m.id) ? 'border-green-400 bg-green-50 opacity-60' : (m.kind === 'ad' ? 'border-orange-300 hover:border-orange-500 bg-orange-50/50' : 'border-blue-300 hover:border-blue-500 bg-blue-50/50')
                                        ]">
                                    <div x-show="itemInPlaylist(m.id)" class="absolute top-1 left-1 z-10 w-6 h-6 rounded-full bg-green-500 text-white flex items-center justify-center text-xs font-bold shadow-md">✓</div>
                                    <div class="aspect-video bg-gray-100 relative overflow-hidden">
                                        <img :src="'/api/media-items/' + m.id + '/thumb'" :alt="m.filename" loading="lazy"
                                             class="w-full h-full object-cover"
                                             onerror="this.style.display='none'">
                                        <div x-show="m.kind === 'ad'" class="absolute top-1 right-1 px-1.5 py-0.5 bg-orange-500 text-white text-[9px] font-bold rounded uppercase">Cuña</div>
                                    </div>
                                    <div class="p-1.5">
                                        <p class="text-[10px] font-medium truncate" :class="m.kind === 'ad' ? 'text-orange-700' : 'text-blue-700'" x-text="m.filename"></p>
                                        <p class="text-[9px] text-gray-500" x-text="formatDur(m.duration_sec)"></p>
                                    </div>
                                </button>
                            </template>
                            <div x-show="filteredLibrary.length === 0" class="col-span-full text-center text-xs text-gray-400 py-8">
                                Sin resultados.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Toast/Confirm overlay --}}
        <div x-show="toast.show" x-cloak x-transition
             class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60]">
            <div class="bg-white rounded-xl shadow-2xl ring-1 ring-gray-200 px-5 py-4 flex items-center gap-4 min-w-[300px] max-w-md">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900" x-text="toast.message"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" @click="toast.cancel()"
                            class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold hover:bg-gray-200 transition">
                        Cancelar
                    </button>
                    <button type="button" @click="toast.confirm()"
                            class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-500 transition">
                        Sí, quitar
                    </button>
                </div>
            </div>
        </div>
        <div x-show="toast.saved" x-cloak x-transition.duration.300ms
             class="fixed top-6 right-6 z-[60]">
            <div class="bg-green-500 text-white rounded-xl shadow-xl px-4 py-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span class="text-sm font-semibold">Cambios guardados</span>
            </div>
        </div>

        {{-- Insert cue picker --}}
        <div x-show="insertCuePickerOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" @click="insertCuePickerOpen = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 z-10">
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center text-orange-600 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base font-semibold text-gray-900">Insertar cuña en el horario</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Si la cuña cae dentro de un item, se divide automáticamente (split).</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Hora de inserción (HH:MM:SS)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" min="0" max="23" x-model="insertCuePicker.hh"
                                   class="w-20 px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono text-center bg-gray-50/50 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20">
                            <span class="text-gray-400 font-bold">:</span>
                            <input type="number" min="0" max="59" x-model="insertCuePicker.mm"
                                   class="w-20 px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono text-center bg-gray-50/50 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20">
                            <span class="text-gray-400 font-bold">:</span>
                            <input type="number" min="0" max="59" x-model="insertCuePicker.ss"
                                   class="w-20 px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono text-center bg-gray-50/50 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20">
                            <span class="ml-auto text-xs text-gray-500 font-mono" x-text="insertCuePickerLabel"></span>
                        </div>
                        <p class="mt-1 text-[10px] text-gray-400" x-show="totalDuration > 0">Por defecto: posición actual del reproductor (<span x-text="formatClock(currentSchedulePos)"></span>).</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Cuña de biblioteca</label>
                        <select x-model="insertCuePicker.media_item_id"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50/50 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20">
                            <option value="">— selecciona una cuña (kind=ad, ready) —</option>
                            <template x-for="m in library.filter(x => x.kind === 'ad')" :key="m.id">
                                <option :value="m.id" x-text="m.filename + ' · ' + Math.round(m.duration_sec || 0) + 's'"></option>
                            </template>
                        </select>
                    </div>

                    <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900" x-show="insertCuePickerError">
                        <span x-text="insertCuePickerError"></span>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-3 text-[11px] text-gray-600">
                        <p><strong>Cómo funciona:</strong></p>
                        <ul class="mt-1 space-y-0.5 list-disc list-inside">
                            <li>Si a la hora indicada hay una película, se divide en 2 partes alrededor de la cuña.</li>
                            <li>Los items programados después de la hora de inserción se desplazan por la duración de la cuña.</li>
                            <li>El preview se actualiza automáticamente al guardar.</li>
                        </ul>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-5">
                    <button type="button" @click="insertCuePickerOpen = false"
                            class="px-4 py-2 text-sm text-gray-600 rounded-lg hover:bg-gray-100">Cancelar</button>
                    <button type="button" @click="submitInsertCue()"
                            :disabled="insertCuePickerSubmitting || !insertCuePicker.media_item_id"
                            class="px-4 py-2 bg-orange-500 hover:bg-orange-400 disabled:bg-gray-300 text-white text-sm font-semibold rounded-lg transition">
                        <span x-show="!insertCuePickerSubmitting">Insertar a las <span x-text="insertCuePickerLabel"></span></span>
                        <span x-show="insertCuePickerSubmitting">Insertando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>