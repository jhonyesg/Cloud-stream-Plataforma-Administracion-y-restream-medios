<x-app-modal name="restream-target" title="Destino de Restream" subtitle="Configura un destino de retransmisión para este canal" maxWidth="2xl">
    <x-restream-urls />
<div
    x-data="{
        channel: null,
        mode: 'create',
        target: null,
        isAdmin: false,
        busy: false,
        errors: {},
        connectedAccounts: [],
        platform: 'facebook',
        useConnectedAccount: false,
        get platformAccount() { return this.connectedAccounts.find((a) => a.platform === this.platform) || null; },
        get canUseConnectedAccount() { return !!this.platformAccount && !this.platformAccount.needs_reconnect; },
        init() {
            this.$watch('isOpen', (open) => {
                if (open) {
                    const p = $store.modals.payload || {};
                    this.channel = p.channel || null;
                    this.mode = p.mode || 'create';
                    this.target = p.target || null;
                    this.isAdmin = !!p.isAdmin;
                    this.connectedAccounts = p.connectedAccounts || [];
                    this.errors = {};
                    this.platform = this.target ? this.target.platform : 'facebook';
                    this.useConnectedAccount = this.target ? !!this.target.platform_account_id : false;
                }
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
                    data.delete('thumbnail');
                    data.delete('scheduled_start_at');
                }

                const scope = this.isAdmin ? 'admin' : 'client';
                const action = this.mode === 'create' ? 'store' : 'update';
                const url = window.restreamUrls[scope][action]
                    .replace('CID', this.channel.id)
                    .replace('TID', this.target ? this.target.id : '');

                let r;
                if (this.mode === 'create') {
                    data.set('enabled', data.get('enabled') === 'on' ? '1' : '0');
                    r = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: data,
                    });
                } else {
                    const payload = {};
                    for (const [k, v] of data.entries()) { if (v !== '' && !(v instanceof File)) payload[k] = v; }
                    payload.enabled = data.get('enabled') === 'on' ? '1' : '0';
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
    >
        <form @submit.prevent="submit($event)" class="p-6 space-y-4">
            <div x-show="channel" class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-xs text-gray-700 flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span><strong>Canal destino:</strong> <span x-text="channel ? channel.display_name : '—'"></span></span>
                <span x-show="mode === 'edit'" class="ml-auto text-[10px] uppercase tracking-wider text-gray-400">No editable</span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Plataforma</label>
                <select name="platform" x-model="platform" :disabled="mode === 'edit'" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm" required>
                    <option value="facebook">Facebook Live</option>
                    <option value="tiktok">TikTok Live</option>
                    <option value="youtube">YouTube Live</option>
                    <option value="custom">Personalizado (RTMP)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                <input type="text" name="name" maxlength="80" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm" :value="target ? target.name : ''" placeholder="Ej. Facebook Live Principal">
            </div>

            <template x-if="canUseConnectedAccount">
                <label class="flex items-center gap-2 p-2.5 bg-indigo-50 border border-indigo-200 rounded-md">
                    <input type="checkbox" x-model="useConnectedAccount" :disabled="mode === 'edit'" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <span class="text-sm text-indigo-900">Usar mi cuenta de <span x-text="platformAccount ? platformAccount.display_name : ''"></span> conectada — crea la transmisión automáticamente</span>
                </label>
            </template>

            <template x-if="!useConnectedAccount || !canUseConnectedAccount">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL de destino (RTMP/RTMPS)</label>
                        <input type="url" name="destination_url" :required="!useConnectedAccount" maxlength="2048" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm" :value="target ? target.destination_url : ''" placeholder="rtmps://live-api-s.facebook.com:443/rtmp/">
                    </div>

                    <div x-show="isAdmin">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            URL fuente (opcional, admin)
                            <span class="text-xs text-gray-500">— si se deja vacía, se usa el RTMP local del canal</span>
                        </label>
                        <input type="url" name="source_url" maxlength="2048" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm font-mono" :value="target ? target.source_url : ''" placeholder="rtmp://emision.local/app/canal-demo">
                        <p class="mt-1 text-xs text-gray-500">Esta URL es la entrada del proceso FFmpeg. Si no la defines, el motor consume la salida RTMP del canal (<code class="px-1 bg-gray-100 rounded">virtualScreen.output_url</code>). El canal debe tener una salida RTMP configurada; no se usa HLS como origen.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <span x-text="mode === 'create' ? 'Stream Key' : 'Stream Key (dejar vacío para conservar)'"></span>
                        </label>
                        <input type="text" name="stream_key" :required="mode === 'create' && !useConnectedAccount" minlength="2" maxlength="500" autocomplete="off" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm font-mono" placeholder="••••••••">
                        <p class="mt-1 text-xs text-gray-500">Se almacena cifrada en el servidor. Nunca se muestra en listas.</p>
                    </div>
                </div>
            </template>

            <template x-if="useConnectedAccount && canUseConnectedAccount">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Título de la transmisión</label>
                        <input type="text" name="title" maxlength="150" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm" :value="target ? target.title : ''" placeholder="Ej. Partido en vivo">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea name="description" rows="3" maxlength="5000" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm" x-text="target ? target.description : ''"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Miniatura (opcional)</label>
                        <input type="file" name="thumbnail" accept="image/*" class="block w-full text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Programar inicio (opcional)</label>
                        <input type="datetime-local" name="scheduled_start_at" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm" :value="target ? target.scheduled_start_at : ''">
                        <p class="mt-1 text-xs text-gray-500">Si la dejas vacía, la transmisión se crea para empezar de inmediato al activar el destino.</p>
                    </div>
                </div>
            </template>

            <div>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="enabled" value="1" :checked="!target || target.enabled" class="rounded border-gray-300 text-rose-600 shadow-sm focus:border-rose-500 focus:ring-rose-500">
                    <span class="text-sm text-gray-700">Activar al guardar (consume un slot)</span>
                </label>
            </div>

            <template x-if="errors.general">
                <div class="text-sm text-red-600" x-text="errors.general.join(' ')"></div>
            </template>
            <template x-if="errors.restream">
                <div class="text-sm text-red-600" x-text="errors.restream.join(' ')"></div>
            </template>
            <template x-if="errors.platform"><div class="text-sm text-red-600" x-text="errors.platform.join(' ')"></div></template>
            <template x-if="errors.name"><div class="text-sm text-red-600" x-text="errors.name.join(' ')"></div></template>
            <template x-if="errors.destination_url"><div class="text-sm text-red-600" x-text="errors.destination_url.join(' ')"></div></template>
            <template x-if="errors.stream_key"><div class="text-sm text-red-600" x-text="errors.stream_key.join(' ')"></div></template>
            <template x-if="errors.platform_account_id"><div class="text-sm text-red-600" x-text="errors.platform_account_id.join(' ')"></div></template>
            <template x-if="errors.title"><div class="text-sm text-red-600" x-text="errors.title.join(' ')"></div></template>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="Alpine.store('modals').close()" class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50">Cancelar</button>
                <button type="submit" :disabled="busy" class="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 text-white text-sm font-medium rounded-md hover:bg-rose-500 disabled:opacity-50">
                    <span x-show="!busy" x-text="mode === 'create' ? 'Crear destino' : 'Guardar cambios'"></span>
                    <span x-show="busy">Guardando…</span>
                </button>
            </div>
        </form>
    </div>
</x-app-modal>