@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>';
@endphp

<x-app-modal name="edit-user" title="Editar usuario" subtitle="Modifica los datos y permisos del usuario" maxWidth="2xl" :icon="$icon" iconBg="bg-indigo-100" iconColor="text-indigo-600">
    <div
        x-data="{
            busy: false,
            currentId: null,
            user: null,
            loaded: false,
            init() {
                this.$watch('isOpen', async (open) => {
                    if (!open) {
                        this.loaded = false;
                        this.user = null;
                        this.currentId = null;
                        return;
                    }
                    if (!this.loaded && !this.currentId) {
                        const id = ($store.modals.payload && $store.modals.payload.id) || null;
                        if (!id) return;
                        this.currentId = id;
                        const r = await fetch(`/admin/users/${this.currentId}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (r.ok) {
                            const data = await r.json();
                            this.user = data.user;
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
                    username: this.user.username,
                    email: this.user.email,
                    display_name: this.user.display_name,
                    role: this.user.role,
                    status: this.user.status,
                    owner_id: this.user.owner_id || '',
                };
                const pw = this.$refs.password?.value;
                if (pw) data.password = pw;
                fetch(`/admin/users/${this.currentId}`, {
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
                        window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Usuario actualizado correctamente.' } }));
                        setTimeout(() => window.location.reload(), 400);
                    } else if (r.status === 422) {
                        Alpine.store('modals').errors = payload.errors || {};
                    } else {
                        window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'No se pudo actualizar el usuario.' } }));
                    }
                }).catch(() => {
                    this.busy = false;
                    window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'Error de red al actualizar el usuario.' } }));
                });
            }
        }"
    >
        <div x-show="!loaded" class="px-6 py-16 text-center">
            <svg class="animate-spin w-6 h-6 mx-auto text-indigo-500 mb-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <p class="text-sm text-gray-500">Cargando…</p>
        </div>

        <form x-show="loaded" @submit.prevent="submit()" class="px-6 py-5">
            <template x-if="user">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Username <span class="text-red-500">*</span></label>
                        <input type="text" name="username" required maxlength="60" x-model="user.username"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                        <p class="mt-1 text-sm text-red-600"
                           x-show="$store.modals.errors.username"
                           x-text="$store.modals.errors.username ? $store.modals.errors.username[0] : ''"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required x-model="user.email"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                        <p class="mt-1 text-sm text-red-600"
                           x-show="$store.modals.errors.email"
                           x-text="$store.modals.errors.email ? $store.modals.errors.email[0] : ''"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre a mostrar</label>
                        <input type="text" name="display_name" maxlength="120" x-model="user.display_name"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                    </div>
                    <div>
                        <x-password-input x-ref="password"
                                          name="password"
                                          label="Contraseña (vacía para no cambiar)"
                                          autocomplete="new-password"
                                          minlength="8"
                                          color="indigo" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Rol <span class="text-red-500">*</span></label>
                        <select name="role" required x-model="user.role"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                            <option value="admin">admin</option>
                            <option value="client">client</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado <span class="text-red-500">*</span></label>
                        <select name="status" required x-model="user.status"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                            <option value="active">active</option>
                            <option value="suspended">suspended</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Owner (UUID, opcional)</label>
                        <input type="text" name="owner_id" x-model="user.owner_id"
                               placeholder="UUID del owner (o vacío)"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 font-mono text-sm transition">
                    </div>
                </div>
            </template>

            <div class="mt-6 -mx-6 -mb-5 px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 rounded-b-2xl relative z-40">
                <button type="button" @click="Alpine.store('modals').close()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button type="button" @click="submit()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 disabled:opacity-50 transition shadow-sm">
                    <svg x-show="!busy" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="busy" class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="busy ? 'Guardando…' : 'Guardar cambios'"></span>
                </button>
            </div>
        </form>
    </div>
</x-app-modal>