@php
    $monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
@endphp

<x-app-modal name="create-template" title="Nueva plantilla" subtitle="Crear programación mensual" maxWidth="md" :icon="$icon" iconBg="bg-teal-100" iconColor="text-teal-600">
    <div x-data="{ name: '', busy: false, async submit() { this.busy = true; try { const r = await fetch('/api/schedule-templates', { method: 'POST', headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ channel_id: '{{ $channelId }}', name: this.name, year: {{ $year }}, month: {{ $month }} }) }); if (r.ok) window.location.reload(); else { const d = await r.json(); alert(d.message || 'Error'); } } catch(e) { alert('Error de red'); } finally { this.busy = false; } } }">
        <div class="px-6 py-5">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre</label>
            <input type="text" x-model="name" placeholder="Programación {{ $monthNames[$month - 1] }} {{ $year }}"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition text-sm">
        </div>
        <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-2">
            <button @click="Alpine.store('modals').close()" :disabled="busy" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 uppercase hover:bg-gray-50 transition">Cancelar</button>
            <button @click="submit()" :disabled="busy || !name" class="px-4 py-2 bg-teal-600 text-white rounded-lg text-xs font-semibold uppercase hover:bg-teal-500 disabled:opacity-50 transition">
                <span x-text="busy ? 'Creando…' : 'Crear'"></span>
            </button>
        </div>
    </div>
</x-app-modal>