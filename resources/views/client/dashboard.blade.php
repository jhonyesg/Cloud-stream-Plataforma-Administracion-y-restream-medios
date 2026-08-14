<x-client-layout active="dashboard">
    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Panel Cliente</h2>
    </x-slot:header>

    <div class="mb-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
        <p class="text-sm text-blue-900">
            Hola <strong>{{ auth()->user()->name }}</strong>. Aquí ves solo tus canales y contenido.
        </p>
    </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500">
                <div class="text-xs uppercase tracking-wide text-gray-500">Mis canales</div>
                <div class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['channels'] }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500">
                <div class="text-xs uppercase tracking-wide text-gray-500">Medios</div>
                <div class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['media_items'] }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-500">
                <div class="text-xs uppercase tracking-wide text-gray-500">Cuñas</div>
                <div class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['ads'] }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-5 border-l-4 border-indigo-500">
                <div class="text-xs uppercase tracking-wide text-gray-500">Playlists</div>
                <div class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['playlists'] }}</div>
            </div>
        </div>

        @include('partials.mediaserver-metrics', ['showSelector' => false])

        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Mis canales</h3>
                <span class="text-xs text-gray-500">{{ $channels->count() }} en total</span>
            </div>

            @if($channels->isEmpty())
                <div class="px-6 py-12 text-center text-gray-500">
                    Aún no tienes canales asignados. Contacta al administrador.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-6">
                    @foreach($channels as $ch)
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-semibold text-gray-900">{{ $ch->display_name }}</h4>
                                <span class="px-2 py-1 text-xs rounded
                                    @switch($ch->status)
                                        @case('active') bg-green-100 text-green-800 @break
                                        @case('draft') bg-gray-100 text-gray-800 @break
                                        @case('suspended') bg-red-100 text-red-800 @break
                                    @endswitch">{{ $ch->status }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mb-3">{{ $ch->slug }} · {{ $ch->resolution }}</p>
                            <div class="text-sm text-gray-700">
                                {{ $ch->playlists->count() }} playlist{{ $ch->playlists->count() === 1 ? '' : 's' }}
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
                                <a href="#" class="text-xs text-blue-600 hover:underline">Medios</a>
                                <span class="text-xs text-gray-300">·</span>
                                <a href="#" class="text-xs text-blue-600 hover:underline">Playlists</a>
                                <span class="text-xs text-gray-300">·</span>
                                <a href="#" class="text-xs text-blue-600 hover:underline">Programación</a>
                                <span class="text-xs text-gray-300">·</span>
                                <a href="#" class="text-xs text-blue-600 hover:underline">Pantalla virtual</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

</x-client-layout>
