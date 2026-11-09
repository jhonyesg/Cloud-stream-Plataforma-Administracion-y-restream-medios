@php
    $tone = $tone ?? 'danger';
    $iconName = $iconName ?? 'trash';
    $icons = [
        'trash' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linecap="round" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>',
        'archive' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 012-2h10a2 2 0 012 2v0a2 2 0 01-2 2H7a2 2 0 01-2-2v0zM5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4"/></svg>',
        'pause' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>',
        'alert' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.071 19h13.858c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
    ];
    $icon = $icons[$iconName] ?? $icons['trash'];

    $tones = [
        'danger'  => ['iconBg' => 'bg-red-100',    'iconColor' => 'text-red-600',    'banner' => 'bg-red-50 border-red-100 text-red-900',     'btn' => 'bg-red-600 hover:bg-red-500 text-white'],
        'warning' => ['iconBg' => 'bg-amber-100',  'iconColor' => 'text-amber-600',  'banner' => 'bg-amber-50 border-amber-100 text-amber-900', 'btn' => 'bg-amber-500 hover:bg-amber-400 text-gray-900'],
        'info'    => ['iconBg' => 'bg-indigo-100', 'iconColor' => 'text-indigo-600', 'banner' => 'bg-indigo-50 border-indigo-100 text-indigo-900','btn' => 'bg-indigo-600 hover:bg-indigo-500 text-white'],
    ];
    $t = $tones[$tone] ?? $tones['danger'];
@endphp

<x-app-modal
    name="confirm"
    :title="$title ?? ''"
    :subtitle="null"
    :icon="$icon"
    :iconBg="$t['iconBg']"
    :iconColor="$t['iconColor']"
    maxWidth="md"
>
    <div
        x-data="{
            busy: false,
            typed: '',
            get message()        { return ($store.modals.payload || {}).message || ''; },
            get cancelLabel()    { return ($store.modals.payload || {}).cancelLabel || 'Cancelar'; },
            get confirmLabel()   { return ($store.modals.payload || {}).confirmLabel || 'Confirmar'; },
            get busyLabel()      { return ($store.modals.payload || {}).busyLabel || 'Procesando…'; },
            get requireText()    { return ($store.modals.payload || {}).requireText || ''; },
            get action()         { return ($store.modals.payload || {}).action || ''; },
            get method()         { return (($store.modals.payload || {}).method || 'POST').toUpperCase(); },
            get body()           { return ($store.modals.payload || {}).body || null; },
            get titleText()      { return ($store.modals.payload || {}).title || '¿Estás seguro?'; },
            get successEvent()   { return ($store.modals.payload || {}).successEvent || 'crud-success'; },
            get errorEvent()     { return ($store.modals.payload || {}).errorEvent || 'crud-error'; },
            get tone()           { return ($store.modals.payload || {}).tone || 'danger'; },
            get requiresTyping() { return !!this.requireText; },
            get canConfirm()     { return !this.requiresTyping || this.typed === this.requireText; },
            get tones() {
                return {
                    danger:  { banner: 'bg-red-50 border-red-100 text-red-900',     btn: 'bg-red-600 hover:bg-red-500 text-white',  textColor: 'text-red-600' },
                    warning: { banner: 'bg-amber-50 border-amber-100 text-amber-900', btn: 'bg-amber-500 hover:bg-amber-400 text-gray-900', textColor: 'text-amber-600' },
                    info:    { banner: 'bg-indigo-50 border-indigo-100 text-indigo-900', btn: 'bg-indigo-600 hover:bg-indigo-500 text-white', textColor: 'text-indigo-600' },
                };
            },
            get t() { return this.tones[this.tone] || this.tones.danger; },
            confirm() {
                if (this.busy) return;
                if (!this.canConfirm) return;
                if (!this.action) {
                    Alpine.store('modals').close();
                    return;
                }
                this.busy = true;
                const init = {
                    method: ['GET','HEAD'].includes(this.method) ? 'GET' : this.method,
                    headers: {
                        'X-CSRF-TOKEN': window.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                };
                if (this.body && !['GET','DELETE','HEAD'].includes(this.method)) {
                    init.headers['Content-Type'] = 'application/json';
                    init.body = JSON.stringify(this.body);
                }
                fetch(this.action, init).then(async (r) => {
                    this.busy = false;
                    let data = {};
                    try { data = await r.json(); } catch (e) {}
                    if (r.ok) {
                        const msg = (data && (data.message || data.ok_message)) || 'Acción completada.';
                        Alpine.store('modals').close();
                        window.dispatchEvent(new CustomEvent(this.successEvent, { detail: Object.assign({ message: msg }, data) }));
                        if (this.successEvent === 'restream-targets-changed') {
                            window.dispatchEvent(new CustomEvent('restream-targets-changed'));
                        }
                    } else if (r.status === 422) {
                        Alpine.store('modals').close();
                        const msg = (data.errors && Object.values(data.errors).flat()[0]) || data.message || 'No se pudo completar la acción.';
                        window.dispatchEvent(new CustomEvent(this.errorEvent, { detail: { message: msg } }));
                    } else {
                        Alpine.store('modals').close();
                        window.dispatchEvent(new CustomEvent(this.errorEvent, { detail: { message: data.message || 'No se pudo completar la acción.' } }));
                    }
                }).catch(() => {
                    this.busy = false;
                    Alpine.store('modals').close();
                    window.dispatchEvent(new CustomEvent(this.errorEvent, { detail: { message: 'Error de red.' } }));
                });
            }
        }"
    >
        <div class="px-6 py-5">
            <h3 class="text-lg font-semibold text-gray-900 leading-tight mb-1" x-text="titleText">¿Estás seguro?</h3>
            <p class="text-sm text-gray-500" x-show="($store.modals.payload || {}).subtitle" x-text="($store.modals.payload || {}).subtitle"></p>

            <div class="mt-4 flex items-start gap-4 p-4 border rounded-xl" :class="t.banner">
                <svg class="w-6 h-6 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.071 19h13.858c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-sm" x-text="message">¿Estás seguro?</p>
            </div>

            <template x-if="requiresTyping">
                <div class="mt-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        Para confirmar, escribe <span class="font-bold select-all" x-text="requireText"></span>:
                    </label>
                    <input
                        type="text"
                        x-model="typed"
                        autocomplete="off"
                        class="block w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                        :class="typed.length > 0 && typed !== requireText ? 'border-red-300 text-red-700' : ''"
                    />
                    <p class="mt-1 text-xs text-gray-500"
                       x-show="typed.length > 0 && typed !== requireText">
                        No coincide. Tipea el texto exacto (mayúsculas/minúsculas importan).
                    </p>
                </div>
            </template>

            <div class="mt-6 -mx-6 px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 rounded-b-2xl relative z-40">
                <button type="button" @click="Alpine.store('modals').close()" :disabled="busy"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                    <span x-text="cancelLabel">Cancelar</span>
                </button>
                <button type="button" @click="confirm()" :disabled="busy || !canConfirm"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg font-semibold text-xs uppercase tracking-widest disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
                        :class="t.btn">
                    <svg x-show="!busy && action" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="busy" class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="busy ? busyLabel : confirmLabel">Confirmar</span>
                </button>
            </div>
        </div>
    </div>
</x-app-modal>
