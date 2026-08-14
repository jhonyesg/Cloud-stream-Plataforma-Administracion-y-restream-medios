<x-admin-layout active="dashboard">
    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Panel de Administración</h2>
    </x-slot:header>
<div class="mb-6 bg-white border-l-4 border-indigo-500 shadow-sm p-4 rounded">
        <p class="text-sm text-indigo-900">
            Bienvenido <strong>{{ auth()->user()->name }}</strong>. Tienes acceso completo al sistema.
        </p>
    </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            @php
                $cards = [
                    ['label' => 'Usuarios', 'value' => $stats['users'], 'color' => 'blue'],
                    ['label' => 'Canales', 'value' => $stats['channels'], 'extra' => $stats['channels_active'].' activos', 'color' => 'green'],
                    ['label' => 'Medios', 'value' => $stats['media_items'], 'extra' => $stats['media_items_ready'].' listos', 'color' => 'pink'],
                    ['label' => 'Almacenamiento total', 'value' => $stats['total_storage_human'], 'extra' => $stats['media_items'].' medios', 'color' => 'slate'],
                    ['label' => 'Cuñas (ads)', 'value' => $stats['ads'], 'color' => 'red'],
                    ['label' => 'Playlists', 'value' => $stats['playlists'], 'color' => 'indigo'],
                ];
            @endphp

            @foreach($cards as $c)
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-{{ $c['color'] }}-500">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ $c['label'] }}</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ $c['value'] }}</div>
                    @if(isset($c['extra']))
                        <div class="text-xs text-gray-500 mt-1">{{ $c['extra'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        @include('partials.mediaserver-metrics', ['showSelector' => true])

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Canales recientes</h3>
                </div>
                <ul class="divide-y divide-gray-200">
                    @forelse($recentChannels as $ch)
                        <li class="px-6 py-3 flex justify-between items-center">
                            <div>
                                <div class="font-medium text-gray-900">{{ $ch->display_name }}</div>
                                <div class="text-xs text-gray-500">{{ $ch->slug }} · {{ $ch->owner?->name ?? 'sin owner' }}</div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded
                                @switch($ch->status)
                                    @case('active') bg-green-100 text-green-800 @break
                                    @case('draft') bg-gray-100 text-gray-800 @break
                                    @case('suspended') bg-red-100 text-red-800 @break
                                    @default bg-gray-100 text-gray-800
                                @endswitch">{{ $ch->status }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-4 text-gray-500 text-sm">Sin canales aún.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Usuarios recientes</h3>
                </div>
                <ul class="divide-y divide-gray-200">
                    @forelse($recentUsers as $u)
                        <li class="px-6 py-3 flex justify-between items-center">
                            <div>
                                <div class="font-medium text-gray-900">{{ $u->name }}</div>
                                <div class="text-xs text-gray-500">{{ $u->email }} · {{ $u->role }}</div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded
                                @switch($u->status)
                                    @case('active') bg-green-100 text-green-800 @break
                                    @case('suspended') bg-red-100 text-red-800 @break
                                @endswitch">{{ $u->status }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-4 text-gray-500 text-sm">Sin usuarios aún.</li>
                    @endforelse
                </ul>
            </div>
        </div>

</x-admin-layout>
