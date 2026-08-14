<x-client-layout active="scheduler">
    @php
        $monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $weekdayShort = ['L','M','X','J','V','S','D'];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $firstDayOfMonth = (int) date('N', mktime(0, 0, 0, $month, 1, $year));
        $firstDayOffset = $firstDayOfMonth - 1;
        $playlistColorMap = [];
        foreach ($playlists as $idx => $pl) {
            $hues = [210, 30, 150, 280, 180, 340, 60, 0, 90, 240];
            $h = $hues[$idx % count($hues)];
            $playlistColorMap[$pl->id] = "hsl({$h}, 70%, 55%)";
        }
        $itemJson = json_encode($mediaItems->map(fn($m) => [
            'id' => $m->id,
            'filename' => $m->filename,
            'kind' => $m->kind,
            'duration_sec' => $m->duration_sec,
        ])->values(), JSON_UNESCAPED_UNICODE);
        $playlistsJson = json_encode($playlists->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'color' => $playlistColorMap[$p->id],
            'total_duration_sec' => $p->total_duration_sec,
        ])->values(), JSON_UNESCAPED_UNICODE);

        // Emission state for this channel (used by both UI and JS component)
        $emissionState = \App\Models\EmissionState::where('channel_id', $currentChannel)->first();
        $isEmitting = $emissionState && in_array($emissionState->status, ['starting', 'live']);
    @endphp

    <x-slot:header>
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Programación de Contenido</h2>
            <p class="text-sm text-gray-500 mt-0.5">Asigna una playlist a cada día del mes</p>
        </div>
    </x-slot:header>

    @if($channels->isEmpty())
        <div class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm p-12 text-center">
            <p class="text-gray-700 font-medium">No tienes canales asignados</p>
            <p class="text-gray-500 text-sm mt-1">Comunícate con el administrador para que te asigne un canal.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm p-4 mb-5">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Canal</label>
                    <select onchange="window.location.href='{{ route('client.scheduler') }}?channel_id='+this.value+'&year={{ $year }}&month={{ $month }}&day={{ $selectedDay }}'"
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50/50 transition">
                        @foreach($channels as $ch)
                            <option value="{{ $ch->id }}" @selected($currentChannel === $ch->id)>{{ $ch->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Mes</label>
                    <div class="flex items-center gap-2">
                        <select onchange="window.location.href='{{ route('client.scheduler') }}?channel_id={{ $currentChannel }}&year={{ $year }}&month='+this.value+'&day={{ $selectedDay }}'"
                                class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50/50 transition">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected($month === $m)>{{ $monthNames[$m - 1] }}</option>
                            @endfor
                        </select>
                        <select onchange="window.location.href='{{ route('client.scheduler') }}?channel_id={{ $currentChannel }}&year='+this.value+'&month={{ $month }}&day={{ $selectedDay }}'"
                                class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50/50 transition">
                            @for($y = now()->year - 1; $y <= now()->year + 2; $y++)
                                <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
                @if($template)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold {{ $template->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }} self-end">
                        {{ ucfirst($template->status) }}
                    </span>
                @endif
                <button type="button"
                        @click="$store.modals.open('create-playlist', { channelId: '{{ $currentChannel }}', channels: {{ $channels->map(fn($c) => ['id' => $c->id, 'display_name' => $c->display_name])->values()->toJson() }}, templateId: '{{ $template?->id ?? '' }}', day: {{ $selectedDay }}, mediaItems: {{ $itemJson }} })"
                        class="ml-auto inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-teal-300 text-teal-700 text-sm font-semibold rounded-xl hover:bg-teal-50 hover:border-teal-400 active:scale-95 shadow-sm transition self-end">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v12M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/></svg>
                    Nueva playlist
                </button>
                @if(!$template)
                    <button type="button" @click="$store.modals.open('create-template')"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-500 transition self-end">
                        + Nueva plantilla
                    </button>
                @endif
                @if($template && $template->blocks->count() > 0)
                    <button type="button"
                            @click="window.schedulerMonthActions.openReplicateMonth('{{ $template->id }}', {{ $year }}, {{ $month }}, {{ $template->blocks->count() }})"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-teal-300 text-teal-700 text-sm font-semibold rounded-xl hover:bg-teal-50 hover:border-teal-400 active:scale-95 shadow-sm transition self-end"
                            title="Replicar toda la programación del mes actual a otros meses">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Replicar este mes a…
                    </button>
                @endif

                {{-- Emission Control + Log Card --}}
                <div x-data="emissionControl({ channelId: '{{ $currentChannel }}', csrfToken: '{{ csrf_token() }}' })"
                     class="flex flex-col gap-3 self-end min-w-[300px]">

                    {{-- Status badge + buttons --}}
                    <div class="flex items-center gap-2">
                        <span x-show="isEmitting" x-cloak
                              :class="status === 'live' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full"
                                  :class="status === 'live' ? 'bg-green-500 animate-pulse' : 'bg-amber-500'"></span>
                            <span x-text="status === 'live' ? 'Al aire' : (status === 'starting' ? 'Iniciando...' : 'Offline')"></span>
                        </span>

                        <template x-if="!isEmitting">
                            <button type="button"
                                    @click="startEmission()"
                                    :disabled="isLoading"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-500 active:scale-95 shadow-sm transition disabled:opacity-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span x-text="isLoading ? 'Iniciando…' : 'Iniciar transmisión'"></span>
                            </button>
                        </template>
                        <template x-if="isEmitting">
                            <button type="button"
                                    @click="stopEmission()"
                                    :disabled="isLoading"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-xl hover:bg-red-500 active:scale-95 shadow-sm transition disabled:opacity-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                                <span x-text="isLoading ? 'Deteniendo…' : 'Detener transmisión'"></span>
                            </button>
                        </template>
                        <button type="button"
                                @click="showLog = !showLog"
                                :class="showLog ? 'bg-slate-800 text-white' : 'bg-white border border-gray-300 text-gray-700'"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-lg hover:opacity-90 active:scale-95 shadow-sm transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span x-text="showLog ? 'Ocultar log' : 'Log de emisión'"></span>
                        </button>
                        <button type="button"
                                @click="$store.modals.open('live-viewer', { channel_id: '{{ $currentChannel }}', channel_name: '{{ addslashes($currentChannelName) }}', slug: '{{ $currentChannelSlug }}' })"
                                :disabled="status === 'offline'"
                                :title="(status === 'live' || status === 'starting') ? 'Abrir el reproductor en vivo' : 'No hay emisión activa'"
                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-sky-300 text-sky-700 text-xs font-bold rounded-lg hover:bg-sky-50 hover:border-sky-400 active:scale-95 shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:border-sky-300">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Ver vivo
                        </button>
                        <span x-show="message" x-text="message" :class="messageType === 'error' ? 'text-red-600 text-xs' : 'text-emerald-600 text-xs'" class="ml-1"></span>
                    </div>

                    {{-- Live Stats bar --}}
                    <div x-show="isEmitting && latestStats.bitrate" x-cloak
                         class="flex items-center gap-3 px-3 py-2 bg-slate-900 text-slate-100 rounded-lg text-[11px] font-mono">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                            <span x-text="latestStats.bitrate ? latestStats.bitrate + ' kbps' : '— kbps'"></span>
                        </div>
                        <div class="w-px h-3 bg-slate-700"></div>
                        <div x-text="latestStats.fps ? latestStats.fps.toFixed(1) + ' fps' : '— fps'"></div>
                        <div class="w-px h-3 bg-slate-700"></div>
                        <div x-text="latestStats.frames_sent ? latestStats.frames_sent + ' frames' : '— frames'"></div>
                        <div class="w-px h-3 bg-slate-700"></div>
                        <div x-text="latestStats.uptime !== undefined && latestStats.uptime !== null ? Math.floor(latestStats.uptime/60) + 'm ' + (latestStats.uptime%60) + 's' : '—'"></div>
                        <div class="w-px h-3 bg-slate-700"></div>
                        <div x-text="latestStats.item || '—'"></div>
                    </div>

                    {{-- Log panel --}}
                    <div x-show="showLog" x-cloak x-transition
                         class="bg-slate-950 border border-slate-800 rounded-xl overflow-hidden">
                        <div class="flex items-center justify-between px-3 py-2 bg-slate-900 border-b border-slate-800">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Log de emisión</span>
                            <div class="flex items-center gap-2">
                                <span x-show="isPolling" class="flex items-center gap-1 text-[10px] text-green-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                                    En vivo
                                </span>
                                <button @click="fetchLog()" class="text-slate-400 hover:text-white transition" title="Refrescar">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="h-48 overflow-y-auto font-mono text-[11px] leading-relaxed p-3 space-y-1"
                             x-ref="logContainer">
                            <template x-for="entry in logLines" :key="entry.i">
                                <div :class="entry.type === 'stats' ? 'text-green-400' : (entry.type === 'error' ? 'text-red-400' : (entry.type === 'pipeline' ? 'text-blue-400' : 'text-slate-400'))"
                                     x-text="entry.raw"></div>
                            </template>
                            <div x-show="logLines.length === 0" class="text-slate-600 italic">Sin logs todavía...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(!$template)
            <div class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm p-12 text-center">
                <p class="text-gray-700 font-medium">No hay plantilla para este mes</p>
            </div>
        @else
            <div x-data="playlistsList({ mediaItems: {{ $itemJson }}, csrfToken: window.csrfToken || '', templateId: '{{ $template->id }}' })">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
                    <div class="lg:col-span-3">
                    @if($playlists->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-3 mb-3 text-xs">
                            @foreach($playlists as $pl)
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 rounded" style="background-color: {{ $playlistColorMap[$pl->id] }}"></div>
                                    <span class="text-gray-600">{{ $pl->name }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="bg-gradient-to-br from-slate-100 via-gray-50 to-slate-100 rounded-2xl ring-2 ring-gray-200/80 shadow-lg overflow-hidden">
                        <div class="grid grid-cols-7 border-b-2 border-gray-300 bg-gradient-to-r from-slate-200 via-gray-100 to-slate-200">
                            @foreach($weekdayShort as $w)
                                <div class="p-2.5 text-[11px] font-bold text-gray-700 uppercase tracking-wider text-center">{{ $w }}</div>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-7">
                            @for($i = 0; $i < $firstDayOffset; $i++)
                                <div class="aspect-square border-r border-b border-gray-300 bg-gray-300/40"></div>
                            @endfor
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                @php
                                    $block = $blocksByDay->get($d);
                                    $playlist = $block?->playlist;
                                    $color = $playlist ? $playlistColorMap[$playlist->id] : null;
                                    $isSelected = $d === $selectedDay;
                                    $dayDate = \Carbon\Carbon::create($year, $month, $d)->startOfDay();
                                    $today = \Carbon\Carbon::now()->startOfDay();
                                    if ($dayDate->equalTo($today)) {
                                        $dayClass = 'bg-blue-100';
                                        $dayTextClass = 'text-blue-800';
                                    } elseif ($dayDate->lt($today)) {
                                        $dayClass = 'bg-red-50';
                                        $dayTextClass = 'text-red-700';
                                    } else {
                                        $dayClass = 'bg-green-50';
                                        $dayTextClass = 'text-green-700';
                                    }
                                    $cellOverflow = 0;
                                    $cellStartsAt = null;
                                    if ($template && $block) {
                                        $firstRow = \App\Models\ProgramTimelineItem::forDay($template->id, $d)->orderBy('starts_at_sec')->first();
                                        $lastRow  = \App\Models\ProgramTimelineItem::forDay($template->id, $d)->orderByDesc('ends_at_sec')->first();
                                        $cellStartsAt = $firstRow?->starts_at_sec;
                                        $cellOverflow = $lastRow ? max(0, (int) $lastRow->ends_at_sec - 86400) : 0;
                                    }
                                    $cellStartsAtLabel = $cellStartsAt !== null ? sprintf('%02d:%02d', intdiv((int)$cellStartsAt, 3600), intdiv(((int)$cellStartsAt) % 3600, 60)) : null;
                                @endphp
                                <div class="aspect-square border-r border-b border-gray-300 p-1.5 transition relative cursor-pointer group hover:bg-teal-100/50 {{ $dayClass }} {{ $isSelected ? 'ring-2 ring-teal-500 ring-inset' : '' }}"
                                     @click="window.location='{{ route('client.scheduler') }}?channel_id={{ $currentChannel }}&year={{ $year }}&month={{ $month }}&day={{ $d }}'">
                                    <div class="flex items-start justify-between">
                                        <div class="text-xs font-semibold {{ $isSelected ? 'text-teal-700' : $dayTextClass }}">{{ $d }}</div>
                                        @if($playlist)
                                            @php
                                                $playlistsJson = $playlists->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()->toJson();
                                            @endphp
                                            <div class="opacity-0 group-hover:opacity-100 transition flex items-center gap-0.5 -mt-0.5 -mr-0.5 relative z-10"
                                                 @click.stop>
                                                <button type="button"
                                                        @click="openEditor('{{ $block->playlist->id }}')"
                                                        title="Editar"
                                                        class="w-6 h-6 rounded-lg bg-gradient-to-br from-teal-400 to-teal-600 shadow-md flex items-center justify-center text-white hover:from-teal-500 hover:to-teal-700 hover:scale-110 transition">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>
                                                <button type="button"
                                                        @click="window.schedulerCellActions.change(templateId, {{ $d }}, csrfToken)"
                                                        title="Cambiar playlist"
                                                        class="w-6 h-6 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 shadow-md flex items-center justify-center text-white hover:from-blue-500 hover:to-blue-700 hover:scale-110 transition">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                </button>
                                                <button type="button"
                                                        @click="window.schedulerCellActions.remove(templateId, {{ $d }}, csrfToken)"
                                                        title="Quitar playlist"
                                                        class="w-6 h-6 rounded-lg bg-gradient-to-br from-red-400 to-red-600 shadow-md flex items-center justify-center text-white hover:from-red-500 hover:to-red-700 hover:scale-110 transition">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                                </button>
                                                <button type="button"
                                                        @click="window.schedulerCellActions.replicate(templateId, {{ $d }}, {{ $year }}, {{ $month }}, {{ $blocksByDay ? $blocksByDay->keys()->toJson() : '[]' }})"
                                                        title="Replicar"
                                                        class="w-6 h-6 rounded-lg bg-gradient-to-br from-amber-400 to-amber-600 shadow-md flex items-center justify-center text-white hover:from-amber-500 hover:to-amber-700 hover:scale-110 transition">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                                </button>
                                                <button type="button"
                                                        @click="$dispatch('open-insert-cue', { templateId: '{{ $template?->id }}', day: {{ $d }}, channelId: '{{ $currentChannel }}' })"
                                                        title="Insertar cuña"
                                                        class="w-6 h-6 rounded-lg bg-gradient-to-br from-purple-400 to-purple-600 shadow-md flex items-center justify-center text-white hover:from-purple-500 hover:to-purple-700 hover:scale-110 transition">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                                </button>
                                            </div>
                                        @else
                                            <button type="button"
                                                    @click.stop="window.schedulerCellActions.change(templateId, {{ $d }}, csrfToken)"
                                                    title="Asignar playlist"
                                                    class="opacity-0 group-hover:opacity-100 transition w-5 h-5 rounded-md bg-gradient-to-br from-teal-400 to-teal-600 shadow-sm flex items-center justify-center text-white hover:from-teal-500 hover:to-teal-700 hover:scale-110">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                    @if($playlist)
                                        <div class="mt-1 px-1.5 py-0.5 rounded text-[10px] font-medium text-white truncate"
                                             style="background-color: {{ $color }}"
                                             @if($cellStartsAtLabel) title="Arranca a las {{ $cellStartsAtLabel }}" @endif>
                                            {{ $playlist->name }}
                                        </div>
                                    @else
                                        <div class="mt-1 flex items-center justify-between gap-1">
                                            <span class="text-[10px] text-gray-300 group-hover:text-teal-500">+ Asignar</span>
                                        </div>
                                    @endif
                                    @if($cellOverflow > 0)
                                        <div class="day-overflow-badge px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500 text-white shadow"
                                             title="Overflow: {{ $cellOverflow }}s después de 24h">
                                            ⚠ +{{ intdiv($cellOverflow, 60) }}m
                                        </div>
                                    @endif
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl ring-1 ring-gray-900/5 shadow-sm overflow-hidden sticky top-4">
                        <div class="p-4 border-b border-gray-100 bg-gradient-to-r from-teal-50 to-cyan-50">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-teal-700 uppercase tracking-wider">Mis Playlists</span>
                                <span class="text-xs text-gray-500 font-mono">{{ $playlists->count() }} en este canal</span>
                            </div>
                        </div>

                        <div class="p-3 space-y-2 max-h-[70vh] overflow-y-auto">
                            @forelse($playlists as $pl)
                                @php
                                    $daysUsed = $blocksByDay->where('playlist_id', $pl->id)->count();
                                    $color = $playlistColorMap[$pl->id] ?? '#94a3b8';
                                    $itemsCount = (int) ($pl->items_count ?? 0);
                                    $totalSec = (int) ($pl->total_duration_sec ?? 0);
                                    if ($totalSec < 60) {
                                        $durLabel = $totalSec . 's';
                                    } else {
                                        $mins = intdiv($totalSec, 60);
                                        $secs = $totalSec % 60;
                                        $durLabel = $mins . 'min' . ($secs > 0 ? ' ' . $secs . 's' : '');
                                    }
                                @endphp
                                <div class="group flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-teal-300 hover:bg-teal-50/40 transition">
                                    <div class="w-3 h-3 rounded-full shrink-0 ring-2 ring-white shadow-sm" style="background-color: {{ $color }}"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate">{{ $pl->name }}</p>
                                        <p class="text-[11px] text-gray-500 mt-0.5">
                                            {{ $itemsCount }} {{ $itemsCount === 1 ? 'item' : 'items' }}
                                            · {{ $durLabel }}
                                            @if($daysUsed > 0)
                                                · {{ $daysUsed }} {{ $daysUsed === 1 ? 'día' : 'días' }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="shrink-0 flex flex-col items-center gap-1.5">
                                        <button type="button"
                                                @click="openEditor('{{ $pl->id }}')"
                                                title="Editar playlist"
                                                class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-teal-700 hover:bg-teal-600 hover:text-white hover:border-teal-600 transition flex items-center justify-center">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button type="button"
                                                @click="deletePlaylist('{{ $pl->id }}', @js($pl->name))"
                                                title="Eliminar playlist"
                                                class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-red-600 hover:bg-red-600 hover:text-white hover:border-red-600 transition flex items-center justify-center">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-sm text-gray-400 py-10 px-3">
                                    <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v12M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/></svg>
                                    <p class="font-medium text-gray-600">Aún no hay playlists</p>
                                    <p class="text-xs mt-1">Usa el botón <strong class="text-teal-600">"Nueva playlist"</strong> arriba para crear la primera.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    @endif

    @include('components.schedule-template-modal', ['channelId' => $currentChannel, 'year' => $year, 'month' => $month])
    @include('components.create-playlist-modal')
    @include('components.media-library-picker-modal')
    @include('components.playlist-editor-fullscreen-modal')
    @include('components.replicate-modal')
    @include('components.replicate-month-modal')
    @include('components.scheduler-dialogs')
    <x-live-viewer-modal />

    {{-- Toast: playlist saved successfully --}}
    <div x-data="{ show: false }"
         x-init="window.addEventListener('playlist-saved', () => { show = true; setTimeout(() => show = false, 2000); })"
         x-show="show" x-cloak x-transition
         class="fixed top-6 right-6 z-[60]">
        <div class="bg-green-500 text-white rounded-xl shadow-xl px-4 py-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span class="text-sm font-semibold">Cambios guardados</span>
        </div>
    </div>

    @php
        $adCount = $mediaItems->where('kind','ad')->where('status','ready')->count();
        $overflowByDay = [];
        foreach ($blocksByDay ?? collect() as $day => $b) {
            $last = \App\Models\ProgramTimelineItem::forDay($template?->id ?? '', (int) $day)
                ->orderByDesc('ends_at_sec')->first();
            if ($last) {
                $overflowByDay[(int) $day] = max(0, (int) $last->ends_at_sec - 86400);
            }
        }
    @endphp
    <style>
        .day-overflow-badge { position: absolute; bottom: 4px; right: 4px; }
    </style>
    <script>
    window.insertCueModalData = {
        adCount: {{ $adCount }},
        overflowByDay: @json($overflowByDay),
    };
    </script>

    {{-- Insert Cue modal --}}
    <div x-data="insertCueModal({
            csrf: window.csrfToken,
            cues: {{ $mediaItems->where('kind','ad')->where('status','ready')->map(fn($m) => ['id' => $m->id, 'filename' => $m->filename, 'duration_sec' => $m->duration_sec])->values()->toJson() }}
        })"
         @open-insert-cue.window="open($event.detail)"
         x-show="isOpen" x-cloak
         class="fixed inset-0 z-[55] flex items-center justify-center p-4"
         style="display: none;">
        <div class="absolute inset-0 bg-black/50" @click="close()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6 z-10">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Insertar cuña</h3>
            <p class="text-xs text-gray-500 mb-4">Inserta una cuña en el timeline 00:00-24:00. Solo aplica al futuro (no afecta lo que está al aire).</p>
            <div class="space-y-3">
                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Cuña de biblioteca</label>
                    <select x-model="form.media_item_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50/50">
                        <option value="">— selecciona una cuña —</option>
                        <template x-for="c in cues" :key="c.id">
                            <option :value="c.id" x-text="c.filename + ' (' + Math.round((c.duration_sec||0)) + 's)'"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Hora de inserción (HH:MM)</label>
                    <input type="time" x-model="form.time" :min="minTime" required
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-gray-50/50" />
                    <p class="mt-1 text-[10px] text-gray-400">Mínimo: ahora + 1 minuto. Solo se puede insertar en el futuro.</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600" x-show="previewSec !== null">
                    <div>Insertará a las <span class="font-mono" x-text="previewClock"></span>
                        (<span x-text="previewSec + 's'"></span>) → desplaza el contenido posterior (<span x-text="previewShift"></span>).
                    </div>
                    <div class="mt-1" :class="previewOverflow ? 'text-amber-700 font-medium' : 'text-gray-500'">
                        <span x-show="previewOverflow">⚠ Generará overflow</span>
                        <span x-show="!previewOverflow">Sin overflow previsto</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 mt-5">
                <button type="button" @click="close()" class="px-4 py-2 text-sm text-gray-600 rounded-lg hover:bg-gray-100">Cancelar</button>
                <button type="button" @click="submit()" :disabled="submitting || !canSubmit"
                        class="px-4 py-2 bg-purple-600 text-white text-sm font-semibold rounded-lg hover:bg-purple-500 disabled:bg-gray-300">
                    <span x-show="!submitting">Insertar cuña</span>
                    <span x-show="submitting">Insertando...</span>
                </button>
            </div>
        </div>
    </div>
</x-client-layout>

<script>
(function() {
    if (!window.Plyr || !window.Hls) return;
    const players = [];
    document.querySelectorAll('video[data-hls-url]').forEach((video) => {
        const url = video.dataset.hlsUrl;
        if (!url) return;
        const player = new window.Plyr(video, {
            controls: ['play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
            autoplay: true,
            muted: true,
        });
        players.push(player);
        player.on('ready', () => {
            if (window.Hls.isSupported()) {
                const hls = new window.Hls({ liveSyncDurationCount: 3, liveMaxLatencyDurationCount: 8 });
                hls.loadSource(url);
                hls.attachMedia(player.media);
                player._hls = hls;
            } else if (player.media.canPlayType('application/vnd.apple.mpegurl')) {
                player.media.src = url;
            }
        });
    });
    window.addEventListener('beforeunload', () => {
        players.forEach(p => {
            if (p._hls) try { p._hls.destroy(); } catch (e) {}
            try { p.destroy(); } catch (e) {}
        });
    });
})();
</script>

<script>
document.addEventListener('alpine:init', () => {
    window.insertCueModal = (opts) => ({
        isOpen: false,
        form: { templateId: '', day: 0, channelId: '', media_item_id: '', time: '' },
        submitting: false,
        cues: opts.cues || [],
        previewSec: null,
        previewClock: '',
        previewShift: '0s',
        previewOverflow: false,
        init() {
            this.$watch('form.media_item_id', () => this.recomputePreview());
            this.$watch('form.time', () => this.recomputePreview());
        },
        get minTime() {
            const d = new Date(Date.now() + 60_000);
            return d.toTimeString().slice(0,5);
        },
        get canSubmit() {
            return this.form.media_item_id && this.form.time;
        },
        open(detail) {
            this.form = { ...this.form, ...detail };
            const d = new Date(Date.now() + 60_000);
            this.form.time = d.toTimeString().slice(0,5);
            this.recomputePreview();
            this.isOpen = true;
        },
        close() {
            this.isOpen = false;
        },
        recomputePreview() {
            if (!this.form.time) { this.previewSec = null; return; }
            const [h, m] = this.form.time.split(':').map(Number);
            this.previewSec = h * 3600 + m * 60;
            this.previewClock = this.form.time;
            const cue = this.cues.find(c => c.id === this.form.media_item_id);
            this.previewShift = (cue ? Math.round(cue.duration_sec||0) : 0) + 's';
            const overflow = (window.insertCueModalData.overflowByDay[this.form.day] || 0);
            this.previewOverflow = (this.previewSec + (cue ? cue.duration_sec : 0)) > 86400;
        },
        async submit() {
            if (!this.canSubmit) return;
            this.submitting = true;
            const [h, m] = this.form.time.split(':').map(Number);
            const atSec = h * 3600 + m * 60;
            try {
                const url = `/api/schedule-templates/${this.form.templateId}/days/${this.form.day}/timeline/insert-cue`;
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ media_item_id: this.form.media_item_id, at_sec: atSec }),
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(data.message || ('HTTP ' + r.status));
                window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Cuña insertada en ' + this.previewClock } }));
                this.close();
                setTimeout(() => window.location.reload(), 600);
            } catch (e) {
                window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: e.message || 'Error al insertar' } }));
            } finally {
                this.submitting = false;
            }
        },
    });
});
</script>

<script>
window.schedulerPlaylists = {!! json_encode($playlists->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()->toArray(), JSON_UNESCAPED_UNICODE) !!};
const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
window.schedulerMonthActions = {
    openReplicateMonth(templateId, sourceYear, sourceMonth, blocksCount) {
        window.replicateMonthConfig = {
            templateId, sourceYear, sourceMonth, blocksCount,
            monthNames,
        };
        Alpine.store('modals').open('replicate-month');
    },
};
window.schedulerCellActions = {
    baseUrl: '{{ url("/") }}',
    async replicate(templateId, day, year, month, existingBlocks) {
        window.replicateConfig = {
            templateId, selectedDay: day, year, month,
            existingBlocks: (existingBlocks || []).map(d => parseInt(d)),
        };
        Alpine.store('modals').open('replicate');
    },
    async change(templateId, day, csrfToken) {
        const playlists = window.schedulerPlaylists || [];
        const options = [
            { value: '', label: '— Sin cambio —' },
            ...playlists.map(p => ({ value: p.id, label: p.name })),
        ];
        window.schedulerDialogStore().askPromptChoice('Cambiar playlist del día ' + day, options, async (val) => {
            if (val === null || val === '') return;
            try {
                const r = await fetch('/api/schedule-templates/' + templateId + '/days/' + day, {
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ playlist_id: val || null }),
                });
                if (r.ok) { window.schedulerDialogStore().flashSuccess('Playlist cambiada'); setTimeout(() => window.location.reload(), 600); }
                else await r.json().then(d => window.schedulerDialogStore().flashError(d.message || 'Error al cambiar'));
            } catch(e) { window.schedulerDialogStore().flashError('Error de red'); }
        });
    },
    async remove(templateId, day, csrfToken) {
        window.schedulerDialogStore().askConfirm('¿Quitar playlist?', '¿Estás seguro de quitar la playlist del día ' + day + '?', async (ok) => {
            if (!ok) return;
            try {
                const r = await fetch('/api/schedule-templates/' + templateId + '/days/' + day, {
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ playlist_id: null }),
                });
                if (r.ok) { window.schedulerDialogStore().flashSuccess('Playlist quitada'); setTimeout(() => window.location.reload(), 600); }
                else window.schedulerDialogStore().flashError('Error al quitar');
            } catch(e) { window.schedulerDialogStore().flashError('Error de red'); }
        });
    },
};

window.playlistsList = (config) => ({
    mediaItems: config.mediaItems || [],
    csrfToken: config.csrfToken || '',
    templateId: config.templateId || null,

    // NOTE: Keep this openEditor logic synchronized with admin/scheduler/index.blade.php.
    // The only allowed difference is the channel filter (client sees assigned channels only).
    async openEditor(playlistId) {
        if (!playlistId) return;
        try {
            const r = await fetch('/api/playlists/' + playlistId, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
            });
            if (!r.ok) {
                const d = await r.json().catch(() => ({}));
                alert(d.message || 'No se pudo cargar la playlist.');
                return;
            }
            const playlist = await r.json();
            const editor = Alpine.store('playlistEditor');
            if (!editor) {
                alert('El editor de playlists no está disponible.');
                return;
            }
            editor.playlistId = playlist.id;
            editor.name = playlist.name;
            editor.items = (playlist.items || []).map(i => {
                const fullDur = i.media_item?.duration_sec || 0;
                let effective = fullDur;
                if (i.cue_in_sec !== null && i.cue_in_sec !== undefined) effective -= i.cue_in_sec;
                if (i.cue_out_sec !== null && i.cue_out_sec !== undefined) effective = i.cue_out_sec - (i.cue_in_sec || 0);
                return {
                    id: i.id,
                    media_item_id: i.media_item_id,
                    filename: i.media_item?.filename || '?',
                    kind: i.media_item?.kind || 'video',
                    duration_sec: Math.max(0, effective),
                    position: i.position,
                    start_sec: i.start_sec ?? null,
                    split_from_id: i.split_from_id ?? null,
                    cue_in_sec: i.cue_in_sec ?? null,
                    cue_out_sec: i.cue_out_sec ?? null,
                };
            });
            const byPos = editor.items.sort((a, b) => a.position - b.position);
            let cursor = 0;
            for (const it of byPos) {
                if (it.start_sec !== null && it.start_sec !== undefined) cursor = parseInt(it.start_sec) || 0;
                it._scheduleStart = cursor;
                cursor += parseInt(it.duration_sec) || 0;
            }
            editor.items = byPos.sort((a, b) => a._scheduleStart - b._scheduleStart);
            editor.library = this.mediaItems.map(m => ({
                id: m.id,
                filename: m.filename,
                kind: m.kind,
                duration_sec: m.duration_sec || 0,
            }));
            if (typeof editor.recalcTotals === 'function') editor.recalcTotals();
            editor.playheadPosition = 0;
            editor.isPlaying = false;
            Alpine.store('modals').open('playlist-editor');
        } catch (e) {
            alert('Error de red al cargar la playlist.');
        }
    },

    deletePlaylist(id, name) {
        if (!id) return;
        const dialogStore = window.schedulerDialogStore();
        dialogStore.askConfirm(
            '¿Eliminar playlist?',
            '¿Estás seguro de eliminar la playlist "' + name + '"? Si está asignada a días del mes, esos días quedarán sin playlist.',
            async (ok) => {
                if (!ok) return;
                try {
                    const r = await fetch('/api/playlists/' + id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                    });
                    if (r.ok) {
                        dialogStore.flashSuccess('Playlist eliminada');
                        setTimeout(() => window.location.reload(), 600);
                        return;
                    }
                    const d = await r.json().catch(() => ({}));
                    dialogStore.flashError(d.message || 'Error al eliminar');
                } catch (e) {
                    dialogStore.flashError('Error de red');
                }
            }
        );
    },
});

window.emissionControl = (config) => ({
    channelId: config.channelId || '',
    csrfToken: config.csrfToken || '',
    isEmitting: {{ $isEmitting ? 'true' : 'false' }},
    status: '{{ $isEmitting ? 'live' : 'offline' }}',
    isLoading: false,
    message: '',
    messageType: '',

    // Log panel
    showLog: false,
    logLines: [],
    isPolling: false,
    logInterval: null,

    // Stats
    latestStats: {
        bitrate: 0,
        fps: 0,
        frames_sent: 0,
        uptime: 0,
        item: '',
    },

    init() {
        // Watch showLog to start/stop log polling
        this.$watch('showLog', (val) => {
            if (val) {
                this.fetchLog();
                this.logInterval = setInterval(() => this.fetchLog(), 5000);
            } else {
                if (this.logInterval) clearInterval(this.logInterval);
                this.logInterval = null;
            }
        });
    },

    async fetchLog() {
        if (!this.channelId) return;
        this.isPolling = true;
        try {
            const r = await fetch(`/api/channels/${this.channelId}/emission/log?lines=100`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
            });
            if (!r.ok) return;
            const data = await r.json();

            this.logLines = (data.lines || []).map((line, i) => ({
                ...line,
                i: i,
            }));

            const statsLines = data.lines?.filter(l => l.type === 'stats') || [];
            if (statsLines.length > 0) {
                const last = statsLines[statsLines.length - 1];
                this.latestStats = {
                    bitrate: last.bitrate || 0,
                    fps: last.fps || 0,
                    frames_sent: last.frames_sent || 0,
                    uptime: last.uptime || 0,
                    item: last.item || '',
                };
            }

            this.$nextTick(() => {
                const container = this.$refs.logContainer;
                if (container) container.scrollTop = container.scrollHeight;
            });
        } catch (e) {
            // silent fail
        } finally {
            this.isPolling = false;
        }
    },

    async startEmission() {
        this.isLoading = true;
        this.message = '';
        try {
            const r = await fetch('/api/channels/' + this.channelId + '/emission/start', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });
            const d = await r.json().catch(() => ({}));
            if (r.ok) {
                this.isEmitting = true;
                this.status = 'live';
                this.message = 'Transmisión iniciada';
                this.messageType = 'success';
                this.showLog = true;
                // Don't reload — keep polling
                this.fetchLog();
                if (this.logInterval) clearInterval(this.logInterval);
                this.logInterval = setInterval(() => this.fetchLog(), 5000);
            } else {
                this.message = d.message || 'Error al iniciar';
                this.messageType = 'error';
            }
        } catch (e) {
            this.message = 'Error de red';
            this.messageType = 'error';
        } finally {
            this.isLoading = false;
        }
    },

    async stopEmission() {
        this.isLoading = true;
        this.message = '';
        try {
            const r = await fetch('/api/channels/' + this.channelId + '/emission/stop', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });
            const d = await r.json().catch(() => ({}));
            if (r.ok) {
                this.isEmitting = false;
                this.status = 'offline';
                this.message = 'Transmisión detenida';
                this.messageType = 'success';
                this.showLog = false;
                if (this.logInterval) clearInterval(this.logInterval);
                this.logInterval = null;
                setTimeout(() => window.location.reload(), 600);
            } else {
                this.message = d.message || 'Error al detener';
                this.messageType = 'error';
            }
        } catch (e) {
            this.message = 'Error de red';
            this.messageType = 'error';
        } finally {
            this.isLoading = false;
        }
    },
});
</script>