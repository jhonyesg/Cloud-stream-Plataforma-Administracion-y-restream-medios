@props([
    'item',
    'canEdit' => true,
    'canDelete' => true,
    'selectable' => false,
])

@php
    $kindConfig = [
        'video' => ['gradient' => 'from-violet-500/30 to-indigo-600/20', 'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z', 'label' => 'Video', 'accent' => 'violet'],
        'image' => ['gradient' => 'from-emerald-500/30 to-teal-600/20', 'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'label' => 'Imagen', 'accent' => 'emerald'],
        'audio' => ['gradient' => 'from-amber-500/30 to-orange-600/20', 'icon' => 'M9 19V6l12-3v12M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z', 'label' => 'Audio', 'accent' => 'amber'],
        'ad'    => ['gradient' => 'from-rose-500/30 to-pink-600/20', 'icon' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13l3-3m0 0l-3-3m3 3H9m7 6H5.5a2.5 2.5 0 110-5H9', 'label' => 'Cuña', 'accent' => 'rose'],
        'other' => ['gradient' => 'from-sky-500/30 to-blue-600/20', 'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z', 'label' => 'Archivo', 'accent' => 'sky'],
    ];
    $cfg = $kindConfig[$item->kind] ?? $kindConfig['other'];
    $iconPath = $cfg['icon'];
    $gradient = $cfg['gradient'];

    $statusStyles = [
        'ready'      => ['bg' => 'bg-emerald-500', 'text' => 'text-emerald-50', 'dot' => 'bg-emerald-400'],
        'pending'    => ['bg' => 'bg-amber-500',   'text' => 'text-amber-50',   'dot' => 'bg-amber-400'],
        'error'      => ['bg' => 'bg-red-500',     'text' => 'text-red-50',     'dot' => 'bg-red-400'],
        'processing' => ['bg' => 'bg-blue-500',    'text' => 'text-blue-50',    'dot' => 'bg-blue-400'],
    ];
    $st = $statusStyles[$item->status] ?? ['bg' => 'bg-gray-500', 'text' => 'text-gray-50', 'dot' => 'bg-gray-400'];

    $sizeBytes = $item->size_bytes ?? 0;
    $sizeText = $sizeBytes >= 1048576
        ? round($sizeBytes / 1048576, 1) . ' MB'
        : ($sizeBytes >= 1024 ? round($sizeBytes / 1024, 1) . ' KB' : $sizeBytes . ' B');

    $thumbUrl = $item->thumbUrl();
    $playUrl = $item->playUrl();
    $canPlay = in_array($item->kind, ['video', 'audio', 'image', 'ad']) || preg_match('/\.(txt|py|js|json|xml|html|css|md|sh|bat|cfg|ini|conf|log|csv|sql)$/i', $item->filename);

    $viewLabel = match($item->kind) {
        'video' => 'Play',
        'audio' => 'Play',
        'ad' => 'Play',
        'image' => 'Ver',
        default => 'Ver',
    };

    $itemJson = json_encode([
        'id' => $item->id,
        'filename' => $item->filename,
        'kind' => $item->kind,
        'play_url' => $playUrl,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
@endphp

<div class="group relative bg-white rounded-2xl overflow-hidden ring-1 ring-gray-900/5 hover:ring-2 hover:ring-amber-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200 flex flex-col"
     x-data="mediaCard({ data: {{ $itemJson }} })"
     :class="$store.mediaSelection && $store.mediaSelection.has(data.id) ? 'ring-2 ring-amber-500 shadow-xl' : ''"
>
    {{-- Thumbnail / preview area --}}
    <div class="relative aspect-video bg-gradient-to-br {{ $gradient }} flex items-center justify-center overflow-hidden">
        {{-- Base icon (always visible, thumbnail covers it if it loads) --}}
        <div class="absolute inset-0 flex items-center justify-center">
            <div class="w-16 h-16 rounded-2xl bg-white/70 backdrop-blur-sm flex items-center justify-center shadow-lg">
                <svg class="w-8 h-8 text-{{ $cfg['accent'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $iconPath }}"/>
                </svg>
            </div>
        </div>

        {{-- Thumbnail image (covers the icon when loaded) --}}
        <img src="{{ $thumbUrl }}" alt="{{ e($item->filename) }}" loading="lazy"
             class="relative w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
             onerror="this.style.opacity='0'">

        @if($canPlay)
            <button
                type="button"
                @click="play()"
                class="absolute inset-0 flex items-center justify-center bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200"
            >
                <div class="w-14 h-14 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center shadow-2xl hover:scale-110 transition-transform">
                    @if($item->kind === 'image')
                        <svg class="w-6 h-6 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    @else
                        <svg class="w-6 h-6 text-gray-900 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    @endif
                </div>
            </button>
        @endif

        {{-- Status badge --}}
        <div class="absolute top-2.5 right-2.5 flex items-center gap-1.5 px-2.5 py-1 rounded-full {{ $st['bg'] }} {{ $st['text'] }} text-[10px] font-bold uppercase tracking-wide shadow-md">
            <span class="w-1.5 h-1.5 rounded-full {{ $st['dot'] }}"></span>
            {{ $item->status }}
        </div>

        @if($selectable)
            <button
                type="button"
                @click.stop="$store.mediaSelection.toggle(data.id)"
                :aria-pressed="$store.mediaSelection.has(data.id) ? 'true' : 'false'"
                :aria-label="$store.mediaSelection.has(data.id) ? 'Quitar de selección' : 'Agregar a selección'"
                class="absolute top-2.5 right-2.5 mt-9 w-7 h-7 rounded-full flex items-center justify-center shadow-md ring-2 transition z-10"
                :class="$store.mediaSelection.has(data.id)
                    ? 'bg-amber-500 ring-amber-600 text-white'
                    : 'bg-white/90 ring-white/40 text-gray-500 hover:bg-white'"
            >
                <svg x-show="$store.mediaSelection.has(data.id)" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                </svg>
                <span x-show="!$store.mediaSelection.has(data.id)" class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
            </button>
        @endif

        {{-- Kind badge --}}
        <div class="absolute top-2.5 left-2.5 px-2.5 py-1 rounded-full bg-black/50 backdrop-blur-md text-white text-[10px] font-bold uppercase tracking-wide flex items-center gap-1 shadow-sm">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $iconPath }}"/></svg>
            {{ $cfg['label'] }}
        </div>
    </div>

    {{-- Info area --}}
    <div class="p-3.5 flex flex-col flex-1 gap-2">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900 truncate leading-snug" title="{{ e($item->filename) }}">{{ $item->filename }}</p>
            <div class="flex items-center gap-2 mt-1">
                <span class="text-xs text-gray-500 font-medium">{{ $sizeText }}</span>
                @if($item->channel)
                    <span class="text-gray-300">·</span>
                    <span class="text-xs text-gray-500 truncate">{{ $item->channel->display_name }}</span>
                @endif
            </div>
        </div>

        {{-- Action bar --}}
        <div class="flex items-center gap-1.5 mt-auto pt-2.5 border-t border-gray-100">
            @if($canPlay)
                <button
                    type="button"
                    @click="play()"
                    class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-{{ $cfg['accent'] }}-50 text-{{ $cfg['accent'] }}-700 text-xs font-semibold hover:bg-{{ $cfg['accent'] }}-100 transition"
                    title="{{ $viewLabel }}"
                >
                    @if($item->kind === 'image')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    @else
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    @endif
                    {{ $viewLabel }}
                </button>
            @endif
            @if($canEdit)
                <button
                    type="button"
                    @click="rename()"
                    class="p-1.5 rounded-lg text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 transition"
                    title="Editar"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
            @endif
            @if($canDelete)
                <button
                    type="button"
                    @click="del()"
                    class="p-1.5 rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-600 transition"
                    title="Eliminar"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                </button>
            @endif
        </div>
    </div>
</div>