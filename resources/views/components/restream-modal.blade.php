@php
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
    $tierOptions = [
        1 => 'Base (gratis) — 1 destino',
        2 => '+1 destino (pago) — 2 destinos',
        3 => '+2 destinos (pago) — 3 destinos',
        4 => '+3 destinos (pago) — 4 destinos',
    ];
@endphp

<x-app-modal name="restream" title="Módulo Restream" subtitle="Habilita Restream para este canal y configura el límite de destinos simultáneos" maxWidth="3xl" :icon="$icon" iconBg="bg-rose-100" iconColor="text-rose-600">
    <div
        x-data="{
            busy: false,
            channelId: null,
            channelName: '',
            quota: null,
            owner: null,
            targets: [],
            tiers: [1,2,3,4],
            errors: {},
            async load() {
                const p = $store.modals.payload || {};
                const id = p.channel_id || null;
                if (!id) return;
                this.channelId = id;
                this.channelName = p.channel_name || '';
                const r = await fetch(`/admin/channels/${id}/restream`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) {
                    const d = await r.json();
                    this.quota = d.quota;
                    this.owner = d.owner;
                    this.targets = d.targets || [];
                    this.tiers = d.tiers || [1,2,3,4];
                }
            },
            init() {
                this.$watch('isOpen', async (open) => {
                    if (open) {
                        this.errors = {};
                        await this.load();
                    } else {
                        this.channelId = null;
                        this.channelName = '';
                        this.quota = null;
                        this.owner = null;
                        this.targets = [];
                    }
                });
            },
            get hasQuota() { return !!this.quota; },
            async submitGrant(form) {
                this.busy = true; this.errors = {};
                const data = new FormData(form);
                data.set('enabled', data.get('enabled') ? '1' : '0');
                const r = await fetch(`/admin/channels/${this.channelId}/restream`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: data,
                });
                this.busy = false;
                const payload = await r.json().catch(() => ({}));
                if (r.ok) { this.quota = payload.quota; window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Restream habilitado.' } })); }
                else if (r.status === 422) { this.errors = payload.errors || { general: [payload.message || 'Error de validación.'] }; }
                else { this.errors = { general: [payload.message || 'Error inesperado.'] }; }
            },
            async submitUpdate(form) {
                this.busy = true; this.errors = {};
                const data = new FormData(form);
                if (data.get('enabled') !== null) data.set('enabled', data.get('enabled') ? '1' : '0');
                const r = await fetch(`/admin/channels/${this.channelId}/restream`, {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: data,
                });
                this.busy = false;
                const payload = await r.json().catch(() => ({}));
                if (r.ok) { this.quota = payload.quota; window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Cuota actualizada.' } })); }
                else if (r.status === 422) { this.errors = payload.errors || { general: [payload.message || 'Error de validación.'] }; }
                else { this.errors = { general: [payload.message || 'Error inesperado.'] }; }
            },
            async disableQuota() {
                if (!confirm('¿Eliminar la cuota de Restream para este canal? Sus destinos quedarán deshabilitados.')) return;
                this.busy = true;
                const r = await fetch(`/admin/channels/${this.channelId}/restream`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.busy = false;
                if (r.ok) { this.quota = null; this.targets = []; window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Cuota eliminada.' } })); }
            },
        }"
    >
        <template x-if="!channelId">
            <div class="p-6 text-sm text-gray-500">Selecciona un canal desde la vista de Canales.</div>
        </template>

        <template x-if="channelId && !hasQuota">
            <form @submit.prevent="submitGrant($event.target)" class="p-6 space-y-4">
                <div class="bg-gray-50 border border-gray-200 rounded-md p-3 text-xs text-gray-600 flex items-center justify-between gap-3">
                    <span><strong>Canal:</strong> <span x-text="channelName"></span></span>
                    <template x-if="owner">
                        <span><strong>Cliente:</strong> <span x-text="owner.display_name || owner.username"></span></span>
                    </template>
                </div>
                <div class="bg-rose-50 border border-rose-200 rounded-md p-4 text-sm text-rose-800">
                    Este canal aún no tiene el módulo Restream habilitado. Concede un tier para activar el módulo. El tier <strong>base (1 destino)</strong> es gratuito; cada nivel adicional es de pago.
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Habilitar al guardar</label>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="enabled" value="1" checked class="rounded border-gray-300 text-rose-600 shadow-sm focus:border-rose-500 focus:ring-rose-500">
                        <span class="text-sm text-gray-700">Activado inmediatamente</span>
                    </label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tier inicial</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($tierOptions as $value => $label)
                            <label class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-md cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="max_outputs" value="{{ $value }}" {{ $value === 1 ? 'checked' : '' }} class="text-rose-600 focus:ring-rose-500">
                                <span class="text-sm text-gray-800">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                    <textarea name="notes" rows="2" maxlength="500" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm" placeholder="Plan contratado, referencia de pago, etc."></textarea>
                </div>
                <template x-if="errors.general">
                    <div class="text-sm text-red-600" x-text="errors.general.join(' ')"></div>
                </template>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="submit" :disabled="busy" class="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 text-white text-sm font-medium rounded-md hover:bg-rose-500 disabled:opacity-50">
                        <span x-show="!busy">Habilitar Restream</span>
                        <span x-show="busy">Guardando…</span>
                    </button>
                </div>
            </form>
        </template>

        <template x-if="channelId && hasQuota">
            <div class="p-6 space-y-6">
                <div class="bg-gray-50 border border-gray-200 rounded-md p-3 text-xs text-gray-600 flex items-center justify-between gap-3">
                    <span><strong>Canal:</strong> <span x-text="channelName"></span></span>
                    <template x-if="owner">
                        <span><strong>Cliente:</strong> <span x-text="owner.display_name || owner.username"></span></span>
                    </template>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-md p-4 text-sm flex flex-wrap gap-4">
                    <div>
                        <div class="text-xs uppercase text-gray-500">Estado</div>
                        <div class="font-medium" :class="quota.enabled ? 'text-green-700' : 'text-red-700'" x-text="quota.enabled ? 'Habilitado' : 'Deshabilitado'"></div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Tier</div>
                        <div class="font-medium text-gray-900" x-text="quota.max_outputs + ' destinos'"></div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Otorgado</div>
                        <div class="font-medium text-gray-900" x-text="quota.granted_at ? new Date(quota.granted_at).toLocaleDateString() : '—'"></div>
                    </div>
                </div>

                <form @submit.prevent="submitUpdate($event.target)" class="space-y-4 border-t pt-4">
                    <h4 class="text-sm font-semibold text-gray-800">Editar habilitación</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="enabled" value="1" :checked="quota.enabled" class="rounded border-gray-300 text-rose-600 shadow-sm focus:border-rose-500 focus:ring-rose-500">
                            <span class="text-sm text-gray-700">Módulo habilitado</span>
                        </label>
                        <div>
                            <select name="max_outputs" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm">
                                @foreach($tierOptions as $value => $label)
                                    <option value="{{ $value }}" :selected="quota && quota.max_outputs === {{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                        <textarea name="notes" rows="2" maxlength="500" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm" x-text="quota.notes || ''"></textarea>
                    </div>
                    <template x-if="errors.general">
                        <div class="text-sm text-red-600" x-text="errors.general.join(' ')"></div>
                    </template>
                    <template x-if="errors.max_outputs">
                        <div class="text-sm text-red-600" x-text="errors.max_outputs.join(' ')"></div>
                    </template>
                    <div class="flex justify-between gap-2">
                        <button type="button" @click="disableQuota()" class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-red-300 text-red-700 text-sm font-medium rounded-md hover:bg-red-50">
                            Eliminar cuota
                        </button>
                        <button type="submit" :disabled="busy" class="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 text-white text-sm font-medium rounded-md hover:bg-rose-500 disabled:opacity-50">
                            <span x-show="!busy">Guardar cambios</span>
                            <span x-show="busy">Guardando…</span>
                        </button>
                    </div>
                </form>

                <div class="border-t pt-4">
                    <h4 class="text-sm font-semibold text-gray-800 mb-2">Destinos configurados en este canal (<span x-text="targets.length"></span>)</h4>
                    <template x-if="targets.length === 0">
                        <p class="text-sm text-gray-500">Este canal aún no tiene destinos configurados.</p>
                    </template>
                    <template x-if="targets.length > 0">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Plataforma</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Habilitado</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Último arranque</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-for="t in targets" :key="t.id">
                                        <tr>
                                            <td class="px-3 py-2 text-gray-700" x-text="t.platform"></td>
                                            <td class="px-3 py-2 text-gray-700" x-text="t.name"></td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 text-xs rounded" :class="t.enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'" x-text="t.enabled ? 'Sí' : 'No'"></span>
                                            </td>
                                            <td class="px-3 py-2 text-gray-700" x-text="t.status"></td>
                                            <td class="px-3 py-2 text-gray-500" x-text="t.last_started_at ? new Date(t.last_started_at).toLocaleString() : '—'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</x-app-modal>
