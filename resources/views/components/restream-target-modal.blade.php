@props([
    'target' => null,
    'mediaImagesJson' => '[]',
])
@php
    $platforms = [
        'youtube' => [
            'name' => 'YouTube',
            'bg' => 'bg-red-50',
            'ring' => 'ring-red-200',
            'svg' => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M21.582 6.186a2.506 2.506 0 0 0-1.768-1.768C18.25 4 12 4 12 4s-6.25 0-7.814.418a2.506 2.506 0 0 0-1.768 1.768C2 7.75 2 12 2 12s0 4.25.418 5.814a2.506 2.506 0 0 0 1.768 1.768C5.75 20 12 20 12 20s6.25 0 7.814-.418a2.506 2.506 0 0 0 1.768-1.768C22 16.25 22 12 22 12s0-4.25-.418-5.814zM10 15.5v-7l6 3.5-6 3.5z"/></svg>',
        ],
        'facebook' => [
            'name' => 'Facebook',
            'bg' => 'bg-blue-50',
            'ring' => 'ring-blue-200',
            'svg' => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>',
        ],
        'custom' => [
            'name' => 'RTMP',
            'bg' => 'bg-amber-50',
            'ring' => 'ring-amber-200',
            'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1"/></svg>',
        ],
    ];
    $mediaImagesJson = $mediaImagesJson ?? '[]';
@endphp
<style>
    /* Modern datetime-local styling — strip browser default chrome */
    .datetime-input {
        color: #1f2937;
        font-family: inherit;
    }
    .datetime-input::-webkit-calendar-picker-indicator {
        opacity: 0;
        cursor: pointer;
        position: absolute;
        right: 0;
        top: 0;
        width: 100%;
        height: 100%;
    }
    .datetime-input::-webkit-datetime-edit,
    .datetime-input::-webkit-datetime-edit-fields-wrapper {
        color: #1f2937;
        padding: 0;
    }
    .datetime-input::-webkit-datetime-edit-month-field,
    .datetime-input::-webkit-datetime-edit-day-field,
    .datetime-input::-webkit-datetime-edit-year-field,
    .datetime-input::-webkit-datetime-edit-hour-field,
    .datetime-input::-webkit-datetime-edit-minute-field {
        color: #1f2937;
    }
    .datetime-input::-webkit-datetime-edit-text {
        color: #9ca3af;
    }
    .datetime-input:not(:focus):invalid::-webkit-datetime-edit {
        color: #9ca3af;
    }
    /* Firefox */
    .datetime-input {
        background-color: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 0.75rem;
    }
    /* Nice scrollbars in the modal body */
    .datetime-input::-webkit-scrollbar { display: none; }
    /* Scrollbar inside the modal form */
    [data-field="platform"] + div + div,
    .overflow-y-auto {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    .overflow-y-auto::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }
    .overflow-y-auto::-webkit-scrollbar-track {
        background: rgba(241, 245, 249, 0.6);
        border-radius: 8px;
    }
    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 8px;
        border: 2px solid transparent;
        background-clip: padding-box;
    }
    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #64748b;
        background-clip: padding-box;
        border: 2px solid transparent;
    }
</style>
<x-app-modal name="restream-target" title="Destino de Restream" subtitle="Configura un destino de retransmisión para este canal" maxWidth="3xl">
    <x-restream-urls />
<div
    x-data="{
        channel: null,
        mode: 'create',
        target: null,
        isAdmin: false,
        usedOutputs: 0,
        maxOutputs: 0,
        busy: false,
        errors: {},
        connectedAccounts: [],
        platform: 'facebook',
        platformOpen: false,
        useConnectedAccount: false,
        tab: 'datos',
        schedulesCount: 0,
        get platformAccount() { return this.connectedAccounts.find((a) => a.platform === this.platform) || null; },
        get canUseConnectedAccount() { return !!this.platformAccount && !this.platformAccount.needs_reconnect; },
        get scheduledMin() { return new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16); },
        get defaultPrivacy() { return this.platform === 'youtube' ? 'public' : 'unlisted'; },
        get _stopMinFromStart() {
            const inp = document.querySelector('[data-field=&quot;scheduled_start_at&quot;]');
            return inp && inp.value ? inp.value : this.scheduledMin;
        },
        get theme() {
            return {
                facebook: { border: 'focus:border-blue-500 focus:ring-blue-500', badge: 'bg-blue-100 ring-1 ring-blue-200', badgeRing: 'ring-blue-200', dot: 'bg-blue-500', swatch: '🔵', label: 'Facebook' },
                youtube: { border: 'focus:border-red-500 focus:ring-red-500', badge: 'bg-red-100 ring-1 ring-red-200', badgeRing: 'ring-red-200', dot: 'bg-red-500', swatch: '🔴', label: 'YouTube' },
                custom:  { border: 'focus:border-amber-500 focus:ring-amber-500', badge: 'bg-amber-100 ring-1 ring-amber-200', badgeRing: 'ring-amber-200', dot: 'bg-amber-500', swatch: '🟠', label: 'RTMP' },
            }[this.platform] || { border: 'focus:border-rose-500 focus:ring-rose-500', badge: 'bg-gray-100 ring-1 ring-gray-200', badgeRing: 'ring-gray-200', dot: 'bg-gray-500', swatch: '⚪', label: this.platform };
        },
        init() {
            this.$watch('isOpen', (open) => {
                if (open) {
                    const p = $store.modals.payload || {};
                    this.channel = p.channel || null;
                    this.mode = p.mode || 'create';
                    this.target = p.target || null;
                    this.$dispatch('restream-target-selected', { target: this.target });
                    this.isAdmin = !!p.isAdmin;
                    this.connectedAccounts = p.connectedAccounts || [];
                    this.usedOutputs = typeof p.usedOutputs === 'number' ? p.usedOutputs : 0;
                    this.maxOutputs = typeof p.maxOutputs === 'number' ? p.maxOutputs : 0;
                    this.errors = {};
                    this.platform = this.target ? this.target.platform : 'facebook';
                    this.useConnectedAccount = this.target ? !!this.target.platform_account_id : false;
                    this.$dispatch('restream-target-modal-opened', { target: this.target });
                }
            });
            window.addEventListener('restream-targets-changed', () => {
                const p = $store.modals.payload || {};
                if (typeof p.usedOutputs === 'number') this.usedOutputs = p.usedOutputs;
                if (typeof p.maxOutputs === 'number') this.maxOutputs = p.maxOutputs;
            });
        },
        focusField(field) {
            this.$nextTick(() => {
                const el = document.querySelector('[data-field=&quot;' + field + '&quot;]');
                if (el) { el.focus({ preventScroll: false }); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            });
        },
        async submit(ev) {
            this.busy = true; this.errors = {};
            const form = ev.target;
            const data = new FormData(form);

            if (this.useConnectedAccount && this.platformAccount) {
                data.set('platform_account_id', this.platformAccount.id);
                data.delete('destination_url');
                data.delete('stream_key');
            } else {
                data.delete('platform_account_id');
                data.delete('title');
                data.delete('description');
                data.delete('thumbnail_media_id');
            }
            // Scheduling applies to ALL platforms — never strip these.

            const scope = this.isAdmin ? 'admin' : 'client';
            const action = this.mode === 'create' ? 'store' : 'update';
            const url = window.restreamUrls[scope][action]
                .replace('CID', this.channel.id)
                .replace('TID', this.target ? this.target.id : '');

            const isEnabled = form.querySelector('[name="enabled"]').checked;

            let r;
            if (this.mode === 'create') {
                data.set('enabled', isEnabled ? '1' : '0');
                r = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: data,
                });
            } else {
                const payload = {};
                for (const [k, v] of data.entries()) { if (v !== '' && !(v instanceof File)) payload[k] = v; }
                payload.enabled = isEnabled ? '1' : '0';
                const thumbnailFile = data.get('thumbnail');
                if (thumbnailFile instanceof File && thumbnailFile.size > 0) {
                    data.set('_method', 'PATCH');
                    r = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: data,
                    });
                } else {
                    r = await fetch(url, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                }
            }
            this.busy = false;
            const body = await r.json().catch(() => ({}));
            if (r.ok) {
                Alpine.store('modals').close();
                window.dispatchEvent(new CustomEvent('restream-targets-changed'));
                window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: this.mode === 'create' ? 'Destino creado.' : 'Destino actualizado.' } }));
            } else if (r.status === 422) {
                this.errors = body.errors || { general: [body.message || 'Error de validación.'] };
            } else if (r.status === 403) {
                this.errors = { general: ['No tienes permiso para esta acción.'] };
            } else {
                this.errors = { general: [body.message || 'Error inesperado.'] };
            }
        }
    }"
    class="flex-1 overflow-y-auto min-h-0"
>
    <form @submit.prevent="submit($event)" x-ref="form" class="px-6 py-4 space-y-4">

            {{-- Consolidated error banner --}}
            <template x-if="Object.keys(errors).length > 0">
                <div class="bg-red-50 border border-red-200 rounded-xl p-3" role="alert">
                    <div class="flex items-center gap-2 text-sm font-semibold text-red-800 mb-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.071 19h13.858c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Hay errores que corregir
                    </div>
                    <ul class="text-sm text-red-700 space-y-0.5">
                        <template x-if="errors.general">
                            <li x-text="errors.general.join(' ')"></li>
                        </template>
                        <template x-for="(msgs, field) in Object.fromEntries(Object.entries(errors).filter(([k]) => k !== 'general'))" :key="field">
                            <li>
                                <button type="button" @click="focusField(field)" class="text-left underline decoration-dotted hover:text-red-900">
                                    <strong class="font-semibold" x-text="field"></strong>: <span x-text="(Array.isArray(msgs) ? msgs : [msgs]).join(' ')"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            {{-- Channel pill --}}
            <div x-show="channel" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-700 flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span><strong>Canal destino:</strong> <span x-text="channel ? channel.display_name : '—'"></span></span>
                <span x-show="mode === 'edit'" class="ml-auto text-[10px] uppercase tracking-wider text-gray-400">No editable</span>
            </div>

            {{-- Tabs (Datos / Programación) --}}
            <div x-show="mode === 'edit'" class="border-b border-gray-200 -mx-1">
                <nav class="flex gap-1 px-1" role="tablist">
                    <button type="button" @click="tab = 'datos'" :class="tab === 'datos' ? 'border-rose-500 text-rose-700' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2.5 text-sm font-medium border-b-2 transition">
                        Datos del destino
                    </button>
                    <button type="button" @click="tab = 'programacion'; $dispatch('restream-refresh-schedules')" :class="tab === 'programacion' ? 'border-rose-500 text-rose-700' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2.5 text-sm font-medium border-b-2 transition inline-flex items-center gap-2">
                        Programación
                        <span x-show="schedulesCount > 0" class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700" x-text="schedulesCount"></span>
                    </button>
                </nav>
            </div>

            {{-- Tab content: Datos --}}
            <div x-show="tab !== 'programacion' || mode !== 'edit'" class="space-y-4">

            {{-- Panel 1: Identificación --}}
            <fieldset class="rounded-xl border border-gray-200 bg-gray-50/40 p-4 space-y-3">
                <legend class="px-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Identificación</legend>
                <div @click.outside="platformOpen = false" @keydown.escape.window="platformOpen = false" class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plataforma</label>
                    <button type="button" @click="platformOpen = !platformOpen" :disabled="mode === 'edit'" data-field="platform"
                            :class="(mode === 'edit' ? 'cursor-not-allowed bg-gray-50 ' : '') + theme.border"
                            x-ref="trigger"
                            class="w-full flex items-center justify-between gap-2 rounded-xl border border-gray-300 focus:ring-rose-500 text-sm py-1.5 pl-2 pr-2.5 bg-white transition">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="w-7 h-7 rounded-md flex items-center justify-center shrink-0" :class="theme.badge" x-html="({!! e(json_encode($platforms)) !!})[platform]?.svg || ''"></span>
                            <span class="font-medium text-gray-900 truncate" x-text="({!! e(json_encode($platforms)) !!})[platform]?.name || platform"></span>
                        </span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 transition" :class="platformOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" name="platform" :value="platform" required>
                    <div x-show="platformOpen" x-transition x-cloak
                         class="absolute z-20 mt-1 left-0 right-0 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
                        <button type="button" @click="platform = 'facebook'; platformOpen = false"
                                :class="platform === 'facebook' ? 'bg-blue-50' : 'hover:bg-gray-50'"
                                class="w-full flex items-center gap-2 px-2.5 py-2 text-sm transition text-left">
                            <span class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 bg-blue-100 ring-1 ring-blue-200">
                                <span x-html="({!! e(json_encode($platforms)) !!})['facebook']?.svg || ''"></span>
                            </span>
                            <span class="flex-1">
                                <span class="block font-medium text-gray-900">Facebook Live</span>
                                <span class="block text-[11px] text-gray-500">Conexión directa OAuth — crea la transmisión automáticamente.</span>
                            </span>
                            <svg x-show="platform === 'facebook'" class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </button>
                        <button type="button" @click="platform = 'youtube'; platformOpen = false"
                                :class="platform === 'youtube' ? 'bg-red-50' : 'hover:bg-gray-50'"
                                class="w-full flex items-center gap-2 px-2.5 py-2 text-sm transition text-left border-t border-gray-100">
                            <span class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 bg-red-100 ring-1 ring-red-200">
                                <span x-html="({!! e(json_encode($platforms)) !!})['youtube']?.svg || ''"></span>
                            </span>
                            <span class="flex-1">
                                <span class="block font-medium text-gray-900">YouTube Live</span>
                                <span class="block text-[11px] text-gray-500">Conexión directa OAuth — crea la transmisión automáticamente.</span>
                            </span>
                            <svg x-show="platform === 'youtube'" class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </button>
                        <button type="button" @click="platform = 'custom'; platformOpen = false"
                                :class="platform === 'custom' ? 'bg-amber-50' : 'hover:bg-gray-50'"
                                class="w-full flex items-center gap-2 px-2.5 py-2 text-sm transition text-left border-t border-gray-100">
                            <span class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 bg-amber-100 ring-1 ring-amber-200">
                                <span x-html="({!! e(json_encode($platforms)) !!})['custom']?.svg || ''"></span>
                            </span>
                            <span class="flex-1">
                                <span class="block font-medium text-gray-900">Personalizado (RTMP)</span>
                                <span class="block text-[11px] text-gray-500">URL de destino + Stream Key manual. Sin OAuth.</span>
                            </span>
                            <svg x-show="platform === 'custom'" class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="name" data-field="name" maxlength="80" required class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm py-2.5" :value="target ? target.name : ''" placeholder="Ej. Facebook Live Principal">
                </div>
            </fieldset>

            {{-- Panel 2: Conexión --}}
            <fieldset class="rounded-xl border border-gray-200 bg-gray-50/40 p-4 space-y-3">
                <legend class="px-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Conexión</legend>

                <template x-if="canUseConnectedAccount">
                    <div>
                        <input type="checkbox" x-model="useConnectedAccount" :disabled="mode === 'edit'" class="sr-only peer">
<div @click="if (mode !== 'edit') useConnectedAccount = !useConnectedAccount"
                             :class="useConnectedAccount ? (theme.badge + ' ring-2') : 'border-gray-200 bg-white hover:bg-gray-50'"
                             class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition select-none"
                             data-field="useConnectedAccount">
                            <span :class="theme.badge"
                                  class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center transition">
                                <span x-html="({!! e(json_encode($platforms)) !!})[platform]?.svg || platform"></span>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-semibold text-gray-900">
                                    Usar mi cuenta de <span class="font-bold" x-text="platformAccount ? platformAccount.display_name : ''"></span>
                                </span>
                                <span class="block text-xs text-gray-500 mt-0.5">Crea la transmisión automáticamente. Ideal para YouTube y Facebook con OAuth conectado.</span>
                                <span x-show="platformAccount && !platformAccount.needs_reconnect" class="inline-flex items-center gap-1.5 mt-1.5 text-[11px] font-medium text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Conectada
                                </span>
                                <span x-show="platformAccount && platformAccount.needs_reconnect" class="inline-flex items-center gap-1.5 mt-1.5 text-[11px] font-medium text-amber-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Necesita reconectar
                                </span>
                            </span>
                            <span :class="useConnectedAccount ? 'bg-indigo-600' : 'bg-gray-200'" class="shrink-0 mt-1 relative inline-flex h-5 w-9 items-center rounded-full transition">
                                <span :class="useConnectedAccount ? 'translate-x-4' : 'translate-x-0.5'" class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"></span>
                            </span>
                        </div>
                    </div>
                </template>

                <template x-if="!useConnectedAccount || !canUseConnectedAccount">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">URL de destino (RTMP/RTMPS)</label>
                            <input type="url" name="destination_url" data-field="destination_url" :required="!useConnectedAccount" maxlength="2048" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm py-2.5 font-mono" :value="target ? target.destination_url : ''" placeholder="rtmps://live-api-s.facebook.com:443/rtmp/">
                        </div>
                        <div x-show="isAdmin">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                URL fuente (opcional, admin)
                                <span class="text-xs text-gray-500 font-normal">— si se deja vacía, se usa el RTMP local del canal</span>
                            </label>
                            <input type="url" name="source_url" data-field="source_url" maxlength="2048" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm py-2.5 font-mono" :value="target ? target.source_url : ''" placeholder="rtmp://emision.local/app/canal-demo">
                            <p class="mt-1 text-xs text-gray-500">Esta URL es la entrada del proceso FFmpeg. Si no la defines, el motor consume la salida RTMP del canal (<code class="px-1 bg-gray-100 rounded">virtualScreen.output_url</code>). El canal debe tener una salida RTMP configurada; no se usa HLS como origen.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <span x-text="mode === 'create' ? 'Stream Key' : 'Stream Key (dejar vacío para conservar)'"></span>
                            </label>
                             <input type="text" name="stream_key" data-field="stream_key" :required="mode === 'create' && !useConnectedAccount" minlength="2" maxlength="500" autocomplete="off" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm py-2.5 font-mono" placeholder="••••••••">
                             <p class="mt-1 text-xs text-gray-500">Se almacena cifrada en el servidor. Nunca se muestra en listas.</p>
                         </div>
                     </div>
                 </template>
             </fieldset>

            <fieldset class="rounded-xl border border-gray-200 bg-gray-50/40 p-4">
                <legend class="px-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Programación</legend>
                <p class="text-xs text-gray-600 leading-snug">
                    La programación ahora vive en la pestaña <strong>Programación</strong> de este mismo modal (arriba). Puedes definir múltiples ventanas con inicio, fin (opcional) y zona horaria, sin límite de cantidad.
                </p>
            </fieldset>

            {{-- Panel 4: Activación --}}
            <fieldset class="rounded-xl border border-gray-200 bg-gray-50/40 p-4">
                <label class="inline-flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="enabled" data-field="enabled" value="1" :checked="!target || target.enabled" :disabled="mode === 'create' && maxOutputs > 0 && usedOutputs >= maxOutputs" class="w-4 h-4 rounded border-gray-300 text-rose-600 shadow-sm focus:border-rose-500 focus:ring-rose-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="text-sm font-medium text-gray-700">Activar al guardar <span class="text-xs text-gray-500 font-normal">(consume un slot)</span></span>
                </label>
                <p x-show="mode === 'create' && maxOutputs > 0 && usedOutputs >= maxOutputs" x-cloak class="mt-2 text-xs text-amber-700">
                    Has alcanzado el límite de destinos activos para este canal (<span x-text="usedOutputs"></span>/<span x-text="maxOutputs"></span>). Desactiva uno existente desde la columna Acciones para liberar un slot.
                </p>
            </fieldset>

            </div>{{-- /Tab content: Datos --}}

            {{-- Tab content: Programación --}}
            @php $modalTargetId = isset($target) && $target ? $target->id : null; @endphp
            <div x-show="tab === 'programacion' &amp;&amp; mode === 'edit'"
                 data-target-id="@if($modalTargetId){{ $modalTargetId }}@endif"
                 x-data="rtsmInModal($el.dataset.targetId || null)"
                 x-init="init(); $watch('$root.parentElement._x_dataStack?.[0]?.target', t => { if (t && t.id) { targetId = t.id; reload(); } });"
                 @restream-refresh-schedules.window="reload()"
                 @restream-target-selected.window="if ($event.detail && $event.detail.target && $event.detail.target.id) { targetId = $event.detail.target.id; reload(); }"
                 class="space-y-4">

                {{-- MODO DE EMISIÓN (estado actual grande y visible) --}}
                <div class="rounded-2xl border-2 overflow-hidden" :class="emissionPanelClass">
                    <div class="px-5 py-4 flex items-center gap-4">
                        <div class="shrink-0 w-12 h-12 rounded-full flex items-center justify-center" :class="emissionIconBg">
                            <template x-if="emissionState === 'live'">
                                <span class="relative flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                                </span>
                            </template>
                            <template x-if="emissionState === 'next'">
                                <svg class="w-6 h-6 text-sky-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </template>
                            <template x-if="emissionState === 'idle'">
                                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                            </template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[11px] uppercase tracking-wider font-bold" :class="emissionLabelColor" x-text="emissionLabel"></div>
                            <div class="text-lg font-semibold text-gray-900 mt-0.5" x-text="emissionHeadline"></div>
                            <div class="text-xs text-gray-600 mt-0.5" x-text="emissionSubline"></div>
                        </div>
                        <div class="shrink-0" x-show="emissionState === 'next'">
                            <div class="text-2xl font-bold tabular-nums text-sky-700" x-text="emissionCountdown"></div>
                            <div class="text-[10px] uppercase tracking-wider text-gray-500 text-center">para empezar</div>
                        </div>
                        <div class="shrink-0" x-show="emissionState === 'live'">
                            <div class="text-2xl font-bold tabular-nums text-rose-600" x-text="emissionRuntime"></div>
                            <div class="text-[10px] uppercase tracking-wider text-gray-500 text-center">en vivo</div>
                        </div>
                    </div>
                </div>

                {{-- CALENDARIO MENSUAL --}}
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex items-center justify-between mb-3 gap-2">
                        <h4 class="text-sm font-semibold text-gray-800 inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span x-text="monthLabel()"></span>
                        </h4>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="prevMonth()" class="p-1.5 rounded hover:bg-gray-100 text-gray-600" title="Mes anterior">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button type="button" @click="today()" class="px-2 py-1 text-xs rounded border border-gray-200 hover:bg-gray-50 font-medium">Hoy</button>
                            <button type="button" @click="nextMonth()" class="p-1.5 rounded hover:bg-gray-100 text-gray-600" title="Mes siguiente">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-[10px] uppercase font-semibold text-gray-500 mb-1.5 text-center">
                        <template x-for="d in ['L','M','X','J','V','S','D']" :key="d">
                            <div x-text="d"></div>
                        </template>
                    </div>
                    <div class="grid grid-cols-7 gap-1">
                        <template x-for="(day, idx) in monthDays()" :key="idx">
                            <button type="button"
                                    @click="day.iso && openNewOnDay(day.iso)"
                                    :disabled="!day.iso"
                                    class="text-left rounded-md border min-h-[64px] p-1 flex flex-col transition"
                                    :class="!day.iso ? 'border-transparent cursor-default' : (day.isToday ? 'border-rose-400 bg-rose-50/40 hover:bg-rose-50' : 'border-gray-200 hover:border-rose-300 hover:bg-gray-50')">
                                <template x-if="day.iso">
                                    <div class="flex flex-col h-full">
                                        <div class="text-xs font-bold tabular-nums" :class="day.isToday ? 'text-rose-600' : 'text-gray-700'" x-text="day.day"></div>
                                        <div class="flex-1 mt-0.5 space-y-0.5 overflow-hidden">
                                             <template x-for="w in day.windows" :key="w.id">
                                                 <div class="text-[9px] leading-tight px-1 py-0.5 rounded truncate cursor-pointer"
                                                      :class="w.status === 'running' ? 'bg-rose-500 text-white' : (w.status === 'stopped' ? 'bg-amber-100 text-amber-800' : (w.status === 'ended' ? 'bg-sky-100 text-sky-800' : 'bg-emerald-100 text-emerald-800'))"
                                                      :title="(w.name || 'Sin nombre') + ' · ' + (w.startShort || '') + '–' + (w.endShort || '')"
                                                      @click.stop="openEdit(w)">
                                                     <span x-text="w.label"></span>
                                                 </div>
                                             </template>
                                            <div x-show="day.moreCount > 0" class="text-[9px] text-gray-500 px-1">+<span x-text="day.moreCount"></span> más</div>
                                        </div>
                                    </div>
                                </template>
                            </button>
                        </template>
                    </div>
                    <div class="text-[10px] text-gray-500 mt-2 flex items-center gap-3">
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-emerald-200"></span> Pendiente</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-rose-500"></span> En vivo</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-sky-200"></span> Finalizado</span>
                        <span class="ml-auto">Click en un día para crear</span>
                    </div>
                </div>

                {{-- LISTA DE VENTANAS --}}
                <div class="rounded-xl border border-gray-200 bg-white">
                    <div class="px-4 py-3 flex items-center justify-between border-b border-gray-100">
                        <h4 class="text-sm font-semibold text-gray-800">Todas las ventanas programadas</h4>
                        <div class="flex items-center gap-2">
                            <a :href="historyUrl" class="text-xs text-gray-500 hover:text-gray-700 underline decoration-dotted">Ver historial</a>
                            <button type="button" @click="openNew()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-rose-600 text-white hover:bg-rose-500 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                Nueva ventana
                            </button>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                        <template x-if="loading">
                            <div class="px-4 py-6 text-center text-xs text-gray-500">Cargando…</div>
                        </template>
                        <template x-if="!loading && schedules.length === 0">
                            <div class="px-4 py-6 text-center text-sm text-gray-500">
                                <p class="font-medium text-gray-700">Aún no has programado ninguna ventana.</p>
                                <p class="text-xs mt-1">Click en un día del calendario o en <strong>Nueva ventana</strong>.</p>
                                <p class="text-[10px] mt-2 text-gray-400">
                                    Cuando la hora llegue, el motor <code>restream:run-target-schedules</code> enciende y apaga este destino automáticamente.
                                </p>
                            </div>
                        </template>
                        <template x-for="s in schedules" :key="s.id">
                            <div class="px-4 py-2.5 flex items-center gap-3 hover:bg-gray-50/60">
                                <div class="shrink-0 w-10 h-7 rounded bg-gray-200 overflow-hidden flex items-center justify-center" x-show="s.thumbnail_url || (target && target.thumbnail_path)">
                                    <img :src="s.thumbnail_url || (target && target.thumbnail_path)" class="w-full h-full object-cover" referrerpolicy="no-referrer">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-medium text-gray-800 truncate" x-text="s.name || 'Sin nombre'"></span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold" :class="badgeColor(s.status)" x-text="statusLabel(s.status)"></span>
                                        <span x-show="s.stopped_by_user" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700">Detenido por ti</span>
                                        <span x-show="!s.enabled" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-200 text-gray-700">Deshabilitada</span>
                                        <span x-show="s.thumbnail_media_id || s.thumbnail_path" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-100 text-indigo-800" title="Esta ventana tiene miniatura personalizada">📌 miniatura propia</span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        <span class="font-medium" x-text="fmtDate(s.starts_at, s.timezone)"></span>
                                        <span class="text-gray-300 mx-1">→</span>
                                        <span class="font-medium" x-text="fmtDate(s.ends_at, s.timezone)"></span>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <span class="text-gray-400" x-text="`${durationLabel(s)}`"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button type="button" @click="openEdit(s)" title="Editar ventana" class="p-1.5 rounded text-gray-500 hover:text-rose-600 hover:bg-rose-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button type="button" @click="removeSchedule(s)" title="Eliminar ventana" class="p-1.5 rounded text-gray-500 hover:text-red-600 hover:bg-red-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Sub-modal para crear/editar ventana (visual) --}}
                <template x-if="modal.open">
                    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="closeModal()">
                        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6" @click.stop>
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="modal.id ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900" x-text="modal.id ? 'Editar ventana' : 'Nueva ventana de emisión'"></h3>
                                    <p class="text-xs text-gray-500">Define cuándo se enciende y se apaga este destino.</p>
                                </div>
                            </div>
                            <form @submit.prevent="save()">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre (opcional)
                                    <input type="text" x-model="form.name" maxlength="120" placeholder="Ej. Noticiero de la mañana" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-500">
                                </label>

                                <div class="grid grid-cols-2 gap-3 mt-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">📅 Inicio <span class="text-rose-500">*</span></label>
                                        <input type="datetime-local" x-model="form.starts_at" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">🛑 Fin <span class="text-rose-500">*</span></label>
                                        <input type="datetime-local" x-model="form.ends_at" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-500">
                                    </div>
                                </div>

                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Presets de duración:</span>
                                    <button type="button" @click="setDuration(30)" class="px-2 py-0.5 text-xs rounded-full border border-gray-300 hover:bg-rose-50 hover:border-rose-400">+30min</button>
                                    <button type="button" @click="setDuration(60)" class="px-2 py-0.5 text-xs rounded-full border border-gray-300 hover:bg-rose-50 hover:border-rose-400">+1h</button>
                                    <button type="button" @click="setDuration(120)" class="px-2 py-0.5 text-xs rounded-full border border-gray-300 hover:bg-rose-50 hover:border-rose-400">+2h</button>
                                    <button type="button" @click="setDuration(180)" class="px-2 py-0.5 text-xs rounded-full border border-gray-300 hover:bg-rose-50 hover:border-rose-400">+3h</button>
                                    <button type="button" @click="setDuration(60 * 24)" class="px-2 py-0.5 text-xs rounded-full border border-gray-300 hover:bg-rose-50 hover:border-rose-400">+24h</button>
                                </div>

                                <div x-show="form.starts_at && form.ends_at" class="mt-2 px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-xs text-gray-700">
                                    <span class="text-gray-500">Duración:</span> <strong class="tabular-nums" x-text="formDuration()"></strong>
                                </div>

                                <label class="block text-sm font-medium text-gray-700 mt-3 mb-1.5">🌐 Zona horaria <span class="text-rose-500">*</span></label>
                                <div class="flex gap-2 items-stretch">
                                    <select x-model="form.timezone" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-500">
                                        <template x-for="tz in timezonesOrdered" :key="tz">
                                            <option :value="tz" x-text="tz + (tz === 'America/Bogota' ? ' (Colombia, defecto)' : '')"></option>
                                        </template>
                                    </select>
                                    <button type="button" @click="form.timezone = 'America/Bogota'" class="px-2 text-xs text-gray-500 hover:text-rose-600 border border-gray-200 rounded-lg" title="Restablecer a Bogotá">🇨🇴</button>
                                </div>
                                <p class="text-[10px] text-gray-500 mt-1">Por defecto <strong>America/Bogota</strong>. Los horarios se muestran en esta zona.</p>

                                {{-- Detalles de la emisión (por ventana) --}}
                                <div class="mt-4 pt-3 border-t border-gray-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Detalles de la emisión</h4>
                                        <span class="text-[10px] text-gray-500">Usa los del destino si los dejas vacíos</span>
                                    </div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">📝 Título de la emisión
                                        <input type="text" x-model="form.schedule_title" maxlength="150" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-500" :placeholder="target ? (target.title || 'Ej. Noticiero de la mañana') : ''">
                                    </label>

                                    <label class="block text-sm font-medium text-gray-700 mt-3 mb-1">🔒 Privacidad
                                        <select x-model="form.platform_privacy" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-500">
                                            <option value="">Por defecto del destino (<span x-text="target ? (target.platform_privacy || 'public') : 'public'"></span>)</option>
                                            <option value="public">Público</option>
                                            <option value="unlisted">Oculto (unlisted)</option>
                                            <option value="private">Privado</option>
                                        </select>
                                    </label>

                                    <label class="inline-flex items-start gap-2.5 cursor-pointer mt-3 p-2.5 rounded-lg border border-gray-200 hover:bg-gray-50">
                                        <input type="checkbox" x-model="form.keep_recording" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-rose-600 shadow-sm focus:ring-rose-500">
                                        <span class="text-sm text-gray-700 leading-snug">
                                            <strong>Conservar la grabación</strong>
                                            <span class="block text-xs text-gray-500 mt-0.5">Si lo desactivas, el video solo se ve en vivo y desaparece al finalizar.</span>
                                        </span>
                                    </label>
                                </div>

                                <div class="mt-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">🖼️ Miniatura de la emisión</label>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-3 space-y-2">
                                        <div class="flex items-center gap-3">
                                            <div class="shrink-0 w-20 h-12 rounded bg-gray-200 overflow-hidden flex items-center justify-center border border-gray-200">
                                                <img x-show="scheduleThumbnailPreview()" :src="scheduleThumbnailPreview()" class="w-full h-full object-cover" referrerpolicy="no-referrer">
                                                <span x-show="!scheduleThumbnailPreview()" class="text-[10px] text-gray-400 text-center px-1">Sin miniatura</span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs text-gray-700">
                                                    <span x-show="!form.thumbnail_media_id && !form.thumbnail_path">Usará la miniatura del destino (defecto).</span>
                                                    <span x-show="form.thumbnail_media_id" class="font-medium text-rose-700">📌 Miniatura personalizada (de tu biblioteca)</span>
                                                    <span x-show="form.thumbnail_path && !form.thumbnail_media_id" class="font-medium text-rose-700">📌 Miniatura por URL</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 mt-1.5">
                                                    <button type="button" x-show="form.thumbnail_media_id" @click="form.thumbnail_media_id = null; form.thumbnail_path = null" class="text-[10px] text-red-600 hover:text-red-800">× Quitar</button>
                                                </div>
                                            </div>
                                        </div>
                                        <details class="text-xs" x-data="{ open: false }" :open="open">
                                            <summary @click="open = !open" class="cursor-pointer text-gray-500 hover:text-rose-600 select-none">
                                                <span x-show="!open">📂 Elegir de mi biblioteca Multimedia (<span x-text="mediaImages.length"></span> disponibles)</span>
                                                <span x-show="open">📂 Ocultar biblioteca</span>
                                            </summary>
                                            <div class="mt-2">
                                                <template x-if="!mediaImages.length">
                                                    <div class="text-center py-3 text-gray-500">
                                                        <p>No tienes imágenes en este canal.</p>
                                                        <a href="/client/media" target="_blank" class="text-rose-600 underline mt-1 inline-block">Subir imágenes en Multimedia →</a>
                                                    </div>
                                                </template>
                                                <template x-if="mediaImages.length">
                                                    <div class="grid grid-cols-3 gap-2 max-h-48 overflow-y-auto p-1">
                                                        <template x-for="m in mediaImages" :key="m.id">
                                                            <button type="button" @click="form.thumbnail_media_id = m.id; form.thumbnail_path = null; open = false"
                                                                    :class="form.thumbnail_media_id === m.id ? 'ring-2 ring-rose-500 border-rose-300' : 'border-gray-200 hover:border-rose-300'"
                                                                    class="relative rounded border overflow-hidden bg-white group">
                                                                <div class="aspect-video bg-gray-100">
                                                                    <img :src="m.thumb_url" :alt="m.filename" class="w-full h-full object-cover" referrerpolicy="no-referrer">
                                                                </div>
                                                                <div class="px-1 py-0.5 text-[9px] text-gray-600 truncate" x-text="m.filename"></div>
                                                                <div x-show="form.thumbnail_media_id === m.id" class="absolute top-1 right-1 w-5 h-5 rounded-full bg-rose-500 text-white flex items-center justify-center text-[10px]">✓</div>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </details>
                                        <details class="text-xs" x-data="{ open: false }">
                                            <summary @click="open = !open" class="cursor-pointer text-gray-500 hover:text-rose-600 select-none">
                                                <span x-show="!open">🔗 O pegar URL externa</span>
                                                <span x-show="open">🔗 Ocultar URL</span>
                                            </summary>
                                            <input type="url" x-model="form.thumbnail_path" @change="if (form.thumbnail_path) form.thumbnail_media_id = null" placeholder="https://i.imgur.com/mi-imagen.jpg" class="mt-2 w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                        </details>
                                    </div>
                                </div>

                                <label class="block text-sm font-medium text-gray-700 mt-3 mb-1.5">Notas (opcional)</label>
                                <textarea x-model="form.notes" maxlength="1000" rows="2" placeholder="Anotaciones internas…" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-500"></textarea>

                                <div class="flex items-center justify-between mt-5 pt-4 border-t border-gray-100">
                                    <button x-show="modal.id" type="button" @click="removeSchedule(modal.id)" class="text-red-600 hover:text-red-800 text-sm font-medium">Eliminar</button>
                                    <div class="flex items-center gap-2 ml-auto">
                                        <button type="button" @click="closeModal()" class="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50 font-medium">Cancelar</button>
                                        <button type="submit" :disabled="busy" class="px-5 py-2 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-500 disabled:opacity-50 font-semibold inline-flex items-center gap-1.5">
                                            <template x-if="busy"><svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg></template>
                                            <span x-text="modal.id ? 'Guardar cambios' : 'Crear ventana'"></span>
                                        </button>
                                    </div>
                                </div>
                                <p x-show="error" x-text="error" class="mt-2 text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></p>
                            </form>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Footer (sticky at bottom of modal) --}}
        </form>
        <div class="shrink-0 flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <button type="button" @click="Alpine.store('modals').close()" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50">Cancelar</button>
            <button type="button" @click="$refs.form?.requestSubmit()" :disabled="busy" class="inline-flex items-center gap-2 px-5 py-2.5 bg-rose-600 text-white text-sm font-medium rounded-xl hover:bg-rose-500 disabled:opacity-50 shadow-sm">
                <svg x-show="busy" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-show="!busy" x-text="mode === 'create' ? 'Crear destino' : 'Guardar cambios'"></span>
                <span x-show="busy">Guardando…</span>
            </button>
        </div>
    </div>
</x-app-modal>

 @once
    <script>
        // Inyecta la lista de imágenes del canal para que todos los componentes
        // anidados (Datos + Programación) puedan buscarlas.
        @php $rtsmMediaImages = json_decode($mediaImagesJson, true) ?: []; @endphp
        window.__rtsmMediaImages = @json($rtsmMediaImages);
        // Reused inside the restream-target-modal Programación tab.
        window.rtsmInModal = function (targetId) {
            return {
                targetId,
                schedules: [],
                loading: false,
                timezones: @json(\DateTimeZone::listIdentifiers()),
                mediaImages: window.__rtsmMediaImages || [],
                form: { name: '', starts_at: '', ends_at: '', timezone: 'America/Bogota', notes: '', thumbnail_path: null, thumbnail_media_id: null, schedule_title: '', schedule_description: '', platform_privacy: '', keep_recording: true },
                modal: { open: false, id: null },
                busy: false,
                error: null,
                _nowTimer: null,
                // Lista de zonas horarias con America/Bogota primero (la del cliente por defecto).
                get timezonesOrdered() {
                    const rest = this.timezones.filter(tz => tz !== 'America/Bogota');
                    return ['America/Bogota', ...rest];
                },
                get historyUrl() {
                    if (!this.targetId) return '#';
                    return '/client/restream/restream-targets/' + this.targetId + '/events/history';
                },
                // Sube por el DOM hasta el x-data del modal padre que tiene `target`.
                _getOuterScope() {
                    let cursor = this.$root.parentElement;
                    while (cursor) {
                        const stack = cursor._x_dataStack;
                        if (stack) {
                            for (const s of stack) {
                                if (s && s.target) return s;
                            }
                        }
                        cursor = cursor.parentElement;
                    }
                    return null;
                },
                get listUrl() {
                    return this.targetId ? '/client/restream/restream-targets/' + this.targetId + '/schedules' : null;
                },
                async init() {
                    // Sube por el DOM hasta encontrar el x-data del modal padre
                    // (que tiene `target`), no el nuestro.
                    let cursor = this.$root.parentElement;
                    while (cursor) {
                        const stack = cursor._x_dataStack;
                        if (stack) {
                            for (const scope of stack) {
                                if (scope && scope.target && scope.target.id) {
                                    this.targetId = scope.target.id;
                                    break;
                                }
                            }
                            if (this.targetId) break;
                        }
                        cursor = cursor.parentElement;
                    }
                    if (this.targetId) {
                        await this.reload();
                        this._nowTimer = setInterval(() => { this._now = Date.now(); }, 1000);
                    }
                },
                destroy() { if (this._nowTimer) clearInterval(this._nowTimer); },
                _now: Date.now(),
                _todayIso() {
                    const d = new Date();
                    const off = d.getTimezoneOffset() * 60000;
                    return new Date(d.getTime() - off).toISOString().slice(0, 10);
                },
                get next7Days() {
                    // Mantenido por compat: el calendario mensual usa monthDays() del componente rtsmMonthCal.
                    return this.schedules.slice(0, 7);
                },
                _todayIsoFromDate(d) {
                    const off = d.getTimezoneOffset() * 60000;
                    return new Date(d.getTime() - off).toISOString().slice(0, 10);
                },
                // Vista mensual del calendario. Devuelve 42 celdas (6 semanas × 7 días),
                // alineadas para que la semana empiece en lunes.
                monthDays() {
                    const tz = 'America/Bogota';
                    const ref = this._calCursor || new Date();
                    const year = ref.getFullYear();
                    const month = ref.getMonth();
                    const firstOfMonth = new Date(year, month, 1);
                    const firstWeekday = (firstOfMonth.getDay() + 6) % 7; // lunes=0
                    const start = new Date(year, month, 1 - firstWeekday);
                    const today = new Date();
                    const todayIso = this._dateInTz(today, tz);
                    const out = [];
                    for (let i = 0; i < 42; i++) {
                        const d = new Date(start);
                        d.setDate(start.getDate() + i);
                        const inMonth = d.getMonth() === month;
                        const iso = this._dateInTz(d, tz);
                        const wins = this.schedules
                            // s.starts_at is UTC. Comparamos con la fecha local en tz.
                            .filter(s => s.starts_at && this._dateInTz(new Date(s.starts_at), s.timezone || tz) === iso)
                            .map(s => ({
                                id: s.id, name: s.name, status: s.status,
                                startShort: this._fmtHm(s.starts_at, s.timezone || tz),
                                endShort: this._fmtHm(s.ends_at, s.timezone || tz),
                                label: (s.name || '●') + ' ' + this._fmtHm(s.starts_at, s.timezone || tz),
                            }));
                        out.push({
                            iso: inMonth ? iso : null,
                            day: d.getDate(),
                            isToday: iso === todayIso,
                            windows: wins.slice(0, 3),
                            moreCount: wins.length > 3 ? wins.length - 3 : 0,
                        });
                    }
                    return out;
                },
                _dateInTz(d, tz) {
                    try {
                        const parts = new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(d);
                        const y = parts.find(p => p.type === 'year').value;
                        const m = parts.find(p => p.type === 'month').value;
                        const day = parts.find(p => p.type === 'day').value;
                        return `${y}-${m}-${day}`;
                    } catch (e) {
                        const off = d.getTimezoneOffset() * 60000;
                        return new Date(d.getTime() - off).toISOString().slice(0, 10);
                    }
                },
                _fmtHm(iso, tz) {
                    if (!iso) return '';
                    try {
                        const d = new Date(iso);
                        return d.toLocaleTimeString('es-CO', { timeZone: tz || 'America/Bogota', hour: '2-digit', minute: '2-digit', hour12: false });
                    } catch (e) { return ''; }
                },
                prevMonth() {
                    const c = this._calCursor || new Date();
                    this._calCursor = new Date(c.getFullYear(), c.getMonth() - 1, 1);
                },
                nextMonth() {
                    const c = this._calCursor || new Date();
                    this._calCursor = new Date(c.getFullYear(), c.getMonth() + 1, 1);
                },
                todayMonth() {
                    this._calCursor = new Date();
                },
                monthLabel() {
                    const c = this._calCursor || new Date();
                    try {
                        return c.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' });
                    } catch (e) { return ''; }
                },
                // Miniatura efectiva de la ventana: la personalizada si existe, si no la del target.
                scheduleThumbnailPreview() {
                    if (this.form.thumbnail_path) return this.form.thumbnail_path;
                    if (this.form.thumbnail_media_id) {
                        const m = this.mediaImages.find(x => x.id === this.form.thumbnail_media_id);
                        if (m && m.thumb_url) return m.thumb_url;
                    }
                    try {
                        const root = this.$root.closest('[x-data]');
                        if (root && root._x_dataStack) {
                            for (const s of root._x_dataStack) {
                                if (s.target && s.target.thumbnail_path) return s.target.thumbnail_path;
                            }
                        }
                    } catch (e) {}
                    return null;
                },
                async uploadScheduleThumb(ev) {
                    const file = ev.target.files?.[0];
                    if (!file) return;
                    this.busy = true;
                    this.error = null;
                    try {
                        const fd = new FormData();
                        fd.append('file', file);
                        fd.append('context', 'restream_target_schedule');
                        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                        const r = await fetch('/api/media/upload', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            body: fd,
                        });
                        if (!r.ok) {
                            const t = await r.json().catch(() => ({}));
                            this.error = t.message || ('Error ' + r.status);
                            this.busy = false; return;
                        }
                        const body = await r.json();
                        this.form.thumbnail_path = body.url || body.path;
                    } catch (e) { this.error = String(e); }
                    this.busy = false;
                },
                get _runningSchedule() {
                    return this.schedules.find(s => s.status === 'running') || null;
                },
                get _nextSchedule() {
                    const nowIso = new Date().toISOString();
                    return this.schedules
                        .filter(s => s.status === 'pending' && s.starts_at > nowIso)
                        .sort((a, b) => a.starts_at.localeCompare(b.starts_at))[0] || null;
                },
                get emissionState() {
                    if (this._runningSchedule) return 'live';
                    if (this._nextSchedule) return 'next';
                    return 'idle';
                },
                get emissionLabel() {
                    return ({
                        live: '🔴 EN VIVO AHORA',
                        next: '⏱ PRÓXIMA EMISIÓN',
                        idle: '💤 SIN EMISIONES PROGRAMADAS',
                    })[this.emissionState];
                },
                get emissionHeadline() {
                    if (this.emissionState === 'live') {
                        const s = this._runningSchedule;
                        return s?.name || 'Emisión en curso';
                    }
                    if (this.emissionState === 'next') {
                        const s = this._nextSchedule;
                        return s?.name || 'Emisión programada';
                    }
                    return 'Programa cuándo se enciende este destino';
                },
                get emissionSubline() {
                    if (this.emissionState === 'live') {
                        const s = this._runningSchedule;
                        if (!s?.ends_at) return 'Iniciada hace un momento';
                        return 'Termina a las ' + (s.ends_at?.slice(11, 16) || '');
                    }
                    if (this.emissionState === 'next') {
                        const s = this._nextSchedule;
                        return 'Inicia el ' + this.fmtDate(s.starts_at, s.timezone);
                    }
                    return 'Usa la pestaña Programación para crear una';
                },
                get emissionPanelClass() {
                    return ({
                        live: 'border-rose-300 bg-gradient-to-br from-rose-50 to-red-50',
                        next: 'border-sky-300 bg-gradient-to-br from-sky-50 to-blue-50',
                        idle: 'border-gray-200 bg-gray-50',
                    })[this.emissionState];
                },
                get emissionIconBg() {
                    return ({
                        live: 'bg-rose-100',
                        next: 'bg-sky-100',
                        idle: 'bg-gray-200',
                    })[this.emissionState];
                },
                get emissionLabelColor() {
                    return ({
                        live: 'text-rose-700',
                        next: 'text-sky-700',
                        idle: 'text-gray-500',
                    })[this.emissionState];
                },
                get emissionCountdown() {
                    if (this.emissionState !== 'next') return '';
                    const target = new Date(this._nextSchedule.starts_at).getTime();
                    const diff = Math.max(0, target - this._now);
                    const h = Math.floor(diff / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const s = Math.floor((diff % 60000) / 1000);
                    if (h >= 24) return Math.floor(h / 24) + 'd ' + (h % 24) + 'h';
                    if (h > 0) return h + 'h ' + m + 'm';
                    if (m > 0) return m + 'm ' + s + 's';
                    return s + 's';
                },
                get emissionRuntime() {
                    if (this.emissionState !== 'live') return '';
                    const s = this._runningSchedule;
                    if (!s?.starts_at) return '00:00:00';
                    const start = new Date(s.starts_at).getTime();
                    const diff = Math.max(0, this._now - start);
                    const h = Math.floor(diff / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const sec = Math.floor((diff % 60000) / 1000);
                    return [h, m, sec].map(n => String(n).padStart(2, '0')).join(':');
                },
                async reload() {
                    if (!this.listUrl) return;
                    this.loading = true;
                    try {
                        const r = await fetch(this.listUrl, { headers: { 'Accept': 'application/json' } });
                        if (r.ok) {
                            const body = await r.json();
                            this.schedules = body.data || [];
                        }
                    } catch (e) { /* silent */ }
                    this.loading = false;
                },
                badgeColor(status) {
                    return {
                        running: 'bg-rose-100 text-rose-800',
                        pending: 'bg-emerald-100 text-emerald-800',
                        ended: 'bg-sky-100 text-sky-800',
                        stopped: 'bg-amber-100 text-amber-800',
                        disabled: 'bg-gray-200 text-gray-700',
                    }[status] || 'bg-gray-100 text-gray-700';
                },
                statusLabel(status) {
                    return { running: 'En vivo', pending: 'Pendiente', ended: 'Finalizado', stopped: 'Detenido', disabled: 'Deshabilitada' }[status] || status;
                },
                fmtDate(iso, tz) {
                    if (!iso) return '—';
                    try {
                        const d = new Date(iso);
                        return d.toLocaleString('es-CO', { timeZone: tz || 'UTC', dateStyle: 'short', timeStyle: 'short' });
                    } catch (e) { return iso; }
                },
                durationLabel(s) {
                    if (!s.starts_at || !s.ends_at) return '';
                    const ms = new Date(s.ends_at) - new Date(s.starts_at);
                    if (ms <= 0) return '';
                    const m = Math.floor(ms / 60000);
                    const h = Math.floor(m / 60);
                    if (h >= 24) return Math.floor(h / 24) + 'd ' + (h % 24) + 'h';
                    if (h > 0) return h + 'h ' + (m % 60) + 'm';
                    return m + ' min';
                },
                openNew() { this.openNewOnDay(null); },
                openNewOnDay(iso) {
                    const start = iso ? new Date(iso + 'T09:00:00') : new Date(Date.now() + 5 * 60000);
                    const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);
                    const root = this._getOuterScope();
                    const t = root?.target || null;
                    this.form = {
                        name: '',
                        starts_at: this._localIso(start),
                        ends_at: this._localIso(end),
                        timezone: 'America/Bogota',
                        notes: '',
                        // Por defecto la miniatura del destino; el cliente puede cambiarla
                        thumbnail_path: t?.thumbnail_path || null,
                        thumbnail_media_id: t?.thumbnail_media_id || null,
                        // Por defecto los datos del destino (título, privacidad, grabación)
                        schedule_title: t?.title || '',
                        schedule_description: t?.description || '',
                        platform_privacy: t?.platform_privacy || '',
                        keep_recording: t?.keep_recording !== false,
                    };
                    this.modal = { open: true, id: null };
                    this.error = null;
                },
                openEdit(s) {
                    this.form = {
                        name: s.name || '',
                        starts_at: s.starts_at ? s.starts_at.slice(0, 16) : '',
                        ends_at: s.ends_at ? s.ends_at.slice(0, 16) : '',
                        timezone: s.timezone || 'America/Bogota',
                        notes: s.notes || '',
                        thumbnail_path: s.thumbnail_path || null,
                        thumbnail_media_id: s.thumbnail_media_id || null,
                        schedule_title: s.schedule_title || '',
                        schedule_description: s.schedule_description || '',
                        platform_privacy: s.platform_privacy || '',
                        keep_recording: s.keep_recording !== false,
                    };
                    this.modal = { open: true, id: s.id };
                    this.error = null;
                },
                closeModal() { this.modal.open = false; },
                setDuration(minutes) {
                    if (!this.form.starts_at) {
                        this.form.starts_at = this._localIso(new Date());
                    }
                    const start = new Date(this.form.starts_at);
                    const end = new Date(start.getTime() + minutes * 60000);
                    this.form.ends_at = this._localIso(end);
                },
                formDuration() {
                    if (!this.form.starts_at || !this.form.ends_at) return '—';
                    const ms = new Date(this.form.ends_at) - new Date(this.form.starts_at);
                    if (ms <= 0) return '⚠ Fin debe ser después del inicio';
                    const m = Math.floor(ms / 60000);
                    const h = Math.floor(m / 60);
                    if (h >= 24) return Math.floor(h / 24) + 'd ' + (h % 24) + 'h ' + (m % 60) + 'm';
                    if (h > 0) return h + 'h ' + (m % 60) + 'm';
                    return m + ' minutos';
                },
                _localIso(d) {
                    const tz = d.getTimezoneOffset() * 60000;
                    return new Date(d.getTime() - tz).toISOString().slice(0, 16);
                },
                async save() {
                    this.busy = true; this.error = null;
                    try {
                        if (!this.form.starts_at || !this.form.ends_at) {
                            this.error = 'Debes indicar fecha de inicio y de fin.';
                            this.busy = false; return;
                        }
                        if (new Date(this.form.ends_at) <= new Date(this.form.starts_at)) {
                            this.error = 'El fin debe ser posterior al inicio.';
                            this.busy = false; return;
                        }
                        const payload = { ...this.form };
                        const url = this.modal.id ? this.listUrl + '/' + this.modal.id : this.listUrl;
                        const method = this.modal.id ? 'PATCH' : 'POST';
                        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                        const r = await fetch(url, {
                            method,
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        if (!r.ok) {
                            const body = await r.json().catch(() => ({}));
                            this.error = (body.message || ('HTTP ' + r.status));
                            this.busy = false; return;
                        }
                        this.closeModal();
                        await this.reload();
                        if (window.Alpine) {
                            const root = this.$root.closest('[x-data]');
                            if (root && root._x_dataStack) {
                                for (const s of root._x_dataStack) { if (s && 'schedulesCount' in s) { s.schedulesCount = this.schedules.length; break; } }
                            }
                        }
                    } catch (e) { this.error = String(e); }
                    this.busy = false;
                },
                async removeSchedule(idOrObj) {
                    const id = typeof idOrObj === 'string' ? idOrObj : idOrObj?.id;
                    if (!id) return;
                    if (!confirm('¿Eliminar esta ventana?')) return;
                    this.busy = true;
                    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                    const r = await fetch(this.listUrl + '/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
                    if (r.ok) {
                        this.closeModal();
                        await this.reload();
                    }
                    this.busy = false;
                },
            };
        };
    </script>
@endonce