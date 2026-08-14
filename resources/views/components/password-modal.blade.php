@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>';
@endphp

<x-app-modal name="password" title="Cambiar contraseña" subtitle="Usa al menos 8 caracteres" maxWidth="md" :icon="$icon" iconBg="bg-amber-100" iconColor="text-amber-600">
    <div
        x-data="{
            busy: false,
            form: { current_password: '', password: '', password_confirmation: '' },
            init() {
                this.$watch('$store.modals.current', (val) => {
                    if (val === 'password') {
                        this.form = { current_password: '', password: '', password_confirmation: '' };
                    }
                });
            },
            submit() {
                this.busy = true;
                Alpine.store('modals').errors = {};
                const url = @js(url('/password'));
                fetch(url, {
                    method: 'PUT',
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
                        window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Contraseña actualizada correctamente.' } }));
                    } else if (r.status === 422) {
                        Alpine.store('modals').errors = data.errors || {};
                    } else {
                        window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'No se pudo actualizar la contraseña.' } }));
                    }
                }).catch(() => {
                    this.busy = false;
                    window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'Error de red al actualizar la contraseña.' } }));
                });
            }
        }"
    >
        <form @submit.prevent="submit()" class="px-6 py-5">
            <div class="space-y-4">
                <x-password-input name="current_password"
                                  label="Contraseña actual"
                                  :required="true"
                                  autocomplete="current-password"
                                  color="amber"
                                  x-model="form.current_password" />
                <x-password-input name="password"
                                  label="Nueva contraseña"
                                  :required="true"
                                  autocomplete="new-password"
                                  color="amber"
                                  minlength="8"
                                  x-model="form.password" />
                <x-password-input name="password_confirmation"
                                  label="Confirmar nueva contraseña"
                                  :required="true"
                                  autocomplete="new-password"
                                  color="amber"
                                  x-model="form.password_confirmation" />
            </div>

            <div class="mt-6 -mx-6 -mb-5 px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 rounded-b-2xl relative z-40">
                <button type="button" @click="Alpine.store('modals').close()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button type="submit" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-500 disabled:opacity-50 transition shadow-sm">
                    <svg x-show="!busy" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="busy" class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="busy ? 'Actualizando…' : 'Cambiar contraseña'"></span>
                </button>
            </div>
        </form>
    </div>
</x-app-modal>