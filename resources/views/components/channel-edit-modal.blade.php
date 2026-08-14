@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>';
@endphp

<x-app-modal name="edit-channel" title="Editar canal" subtitle="Modifica configuración, ruta y asignaciones" maxWidth="2xl" :icon="$icon" iconBg="bg-emerald-100" iconColor="text-emerald-600">
    <div
        x-data="{
            busy: false,
            currentId: null,
            ch: null,
            loaded: false,
            allUsers: (window.allAssignableUsers || []),
            init() {
                this.$watch('isOpen', async (open) => {
                    if (!open) {
                        this.loaded = false;
                        this.ch = null;
                        this.currentId = null;
                        return;
                    }
                    if (!this.loaded && !this.currentId) {
                        const id = ($store.modals.payload && $store.modals.payload.id) || null;
                        if (!id) return;
                        this.currentId = id;
                        const url = `/admin/channels/${this.currentId}`;
                        const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (r.ok) {
                            const data = await r.json();
                            this.ch = data.channel;
                            this.ch.assigned_users = (data.channel.assignedUsers || []).map(u => ({ id: u.id, name: u.display_name || u.username || u.email }));
                            this.loaded = true;
                        }
                    }
                });
            },
            submit() {
                if (!this.currentId) return;
                this.busy = true;
                Alpine.store('modals').errors = {};
                const data = {
                    display_name: this.ch.display_name,
                    slug: this.ch.slug,
                    owner_id: this.ch.owner_id,
                    status: this.ch.status,
                    description: this.ch.description,
                    root_path: this.ch.root_path,
                };
                if (this.$refs.assignedIdsInput) {
                    data.assigned_user_ids = JSON.parse(this.$refs.assignedIdsInput.value || '[]');
                }
                fetch(`/admin/channels/${this.currentId}`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': window.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                }).then(async (r) => {
                    this.busy = false;
                    const payload = await r.json().catch(() => ({}));
                    if (r.ok) {
                        Alpine.store('modals').close();
                        window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Canal actualizado correctamente.' } }));
                        setTimeout(() => window.location.reload(), 400);
                    } else if (r.status === 422) {
                        Alpine.store('modals').errors = payload.errors || {};
                    } else {
                        window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'No se pudo actualizar el canal.' } }));
                    }
                }).catch(() => {
                    this.busy = false;
                    window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'Error de red al actualizar el canal.' } }));
                });
            }
        }"
    >
        <div x-show="!loaded" class="px-6 py-16 text-center">
            <svg class="animate-spin w-6 h-6 mx-auto text-emerald-500 mb-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <p class="text-sm text-gray-500">Cargando…</p>
        </div>

        <form x-show="loaded" @submit.prevent="submit()" class="px-6 py-5">
            <template x-if="ch">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre <span class="text-red-500">*</span></label>
                        <input type="text" name="display_name" required maxlength="120" x-model="ch.display_name"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition">
                        <p class="mt-1 text-sm text-red-600"
                           x-show="$store.modals.errors.display_name"
                           x-text="$store.modals.errors.display_name ? $store.modals.errors.display_name[0] : ''"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Slug</label>
                        <input type="text" name="slug" maxlength="120" pattern="[a-z0-9-]+" x-model="ch.slug"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 font-mono text-sm transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado <span class="text-red-500">*</span></label>
                        <select name="status" required x-model="ch.status"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition">
                            <option value="active">active</option>
                            <option value="draft">draft</option>
                            <option value="suspended">suspended</option>
                            <option value="archived">archived</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2 p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                        <p class="text-sm text-emerald-800">
                            <strong>Resolución virtual:</strong> {{ $ch->resolution ?? '1280×720' }}.
                            <span class="text-emerald-700">Configurala desde el botón <em>Pantalla Virtual</em> en la lista de canales.</span>
                        </p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Descripción</label>
                        <textarea name="description" maxlength="2000" rows="2" x-model="ch.description"
                                  class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition"></textarea>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Ruta <span class="text-gray-400 text-xs ml-1">(directorio raíz de la multimedia)</span>
                        </label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                id="channel-edit-root-path-input"
                                name="root_path"
                                maxlength="1024"
                                pattern="/[A-Za-z0-9._\/\- ]+"
                                x-model="ch.root_path"
                                placeholder="/mnt/multimedia/mi-canal"
                                class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 font-mono text-sm transition"
                            >
                            <button
                                type="button"
                                @click="$store.modals.open('fs-explorer', { target: 'channel-edit-root-path-input', current: ch.root_path })"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-slate-700 text-white text-sm font-medium rounded-lg hover:bg-slate-600 shrink-0 transition shadow-sm"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                </svg>
                                Explorar
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Ruta absoluta del servidor. Ej: <code class="px-1 py-0.5 bg-gray-100 rounded">/mnt/multimedia/cine-dios</code></p>
                        <p class="mt-1 text-sm text-red-600"
                           x-show="$store.modals.errors.root_path"
                           x-text="$store.modals.errors.root_path ? $store.modals.errors.root_path[0] : ''"></p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Usuarios asignados <span class="text-gray-400 text-xs ml-1">(acceso completo al canal)</span>
                        </label>
                        <div
                            x-data="{
                                open: false,
                                query: '',
                                selectedIds: [],
                                init() {
                                    this.$watch('ch', (val) => {
                                        if (val && Array.isArray(val.assigned_users)) {
                                            this.selectedIds = val.assigned_users.map(u => u.id);
                                            this.$nextTick(() => this.sync());
                                        }
                                    });
                                },
                                sync() { if (this.$refs.input) this.$refs.input.value = JSON.stringify(this.selectedIds); },
                                get allUsers() { return window.allAssignableUsers || []; },
                                get selectedUsers() { return this.allUsers.filter(u => this.selectedIds.includes(u.id)); },
                                get filtered() {
                                    const q = this.query.trim().toLowerCase();
                                    if (!q) return this.allUsers;
                                    return this.allUsers.filter(u => (u.label || '').toLowerCase().includes(q));
                                },
                                toggle(id) {
                                    const i = this.selectedIds.indexOf(id);
                                    if (i >= 0) this.selectedIds.splice(i, 1);
                                    else this.selectedIds.push(id);
                                    this.sync();
                                },
                                remove(id) {
                                    const i = this.selectedIds.indexOf(id);
                                    if (i >= 0) this.selectedIds.splice(i, 1);
                                    this.sync();
                                }
                            }"
                            @click.outside="open = false"
                            class="relative"
                        >
                            <input type="hidden" name="assigned_user_ids" x-ref="input" value="[]">
                            <div class="w-full min-h-[46px] border border-gray-300 rounded-lg shadow-sm bg-white px-2 py-1.5 flex flex-wrap gap-1.5 items-center cursor-text focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20 transition"
                                 @click="open = true; $refs.search.focus()">
                                <template x-for="u in selectedUsers" :key="u.id">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-medium">
                                        <span x-text="u.name"></span>
                                        <button type="button" @click.stop="remove(u.id)" class="text-emerald-600 hover:text-emerald-900" aria-label="Quitar">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </span>
                                </template>
                                <input x-ref="search" type="text" x-model="query" @focus="open = true" @keydown.escape="open = false"
                                       placeholder="Buscar usuarios por nombre, usuario o email…"
                                       class="flex-1 min-w-[120px] outline-none text-sm border-0 p-0 focus:ring-0">
                            </div>
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 class="absolute z-20 mt-1 w-full bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 max-h-64 overflow-y-auto"
                                 style="display: none;">
                                <ul class="py-1">
                                    <template x-if="filtered.length === 0">
                                        <li class="px-3 py-2 text-sm text-gray-500">Sin resultados.</li>
                                    </template>
                                    <template x-for="u in filtered" :key="u.id">
                                        <li>
                                            <button type="button" @click="toggle(u.id)"
                                                    class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-emerald-50 text-sm">
                                                <span class="w-4 h-4 rounded border flex items-center justify-center shrink-0"
                                                      :class="selectedIds.includes(u.id) ? 'bg-emerald-600 border-emerald-600' : 'border-gray-300 bg-white'">
                                                    <svg x-show="selectedIds.includes(u.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </span>
                                                <span class="truncate" x-text="u.label"></span>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">El owner tiene acceso implícito y no aparece aquí.</p>
                    </div>
                </div>
            </template>

            <div class="mt-6 -mx-6 -mb-5 px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 rounded-b-2xl relative z-40">
                <button type="button" @click="Alpine.store('modals').close()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button type="button" @click="submit()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 disabled:opacity-50 transition shadow-sm">
                    <svg x-show="!busy" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="busy" class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="busy ? 'Guardando…' : 'Guardar cambios'"></span>
                </button>
            </div>
        </form>
    </div>
</x-app-modal>