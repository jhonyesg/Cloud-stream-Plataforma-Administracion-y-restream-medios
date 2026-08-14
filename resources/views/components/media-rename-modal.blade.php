@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>';
@endphp

<x-app-modal name="rename-media" title="Editar archivo" subtitle="Renombrar y asignar tipo de medio" maxWidth="md" :icon="$icon" iconBg="bg-indigo-100" iconColor="text-indigo-600">
    <div
        x-data="{
            busy: false,
            id: null,
            filename: '',
            kind: 'video',
            originalKind: 'video',
            init() {
                this.$watch('$store.modals.current', async (val) => {
                    if (val !== 'rename-media') return;
                    const p = $store.modals.payload || {};
                    this.id = p.id || null;
                    this.filename = p.filename || '';
                    this.kind = p.kind || 'video';
                    this.originalKind = this.kind;
                    if (this.$refs.input) {
                        this.$nextTick(() => {
                            this.$refs.input.focus();
                            const dotIdx = this.filename.lastIndexOf('.');
                            if (dotIdx > 0) this.$refs.input.setSelectionRange(0, dotIdx);
                        });
                    }
                });
            },
            async submit() {
                if (!this.id || !this.filename.trim()) return;
                this.busy = true;
                Alpine.store('modals').errors = {};
                const payload = { filename: this.filename.trim() };
                if (this.kind !== this.originalKind) payload.kind = this.kind;
                try {
                    const r = await fetch('/api/media-items/' + this.id, {
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': window.csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await r.json().catch(() => ({}));
                    if (r.ok) {
                        Alpine.store('modals').close();
                        window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Archivo actualizado correctamente.' } }));
                        setTimeout(() => window.location.reload(), 400);
                    } else if (r.status === 422) {
                        Alpine.store('modals').errors = data.errors || { filename: [data.message || 'Nombre inválido.'] };
                    } else {
                        window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: data.message || 'No se pudo guardar.' } }));
                    }
                } catch (e) {
                    window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'Error de red.' } }));
                } finally {
                    this.busy = false;
                }
            }
        }"
    >
        <div class="px-6 py-5">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre del archivo</label>
            <input
                type="text"
                x-ref="input"
                x-model="filename"
                maxlength="512"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition font-mono text-sm"
            >
            <p class="mt-1 text-xs text-gray-500">La extensión se preservará al renombrar; las playlists se actualizan automáticamente.</p>
            <p class="mt-1 text-sm text-red-600"
               x-show="$store.modals.errors.filename"
               x-text="$store.modals.errors.filename ? $store.modals.errors.filename[0] : ''"></p>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de medio</label>
                <select x-model="kind"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition text-sm">
                    <option value="video">Video</option>
                    <option value="image">Imagen</option>
                    <option value="audio">Audio</option>
                    <option value="ad">Cuña (Publicidad)</option>
                    <option value="other">Otro</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">El tipo determina cómo se visualiza y reproduce el archivo.</p>
            </div>
        </div>

        <div class="mt-0 -mx-6 px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 rounded-b-2xl relative z-40">
            <button type="button" @click="Alpine.store('modals').close()" :disabled="busy"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                Cancelar
            </button>
            <button type="button" @click="submit()" :disabled="busy"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 disabled:opacity-50 transition shadow-sm">
                <svg x-show="!busy" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="busy" class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="busy ? 'Guardando…' : 'Guardar'"></span>
            </button>
        </div>
    </div>
</x-app-modal>