<x-client-layout active="media">
    <x-slot:header>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Biblioteca Multimedia</h2>
                <p class="text-sm text-gray-500 mt-0.5">Tus archivos multimedia</p>
            </div>
        </div>
    </x-slot:header>

    @if($channels->isEmpty())
        <div class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm p-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7M4 7l8-4 8 4M4 7l8 4 8-4"/></svg>
            </div>
            <p class="text-gray-700 font-medium">No tienes canales asignados</p>
            <p class="text-gray-500 text-sm mt-1">No tienes acceso a la biblioteca multimedia porque ningún canal te ha sido asignado.</p>
            <p class="text-gray-500 text-sm mt-2">Comunícate con el administrador para que te asigne un canal y puedas gestionar tus archivos multimedia.</p>
        </div>
    @else
        {{-- Stats row --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-5">
            @php
                $totalItems = $mediaItems->total();
                $videoCount = collect($mediaItems->items())->where('kind', 'video')->count();
                $readyCount = collect($mediaItems->items())->where('status', 'ready')->count();
                $statCards = [
                    ['label' => 'Total', 'value' => $totalItems, 'color' => 'amber', 'icon' => 'M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7M4 7l8-4 8 4M4 7l8 4 8-4'],
                    ['label' => 'Videos', 'value' => $videoCount, 'color' => 'violet', 'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
                    ['label' => 'Listos', 'value' => $readyCount, 'color' => 'emerald', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
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

        {{-- Filter bar --}}
        <x-media-filter-bar
            :channels="$channels"
            :currentChannel="$currentChannel"
            :currentKind="$currentKind"
            :currentStatus="$currentStatus"
            :currentSearch="$currentSearch"
        />

<div class="flex items-center justify-between mb-4 gap-3 flex-wrap"
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
                     setTimeout(() => window.location.reload(), 1200);
                 } catch (e) {
                     window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: 'No se pudieron generar las miniaturas: ' + e.message } }));
                 } finally {
                     this.busy = false;
                 }
             }
         }">
            <div class="flex items-center gap-3 flex-wrap">
                <p class="text-sm text-gray-500">
                    <span class="font-semibold text-gray-700">{{ $mediaItems->total() }}</span> medios
                    @if($mediaItems->hasPages())
                        · página {{ $mediaItems->currentPage() }}
                    @endif
                </p>
                <button
                    type="button"
                    @click="generateThumbs()"
                    :disabled="busy"
                    :class="busy ? 'opacity-60 cursor-wait' : ''"
                    class="inline-flex items-center gap-2 px-3 py-2 bg-white text-gray-700 text-xs font-semibold rounded-xl ring-1 ring-gray-900/5 hover:bg-gray-50 hover:ring-amber-300 active:scale-95 shadow-sm transition-all"
                >
                    <svg :class="busy ? 'animate-spin' : ''" class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    <span x-text="busy ? 'Generando…' : 'Generar miniaturas'"></span>
                </button>
                <button
                    type="button"
                    @click="$store.mediaSelection.enabled = !$store.mediaSelection.enabled; if (!$store.mediaSelection.enabled) $store.mediaSelection.reset();"
                    :class="$store.mediaSelection.enabled ? 'bg-amber-100 text-amber-800 ring-amber-300' : 'bg-white text-gray-700 ring-gray-900/5'"
                    class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold rounded-xl ring-1 hover:ring-amber-300 active:scale-95 shadow-sm transition-all"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="$store.mediaSelection.enabled ? 'Salir de selección' : 'Seleccionar'"></span>
                </button>
                <button
                    type="button"
                    x-show="$store.mediaSelection.enabled"
                    @click='$store.mediaSelection.addMany(@json($mediaItems->pluck("id")->all())); $store.mediaSelection.enabled = true;'
                    class="inline-flex items-center gap-2 px-3 py-2 bg-white text-gray-700 text-xs font-semibold rounded-xl ring-1 ring-gray-900/5 hover:bg-gray-50 hover:ring-amber-300 active:scale-95 shadow-sm transition-all"
                >
                    Seleccionar todo ({{ $mediaItems->count() }})
                </button>
            </div>
            @if(!empty($accessibleChannels))
                <button
                    type="button"
                    @click="$store.modals.open('upload-media')"
                    @if(!empty($activeStorage['is_over'])) disabled title="Has alcanzado el límite de almacenamiento de este canal. Elimina archivos para liberar espacio." @endif
                    @if(!empty($activeStorage['is_over'])) class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-300 text-gray-500 text-sm font-semibold rounded-xl cursor-not-allowed shadow-sm" @else class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-sm font-semibold rounded-xl hover:from-amber-600 hover:to-orange-600 active:scale-95 shadow-md shadow-amber-500/20 transition-all" @endif
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    Subir archivos
                </button>
            @endif
        </div>

        {{-- Media grid --}}
        @if($mediaItems->isEmpty())
            <div class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm p-16 text-center">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7M4 7l8-4 8 4M4 7l8 4 8-4"/></svg>
                </div>
                <p class="text-gray-500 text-sm">No hay medios en tus canales.</p>
                <p class="text-gray-400 text-xs mt-1">Sube archivos o contacta al administrador.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($mediaItems as $item)
                    @php
                        $isOwner = $item->channel && in_array($item->channel->id, $ownedChannelIds);
                    @endphp
                    <x-media-card :item="$item" :canEdit="$isOwner" :canDelete="$isOwner" :selectable="true" />
                @endforeach
            </div>
        @endif

        {{-- Pagination --}}
        @if($mediaItems->hasPages())
            <div class="mt-6">
                {{ $mediaItems->links() }}
            </div>
        @endif

        {{-- Storage panel --}}
        @isset($storage)
            @php
                $activeStorage = $channelStorage[$currentChannel] ?? $storage;
            @endphp
            <div class="mt-6 bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm p-5">
                <div class="flex items-baseline justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Almacenamiento · {{ $activeStorage['channel_name'] }}</span>
                    <span class="text-xs text-gray-400">{{ $mediaItems->total() }} medios</span>
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900 leading-tight">
                    {{ $activeStorage['used_human'] }}
                    <span class="text-base font-medium text-gray-400">/ {{ $activeStorage['limit_human'] }}</span>
                </div>
                @if($activeStorage['has_quota'])
                    <div class="mt-3 w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                        <div class="h-2.5 rounded-full {{ $activeStorage['bar_color'] }} transition-all" style="width: {{ $activeStorage['percent'] }}%"></div>
                    </div>
                    <div class="mt-3 text-sm {{ $activeStorage['text_color'] }}">
                        @if($activeStorage['is_over'])
                            Has alcanzado el límite de este canal. Elimina archivos para liberar espacio.
                        @else
                            Te quedan {{ $activeStorage['remaining_human'] }} ({{ number_format($activeStorage['percent'], 0) }}% usado).
                        @endif
                    </div>
                @else
                    <div class="mt-3 text-sm text-gray-500">Sin límite de almacenamiento configurado.</div>
                @endif
            </div>
        @endisset
    @endif

<x-media-uploader :available-channels="$accessibleChannels" :preselected-channel-id="$preselectedChannelId" />
    <x-media-player-modal />
    <x-media-rename-modal />
    <x-confirm-delete-modal />
    <x-media-bulk-bar />
</x-client-layout>