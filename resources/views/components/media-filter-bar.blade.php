@props([
    'channels' => [],
    'currentChannel' => null,
    'currentKind' => null,
    'currentStatus' => null,
    'currentSearch' => null,
])

@php
    $kinds = [
        '' => 'Todos los tipos',
        'video' => 'Video',
        'image' => 'Imagen',
        'audio' => 'Audio',
        'ad' => 'Cuña (Publicidad)',
        'other' => 'Otro',
    ];
    $statuses = [
        '' => 'Todos los estados',
        'ready' => 'Listo',
        'pending' => 'Pendiente',
        'error' => 'Error',
        'processing' => 'Procesando',
    ];
    $hasFilters = $currentChannel || $currentKind || $currentStatus || $currentSearch;
@endphp

<div class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm p-4 mb-5">
    <form method="GET" action="" id="media-filter-form">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Canal</label>
                <div class="relative">
                    <select name="channel_id" onchange="this.form.submit()"
                            class="w-full pl-3 pr-8 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50/50 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition appearance-none cursor-pointer">
                        <option value="">Todos los canales</option>
                        @foreach($channels as $ch)
                            <option value="{{ $ch->id }}" @selected($currentChannel === $ch->id)>{{ $ch->display_name }}</option>
                        @endforeach
                    </select>
                    <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Tipo</label>
                <div class="relative">
                    <select name="kind" onchange="this.form.submit()"
                            class="w-full pl-3 pr-8 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50/50 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition appearance-none cursor-pointer">
                        @foreach($kinds as $value => $label)
                            <option value="{{ $value }}" @selected($currentKind === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Estado</label>
                <div class="relative">
                    <select name="status" onchange="this.form.submit()"
                            class="w-full pl-3 pr-8 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50/50 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition appearance-none cursor-pointer">
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Buscar</label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" name="q" value="{{ $currentSearch }}"
                               placeholder="Nombre del archivo…"
                               class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50/50 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:bg-white transition">
                    </div>
                    <button type="submit"
                            class="px-3.5 py-2.5 bg-amber-600 text-white rounded-xl text-sm font-semibold hover:bg-amber-500 active:scale-95 transition shrink-0 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>
                </div>
            </div>
        </div>

        @if($hasFilters)
            <div class="mt-3 flex items-center gap-2">
                <span class="text-xs text-gray-400 font-medium">Filtros activos</span>
                <a href="{{ request()->url() }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-gray-100 text-gray-600 text-xs font-medium hover:bg-gray-200 transition">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Limpiar
                </a>
            </div>
        @endif
    </form>
</div>