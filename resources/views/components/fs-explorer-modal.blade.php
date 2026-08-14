@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>';
@endphp

<x-app-modal name="fs-explorer" title="Explorar servidor" subtitle="Navega las carpetas y selecciona la ruta raíz del canal" maxWidth="2xl" :icon="$icon" iconBg="bg-slate-100" iconColor="text-slate-600">
    <div
        x-data="{
            current: '',
            parent: null,
            entries: [],
            busy: false,
            error: '',
            init() {
                this.$watch('isTop', async (top) => {
                    if (!top) return;
                    const payload = $store.modals.payload || {};
                    this.current = payload.current || '';
                    this.parent = null;
                    this.entries = [];
                    this.error = '';
                    await this.fetchEntries(this.current);
                });
            },
            async fetchEntries(path) {
                this.busy = true;
                this.error = '';
                try {
                    const url = '/admin/fs/browse' + (path ? '?path=' + encodeURIComponent(path) : '');
                    const r = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!r.ok) {
                        const data = await r.json().catch(() => ({}));
                        const first = data.errors ? Object.values(data.errors).flat()[0] : null;
                        const isRootError = first && first.includes('Browse root');
                        if (!path || isRootError) {
                            this.error = first || ('Error ' + r.status);
                            this.entries = [];
                            return;
                        }
                        this.fetchEntries('');
                        return;
                    }
                    const data = await r.json();
                    this.current = data.path;
                    this.parent = data.parent;
                    this.entries = data.entries || [];
                } catch (e) {
                    this.error = 'Error de red.';
                } finally {
                    this.busy = false;
                }
            },
            navigateTo(path) { this.fetchEntries(path); },
            goUp() { if (this.parent) this.fetchEntries(this.parent); },
            pick() {
                if (!this.current) return;
                const targetId = ($store.modals.payload || {}).target;
                if (targetId) {
                    const el = document.getElementById(targetId);
                    if (el) {
                        el.value = this.current;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                Alpine.store('modals').close();
                window.dispatchEvent(new CustomEvent('fs-picked', { detail: { target: targetId, path: this.current } }));
            }
        }"
    >
        <div class="px-6 py-5">
            {{-- Breadcrumb bar --}}
            <div class="flex items-center gap-2 mb-4 -mx-2 px-2">
                <button type="button" @click="goUp()" :disabled="!parent || busy"
                        class="p-1.5 rounded-lg hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent transition text-gray-600"
                        title="Subir un nivel">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                    </svg>
                </button>

                <div class="flex-1 flex items-center gap-1 overflow-x-auto whitespace-nowrap font-mono text-xs text-gray-700 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200 min-w-0">
                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                    <template x-if="current">
                        <span class="flex items-center gap-0.5 min-w-0">
                            <template x-for="(seg, idx) in current.split('/').filter(Boolean)" :key="idx">
                                <span class="flex items-center gap-0.5">
                                    <span class="text-gray-300">/</span>
                                    <button type="button"
                                            @click="navigateTo('/' + current.split('/').filter(Boolean).slice(0, idx+1).join('/'))"
                                            class="hover:text-indigo-600 hover:underline px-0.5"
                                            x-text="seg"></button>
                                </span>
                            </template>
                        </span>
                    </template>
                </div>
            </div>

            {{-- Directory list --}}
            <div class="border border-gray-200 rounded-xl overflow-hidden bg-white min-h-[260px] max-h-[400px] overflow-y-auto">
                <div x-show="busy" class="flex items-center justify-center py-16 text-sm text-gray-500">
                    <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Cargando…
                </div>
                <div x-show="!busy && error" class="px-4 py-12 text-center">
                    <svg class="mx-auto w-10 h-10 text-red-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-red-600" x-text="error"></p>
                </div>
                <ul x-show="!busy && !error" class="divide-y divide-gray-100">
                    <template x-if="!busy && !error && entries.length === 0">
                        <li class="px-4 py-12 text-center">
                            <svg class="mx-auto w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-sm text-gray-500">Esta carpeta está vacía.</p>
                        </li>
                    </template>
                    <template x-for="entry in entries" :key="entry.path">
                        <li>
                            <button type="button"
                                    @click="navigateTo(entry.path)"
                                    @dblclick="pick()"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-indigo-50 text-left transition group">
                                <svg class="w-5 h-5 text-indigo-400 group-hover:text-indigo-600 shrink-0 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                </svg>
                                <span class="text-sm font-medium text-gray-800 truncate" x-text="entry.name"></span>
                                <svg class="w-4 h-4 text-gray-300 ml-auto opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="mt-3 flex items-center gap-2 text-xs text-gray-500">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Doble click en una carpeta para seleccionarla</span>
            </div>

            <div class="mt-6 -mx-6 px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 rounded-b-2xl">
                <button type="button" @click="Alpine.store('modals').close()"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button type="button" @click="pick()" :disabled="!current"
                        class="inline-flex items-center px-4 py-2 bg-slate-900 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Seleccionar carpeta
                </button>
            </div>
        </div>
    </div>
</x-app-modal>