@props([
    'protocol',
    'url',
    'labelClass' => 'text-slate-300',
])

<div class="flex items-center gap-2 rounded-lg border border-white/10 bg-slate-900/50 px-3 py-2">
    <span class="w-16 shrink-0 text-[11px] font-bold uppercase tracking-wider {{ $labelClass }}">{{ $protocol }}</span>
    <code class="min-w-0 flex-1 truncate select-all font-mono text-xs text-slate-200" title="{{ $url }}">{{ $url }}</code>
    <button type="button"
        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-white/5 text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-cyan-400"
        data-url="{{ $url }}"
        aria-label="Copiar la URL {{ $protocol }}"
        @click="copy($event.currentTarget.dataset.url)">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
            <rect x="9" y="9" width="11" height="11" rx="2"></rect>
            <path d="M5 15V6a2 2 0 0 1 2-2h9"></path>
        </svg>
    </button>
</div>
