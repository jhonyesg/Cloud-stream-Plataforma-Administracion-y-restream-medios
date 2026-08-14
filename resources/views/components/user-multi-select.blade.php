@props([
    'name' => 'assigned_user_ids',
    'value' => [],
    'users' => [],
    'placeholder' => 'Buscar usuarios…',
    'excludeOwner' => false,
])

@php
    $value = is_array($value) ? array_values(array_unique(array_filter($value))) : [];
    $selectedUsers = collect($users)->whereIn('id', $value);
    $valueJson = json_encode($value);
@endphp

<div
    x-data="{
        open: false,
        query: '',
        selectedIds: @js($value),
        allUsers: @js(collect($users)->map(fn($u) => [
            'id' => $u->id,
            'label' => trim(($u->display_name ?? '') . ' · ' . ($u->username ?? '') . ' · ' . ($u->email ?? '')),
            'name' => $u->display_name ?? $u->username ?? $u->email,
        ])->values()),
        get selectedUsers() {
            return this.allUsers.filter(u => this.selectedIds.includes(u.id));
        },
        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (!q) return this.allUsers;
            return this.allUsers.filter(u =>
                u.label.toLowerCase().includes(q) ||
                u.name.toLowerCase().includes(q)
            );
        },
        syncInput() {
            const inp = this.$refs.input;
            if (inp) inp.value = JSON.stringify(this.selectedIds);
        },
        toggle(id) {
            const i = this.selectedIds.indexOf(id);
            if (i >= 0) this.selectedIds.splice(i, 1);
            else this.selectedIds.push(id);
            this.syncInput();
        },
        remove(id) {
            const i = this.selectedIds.indexOf(id);
            if (i >= 0) this.selectedIds.splice(i, 1);
            this.syncInput();
        },
        init() { this.syncInput(); }
    }"
    @click.outside="open = false"
    class="relative"
>
    <input type="hidden" name="{{ $name }}" x-ref="input" :value="JSON.stringify(selectedIds)">

    <div class="w-full min-h-[42px] border border-gray-300 rounded-md shadow-sm bg-white px-2 py-1.5 flex flex-wrap gap-1.5 items-center cursor-text focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500"
         @click="open = true; $refs.search.focus()">
        <template x-for="u in selectedUsers" :key="u.id">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 text-xs">
                <span x-text="u.name"></span>
                <button type="button" @click.stop="remove(u.id)" class="text-indigo-600 hover:text-indigo-900" aria-label="Quitar">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </span>
        </template>
        <input
            x-ref="search"
            type="text"
            x-model="query"
            @focus="open = true"
            @keydown.escape="open = false"
            placeholder="{{ $selectedUsers->isNotEmpty() ? '' : $placeholder }}"
            class="flex-1 min-w-[120px] outline-none text-sm border-0 p-0 focus:ring-0"
        >
    </div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-20 mt-1 w-full bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 max-h-64 overflow-y-auto"
        style="display: none;"
    >
        <ul class="py-1">
            <template x-if="filtered.length === 0">
                <li class="px-3 py-2 text-sm text-gray-500">Sin resultados.</li>
            </template>
            <template x-for="u in filtered" :key="u.id">
                <li>
                    <button type="button" @click="toggle(u.id)"
                            class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-indigo-50 text-sm">
                        <span class="w-4 h-4 rounded border flex items-center justify-center shrink-0"
                              :class="selectedIds.includes(u.id) ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300 bg-white'">
                            <svg x-show="selectedIds.includes(u.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                        <span class="truncate" x-text="u.label"></span>
                    </button>
                </li>
            </template>
        </ul>
    </div>
</div>