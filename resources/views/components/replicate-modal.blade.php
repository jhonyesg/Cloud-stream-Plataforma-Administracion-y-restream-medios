@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>';
@endphp

<x-app-modal name="replicate" title="Replicar playlist" subtitle="Elige el rango donde se replicará" maxWidth="2xl" :icon="$icon" iconBg="bg-teal-100" iconColor="text-teal-600">
    <template x-if="$store.modals && $store.modals.stack && $store.modals.stack.some(e => e.name === 'replicate')">
    <div
        x-data="replicateModal({ templateId: window.replicateConfig?.templateId || '', selectedDay: window.replicateConfig?.selectedDay || 1, year: window.replicateConfig?.year || new Date().getFullYear(), month: window.replicateConfig?.month || (new Date().getMonth() + 1), csrfToken: window.csrfToken || '', existingBlocks: window.replicateConfig?.existingBlocks || [] })"
        x-init="init()"
    >
        <div class="px-6 py-5">
            {{-- Day info --}}
            <div class="bg-teal-50 border border-teal-200 rounded-xl p-3 mb-5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center text-teal-700 font-bold text-lg" x-text="selectedDay"></div>
                <div>
                    <p class="text-sm font-medium text-gray-900">Día <span x-text="selectedDay"></span> será la fuente</p>
                    <p class="text-xs text-gray-500">La playlist de este día se copiará al rango elegido</p>
                </div>
            </div>

            {{-- Three range options --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                {{-- Semana --}}
                <div class="rounded-xl border-2 p-4 transition cursor-pointer"
                     :class="hovered === 'week' ? 'border-blue-400 bg-blue-50' : 'border-blue-200 bg-white'"
                     @mouseenter="hovered = 'week'" @mouseleave="hovered = null">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="font-bold text-gray-900">Semana</h3>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">Próximos 7 días desde el día seleccionado</p>
                    <div class="text-xs space-y-1 mb-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Sobrescriben:</span>
                            <span class="font-bold text-blue-700" x-text="weekPreview.overwrite"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Llenos vacíos:</span>
                            <span class="font-bold text-green-700" x-text="weekPreview.fill"></span>
                        </div>
                    </div>
                    <button type="button" @click="replicate('week')" :disabled="busy"
                            class="w-full py-2.5 mt-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold rounded-lg hover:from-blue-700 hover:to-indigo-700 active:scale-95 transition disabled:opacity-50 shadow-sm">
                        <span x-text="busy ? 'Replicando…' : 'Replicar semana'"></span>
                    </button>
                </div>

                {{-- Mes --}}
                <div class="rounded-xl border-2 p-4 transition cursor-pointer"
                     :class="hovered === 'month' ? 'border-teal-400 bg-teal-50' : 'border-teal-200 bg-white'"
                     @mouseenter="hovered = 'month'" @mouseleave="hovered = null">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-9 h-9 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h18M3 6h18M3 18h18M8 3v18M16 3v18"/></svg>
                        </div>
                        <h3 class="font-bold text-gray-900">Mes</h3>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">Todos los días del mes actual</p>
                    <div class="text-xs space-y-1 mb-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Sobrescriben:</span>
                            <span class="font-bold text-teal-700" x-text="monthPreview.overwrite"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Llenos vacíos:</span>
                            <span class="font-bold text-green-700" x-text="monthPreview.fill"></span>
                        </div>
                    </div>
                    <button type="button" @click="replicate('month')" :disabled="busy"
                            class="w-full py-2.5 mt-3 bg-gradient-to-r from-teal-500 to-emerald-600 text-white text-xs font-bold rounded-lg hover:from-teal-600 hover:to-emerald-700 active:scale-95 transition disabled:opacity-50 shadow-sm">
                        <span x-text="busy ? 'Replicando…' : 'Replicar mes'"></span>
                    </button>
                </div>

                {{-- Año --}}
                <div class="rounded-xl border-2 p-4 transition cursor-pointer"
                     :class="hovered === 'year' ? 'border-amber-400 bg-amber-50' : 'border-amber-200 bg-white'"
                     @mouseenter="hovered = 'year'" @mouseleave="hovered = null">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3 class="font-bold text-gray-900">Año</h3>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">12 meses × todos los días del año</p>
                    <div class="text-xs space-y-1 mb-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Sobrescriben:</span>
                            <span class="font-bold text-amber-700" x-text="yearPreview.overwrite"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Llenos vacíos:</span>
                            <span class="font-bold text-green-700" x-text="yearPreview.fill"></span>
                        </div>
                    </div>
                    <button type="button" @click="replicate('year')" :disabled="busy"
                            class="w-full py-2.5 mt-3 bg-gradient-to-r from-amber-500 to-orange-600 text-white text-xs font-bold rounded-lg hover:from-amber-600 hover:to-orange-700 active:scale-95 transition disabled:opacity-50 shadow-sm">
                        <span x-text="busy ? 'Replicando…' : 'Replicar año'"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 rounded-b-2xl flex items-center justify-between">
            <p class="text-xs text-gray-500">Las acciones sobrescriben días ya programados.</p>
            <div class="flex gap-2">
                <button type="button" @click="Alpine.store('modals').close()"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 uppercase hover:bg-gray-50 transition">
                    Cancelar
                </button>
            </div>
        </div>

        {{-- Toast (large, animated, persistent) --}}
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
window.replicateModal = (config) => ({
    templateId: config.templateId,
    selectedDay: config.selectedDay,
    year: config.year,
    month: config.month,
    csrfToken: config.csrfToken,
    existingBlocks: config.existingBlocks,

    hovered: null,
    busy: false,
    toast: { show: false, title: '', message: '' },
    _toastTimer: null,

    init() {},

    get daysInMonth() {
        return new Date(this.year, this.month, 0).getDate();
    },

get existingDays() {
        const cfg = window.replicateConfig || {};
        return new Set(cfg.existingBlocks || []);
    },

    get weekTargetDays() {
        const selectedDate = new Date(this.year, this.month - 1, this.selectedDay);
        const dayOfWeek = (selectedDate.getDay() + 6) % 7;
        const mondayDay = this.selectedDay - dayOfWeek;
        const daysInMonth = this.daysInMonth;
        const days = [];
        for (let i = 0; i < 7; i++) {
            const d = mondayDay + i;
            if (d >= 1 && d <= daysInMonth) days.push(d);
        }
        return days;
    },

    get monthTargetDays() {
        const days = [];
        for (let d = 1; d <= this.daysInMonth; d++) {
            if (d !== this.selectedDay) days.push(d);
        }
        return days;
    },

    get yearTargetDays() {
        return this.monthTargetDays;
    },

    calcPreview(targetDays) {
        const existing = this.existingDays;
        const overwrite = targetDays.filter(d => existing.has(d)).length;
        const fill = targetDays.length - overwrite;
        return { overwrite, fill };
    },

    get weekPreview() { return this.calcPreview(this.weekTargetDays); },
    get monthPreview() { return this.calcPreview(this.monthTargetDays); },
    get yearPreview() { return this.calcPreview(this.monthTargetDays); },

    async replicate(range) {
        if (this.busy) return;
        const cfg = window.replicateConfig || {};
        const templateId = cfg.templateId || '';
        const selectedDay = cfg.selectedDay || 1;
        const csrfToken = window.csrfToken || '';

        if (!templateId) {
            alert('No hay plantilla seleccionada');
            return;
        }

        const targetDays = range === 'week'
            ? this.weekTargetDays
            : range === 'month'
                ? this.monthTargetDays
                : this.yearTargetDays;

        if (targetDays.length === 0) {
            this.showToast('No hay días para replicar');
            return;
        }

        this.busy = true;
        try {
            const r = await fetch('/api/schedule-templates/' + templateId + '/replicate', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ source_day: selectedDay, target_days: targetDays }),
            });
            if (r.ok) {
                const data = await r.json();
                const rangeLabel = range === 'week' ? 'la semana' : (range === 'month' ? 'el mes' : 'el año');
                this.showToast('¡Replicación exitosa!', data.replicated + ' días actualizados en ' + rangeLabel);
                setTimeout(() => window.location.reload(), 1500);
            } else {
                const d = await r.json().catch(() => ({}));
                alert(d.message || 'Error al replicar');
            }
        } catch(e) {
            alert('Error de red');
        } finally {
            this.busy = false;
        }
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