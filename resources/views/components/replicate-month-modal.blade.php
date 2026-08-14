@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
    $monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
@endphp

<x-app-modal name="replicate-month" title="Replicar este mes a otros meses" subtitle="Copia toda la programación del mes actual a uno o varios meses del año" maxWidth="2xl" :icon="$icon" iconBg="bg-teal-100" iconColor="text-teal-600">
    <template x-if="$store.modals && $store.modals.stack && $store.modals.stack.some(e => e.name === 'replicate-month')">
    <div
        x-data="replicateMonthModal({ templateId: window.replicateMonthConfig?.templateId || '', sourceYear: window.replicateMonthConfig?.sourceYear || new Date().getFullYear(), sourceMonth: window.replicateMonthConfig?.sourceMonth || (new Date().getMonth() + 1), monthNames: window.replicateMonthConfig?.monthNames || {{ json_encode($monthNames) }}, blocksCount: window.replicateMonthConfig?.blocksCount || 0, csrfToken: window.csrfToken || '' })"
        x-init="init()"
    >
        <div class="px-6 py-5">
            {{-- Source info --}}
            <div class="bg-teal-50 border border-teal-200 rounded-xl p-3 mb-5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center text-teal-700 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">
                        Mes origen: <span x-text="monthNames[sourceMonth - 1] + ' ' + sourceYear"></span>
                    </p>
                    <p class="text-xs text-gray-500">
                        <span x-text="blocksCount"></span> días programados se copiarán a cada mes destino
                    </p>
                </div>
            </div>

            {{-- Shortcut: rest of year --}}
            <div class="mb-3 flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-700">Meses destino</p>
                <button type="button" @click="selectRestOfYear()"
                        class="text-xs font-semibold text-teal-700 hover:text-teal-800 transition">
                    Replicar al resto del año →
                </button>
            </div>

            {{-- Month grid (checkboxes) --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-5">
                <template x-for="(name, idx) in monthNames" :key="idx">
                    <label class="flex items-center gap-2 px-3 py-2.5 rounded-lg border cursor-pointer transition"
                           :class="idx + 1 === sourceMonth
                               ? 'border-gray-200 bg-gray-50 opacity-50 cursor-not-allowed'
                               : selectedMonths.has(idx + 1)
                                   ? 'border-teal-400 bg-teal-50'
                                   : 'border-gray-200 bg-white hover:border-teal-300'">
                        <input type="checkbox"
                               :value="idx + 1"
                               :checked="selectedMonths.has(idx + 1)"
                               :disabled="idx + 1 === sourceMonth"
                               @change="toggleMonth(idx + 1)"
                               class="rounded border-gray-300 text-teal-600 focus:ring-teal-500 disabled:opacity-50">
                        <span class="text-sm"
                              :class="idx + 1 === sourceMonth ? 'text-gray-400' : 'text-gray-800'">
                            <span x-text="name"></span>
                        </span>
                        <span x-show="idx + 1 === sourceMonth" class="ml-auto text-[10px] uppercase font-bold text-gray-400">origen</span>
                    </label>
                </template>
            </div>

            {{-- Preview --}}
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 mb-5 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Meses seleccionados:</span>
                    <span class="font-bold text-gray-900" x-text="selectedMonths.size"></span>
                </div>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-gray-500">Días a copiar por mes:</span>
                    <span class="font-bold text-gray-900" x-text="blocksCount"></span>
                </div>
            </div>

            {{-- Conflict panel --}}
            <div x-show="conflicts.length > 0" x-cloak
                 class="bg-amber-50 border border-amber-300 rounded-xl p-3 mb-5">
                <div class="flex items-start gap-2 mb-2">
                    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                    <p class="text-sm font-semibold text-amber-900">
                        Vas a reemplazar el contenido de <span x-text="conflicts.length"></span> <span x-text="conflicts.length === 1 ? 'mes' : 'meses'"></span>:
                    </p>
                </div>
                <ul class="ml-7 mb-3 space-y-1 text-sm">
                    <template x-for="c in conflicts" :key="c.month">
                        <li class="flex items-center gap-2">
                            <span class="font-medium text-gray-900" x-text="monthNames[c.month - 1]"></span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
                                  :class="c.status === 'active' ? 'bg-red-100 text-red-700' : (c.status === 'archived' ? 'bg-gray-200 text-gray-600' : 'bg-amber-100 text-amber-700')"
                                  x-text="c.status"></span>
                            <span class="text-gray-600 text-xs">
                                · <span x-text="c.blocks_count"></span> <span x-text="c.blocks_count === 1 ? 'día' : 'días'"></span>
                            </span>
                        </li>
                    </template>
                </ul>
                <label class="flex items-center gap-2 ml-7 cursor-pointer">
                    <input type="checkbox"
                           x-model="confirmReplace"
                           class="rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                    <span class="text-sm font-medium text-amber-900">Sí, reemplazar las plantillas existentes en los meses destino</span>
                </label>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 rounded-b-2xl flex items-center justify-between gap-3">
            <p class="text-xs text-gray-500">Los meses destino se crearán como plantillas en borrador. Si los meses destino ya tienen plantilla, se reemplazará su contenido por el del mes origen.</p>
            <div class="flex gap-2 shrink-0">
                <button type="button" @click="Alpine.store('modals').close()"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 uppercase hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button type="button"
                        @click="replicate()"
                        :disabled="busy || selectedMonths.size === 0 || (conflicts.length > 0 && !confirmReplace)"
                        class="px-4 py-2 bg-gradient-to-r from-teal-500 to-emerald-600 text-white rounded-lg text-xs font-bold uppercase hover:from-teal-600 hover:to-emerald-700 active:scale-95 transition disabled:opacity-50 shadow-sm">
                    <span x-text="replicateLabel()"></span>
                </button>
            </div>
        </div>

        {{-- Error banner --}}
        <div x-show="errorMessage" x-cloak
             class="mx-6 mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800 max-h-40 overflow-y-auto">
            <p class="font-semibold mb-1" x-show="errorMessage">No se pudo completar la replicación</p>
            <p x-text="errorMessage"></p>
        </div>

        {{-- Toast (success) --}}
        <div x-show="toast.show" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 scale-95"
             class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[70]">
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 text-white rounded-2xl shadow-2xl px-8 py-6 flex items-center gap-4 min-w-[400px] max-w-md">
                <div class="w-14 h-14 rounded-full bg-white/20 backdrop-blur flex items-center justify-center shrink-0">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div class="flex-1">
                    <p class="text-lg font-bold" x-text="toast.title"></p>
                    <p class="text-sm text-white/90 mt-0.5" x-text="toast.message"></p>
                </div>
            </div>
        </div>
    </div>
    </template>
</x-app-modal>

<script>
window.replicateMonthModal = (config) => ({
    templateId: config.templateId,
    sourceYear: config.sourceYear,
    sourceMonth: config.sourceMonth,
    monthNames: config.monthNames,
    blocksCount: config.blocksCount,
    csrfToken: config.csrfToken,

    selectedMonths: new Set(),
    conflicts: [],
    confirmReplace: false,
    previewLoading: false,
    busy: false,
    errorMessage: '',
    toast: { show: false, title: '', message: '' },
    _toastTimer: null,
    _previewTimer: null,

    init() {},

    toggleMonth(m) {
        if (m === this.sourceMonth) return;
        if (this.selectedMonths.has(m)) {
            this.selectedMonths.delete(m);
        } else {
            this.selectedMonths.add(m);
        }
        this.selectedMonths = new Set(this.selectedMonths);
        this.errorMessage = '';
        this.schedulePreview();
    },

    selectRestOfYear() {
        const next = new Set();
        for (let m = this.sourceMonth + 1; m <= 12; m++) {
            next.add(m);
        }
        this.selectedMonths = next;
        this.errorMessage = '';
        this.schedulePreview();
    },

    schedulePreview() {
        if (this._previewTimer) clearTimeout(this._previewTimer);
        this._previewTimer = setTimeout(() => this.fetchPreview(), 250);
    },

    async fetchPreview() {
        if (!this.templateId || this.selectedMonths.size === 0) {
            this.conflicts = [];
            this.confirmReplace = false;
            return;
        }
        this.previewLoading = true;
        try {
            const r = await fetch('/api/schedule-templates/' + this.templateId + '/clone-preview', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    year: this.sourceYear,
                    months: [...this.selectedMonths],
                }),
            });
            const data = await r.json().catch(() => ({}));
            this.conflicts = Array.isArray(data.conflicts) ? data.conflicts : [];
        } catch (e) {
            this.conflicts = [];
        } finally {
            this.previewLoading = false;
            this.confirmReplace = false;
        }
    },

    replicateLabel() {
        if (this.busy) return 'Replicando…';
        const n = this.selectedMonths.size;
        const unit = n === 1 ? ' mes' : ' meses';
        if (this.conflicts.length > 0 && this.confirmReplace) {
            return 'Sí, reemplazar y replicar en ' + n + unit;
        }
        if (this.conflicts.length > 0) {
            return 'Reemplazar y replicar en ' + n + unit;
        }
        return 'Replicar a ' + n + unit;
    },

    async replicate() {
        if (this.busy) return;
        if (this.selectedMonths.size === 0) return;
        if (this.conflicts.length > 0 && !this.confirmReplace) return;
        if (!this.templateId) {
            this.errorMessage = 'No hay plantilla seleccionada.';
            return;
        }

        this.busy = true;
        this.errorMessage = '';

        const targets = [...this.selectedMonths].sort((a, b) => a - b);
        const promises = targets.map(m =>
            fetch('/api/schedule-templates/' + this.templateId + '/clone', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ year: this.sourceYear, month: m }),
            }).then(async r => {
                const data = await r.json().catch(() => ({}));
                return { ok: r.ok, status: r.status, month: m, data };
            })
        );

        const results = await Promise.allSettled(promises);

        let succeeded = 0;
        let replaced = 0;
        const failed = [];

        for (const r of results) {
            if (r.status === 'fulfilled') {
                const v = r.value;
                if (v.ok) {
                    succeeded++;
                    if (this.conflicts.some(c => c.month === v.month)) replaced++;
                } else {
                    const msg = (v.data && v.data.message) ? v.data.message : 'Error al clonar';
                    failed.push({ month: v.month, message: msg });
                }
            } else {
                failed.push({ month: '?', message: r.reason?.message || 'Error de red' });
            }
        }

        this.busy = false;

        if (failed.length === 0) {
            const base = succeeded + (succeeded === 1 ? ' mes clonado' : ' meses clonados');
            const tail = replaced > 0 ? ' · ' + replaced + (replaced === 1 ? ' reemplazado' : ' reemplazados') : '';
            this.showToast('¡Replicación exitosa!', base + tail);
            setTimeout(() => window.location.reload(), 1500);
            return;
        }

        const monthLabels = failed.map(f => this.monthNames[(f.month - 1)] + ' (' + f.message + ')').join(', ');
        const prefix = succeeded > 0
            ? succeeded + ' meses clonados, ' + failed.length + ' fallaron: '
            : 'No se pudo clonar a ' + failed.length + (failed.length === 1 ? ' mes: ' : ' meses: ');
        this.errorMessage = prefix + monthLabels;
    },

    showToast(title, msg) {
        if (this._toastTimer) clearTimeout(this._toastTimer);
        this.toast.title = title;
        this.toast.message = msg;
        this.toast.show = true;
        this._toastTimer = setTimeout(() => {
            this.toast.show = false;
            this._toastTimer = null;
        }, 5000);
    },
});
</script>