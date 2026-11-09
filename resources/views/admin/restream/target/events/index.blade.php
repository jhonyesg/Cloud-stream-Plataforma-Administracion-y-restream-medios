<x-admin-layout active="restream">
    <x-slot:header>
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Historial — {{ $target->name }} <span class="text-sm text-gray-500 font-normal">(admin)</span>
            </h2>
            <a href="{{ route('admin.restream.schedules', ['channel' => $channel->id, 'target' => $target->id]) }}"
               class="text-sm text-indigo-600 hover:text-indigo-800">← Calendario</a>
        </div>
    </x-slot:header>

    <div class="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Eventos del destino</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-2 text-left">Cuándo</th>
                        <th class="px-4 py-2 text-left">Tipo</th>
                        <th class="px-4 py-2 text-left">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($events as $e)
                        @php $p = $e->presentation; @endphp
                        <tr>
                            <td class="px-4 py-2 text-gray-600 whitespace-nowrap">{{ optional($e->occurred_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs
                                    @switch($p['color'])
                                        @case('green') bg-emerald-100 text-emerald-800 @break
                                        @case('blue') bg-sky-100 text-sky-800 @break
                                        @case('red') bg-red-100 text-red-800 @break
                                        @case('amber') bg-amber-100 text-amber-800 @break
                                        @default bg-gray-100 text-gray-700
                                    @endswitch">{{ $e->kind }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ $e->human_reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Sin eventos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
