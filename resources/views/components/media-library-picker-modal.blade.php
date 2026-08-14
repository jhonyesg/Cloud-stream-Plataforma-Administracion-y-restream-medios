<x-app-modal name="library-picker" title="Biblioteca de medios" subtitle="Selecciona videos o cuñas para agregar" maxWidth="3xl">
    <div x-data="libraryPicker()" x-init="$store.libraryPicker = $data">
        <div class="px-6 py-4 border-b border-gray-100">
            <div class="flex gap-2">
                <input type="text" x-model="search" placeholder="Buscar..."
                       class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20">
                <select x-model="filter" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="all">Todos</option>
                    <option value="video">Solo videos</option>
                    <option value="ad">Solo cuñas</option>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 max-h-[60vh] overflow-y-auto">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <template x-for="m in filteredItems" :key="m.id">
                    <button type="button" @click="addItem(m)"
                            class="text-left rounded-xl border-2 overflow-hidden transition hover:shadow-md"
                            :class="m.kind === 'ad' ? 'border-orange-300 hover:border-orange-500 bg-orange-50/50' : 'border-blue-300 hover:border-blue-500 bg-blue-50/50'">
                        <div class="aspect-video bg-gray-100 relative overflow-hidden">
                            <img :src="'/api/media-items/' + m.id + '/thumb'" :alt="m.filename" loading="lazy"
                                 class="w-full h-full object-cover"
                                 onerror="this.style.display='none'">
                            <div x-show="m.kind === 'ad'" class="absolute top-1 right-1 px-1.5 py-0.5 bg-orange-500 text-white text-[9px] font-bold rounded uppercase">Cuña</div>
                        </div>
                        <div class="p-2">
                            <p class="text-[11px] font-medium truncate" :class="m.kind === 'ad' ? 'text-orange-700' : 'text-blue-700'" x-text="m.filename"></p>
                            <p class="text-[10px] text-gray-500" x-text="formatDur(m.duration_sec)"></p>
                        </div>
                    </button>
                </template>
                <div x-show="filteredItems.length === 0" class="col-span-full text-center text-sm text-gray-400 py-12">
                    No hay medios que coincidan con el filtro.
                </div>
            </div>
        </div>
        <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 rounded-b-2xl flex justify-end">
            <button type="button" @click="$store.modals.close()" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 uppercase hover:bg-gray-50 transition">
                Cerrar
            </button>
        </div>
    </div>
</x-app-modal>

<script>
window.libraryPicker = () => ({
    items: [],
    search: '',
    filter: 'all',
    playlistId: null,
    onAdded: null,

    init() {
        Alpine.store('libraryPicker', this);
    },

    open(items, playlistId, callback) {
        this.items = items || [];
        this.playlistId = playlistId;
        this.onAdded = callback;
        this.search = '';
        this.filter = 'all';
        Alpine.store('modals').open('library-picker');
    },

    get filteredItems() {
        const q = this.search.toLowerCase();
        return this.items.filter(m => {
            if (this.filter !== 'all' && m.kind !== this.filter) return false;
            if (q && !m.filename.toLowerCase().includes(q)) return false;
            return true;
        });
    },

    formatDur(sec) {
        sec = parseInt(sec) || 0;
        if (sec < 60) return sec + 's';
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return m + 'min' + (s > 0 ? ' ' + s + 's' : '');
    },

    async addItem(m) {
        try {
            const r = await fetch('/api/playlists/' + this.playlistId + '/items', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ media_item_id: m.id }),
            });
            if (r.ok) {
                const item = await r.json();
                if (this.onAdded) {
                    this.onAdded({
                        id: item.id,
                        media_item_id: m.id,
                        filename: m.filename,
                        kind: m.kind,
                        duration_sec: m.duration_sec,
                        thumb_url: '/api/media-items/' + m.id + '/thumb',
                        position: item.position,
                    });
                }
                Alpine.store('modals').close();
            }
        } catch(e) { alert('Error'); }
    },
});
</script>