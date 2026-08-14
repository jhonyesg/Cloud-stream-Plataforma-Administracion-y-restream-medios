@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/></svg>';

    $availableChannels = $availableChannels ?? collect();
    $effectiveChannelId = $channelId ?? $preselectedChannelId ?? null;
@endphp

<x-app-modal name="upload-media" title="Subir archivos multimedia" subtitle="Arrastra archivos o haz clic para seleccionar" maxWidth="2xl" :icon="$icon" iconBg="bg-emerald-100" iconColor="text-emerald-600">
    <div
        x-data="{
            channelId: @js($effectiveChannelId ?? ''),
            busy: false,
            files: [],
            error: '',
            processedCount: 0,
            errorCount: 0,
            get maxBytes() { return @js((int) config('cloudstream.media.max_upload_size', 5368709120)); },
            get maxMb() { return Math.round(this.maxBytes / 1024 / 1024); },
            get maxGb() { return (this.maxBytes / 1024 / 1024 / 1024).toFixed(1); },
            get maxLabel() { return this.maxBytes >= 1073741824 ? (this.maxGb + ' GB') : (this.maxMb + ' MB'); },
            get totalCount() { return this.files.length; },
            get inFlightCount() { return this.files.filter(f => f.status === 'uploading').length; },
            get allDone() { return this.totalCount > 0 && this.processedCount + this.errorCount === this.totalCount; },
            onDrop(e) {
                e.preventDefault();
                const dropped = Array.from(e.dataTransfer.files || []);
                this.addFiles(dropped);
            },
            addFiles(arr) {
                this.error = '';
                for (const f of arr) {
                    if (f.size > this.maxBytes) {
                        this.error = `${f.name} excede ${this.maxLabel}`;
                        continue;
                    }
                    this.files.push({ file: f, name: f.name, size: f.size, progress: 0, status: 'pending', errorMsg: '', xhr: null });
                }
            },
            onPick(e) {
                this.addFiles(Array.from(e.target.files || []));
                e.target.value = '';
            },
            remove(idx) {
                const f = this.files[idx];
                if (f && f.xhr) { try { f.xhr.abort(); } catch(e) {} }
                this.files.splice(idx, 1);
            },
            cancelFile(idx) {
                const f = this.files[idx];
                if (!f || !f.xhr) return;
                try { f.xhr.abort(); } catch(e) {}
                f.status = 'error';
                f.errorMsg = 'Cancelado por el usuario';
                f.progress = 0;
                f.xhr = null;
                this.errorCount++;
                this.maybeFinish();
            },
            retryFile(idx) {
                const f = this.files[idx];
                if (!f) return;
                f.status = 'pending';
                f.errorMsg = '';
                f.progress = 0;
                this.errorCount = Math.max(0, this.errorCount - 1);
                this.uploadOne(f).then(() => this.maybeFinish()).catch(() => this.maybeFinish());
            },
            formatSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024*1024) return (bytes / 1024).toFixed(1) + ' KB';
                if (bytes < 1024*1024*1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB';
                return (bytes / 1024 / 1024 / 1024).toFixed(2) + ' GB';
            },
            statusLabel(s) {
                const p = Math.round(s.progress || 0);
                return {
                    pending: 'En cola',
                    uploading: 'Subiendo ' + p + '%',
                    done: 'Completado',
                    error: 'Error · ' + (s.errorMsg || 'desconocido'),
                }[s.status] || s.status;
            },
            statusBarClass(s) {
                return {
                    pending: 'bg-slate-300',
                    uploading: 'bg-indigo-500',
                    done: 'bg-emerald-500',
                    error: 'bg-red-500',
                }[s.status] || 'bg-slate-300';
            },
            uploadOne(item) {
                return new Promise((resolve, reject) => {
                    if (!this.channelId) { reject(new Error('Sin canal')); return; }
                    const fd = new FormData();
                    fd.append('channel_id', this.channelId);
                    fd.append('file', item.file);
                    const xhr = new XMLHttpRequest();
                    item.xhr = xhr;
                    item.status = 'uploading';
                    item.progress = 0;
                    xhr.open('POST', '/api/media-items/upload', true);
                    xhr.setRequestHeader('X-CSRF-TOKEN', window.csrfToken);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable && e.total > 0) {
                            item.progress = Math.min(100, (e.loaded / e.total) * 100);
                        }
                    });
                    xhr.addEventListener('load', () => {
                        let body = {};
                        try { body = JSON.parse(xhr.responseText || '{}'); } catch(e) {}
                        if (xhr.status >= 200 && xhr.status < 300) {
                            item.status = 'done';
                            item.progress = 100;
                            item.xhr = null;
                            this.processedCount++;
                            resolve(body);
                        } else {
                            item.status = 'error';
                            item.errorMsg = body.message || body.errors?.file?.[0] || ('HTTP ' + xhr.status);
                            item.xhr = null;
                            this.errorCount++;
                            reject(new Error(item.errorMsg));
                        }
                    });
                    xhr.addEventListener('error', () => {
                        item.status = 'error';
                        item.errorMsg = 'Error de red';
                        item.xhr = null;
                        this.errorCount++;
                        reject(new Error('network'));
                    });
                    xhr.addEventListener('abort', () => {
                        // Handled by cancelFile(); do not increment errorCount here.
                        reject(new Error('aborted'));
                    });
                    xhr.send(fd);
                });
            },
            async upload() {
                if (!this.channelId) { this.error = 'Selecciona un canal'; return; }
                if (this.files.length === 0) { this.error = 'Agrega al menos un archivo'; return; }
                this.busy = true;
                this.error = '';
                this.processedCount = 0;
                this.errorCount = 0;
                try {
                    for (const item of this.files) {
                        if (item.status === 'done') { this.processedCount++; continue; }
                        if (item.status === 'error') { /* already counted */ continue; }
                        try {
                            await this.uploadOne(item);
                        } catch (e) {
                            // per-file error already recorded
                        }
                    }
                    this.maybeFinish();
                } finally {
                    this.busy = false;
                }
            },
            maybeFinish() {
                if (!this.allDone) return;
                const ok = this.processedCount;
                const bad = this.errorCount;
                if (bad === 0 && ok > 0) {
                    Alpine.store('modals').close();
                    window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: `${ok} archivo(s) subido(s) correctamente.` } }));
                    setTimeout(() => window.location.reload(), 600);
                } else if (bad > 0) {
                    window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: `${bad} archivo(s) fallaron · ${ok} exitoso(s).` } }));
                }
            },
            submitLabel() {
                if (this.busy) return `Subiendo ${this.processedCount + this.errorCount}/${this.totalCount}…`;
                if (this.totalCount === 0) return 'Subir archivos';
                if (this.totalCount === 1) return 'Subir 1 archivo';
                return `Subir ${this.totalCount} archivos`;
            }
        }"
    >
<div class="px-6 py-5">
            @if(empty($effectiveChannelId))
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Canal destino</label>
                    <select x-model="channelId"
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl shadow-sm text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                        <option value="">— Selecciona un canal —</option>
                        @foreach($availableChannels as $ch)
                            <option value="{{ $ch->id }}">{{ $ch->display_name }} ({{ $ch->slug }})</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="mb-5 px-4 py-3 bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200/60 rounded-xl text-sm text-emerald-900 flex items-center gap-2.5 shadow-sm">
                    <span class="w-7 h-7 rounded-lg bg-emerald-500 text-white flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-wider text-emerald-700/80 font-semibold">Subiendo a</div>
                        <div class="font-semibold truncate">{{ $availableChannels->firstWhere('id', $effectiveChannelId)?->display_name ?? 'Canal asignado' }}</div>
                    </div>
                </div>
            @endif

            <div
                @dragover.prevent="$el.classList.add('border-emerald-500', 'bg-emerald-50/40', 'shadow-md'); $el.classList.add('ring-4', 'ring-emerald-500/10')"
                @dragleave.prevent="$el.classList.remove('border-emerald-500', 'bg-emerald-50/40', 'shadow-md', 'ring-4', 'ring-emerald-500/10')"
                @drop.prevent="$el.classList.remove('border-emerald-500', 'bg-emerald-50/40', 'shadow-md', 'ring-4', 'ring-emerald-500/10'); onDrop($event)"
                class="relative border-2 border-dashed border-slate-300 bg-gradient-to-br from-slate-50 to-white rounded-2xl p-8 text-center transition-all duration-200"
            >
                <div class="mx-auto w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center mb-3 shadow-lg shadow-emerald-500/20">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-700 mb-1">Arrastra archivos aquí</p>
                <p class="text-xs text-slate-500 mb-3">o haz clic para seleccionarlos</p>
                <label class="inline-flex items-center px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:border-slate-300 cursor-pointer transition shadow-sm">
                    <input type="file" multiple class="hidden" @change="onPick($event)">
                    Seleccionar archivos
                </label>
                <p class="mt-3 text-[11px] uppercase tracking-wider text-slate-400 font-semibold" x-text="'Máximo ' + maxLabel + ' por archivo'"></p>
            </div>

            <div x-show="files.length > 0" class="mt-5 space-y-2 max-h-80 overflow-y-auto -mx-1 px-1">
                <template x-for="(item, idx) in files" :key="idx">
                    <div class="p-3.5 bg-white border border-slate-200/80 rounded-xl shadow-sm hover:shadow-md transition-shadow"
                         :class="{
                             'ring-2 ring-indigo-500/30 border-indigo-300': item.status === 'uploading',
                             'ring-2 ring-emerald-500/30 border-emerald-300': item.status === 'done',
                             'ring-2 ring-red-500/30 border-red-300': item.status === 'error',
                         }">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg shrink-0 flex items-center justify-center transition-colors"
                                 :class="{
                                     'bg-slate-100 text-slate-500': item.status === 'pending',
                                     'bg-indigo-100 text-indigo-600': item.status === 'uploading',
                                     'bg-emerald-100 text-emerald-600': item.status === 'done',
                                     'bg-red-100 text-red-600': item.status === 'error',
                                 }">
                                <svg x-show="item.status !== 'done'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <svg x-show="item.status === 'done'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-800 truncate flex-1" x-text="item.name"></span>
                                    <span class="text-xs text-slate-500 shrink-0 tabular-nums" x-text="formatSize(item.size)"></span>
                                </div>
                                <div class="text-xs mt-0.5 font-medium"
                                     :class="{
                                         'text-slate-400': item.status === 'pending',
                                         'text-indigo-600': item.status === 'uploading',
                                         'text-emerald-600': item.status === 'done',
                                         'text-red-600': item.status === 'error',
                                     }"
                                     x-text="statusLabel(item)"></div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button x-show="item.status === 'uploading'" type="button" @click="cancelFile(idx)"
                                        class="text-xs px-2.5 py-1 rounded-md text-slate-600 hover:bg-slate-100 transition"
                                        title="Cancelar este archivo">
                                    Cancelar
                                </button>
                                <button x-show="item.status === 'error'" type="button" @click="retryFile(idx)"
                                        class="text-xs px-2.5 py-1 rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-sm"
                                        title="Reintentar este archivo">
                                    Reintentar
                                </button>
                                <button x-show="item.status === 'pending'" type="button" @click="remove(idx)"
                                        class="text-slate-400 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-md transition" title="Quitar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="mt-2.5 h-2 w-full bg-slate-100 rounded-full overflow-hidden relative">
                            <div class="h-full rounded-full transition-all duration-200 ease-out"
                                 :class="statusBarClass(item)"
                                 :style="`width: ${item.status === 'done' ? 100 : (item.status === 'pending' ? 0 : Math.round(item.progress || 0))}%`"></div>
                            <div x-show="item.status === 'uploading'"
                                 class="absolute inset-y-0 w-12 bg-gradient-to-r from-transparent via-white/50 to-transparent"
                                 style="animation: csShimmer 1.5s linear infinite;"></div>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="error" x-transition.opacity class="mt-3 p-3 bg-red-50 border border-red-100 rounded-xl text-sm text-red-700 flex items-start gap-2" x-text="error">
            </div>
        </div>

        <div class="mt-0 -mx-6 px-6 py-4 bg-slate-50/80 border-t border-slate-100 flex justify-end gap-2 rounded-b-2xl relative z-40">
            <button type="button" @click="Alpine.store('modals').close()" :disabled="busy"
                    class="inline-flex items-center px-4 py-2 bg-white border border-slate-200 rounded-lg font-semibold text-xs text-slate-700 uppercase tracking-wider shadow-sm hover:bg-slate-50 transition disabled:opacity-50">
                Cancelar
            </button>
            <button type="button" @click="upload()" :disabled="busy || files.length === 0"
                    class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-semibold text-xs uppercase tracking-wider rounded-lg shadow-lg shadow-emerald-500/20 hover:shadow-xl hover:shadow-emerald-500/30 hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                <svg x-show="!busy" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <svg x-show="busy" class="animate-spin w-4 h-4 mr-1.5 fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="submitLabel()"></span>
            </button>
        </div>
    </div>
</x-app-modal>

<style>
    @keyframes csShimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(400%); }
    }
</style>