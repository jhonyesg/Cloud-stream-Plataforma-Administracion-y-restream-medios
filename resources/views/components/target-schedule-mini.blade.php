@php
    /**
     * Mini-calendario embebible: 7 días con badges de ventanas próximas.
     *
     * @var \App\Models\RestreamTarget $target
     */
    $horizon = now()->addDays(7);
    $upcoming = $target->schedules()
        ->where('enabled', true)
        ->where('starts_at', '>', now())
        ->where('starts_at', '<=', $horizon)
        ->orderBy('starts_at')
        ->get();
@endphp

@if ($upcoming->count() > 0)
    <div class="mt-3 px-4 pb-3 border-t border-gray-100 pt-3">
        <div class="flex items-center justify-between mb-2">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Próximas 7 días</h4>
            <a href="{{ route('client.restream.schedules.index', ['target' => $target->id]) }}"
               class="text-xs text-indigo-600 hover:text-indigo-800">Calendario completo →</a>
        </div>
        <div class="grid grid-cols-7 gap-1">
            @for ($i = 0; $i < 7; $i++)
                @php
                    $day = now()->addDays($i);
                    $dayStart = $day->copy()->startOfDay();
                    $dayEnd = $day->copy()->endOfDay();
                    $dayEvents = $upcoming->filter(fn ($e) => $e->starts_at->between($dayStart, $dayEnd));
                @endphp
                <div class="text-center">
                    <div class="text-[10px] text-gray-500 mb-0.5">{{ $day->translatedFormat('D') }}</div>
                    <div class="text-xs font-semibold {{ $day->isToday() ? 'text-indigo-600' : 'text-gray-700' }}">{{ $day->day }}</div>
                    @foreach ($dayEvents as $ev)
                        <div class="mt-1 px-1 py-0.5 text-[9px] rounded truncate {{ $ev->status === 'running' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}"
                             title="{{ $ev->name ?? 'Sin nombre' }} · {{ optional($ev->local_starts_at)->format('H:i') }} {{ $ev->is_indefinite ? '∞' : '' }}">
                            {{ $ev->name ? \Str::limit($ev->name, 8) : '●' }}
                        </div>
                    @endforeach
                </div>
            @endfor
        </div>
    </div>
@endif
