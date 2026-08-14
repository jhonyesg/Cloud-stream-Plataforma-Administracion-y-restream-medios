@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v12M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/></svg>';
@endphp

<x-app-modal name="create-playlist" title="Nueva playlist" subtitle="Crea una playlist para este canal" maxWidth="md" :icon="$icon" iconBg="bg-teal-100" iconColor="text-teal-600">
    <div
        x-data="{
            name: '',
            channelId: '',
            assignToDay: false,
            busy: false,
            error: '',

            init() {
                this.$watch('$store.modals.current', (name) => {
                    if (name === 'create-playlist') {
                        const p = ($store.modals && $store.modals.payload) || {};
                        this.channelId = p.channelId || '';
                        this.name = '';
                        this.error = '';
                        this.busy = false;
                        this.assignToDay = false;
                    }
                });
            },

            get canAssign() {
                const p = ($store.modals && $store.modals.payload) || {};
                return !!(p.templateId && p.day);
            },

            get dayLabel() {
                const p = ($store.modals && $store.modals.payload) || {};
                return p.day || '';
            },

            get channels() {
                const p = ($store.modals && $store.modals.payload) || {};
                return p.channels || [];
            },

            close() {
                Alpine.store('modals').close();
                this.reset();
            },

            reset() {
                this.name = '';
                this.busy = false;
                this.error = '';
                this.assignToDay = false;
            },

            async submit() {
                this.error = '';
                if (!this.name || !this.name.trim()) {
                    this.error = 'El nombre es obligatorio.';
                    return;
                }
                if (!this.channelId) {
                    this.error = 'Debes elegir un canal.';
                    return;
                }
                const p = ($store.modals && $store.modals.payload) || {};
                this.busy = true;
                try {
                    const r = await fetch('/api/playlists', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': window.csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ channel_id: this.channelId, name: this.name.trim() }),
                    });
                    if (!r.ok) {
                        const d = await r.json().catch(() => ({}));
                        this.error = (d && (d.message || (d.errors ? Object.values(d.errors).flat()[0] : null))) || 'No se pudo crear la playlist.';
                        this.busy = false;
                        return;
                    }
                    const playlist = await r.json();

                    if (this.assignToDay && p.templateId && p.day) {
                        const r2 = await fetch('/api/schedule-templates/' + p.templateId + '/days/' + p.day, {
                            method: 'PUT',
                            headers: {
                                'X-CSRF-TOKEN': window.csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ playlist_id: playlist.id }),
                        });
                        if (!r2.ok) {
                            const d = await r2.json().catch(() => ({}));
                            this.error = (d && d.message) || 'Playlist creada, pero no se pudo asignar al día.';
                            this.busy = false;
                            return;
                        }
                        Alpine.store('modals').close();
                        this.reset();
                        window.location.reload();
                        return;
                    }

                    const editor = Alpine.store('playlistEditor');
                    if (editor) {
                        editor.playlistId = playlist.id;
                        editor.name = playlist.name;
                        editor.items = [];
                        editor.library = (p.mediaItems || []).map(m => ({
                            id: m.id,
                            filename: m.filename,
                            kind: m.kind,
                            duration_sec: m.duration_sec || 0,
                        }));
                        if (typeof editor.recalcTotals === 'function') editor.recalcTotals();
                        editor.playheadPosition = 0;
                        editor.isPlaying = false;
                    }
                    Alpine.store('modals').close();
                    this.reset();
                    Alpine.store('modals').open('playlist-editor');
                } catch (e) {
                    this.error = 'Error de red.';
                    this.busy = false;
                }
            },
        }"
    >
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre</label>
                <input type="text" x-model="name" placeholder="Ej. Programación de mañana"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Canal</label>
                <select x-model="channelId"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition text-sm bg-white">
                    <template x-for="ch in channels" :key="ch.id">
                        <option :value="ch.id" x-text="ch.display_name"></option>
                    </template>
                </select>
            </div>

            <div x-show="canAssign" class="flex items-start gap-2 p-3 bg-teal-50 border border-teal-200 rounded-lg">
                <input type="checkbox" id="assign-to-day" x-model="assignToDay"
                       class="mt-0.5 w-4 h-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
                <label for="assign-to-day" class="text-sm text-gray-700">
                    Asignar al día <strong x-text="dayLabel"></strong> de este mes
                </label>
            </div>

            <div x-show="error" x-cloak class="px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700" x-text="error"></div>
        </div>

        <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-2">
            <button type="button" @click="close()" :disabled="busy"
                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 uppercase hover:bg-gray-50 transition">
                Cancelar
            </button>
            <button type="button" @click="submit()" :disabled="busy || !name.trim() || !channelId"
                    class="px-4 py-2 bg-teal-600 text-white rounded-lg text-xs font-semibold uppercase hover:bg-teal-500 disabled:opacity-50 transition">
                <span x-text="busy ? 'Creando…' : (canAssign && assignToDay ? 'Crear y asignar' : 'Crear y editar')"></span>
            </button>
        </div>
    </div>
</x-app-modal>
