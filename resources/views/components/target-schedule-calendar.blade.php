@php
    /**
     * @var \Illuminate\Database\Eloquent\Collection|\App\Models\RestreamTargetSchedule[] $schedules
     * @var \App\Models\RestreamTarget $target
     * @var \Carbon\Carbon|null $cursor
     */
    $cursor = $cursor ?? now();
    $view = $view ?? 'month';

    // Look up the most recent "auto_stopped" event for each ended window so
    // the calendar can render a "✓ YouTube cerrado" / "⚠ YouTube aún live"
    // badge. Cost: one IN-query on `restream_target_events`.
    $youtubeStatuses = [];
    if ($target->platform === 'youtube') {
        $ended = $schedules->where('status', 'ended')->pluck('id');
        if ($ended->isNotEmpty()) {
            $lastAutoStops = \App\Models\RestreamTargetEvent::query()
                ->where('target_id', $target->id)
                ->whereIn('kind', ['auto_stopped', 'youtube_zombie_recovered', 'youtube_zombie_detected', 'manual_stopped', 'admin_stopped'])
                ->whereJsonContains('metadata->window_id', '__never_match__')
                ->pluck('id');
            // Simpler: grab all auto/manual stop events and match by metadata.window_id.
            $events = \App\Models\RestreamTargetEvent::query()
                ->where('target_id', $target->id)
                ->whereIn('kind', ['auto_stopped', 'manual_stopped', 'admin_stopped'])
                ->orderByDesc('occurred_at')
                ->limit(500)
                ->get(['id', 'kind', 'metadata', 'occurred_at']);
            foreach ($events as $ev) {
                $wid = $ev->metadata['window_id'] ?? null;
                if (! $wid || isset($youtubeStatuses[$wid])) {
                    continue;
                }
                $youtubeStatuses[$wid] = [
                    'kind' => $ev->kind,
                    'broadcast_id' => $ev->metadata['broadcast_id'] ?? null,
                    'occurred_at' => optional($ev->occurred_at)->toIso8601String(),
                ];
            }

            // For each ended window whose bound broadcast id is known, look up
            // the current YouTube lifecycle via the target's last persisted poll.
            $currentLifecycle = $target->platform_broadcast_lifecycle;
            $currentLifecycleAt = optional($target->platform_broadcast_lifecycle_at)->toIso8601String();
            foreach ($youtubeStatuses as $wid => &$info) {
                $info['youtube_lifecycle_now'] = $currentLifecycle;
                $info['youtube_lifecycle_at'] = $currentLifecycleAt;
                $info['youtube_closed'] = in_array($currentLifecycle, ['complete', 'revoked'], true);
            }
            unset($info);
        }
    }

    $schedulesJson = $schedules->map(function ($s) use ($youtubeStatuses) {
        $wid = $s->id;
        $yt = $youtubeStatuses[$wid] ?? null;
        return [
            'id' => $s->id,
            'name' => $s->name,
            'starts_at' => optional($s->starts_at)->toIso8601String(),
            'ends_at' => optional($s->ends_at)->toIso8601String(),
            'timezone' => $s->timezone,
            'status' => $s->status,
            'is_indefinite' => (bool) $s->is_indefinite,
            'youtube_closed' => $yt['youtube_closed'] ?? null,
            'youtube_lifecycle_now' => $yt['youtube_lifecycle_now'] ?? null,
            'youtube_lifecycle_at' => $yt['youtube_lifecycle_at'] ?? null,
        ];
    })->values()->all();
    $timezones = \DateTimeZone::listIdentifiers();
    $postUrl = url('/client/restream/restream-targets/' . $target->id . '/schedules');
    $targetId = $target->id;
@endphp

<div class="rtsm-calendar w-full" style="min-width: 760px;" x-data="rtsmCalendar(@js($cursor->toDateString()), @js($view))">
    <div class="flex items-center justify-between mb-3 gap-2">
        <div class="flex items-center gap-2">
            <button type="button" @click="prev()" class="px-2 py-1 rounded border border-gray-200 hover:bg-gray-50 text-gray-600">&larr;</button>
            <button type="button" @click="today()" class="px-2 py-1 rounded border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm">Hoy</button>
            <button type="button" @click="next()" class="px-2 py-1 rounded border border-gray-200 hover:bg-gray-50 text-gray-600">&rarr;</button>
            <span class="ml-2 font-semibold text-gray-800" x-text="label()"></span>
        </div>
        <div class="inline-flex rounded border border-gray-200 overflow-hidden text-sm">
            <button type="button" @click="setView('month')" :class="view === 'month' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="px-3 py-1">Mes</button>
            <button type="button" @click="setView('week')" :class="view === 'week' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="px-3 py-1">Semana</button>
        </div>
    </div>

    <div class="grid grid-cols-7 text-xs text-gray-500 font-medium border-b border-gray-200 pb-1">
        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $wd)
            <div class="px-2 py-1">{{ $wd }}</div>
        @endforeach
    </div>

    <div class="grid grid-cols-7 gap-px bg-gray-200 border border-gray-200 rounded-b">
        <template x-for="cell in cells" :key="cell.iso">
            <div class="bg-white min-h-[7rem] p-1 relative"
                 :class="cell.isToday ? 'ring-1 ring-indigo-400' : ''">
                <div class="text-xs text-gray-500 flex justify-between">
                    <span x-text="cell.day"></span>
                    <button type="button" @click="openNew(cell.iso)" class="text-indigo-500 hover:text-indigo-700" title="Nueva ventana en este día">+</button>
                </div>
                <template x-for="ev in cell.events" :key="ev.id">
                    <button type="button"
                            @click="openEdit(ev)"
                            class="block w-full text-left text-[10px] mt-0.5 px-1.5 py-0.5 rounded truncate"
                            :class="evClass(ev)">
                        <span x-text="ev.label"></span>
                        <template x-if="ev.status === 'ended' && ev.youtube_closed === true">
                            <span class="ml-1" title="Broadcast de YouTube cerrado">✓</span>
                        </template>
                        <template x-if="ev.status === 'ended' && ev.youtube_closed === false">
                            <span class="ml-1" :title="'YouTube aún ' + ev.youtube_lifecycle_now + ' (poll ' + ev.youtube_lifecycle_at + ')'">⚠</span>
                        </template>
                    </button>
                </template>
            </div>
        </template>
    </div>

    <template x-if="modal.open">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="closeModal()">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-5" x-data="rtsmModalForm()" x-init="hydrate(modal)">
                <h3 class="text-lg font-semibold text-gray-800 mb-3" x-text="modal.id ? 'Editar ventana' : 'Nueva ventana'"></h3>
                <form @submit.prevent="save()">
                    <label class="block text-sm text-gray-700 mb-1">Nombre (opcional)
                        <input type="text" x-model="form.name" maxlength="120" class="mt-1 w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block text-sm text-gray-700">Inicio
                            <input type="datetime-local" x-model="form.starts_at" required class="mt-1 w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                        </label>
                        <label class="block text-sm text-gray-700">Fin (opcional)
                            <input type="datetime-local" x-model="form.ends_at" :disabled="form.indefinite" class="mt-1 w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                        </label>
                    </div>
                    <label class="mt-2 inline-flex items-center text-sm text-gray-700">
                        <input type="checkbox" x-model="form.indefinite" @change="if (form.indefinite) form.ends_at = ''" class="mr-1"> Sin fin programado
                    </label>
                    <label class="block text-sm text-gray-700 mt-2">Zona horaria
                        <select x-model="form.timezone" class="mt-1 w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                            <template x-for="tz in timezones" :key="tz">
                                <option :value="tz" x-text="tz"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-sm text-gray-700 mt-2">Notas
                        <textarea x-model="form.notes" maxlength="1000" rows="2" class="mt-1 w-full border border-gray-300 rounded px-2 py-1.5 text-sm"></textarea>
                    </label>
                    <div class="flex items-center justify-between mt-4">
                        <button x-show="modal.id" type="button" @click="remove()" class="text-red-600 text-sm hover:underline">Eliminar</button>
                        <div class="flex gap-2 ml-auto">
                            <button type="button" @click="closeModal()" class="px-3 py-1.5 text-sm rounded border border-gray-300 hover:bg-gray-50">Cancelar</button>
                            <button type="submit" :disabled="busy" class="px-3 py-1.5 text-sm rounded bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">Guardar</button>
                        </div>
                    </div>
                    <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>
                </form>
            </div>
        </div>
    </template>
</div>

@once
    @push('scripts')
        <script>
            function rtsmCalendar(initialCursor, initialView) {
                return {
                    cursor: new Date(initialCursor + 'T00:00:00'),
                    view: initialView,
                    events: @json($schedulesJson),
                    modal: { open: false, id: null, date: null, busy: false, error: null },
                    timezones: @json($timezones),
                    postUrl: @json($postUrl),
                    targetId: @json($targetId),
                    get cells() {
                        const days = this.view === 'month' ? 35 : 7;
                        const start = this.view === 'month'
                            ? this.startOfMonth(this.cursor)
                            : this.startOfWeek(this.cursor);
                        const today = new Date(); today.setHours(0,0,0,0);
                        const out = [];
                        for (let i = 0; i < days; i++) {
                            const d = new Date(start);
                            d.setDate(start.getDate() + i);
                            const iso = d.toISOString().slice(0, 10);
                            out.push({
                                iso, day: d.getDate(),
                                isToday: d.getTime() === today.getTime(),
                                events: this.events.filter(e => {
                                    const s = new Date(e.starts_at);
                                    const eDate = e.ends_at ? new Date(e.ends_at) : null;
                                    const cellDate = new Date(d);
                                    cellDate.setHours(0,0,0,0);
                                    s.setHours(0,0,0,0);
                                    if (s.getTime() !== cellDate.getTime()) return false;
                                    return true;
                                }).map(e => ({
                                    ...e,
                                    label: (e.name ? e.name + ' · ' : '') + new Date(e.starts_at).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}) + (e.is_indefinite ? ' ∞' : ''),
                                })),
                            });
                        }
                        return out;
                    },
                    label() {
                        if (this.view === 'month') {
                            return this.cursor.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' });
                        }
                        const start = this.startOfWeek(this.cursor);
                        const end = new Date(start); end.setDate(start.getDate() + 6);
                        return start.toLocaleDateString('es-CO', { day: 'numeric', month: 'short' }) + ' – ' + end.toLocaleDateString('es-CO', { day: 'numeric', month: 'short' });
                    },
                    prev() { this.cursor.setMonth(this.cursor.getMonth() - 1); this.refresh(); },
                    next() { this.cursor.setMonth(this.cursor.getMonth() + 1); this.refresh(); },
                    today() { this.cursor = new Date(); this.refresh(); },
                    setView(v) { this.view = v; },
                    openNew(iso) { this.modal = { open: true, id: null, date: iso, busy: false, error: null }; },
                    openEdit(ev) { this.modal = { open: true, id: ev.id, date: null, busy: false, error: null }; },
                    closeModal() { this.modal.open = false; },
                    evClass(ev) {
                        return {
                            'bg-emerald-100 text-emerald-800': ev.status === 'running',
                            'bg-gray-100 text-gray-600': ev.status === 'pending',
                            'bg-sky-100 text-sky-800': ev.status === 'ended',
                            'bg-red-100 text-red-800': ev.status === 'disabled',
                        };
                    },
                    startOfMonth(d) { return new Date(d.getFullYear(), d.getMonth(), 1); },
                    startOfWeek(d) {
                        const day = (d.getDay() + 6) % 7; // monday=0
                        const r = new Date(d);
                        r.setDate(d.getDate() - day);
                        return new Date(r.getFullYear(), r.getMonth(), r.getDate());
                    },
                    refresh() { this.cursor = new Date(this.cursor.getTime()); },
                };
            }
            function rtsmModalForm() {
                return {
                    form: { name: '', starts_at: '', ends_at: '', indefinite: false, timezone: 'UTC', notes: '' },
                    timezones: @json($timezones),
                    postUrl: @json($postUrl),
                    modal: { id: null, date: null },
                    events: null,
                    busy: false,
                    error: null,
                    hydrate(modal) {
                        this.modal = modal;
                        const events = window.__rtsmEvents || [];
                        if (modal.id) {
                            const ev = events.find(e => e.id === modal.id) || null;
                            if (ev) {
                                this.form = {
                                    name: ev.name || '',
                                    starts_at: ev.starts_at ? ev.starts_at.slice(0, 16) : '',
                                    ends_at: ev.ends_at ? ev.ends_at.slice(0, 16) : '',
                                    indefinite: ev.is_indefinite || false,
                                    timezone: ev.timezone || 'UTC',
                                    notes: '',
                                };
                            }
                        } else if (modal.date) {
                            this.form = { name: '', starts_at: modal.date + 'T09:00', ends_at: modal.date + 'T11:00', indefinite: false, timezone: 'UTC', notes: '' };
                        }
                    },
                    async save() {
                        this.busy = true; this.error = null;
                        try {
                            const payload = { ...this.form };
                            if (payload.indefinite) delete payload.ends_at;
                            delete payload.indefinite;
                            const url = this.modal.id ? this.postUrl + '/' + this.modal.id : this.postUrl;
                            const method = this.modal.id ? 'PATCH' : 'POST';
                            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                            const r = await fetch(url, {
                                method,
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                                body: JSON.stringify(payload),
                            });
                            if (!r.ok) {
                                const body = await r.json().catch(() => ({}));
                                this.error = body.message || ('HTTP ' + r.status);
                                this.busy = false; return;
                            }
                            location.reload();
                        } catch (e) { this.error = String(e); }
                        this.busy = false;
                    },
                    async remove() {
                        if (! this.modal.id) return;
                        if (! confirm('¿Eliminar esta ventana?')) return;
                        this.busy = true;
                        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                        const url = this.postUrl + '/' + this.modal.id;
                        const r = await fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
                        if (r.ok) location.reload();
                        this.busy = false;
                    },
                };
            }
        </script>
@endonce
