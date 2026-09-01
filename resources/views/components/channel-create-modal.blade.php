@php
    $channel = new \App\Models\Channel();
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
@endphp

<x-app-modal name="create-channel" title="Nuevo canal" subtitle="Define el canal, su ruta de multimedia y a quién se asigna" maxWidth="2xl" :icon="$icon" iconBg="bg-emerald-100" iconColor="text-emerald-600">
    <div
        x-data="{
            busy: false,
            submit() {
                this.busy = true;
                Alpine.store('modals').errors = {};
                const form = this.$refs.form;
                const data = Object.fromEntries(new FormData(form).entries());
                if (typeof data.assigned_user_ids === 'string') {
                    try { data.assigned_user_ids = JSON.parse(data.assigned_user_ids || '[]'); }
                    catch (e) { data.assigned_user_ids = []; }
                }
                const url = @js(route('admin.channels.store'));
                fetch(url, {
                    method: 'POST',
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
                        window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Canal creado correctamente.' } }));
                        setTimeout(() => window.location.reload(), 400);
                    } else if (r.status === 422) {
                        Alpine.store('modals').errors = payload.errors || {};
                    } else {
                        window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'No se pudo crear el canal.' } }));
                    }
                }).catch(() => {
                    this.busy = false;
                    window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'Error de red al crear el canal.' } }));
                });
            }
        }"
        class="flex flex-col flex-1 min-h-0"
    >
        <form x-ref="form" @submit.prevent="submit()" class="flex flex-col flex-1 min-h-0">
            <div
                x-show="$store.modals && $store.modals.errors && Object.keys($store.modals.errors).length > 0"
                x-transition.opacity
                class="shrink-0 px-6 py-3 bg-red-50 border-b border-red-200"
            >
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                    </svg>
                    <div class="text-sm text-red-700 min-w-0">
                        <p class="font-semibold">Revisa los campos del formulario:</p>
                        <ul class="mt-1 list-disc list-inside space-y-0.5">
                            <template x-for="(msgs, field) in $store.modals.errors" :key="field">
                                <li>
                                    <span class="font-medium" x-text="field"></span>:
                                    <span x-text="(msgs || []).join(' ')"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="overflow-y-auto flex-1 min-h-0 px-6 py-5">
                @include('admin.channels.partials.form', ['channel' => $channel])
            </div>

            <div class="shrink-0 px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 rounded-b-2xl relative z-40">
                <button type="button" @click="Alpine.store('modals').close()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button type="button" @click="submit()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 disabled:opacity-50 transition shadow-sm">
                    <svg x-show="!busy" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <svg x-show="busy" class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="busy ? 'Creando…' : 'Crear canal'"></span>
                </button>
            </div>
        </form>
    </div>
</x-app-modal>