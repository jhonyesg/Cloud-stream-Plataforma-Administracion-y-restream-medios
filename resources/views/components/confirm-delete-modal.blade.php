@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>';
@endphp

<x-app-modal name="confirm-delete" title="Confirmar eliminación" subtitle="Esta acción no se puede deshacer" maxWidth="md" :icon="$icon" iconBg="bg-red-100" iconColor="text-red-600">
    <div
        x-data="{
            busy: false,
            get action() { return ($store.modals.payload || {}).action || ''; },
            get method() { return ($store.modals.payload || {}).method || 'DELETE'; },
            get title() { return ($store.modals.payload || {}).title || 'Confirmar eliminación'; },
            get message() { return ($store.modals.payload || {}).message || '¿Estás seguro?'; },
            get body() { return ($store.modals.payload || {}).body || null; },
            get confirmLabel() { return ($store.modals.payload || {}).confirmLabel || 'Sí, eliminar'; },
            get busyLabel() { return ($store.modals.payload || {}).busyLabel || 'Eliminando…'; },
            submit() {
                if (!this.action) return;
                this.busy = true;
                const init = {
                    method: this.method || 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                    },
                };
                if (this.body && this.method !== 'GET' && this.method !== 'DELETE') {
                    init.body = JSON.stringify(this.body);
                }
                fetch(this.action, init).then(async (r) => {
                    this.busy = false;
                    if (r.ok) {
                        const data = await r.json().catch(() => ({}));
                        Alpine.store('modals').close();
                        if (data && (data.deleted !== undefined || data.processed !== undefined || data.skipped !== undefined)) {
                            let msg = '';
                            if (data.deleted !== undefined) {
                                msg = `${data.deleted} archivo(s) eliminado(s)`;
                                if (data.skipped) msg += `, ${data.skipped} omitido(s)`;
                                if (data.failed) msg += `, ${data.failed} con error`;
                            } else if (data.processed !== undefined) {
                                msg = `${data.processed} miniatura(s) procesada(s)`;
                                if (data.skipped) msg += `, ${data.skipped} omitida(s)`;
                                if (data.failed) msg += `, ${data.failed} con error`;
                            }
                            window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: msg } }));
                        } else {
                            window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Acción completada correctamente.' } }));
                        }
                        setTimeout(() => window.location.reload(), 800);
                    } else if (r.status === 422) {
                        const data = await r.json().catch(() => ({}));
                        Alpine.store('modals').close();
                        const msg = (data.errors && Object.values(data.errors).flat()[0]) || data.message || 'No se pudo completar la acción.';
                        window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: msg } }));
                    } else {
                        Alpine.store('modals').close();
                        window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'No se pudo completar la acción.' } }));
                    }
                }).catch(() => {
                    this.busy = false;
                    Alpine.store('modals').close();
                    window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'Error de red.' } }));
                });
            }
        }"
    >
        <div class="px-6 py-5">
            <div class="flex items-start gap-4 p-4 bg-red-50 border border-red-100 rounded-xl">
                <svg class="w-6 h-6 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.071 19h13.858c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-sm text-red-900" x-text="message">¿Estás seguro?</p>
            </div>

            <div class="mt-6 -mx-6 px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 rounded-b-2xl relative z-40">
                <button type="button" @click="Alpine.store('modals').close()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button type="button" @click="submit()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 disabled:opacity-50 transition shadow-sm">
                    <svg x-show="!busy" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                    <svg x-show="busy" class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="busy ? busyLabel : confirmLabel"></span>
                </button>
            </div>
        </div>
    </div>
</x-app-modal>