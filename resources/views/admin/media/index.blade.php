<x-admin-layout active="media">
    <x-slot:header>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Biblioteca Multimedia</h2>
                <p class="text-sm text-gray-500 mt-0.5">Gestiona todos los archivos multimedia del sistema</p>
            </div>
        </div>
    </x-slot:header>

    {{-- Stats row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        @php
            $videoCount = \App\Models\MediaItem::where('kind', 'video')->count();
            $readyCount = \App\Models\MediaItem::where('status', 'ready')->count();
            $pendingCount = \App\Models\MediaItem::where('status', 'pending')->count();
            $statCards = [
                ['label' => 'Total', 'value' => $mediaItems->total(), 'color' => 'amber', 'icon' => 'M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7M4 7l8-4 8 4M4 7l8 4 8-4'],
                ['label' => 'Videos', 'value' => $videoCount, 'color' => 'violet', 'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
                ['label' => 'Listos', 'value' => $readyCount, 'color' => 'emerald', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label' => 'Pendientes', 'value' => $pendingCount, 'color' => 'amber', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ];
        @endphp
        @foreach($statCards as $card)
            <div class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-{{ $card['color'] }}-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-{{ $card['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/></svg>
                </div>
                <div class="min-w-0">
                    <div class="text-2xl font-bold text-gray-900 leading-none">{{ $card['value'] }}</div>
                    <div class="text-xs text-gray-400 font-medium uppercase tracking-wide mt-1">{{ $card['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Filter bar + upload button --}}
    <div class="flex items-center justify-between gap-4 mb-4">
        <div class="flex-1">
            <x-media-filter-bar
                :channels="$channels"
                :currentChannel="$currentChannel"
                :currentKind="$currentKind"
                :currentStatus="$currentStatus"
                :currentSearch="$currentSearch"
            />
        </div>
    </div>

    <div class="flex items-center justify-between mb-4"
         x-data="{
             busy: false,
             async generateThumbs() {
                 if (this.busy) return;
                 this.busy = true;
                 try {
                     const fd = new FormData();
                     const ch = @js($currentChannel ?: '');
                     if (ch) fd.append('channel_id', ch);
                     fd.append('limit', '50');
                     const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                     const res = await fetch('{{ route('media.thumbnails.generate') }}', {
                         method: 'POST',
                         headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                         body: fd,
                     });
                     if (!res.ok) throw new Error('HTTP ' + res.status);
                     const data = await res.json();
                     const msg = `Miniaturas generadas: ${data.processed}` +
                                 (data.failed ? ` (${data.failed} con error)` : '') +
                                 (data.remaining ? ` · restantes: ${data.remaining}` : '');
                     window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: msg } }));
                     if (data.remaining === 0) {
                         setTimeout(() => window.location.reload(), 1200);
                     }
                 } catch (e) {
                     window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'No se pudieron generar las miniaturas: ' + e.message } }));
                 } finally {
                     this.busy = false;
                 }
             }
         }">
        <p class="text-sm text-gray-500">
            <span class="font-semibold text-gray-700">{{ $mediaItems->total() }}</span> medios
            @if($mediaItems->hasPages())
                · página {{ $mediaItems->currentPage() }}
            @endif
        </p>
        <div class="flex items-center gap-2 ml-4">
            <label for="per_page" class="text-xs text-gray-500 font-medium">Por página</label>
            <select id="per_page" onchange="window.location.href = this.dataset.base + (this.dataset.base.includes('?') ? '&' : '?') + 'per_page=' + this.value"
                    data-base="{{ url()->current() . '?' . http_build_query(request()->only(['channel_id','kind','status','q'])) }}"
                    class="text-xs bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 font-semibold text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-300 cursor-pointer">
                @foreach($allowedPerPage as $n)
                    <option value="{{ $n }}" @selected($currentPerPage === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="$store.mediaSelection.enabled = !$store.mediaSelection.enabled; if (!$store.mediaSelection.enabled) $store.mediaSelection.reset();"
                :class="$store.mediaSelection.enabled ? 'bg-amber-100 text-amber-800 ring-amber-300' : 'bg-white text-gray-700 ring-gray-900/5'"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-xl ring-1 hover:ring-amber-300 active:scale-95 shadow-sm transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-text="$store.mediaSelection.enabled ? 'Salir de selección' : 'Seleccionar'"></span>
            </button>
            <button
                type="button"
                x-show="$store.mediaSelection.enabled"
                @click='$store.mediaSelection.addMany(@json($mediaItems->pluck("id")->all())); $store.mediaSelection.enabled = true;'
                class="inline-flex items-center gap-2 px-3 py-2.5 bg-white text-gray-700 text-xs font-semibold rounded-xl ring-1 ring-gray-900/5 hover:bg-gray-50 hover:ring-amber-300 active:scale-95 shadow-sm transition-all"
            >
                Seleccionar todo ({{ $mediaItems->count() }})
            </button>
            <button
                type="button"
                @click="generateThumbs()"
                :disabled="busy"
                :class="busy ? 'opacity-60 cursor-wait' : ''"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-xl ring-1 ring-gray-900/5 hover:bg-gray-50 hover:ring-amber-300 active:scale-95 shadow-sm transition-all"
                title="Regenerar miniaturas de todos los items sin miniatura en el filtro actual"
            >
                <svg :class="busy ? 'animate-spin' : ''" class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                <span x-text="busy ? 'Generando…' : 'Regenerar todo'"></span>
            </button>
            <button
                type="button"
                @click="$store.modals.open('upload-media')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-sm font-semibold rounded-xl hover:from-amber-600 hover:to-orange-600 active:scale-95 shadow-md shadow-amber-500/20 transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                Subir archivos
            </button>
        </div>
    </div>

    {{-- Media grid --}}
    @if($mediaItems->isEmpty())
        <div class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm p-16 text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7M4 7l8-4 8 4M4 7l8 4 8-4"/></svg>
            </div>
            <p class="text-gray-500 text-sm">No hay medios para mostrar.</p>
            <p class="text-gray-400 text-xs mt-1">Sube archivos o ajusta los filtros.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($mediaItems as $item)
                <x-media-card :item="$item" :canEdit="true" :canDelete="true" :selectable="true" />
            @endforeach
        </div>
    @endif

    {{-- Pagination --}}
    @if($mediaItems->hasPages())
        <div class="mt-6">
            {{ $mediaItems->links() }}
        </div>
    @endif

    <x-media-uploader :available-channels="$availableChannels" />
    <x-media-player-modal />
    <x-media-rename-modal />
    <x-confirm-delete-modal />
    <x-media-bulk-bar />
</x-admin-layout>