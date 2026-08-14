<div
    x-data="mediaBulkBar()"
    x-show="$store.mediaSelection && $store.mediaSelection.enabled"
    x-cloak
    class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-full max-w-2xl px-4"
>
    <div
        x-show="$store.mediaSelection.count > 0"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="bg-white rounded-2xl shadow-2xl ring-1 ring-gray-900/10 px-4 py-3 flex items-center gap-3 flex-wrap"
    >
        <div class="flex items-center gap-2 pr-3 border-r border-gray-100">
            <div class="w-7 h-7 rounded-full bg-amber-500 text-white flex items-center justify-center text-xs font-bold"
                 x-text="$store.mediaSelection.count"></div>
            <span class="text-sm font-semibold text-gray-700"
                  x-text="$store.mediaSelection.count === 1 ? 'seleccionado' : 'seleccionados'"></span>
        </div>

        <button
            type="button"
            @click="generateThumbs()"
            :disabled="busy"
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-amber-50 text-amber-700 text-xs font-semibold hover:bg-amber-100 disabled:opacity-50 transition"
            title="Regenerar las miniaturas de los items seleccionados (sobrescribe las existentes)"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
            </svg>
            <span x-text="busy ? 'Regenerando…' : 'Regenerar miniaturas'"></span>
        </button>

        <button
            type="button"
            @click="askDelete()"
            :disabled="busy"
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-red-50 text-red-700 text-xs font-semibold hover:bg-red-100 disabled:opacity-50 transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/>
            </svg>
            Eliminar
        </button>

        <div class="ml-auto flex items-center gap-1">
            <button
                type="button"
                @click="$store.mediaSelection.clear()"
                class="text-xs text-gray-500 hover:text-gray-700 font-medium px-2 py-1 rounded hover:bg-gray-100 transition"
            >
                Limpiar
            </button>
            <button
                type="button"
                @click="$store.mediaSelection.exit()"
                class="text-xs text-gray-500 hover:text-gray-700 font-medium px-2 py-1 rounded hover:bg-gray-100 transition"
            >
                Salir
            </button>
        </div>
    </div>

    <div
        x-show="$store.mediaSelection.count === 0"
        class="mt-2 bg-white/90 backdrop-blur rounded-xl shadow ring-1 ring-gray-900/10 px-4 py-2 flex items-center justify-between text-xs"
    >
        <span class="text-gray-500 font-medium">0 seleccionados · modo selección activo</span>
        <button
            type="button"
            @click="$store.mediaSelection.exit()"
            class="text-gray-500 hover:text-gray-700 font-semibold"
        >
            Salir
        </button>
    </div>
</div>

<script>
    window.mediaBulkBar = () => ({
        busy: false,
        ids() {
            const s = Alpine.store('mediaSelection');
            return s ? s.list : [];
        },
        async generateThumbs() {
            const ids = this.ids();
            if (ids.length === 0 || this.busy) return;
            this.busy = true;
            try {
                const res = await fetch('/api/media-items/bulk-thumbnails', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ids }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    const msg = `Miniaturas regeneradas: ${data.processed} procesadas` +
                                (data.failed ? `, ${data.failed} con error` : '') +
                                (data.skipped ? `, ${data.skipped} omitidas` : '');
                    window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: msg } }));
                    Alpine.store('mediaSelection').exit();
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    const msg = (data.errors && Object.values(data.errors).flat()[0]) || data.message || 'No se pudieron regenerar las miniaturas.';
                    window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: msg } }));
                }
            } catch (e) {
                window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'Error de red al regenerar miniaturas.' } }));
            } finally {
                this.busy = false;
            }
        },
        askDelete() {
            const ids = this.ids();
            if (ids.length === 0) return;
            Alpine.store('modals').open('confirm-delete', {
                action: '/api/media-items/bulk-delete',
                method: 'POST',
                title: 'Eliminar ' + ids.length + ' archivo' + (ids.length === 1 ? '' : 's'),
                message: '¿Eliminar ' + ids.length + ' archivo' + (ids.length === 1 ? '' : 's') + '? Esta acción no se puede deshacer. Los archivos que no tengas permiso para eliminar serán omitidos.',
                body: { ids },
            });
        },
    });
</script>

<style>[x-cloak] { display: none !important; }</style>