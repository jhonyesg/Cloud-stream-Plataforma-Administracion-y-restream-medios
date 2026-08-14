@php
    $isEdit = isset($channel) && $channel->exists;
    $ownerOptions = \App\Models\User::orderBy('display_name')->orderBy('username')->limit(200)->get();
    $assignableUsers = \App\Models\User::orderBy('display_name')->orderBy('username')->limit(500)->get();
    $currentAssigned = $isEdit ? $channel->assignedUsers->pluck('id')->all() : [];

    $ownerList = $ownerOptions->mapWithKeys(fn ($u) => [$u->id => ($u->display_name ?? $u->username) . ' (' . $u->email . ')'])->all();
    $statusOptions = ['active' => 'active', 'draft' => 'draft', 'suspended' => 'suspended', 'archived' => 'archived'];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <x-form-input label="Nombre" name="display_name" :required="true" maxlength="120"
                  :value="$channel->display_name ?? ''" color="emerald" />

    <x-form-input label="Slug" name="slug" maxlength="120" pattern="[a-z0-9-]+"
                  :value="$channel->slug ?? ''" color="emerald"
                  placeholder="auto si vacío">
        <p class="mt-1 text-xs text-gray-500">Solo minúsculas, números y guiones. Se genera automáticamente si lo dejas vacío.</p>
    </x-form-input>

    <div class="sm:col-span-2">
        <label for="channel-public-hls-url-input" class="block text-sm font-medium text-gray-700 mb-1.5">
            URL pública HLS (player)
        </label>
        <input
            type="url"
            id="channel-public-hls-url-input"
            name="public_hls_url"
            maxlength="500"
            value="{{ old('public_hls_url', $channel->public_hls_url ?? '') }}"
            placeholder="https://canal.tudominio.com/live/{{ $channel->slug ?? '{slug}' }}.m3u8"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition font-mono text-sm"
        >
        <p class="mt-1 text-xs text-gray-500">URL <code>.m3u8</code> que nginx-rtmp sirve y consumen los espectadores. Se construye como <code>{tu_dominio}/live/{slug}.m3u8</code>. Requerida para previsualizar el canal desde el admin.</p>
        <p class="mt-1 text-sm text-red-600"
           x-show="$store.modals.errors.public_hls_url"
           x-text="$store.modals.errors.public_hls_url ? $store.modals.errors.public_hls_url[0] : ''"></p>
    </div>

    <div class="sm:col-span-2">
        <x-form-input type="select" label="Owner" name="owner_id" :required="true"
                      :options="$ownerList" :value="$channel->owner_id ?? ''"
                      placeholder="— Selecciona un owner —" color="emerald" />
    </div>

    <x-form-input type="select" label="Estado" name="status" :required="true"
                  :options="$statusOptions" :value="$channel->status ?? 'draft'" color="emerald" />

    <div class="sm:col-span-2 p-3 bg-emerald-50 border border-emerald-200 rounded-xl">
        <p class="text-sm text-emerald-800">
            <strong>Resolución virtual:</strong> {{ $channel->resolution ?? '1280×720' }}.
            <span class="text-emerald-700">Configurala desde el botón <em>Pantalla Virtual</em> en la lista de canales.</span>
        </p>
    </div>

    <div class="sm:col-span-2">
        <x-form-input type="textarea" label="Descripción" name="description" :rows="2" maxlength="2000"
                      :value="$channel->description ?? ''" color="emerald" />
    </div>

    <div class="sm:col-span-2">
        <label for="channel-root-path-input" class="block text-sm font-medium text-gray-700 mb-1.5">
            Ruta <span class="text-gray-400 text-xs ml-1">(directorio raíz de la multimedia)</span>
        </label>
        <div class="flex gap-2">
            <input
                type="text"
                id="channel-root-path-input"
                name="root_path"
                maxlength="1024"
                pattern="/[A-Za-z0-9._\/\- ]+"
                value="{{ old('root_path', $channel->root_path ?? '') }}"
                placeholder="/mnt/multimedia/mi-canal"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-xl shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition font-mono text-sm"
            >
            <button
                type="button"
                @click="$store.modals.open('fs-explorer', { target: 'channel-root-path-input', current: document.getElementById('channel-root-path-input').value })"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-700 text-white text-sm font-medium rounded-xl hover:bg-slate-600 shrink-0"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                Explorar
            </button>
        </div>
        <p class="mt-1 text-xs text-gray-500">Ruta absoluta del servidor. Ej: <code>/mnt/multimedia/cine-dios</code></p>
        <p class="mt-1 text-sm text-red-600"
           x-show="$store.modals.errors.root_path"
           x-text="$store.modals.errors.root_path ? $store.modals.errors.root_path[0] : ''"></p>
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
            Límite de almacenamiento (GB)
            <span class="text-xs text-gray-400 font-normal">— vacío = ilimitado</span>
        </label>
        <div class="flex items-center gap-3">
            <input type="number" name="storage_limit_gb" min="0" step="0.1"
                   value="{{ $channel->storage_limit_bytes !== null ? round($channel->storage_limit_bytes / (1024 * 1024 * 1024), 1) : '' }}"
                   placeholder="Ilimitado"
                   class="w-32 px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition">
            @if($isEdit)
                <span class="text-sm text-gray-500">
                    Usado actualmente: <strong>{{ \App\Models\User::humanBytes((int) $channel->used_bytes) }}</strong>
                </span>
            @endif
        </div>
        <p class="mt-1 text-xs text-gray-400">
            Aplica a la suma de archivos en este canal. Cada canal tiene su propia cuota independiente.
        </p>
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
            Usuarios asignados <span class="text-gray-400 text-xs ml-1">(acceso completo al canal)</span>
        </label>
        <x-user-multi-select
            name="assigned_user_ids"
            :value="$currentAssigned"
            :users="$assignableUsers"
            placeholder="Buscar usuarios por nombre, usuario o email…"
        />
        <p class="mt-1 text-xs text-gray-500">El owner tiene acceso implícito y no aparece aquí.</p>
        <p class="mt-1 text-sm text-red-600"
           x-show="$store.modals.errors.assigned_user_ids"
           x-text="$store.modals.errors.assigned_user_ids ? $store.modals.errors.assigned_user_ids[0] : ''"></p>
    </div>
</div>