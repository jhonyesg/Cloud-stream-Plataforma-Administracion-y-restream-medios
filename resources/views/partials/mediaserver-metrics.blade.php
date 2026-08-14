@php
    $ms = $mediaserver ?? ['available' => false, 'streams' => [], 'rules' => [], 'selected' => null];
    $msAvailable = $ms['available'] ?? false;
    $msStreams = $ms['streams'] ?? [];
    $msSelected = $ms['selected'] ?? null;
    $msRules = $ms['rules'] ?? [];
    $showSelector = $showSelector ?? false;

    $activeStreams = collect($msStreams)->filter(fn ($s) => $s['active'])->count();
    $totalViewers = collect($msStreams)->sum('clients');
    $degraded = collect($msStreams)->filter(fn ($s) => in_array($s['health_state'], ['degraded', 'paused', 'zombie'], true))->count();

    $healthBadge = function (string $state): string {
        return match ($state) {
            'healthy' => 'bg-green-100 text-green-800',
            'degraded' => 'bg-amber-100 text-amber-800',
            'paused' => 'bg-yellow-100 text-yellow-800',
            'zombie' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    };
    $healthLabel = function (string $state): string {
        return match ($state) {
            'healthy' => 'Saludable',
            'degraded' => 'Degradado',
            'paused' => 'Pausado',
            'zombie' => 'Zombie',
            default => 'Desconocido',
        };
    };
    $formatKbps = fn ($kbps) => number_format((int) $kbps) . ' kbps';
    $formatUptime = function ($ms) {
        $sec = (int) floor(((int) $ms) / 1000);
        if ($sec <= 0) return '—';
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    };
@endphp

<div class="bg-white rounded-lg shadow mb-8" x-data="{ selected: '{{ $msSelected['stream_name'] ?? '' }}' }">
    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Métricas en vivo · MediaServer</h3>
            <p class="text-xs text-gray-500">Estado de emisión, viewers, bitrate y reglas por stream</p>
        </div>
        @if($showSelector && count($msStreams) > 0)
            <div class="flex items-center gap-2">
                <label for="ms-stream-select" class="text-xs text-gray-500">Stream:</label>
                <select id="ms-stream-select"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        x-model="selected"
                        @change="if (selected) window.location.href = '?stream=' + encodeURIComponent(selected); else window.location.href = window.location.pathname;">
                    <option value="">Todos (resumen)</option>
                    @foreach($msStreams as $s)
                        <option value="{{ $s['stream_name'] }}">{{ $s['channel']?->display_name ?? $s['stream_name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    @if(! $msAvailable)
        <div class="px-6 py-8 text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                MediaServer no disponible. Verifica la conexión con la plataforma para ver las métricas en vivo.
            </div>
        </div>
    @elseif(count($msStreams) === 0)
        <div class="px-6 py-8 text-center text-gray-500 text-sm">
            No hay streams con emisión activa.
        </div>
    @else
        @if(! $msSelected)
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 px-6 py-5 border-b border-gray-100">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Streams activos</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ $activeStreams }} / {{ count($msStreams) }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Viewers conectados</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalViewers) }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Streams degradados</div>
                    <div class="text-2xl font-bold {{ $degraded > 0 ? 'text-amber-600' : 'text-gray-900' }} mt-1">{{ $degraded }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Streams bloqueados</div>
                    <div class="text-2xl font-bold {{ collect($msStreams)->contains(fn ($s) => $s['rules']['blocked']) ? 'text-red-600' : 'text-gray-900' }} mt-1">{{ collect($msStreams)->filter(fn ($s) => $s['rules']['blocked'])->count() }}</div>
                </div>
            </div>
        @endif

        <ul class="divide-y divide-gray-200">
            @php
                $items = $msSelected ? [$msSelected] : $msStreams;
            @endphp
            @foreach($items as $s)
                <li class="px-6 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900">{{ $s['channel']?->display_name ?? $s['stream_name'] }}</span>
                            @if(! $s['channel'])
                                <span class="px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-500">sin canal asociado</span>
                            @endif
                            <span class="px-2 py-0.5 text-xs rounded {{ $healthBadge($s['health_state']) }}">{{ $healthLabel($s['health_state']) }}</span>
                            @if($s['rules']['blocked'])
                                <span class="px-2 py-0.5 text-xs rounded bg-red-100 text-red-800">Bloqueado</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500">{{ $s['stream_name'] }} · uptime {{ $formatUptime($s['uptime_ms']) }}</div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div>
                            <div class="text-xs text-gray-500">Viewers</div>
                            <div class="font-semibold text-gray-900">{{ number_format($s['clients']) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Bitrate recv / send</div>
                            <div class="font-semibold text-gray-900">{{ $formatKbps($s['kbps_recv']) }} / {{ $formatKbps($s['kbps_send']) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Video / Audio</div>
                            <div class="font-semibold text-gray-900">
                                {{ $s['video']['codec'] ?? '—' }} {{ isset($s['video']['width']) ? $s['video']['width'].'×'.$s['video']['height'] : '' }}
                                · {{ $s['audio']['codec'] ?? '—' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Puerto SRT</div>
                            <div class="font-semibold text-gray-900">{{ $s['srt_port'] ?? 'SRT no asignado' }}</div>
                        </div>
                    </div>

                    @if($s['viewers'])
                        <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-gray-500 mb-1">Viewers detalle</div>
                                <div class="text-sm text-gray-700">
                                    {{ number_format($s['viewers']['total']) }} viewers · {{ number_format($s['viewers']['unique_ips']) }} IPs únicas
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 mb-1">Agentes / players</div>
                                <div class="flex flex-wrap gap-1">
                                    @forelse($s['viewers']['agents'] as $agent => $count)
                                        <span class="px-2 py-0.5 text-xs rounded bg-indigo-50 text-indigo-700">{{ $agent }}: {{ $count }}</span>
                                    @empty
                                        <span class="text-sm text-gray-500">Sin viewers</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        @if(count($s['viewers']['list']) > 0)
                            <div class="mt-3 pt-3 border-t border-gray-100"
                                 x-data='{
                                    open: true,
                                    search: "",
                                    sortKey: null,
                                    sortDir: null,
                                    clients: {{ Illuminate\Support\Js::from($s['viewers']['list'])->toHtml() }},
                                    get filtered() {
                                        let rows = this.clients;
                                        if (this.search.trim() !== "") {
                                            const q = this.search.toLowerCase();
                                            rows = rows.filter(c =>
                                                (c.ip || "").toLowerCase().includes(q) ||
                                                (c.country || "").toLowerCase().includes(q) ||
                                                (c.type || "").toLowerCase().includes(q) ||
                                                (c.user_agent || "").toLowerCase().includes(q)
                                            );
                                        }
                                        if (this.sortKey && this.sortDir) {
                                            const key = this.sortKey;
                                            const dir = this.sortDir === "asc" ? 1 : -1;
                                            rows = [...rows].sort((a, b) => {
                                                const va = a[key] ?? "";
                                                const vb = b[key] ?? "";
                                                if (typeof va === "number" && typeof vb === "number") return (va - vb) * dir;
                                                return String(va).localeCompare(String(vb)) * dir;
                                            });
                                        }
                                        return rows;
                                    },
                                    toggleSort(key) {
                                        if (this.sortKey !== key) {
                                            this.sortKey = key;
                                            this.sortDir = "asc";
                                        } else if (this.sortDir === "asc") {
                                            this.sortDir = "desc";
                                        } else {
                                            this.sortKey = null;
                                            this.sortDir = null;
                                        }
                                    },
                                    arrow(key) {
                                        if (this.sortKey !== key) return "↕";
                                        return this.sortDir === "asc" ? "▲" : "▼";
                                    },
                                    fmtAlive(sec) {
                                        sec = Math.floor(sec || 0);
                                        const h = Math.floor(sec / 3600);
                                        const m = Math.floor((sec % 3600) / 60);
                                        return h > 0 ? h + "h " + m + "m" : m + "m";
                                    },
                                    flag(code) {
                                        if (!code || code.length !== 2) return "🌐";
                                        code = code.toUpperCase();
                                        if (!/^[A-Z]{2}$/.test(code)) return "🌐";
                                        return String.fromCodePoint(127397 + code.charCodeAt(0), 127397 + code.charCodeAt(1));
                                    },
                                    typeClass(type) {
                                        const map = {
                                            "hls-play": "bg-green-100 text-green-700",
                                            "rtmp-play": "bg-blue-100 text-blue-700",
                                            "flv-play": "bg-purple-100 text-purple-700",
                                            "webrtc-play": "bg-cyan-100 text-cyan-700",
                                            "fmle-publish": "bg-amber-100 text-amber-700"
                                        };
                                        return map[type] || "bg-gray-100 text-gray-600";
                                    }
                                 }'>
                                <div class="flex items-center justify-between mb-3">
                                    <div class="text-xs text-gray-500">Clientes conectados</div>
                                    <button type="button"
                                            class="text-xs text-indigo-600 hover:underline"
                                            @click="open = ! open">
                                        <span x-show="! open">Ver lista ({{ count($s['viewers']['list']) }})</span>
                                        <span x-show="open" x-cloak>Ocultar</span>
                                    </button>
                                </div>

                                <div x-show="open" x-cloak>
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="relative flex-1 max-w-xs">
                                            <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
                                            </svg>
                                            <input type="search"
                                                   placeholder="Buscar IP, país, agente..."
                                                   class="w-full pl-8 pr-3 py-1.5 text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                   x-model="search">
                                        </div>
                                        <span class="text-xs text-gray-500" x-text="filtered.length + ' de ' + clients.length + ' clientes'"></span>
                                    </div>

                                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                                        <table class="min-w-full text-xs text-left">
                                            <thead class="bg-gray-50">
                                                <tr class="text-gray-600 border-b border-gray-200">
                                                    <th class="py-2 px-3 font-semibold cursor-pointer select-none hover:text-indigo-600" @click="toggleSort('ip')">IP <span x-text="arrow('ip')"></span></th>
                                                    <th class="py-2 px-3 font-semibold cursor-pointer select-none hover:text-indigo-600" @click="toggleSort('country')">País <span x-text="arrow('country')"></span></th>
                                                    <th class="py-2 px-3 font-semibold cursor-pointer select-none hover:text-indigo-600" @click="toggleSort('type')">Tipo <span x-text="arrow('type')"></span></th>
                                                    <th class="py-2 px-3 font-semibold cursor-pointer select-none hover:text-indigo-600" @click="toggleSort('user_agent')">Agente <span x-text="arrow('user_agent')"></span></th>
                                                    <th class="py-2 px-3 font-semibold cursor-pointer select-none hover:text-indigo-600" @click="toggleSort('alive_seconds')">Conectado <span x-text="arrow('alive_seconds')"></span></th>
                                                    <th class="py-2 px-3 font-semibold cursor-pointer select-none hover:text-indigo-600" @click="toggleSort('kbps_send_30s')">Bitrate <span x-text="arrow('kbps_send_30s')"></span></th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                <template x-for="client in filtered" :key="client.ip + client.type">
                                                    <tr class="hover:bg-indigo-50/40 transition">
                                                        <td class="py-2 px-3 font-mono text-gray-800" x-text="client.ip"></td>
                                                        <td class="py-2 px-3 text-gray-700 whitespace-nowrap">
                                                            <span x-text="flag(client.country)"></span>
                                                            <span class="ml-1" x-text="client.country || '—'"></span>
                                                        </td>
                                                        <td class="py-2 px-3">
                                                            <span class="px-2 py-0.5 text-xs rounded" :class="typeClass(client.type)" x-text="client.type"></span>
                                                        </td>
                                                        <td class="py-2 px-3 text-gray-700 max-w-[220px] truncate" :title="client.user_agent || ''">
                                                            <span x-text="client.user_agent || '—'"></span>
                                                        </td>
                                                        <td class="py-2 px-3 text-gray-700" x-text="fmtAlive(client.alive_seconds)"></td>
                                                        <td class="py-2 px-3 text-gray-700 whitespace-nowrap">
                                                            <span class="font-semibold" x-text="Number(client.kbps_send_30s || 0).toLocaleString('es-CO')"></span>
                                                            <span class="text-gray-400"> kbps</span>
                                                        </td>
                                                    </tr>
                                                </template>
                                                <tr x-show="filtered.length === 0">
                                                    <td colspan="6" class="py-4 text-center text-gray-400">Sin resultados para la búsqueda</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <div class="text-xs text-gray-500 mb-1">Reglas de bloqueo</div>
                        @php
                            $geo = $s['rules']['geoblock'];
                            $clientRules = $s['rules']['client_rules'];
                        @endphp
                        @if(! $s['rules']['blocked'] && ! $geo && count($clientRules) === 0)
                            <span class="text-sm text-gray-500">Sin reglas de bloqueo</span>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @if($s['rules']['blocked'])
                                    <span class="px-2 py-0.5 text-xs rounded bg-red-100 text-red-800">En blacklist</span>
                                @endif
                                @if($geo)
                                    <span class="px-2 py-0.5 text-xs rounded bg-blue-100 text-blue-800">Geoblock: {{ implode(', ', $geo['allowed_countries'] ?? []) }}</span>
                                @endif
                                @foreach($clientRules as $rule)
                                    <span class="px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-700">
                                        {{ $rule['name'] }} ({{ $rule['match_type'] }}: {{ $rule['match_value'] }}, play: {{ $rule['action_play'] }})
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
