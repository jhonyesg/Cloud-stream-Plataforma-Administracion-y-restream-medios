@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>';
@endphp

<x-app-modal name="profile" title="Mi perfil" subtitle="Actualiza tu información personal" maxWidth="lg" :icon="$icon" iconBg="bg-indigo-100" iconColor="text-indigo-600">
    <div
        x-data="{
            busy: false,
            form: { display_name: @js(auth()->user()->display_name ?? ''), username: @js(auth()->user()->username ?? ''), email: @js(auth()->user()->email ?? '') },
            submit() {
                this.busy = true;
                if (window.Alpine && Alpine.store) Alpine.store('modals').errors = {};
                const url = @js(route('profile.update'));
                fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': window.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                }).then(async (r) => {
                    this.busy = false;
                    const data = await r.json().catch(() => ({}));
                    if (r.ok) {
                        Alpine.store('modals').close();
                        window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Perfil actualizado correctamente.' } }));
                        setTimeout(() => window.location.reload(), 400);
                    } else if (r.status === 422) {
                        Alpine.store('modals').errors = data.errors || {};
                    } else {
                        window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'No se pudo actualizar el perfil.' } }));
                    }
                }).catch(() => {
                    this.busy = false;
                    window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'Error de red al actualizar el perfil.' } }));
                });
            }
        }"
    >
        <form @submit.prevent="submit()" class="px-6 py-5">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre a mostrar</label>
                    <input type="text" x-model="form.display_name" maxlength="120"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                    <p class="mt-1 text-sm text-red-600"
                       x-show="$store.modals.errors.display_name"
                       x-text="$store.modals.errors.display_name ? $store.modals.errors.display_name[0] : ''"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Usuario</label>
                    <input type="text" x-model="form.username" maxlength="60"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                    <p class="mt-1 text-sm text-red-600"
                       x-show="$store.modals.errors.username"
                       x-text="$store.modals.errors.username ? $store.modals.errors.username[0] : ''"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input type="email" x-model="form.email"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                    <p class="mt-1 text-sm text-red-600"
                       x-show="$store.modals.errors.email"
                       x-text="$store.modals.errors.email ? $store.modals.errors.email[0] : ''"></p>
                </div>
            </div>

            <p class="mt-4 text-xs text-gray-500 flex items-start gap-1.5">
                <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Este formulario no cambia tu contraseña. Para cambiarla, usa el botón <strong>"Cambiar contraseña"</strong> del menú superior.</span>
            </p>

            <div class="mt-6 -mx-6 -mb-5 px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 rounded-b-2xl relative z-40">
                <button type="button" @click="Alpine.store('modals').close()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button type="submit" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 disabled:opacity-50 transition shadow-sm">
                    <svg x-show="!busy" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="busy" class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="busy ? 'Guardando…' : 'Guardar cambios'"></span>
                </button>
            </div>
        </form>
    </div>
</x-app-modal>