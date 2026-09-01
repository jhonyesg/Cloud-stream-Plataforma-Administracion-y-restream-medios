<x-admin-layout active="users">
    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Usuarios</h2>
    </x-slot:header>

    <div class="bg-amber-50 border border-amber-200 rounded-md p-3 text-xs text-amber-800 mb-4">
        La habilitación de Restream se gestiona desde <a href="{{ route('admin.channels') }}" class="underline font-semibold">Canales</a> (botón Restream por canal).
    </div>

    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-600">{{ $users->total() }} usuarios</p>
        <button
            type="button"
            @click="$store.modals.open('create-user')"
            class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 text-white text-sm font-medium rounded-md hover:bg-sky-500 shadow-sm"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo usuario
        </button>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
<thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Creado</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                @foreach($users as $u)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-medium text-gray-900">{{ $u->name }}</div>
                            <div class="text-xs text-gray-500">{{ '@'.$u->username }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $u->email }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded
                                @if($u->role === 'admin') bg-purple-100 text-purple-800
                                @else bg-blue-100 text-blue-800 @endif">{{ $u->role }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded
                                @if($u->status === 'active') bg-green-100 text-green-800
                                @else bg-red-100 text-red-800 @endif">{{ $u->status }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $u->owner?->name ?? '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $u->created_at?->format('Y-m-d') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    @click="$store.modals.open('edit-user', { id: '{{ $u->id }}' })"
                                    title="Editar usuario"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md bg-gradient-to-br from-indigo-400 to-indigo-600 text-white shadow-sm hover:from-indigo-500 hover:to-indigo-700 hover:scale-105 active:scale-95 transition"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Editar
                                </button>
                                @if(auth()->id() !== $u->id)
                                    <button
                                        type="button"
                                        @click="$store.modals.open('confirm-delete', { action: '{{ route('admin.users.destroy', $u) }}', method: 'DELETE', title: 'Eliminar usuario', message: '¿Eliminar a {{ addslashes($u->name) }}? Esta acción no se puede deshacer.' })"
                                        title="Eliminar usuario"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md bg-gradient-to-br from-red-400 to-red-600 text-white shadow-sm hover:from-red-500 hover:to-red-700 hover:scale-105 active:scale-95 transition"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                        Eliminar
                                    </button>
                                @else
                                    <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-gray-100 text-gray-500 border border-gray-200" title="No puedes eliminarte a ti mismo">
                                        (tú)
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>

    <x-user-create-modal />
    <x-user-edit-modal />
    <x-confirm-delete-modal />
</x-admin-layout>