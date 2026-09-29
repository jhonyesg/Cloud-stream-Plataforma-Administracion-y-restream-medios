<x-admin-layout active="restream">
    <x-slot:header>
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Programación — {{ $target->name }} <span class="text-sm text-gray-500 font-normal">(admin)</span>
            </h2>
            <a href="{{ route('admin.restream.events', ['channel' => $channel->id, 'target' => $target->id]) }}"
               class="text-sm text-indigo-600 hover:text-indigo-800">Ver historial →</a>
        </div>
    </x-slot:header>

    <div class="py-6 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <x-target-schedule-calendar :schedules="$schedules" :target="$target" />
        </div>

        <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Ventanas programadas</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-2 text-left">Nombre</th>
                        <th class="px-4 py-2 text-left">Inicio</th>
                        <th class="px-4 py-2 text-left">Fin</th>
                        <th class="px-4 py-2 text-left">Estado</th>
                        <th class="px-4 py-2 text-left">Cuenta regresiva</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($schedules as $s)
                        <tr>
                            <td class="px-4 py-2">{{ $s->name ?: '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ optional($s->local_starts_at)->format('Y-m-d H:i') }} {{ $s->timezone }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $s->is_indefinite ? '∞' : optional($s->local_ends_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $badge = match ($s->status) {
                                        'running' => 'bg-emerald-100 text-emerald-800',
                                        'pending' => 'bg-gray-100 text-gray-600',
                                        'ended' => 'bg-sky-100 text-sky-800',
                                        'disabled' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-600',
                                    };
                                    $label = match ($s->status) {
                                        'running' => 'En vivo',
                                        'pending' => 'Pendiente',
                                        'ended' => 'Finalizado',
                                        'disabled' => 'Deshabilitada',
                                        default => $s->status,
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $badge }}">{{ $label }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <span class="font-mono text-xs text-gray-700"
                                      data-countdown-schedule
                                      data-starts-at="{{ optional($s->starts_at)->toIso8601String() }}"
                                      data-ends-at="{{ optional($s->ends_at)->toIso8601String() }}"
                                      data-status="{{ $s->status }}"
                                      data-indefinite="{{ $s->is_indefinite ? '1' : '0' }}"></span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Sin ventanas programadas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <script>
        (function () {
            function fmt(diffMs) {
                if (diffMs <= 0) return 'Finalizado';
                const totalSec = Math.floor(diffMs / 1000);
                const h = Math.floor(totalSec / 3600);
                const m = Math.floor((totalSec % 3600) / 60);
                const s = totalSec % 60;
                if (h > 0) return `${h}h ${String(m).padStart(2,'0')}m`;
                if (m > 0) return `${m}m ${String(s).padStart(2,'0')}s`;
                return `${s}s`;
            }
            function ago(diffMs) {
                if (diffMs < 0) diffMs = -diffMs;
                const totalSec = Math.floor(diffMs / 1000);
                if (totalSec < 60) return `${totalSec}s`;
                const totalMin = Math.floor(totalSec / 60);
                if (totalMin < 60) return `${totalMin}m`;
                const totalHr = Math.floor(totalMin / 60);
                if (totalHr < 24) return `${totalHr}h ${totalMin % 60}m`;
                const totalDay = Math.floor(totalHr / 24);
                return `${totalDay}d ${totalHr % 24}h`;
            }
            function tick() {
                const now = Date.now();
                document.querySelectorAll('[data-countdown-schedule]').forEach((el) => {
                    const status = el.dataset.status;
                    const startsAt = el.dataset.startsAt ? new Date(el.dataset.startsAt).getTime() : null;
                    const endsAt = el.dataset.endsAt ? new Date(el.dataset.endsAt).getTime() : null;
                    const indefinite = el.dataset.indefinite === '1';
                    if (indefinite && status === 'running') {
                        el.textContent = 'Sin fin';
                        return;
                    }
                    if (status === 'pending' && startsAt) {
                        const diff = startsAt - now;
                        if (diff > 0) {
                            el.textContent = `Inicia en ${fmt(diff)}`;
                        } else {
                            el.textContent = 'Iniciando…';
                        }
                        return;
                    }
                    if (status === 'running' && endsAt) {
                        const diff = endsAt - now;
                        el.textContent = diff > 0 ? `Termina en ${fmt(diff)}` : 'Finalizando…';
                        return;
                    }
                    if (status === 'ended' && endsAt) {
                        el.textContent = `Finalizó hace ${ago(endsAt - now)}`;
                        return;
                    }
                    if (status === 'disabled') {
                        el.textContent = '—';
                        return;
                    }
                    el.textContent = '—';
                });
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</x-admin-layout>