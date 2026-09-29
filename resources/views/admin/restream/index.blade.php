<x-admin-layout active="restream">
    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Restream</h2>
    </x-slot:header>

    <div
        x-data="{
            channels: {{ $channelsJson ?? '[]' }},
            targets: {{ $targetsJson ?? '[]' }},
            connectedAccounts: {{ $connectedAccountsJson ?? '[]' }},
            clients: {{ $clientsJson ?? '[]' }},
            currentChannelId: @js($currentChannelId),
            usedOutputs: @js((int) $usedOutputs),
            maxOutputs: @js((int) $maxOutputs),
            {{-- Pre-encoded JSON string so Blade's htmlspecialchars escapes the inner quotes;
                 DO NOT switch to @json() here — it emits raw " which closes the x-data attribute. --}}
            currentChannel: {{ $currentChannelJson ?? 'null' }},
            flash: '',
            flashKind: 'info',
            csrf() { return document.querySelector('meta[name=csrf-token]')?.content || window.csrfToken; },
            accountFor(ownerId, platform) {
                return this.connectedAccounts.find((a) => a.owner_id === ownerId && a.platform === platform) || null;
            },
            accountsByPlatform(platform) {
                return this.connectedAccounts.filter((a) => a.platform === platform);
            },
            connectAccountAs(ownerId, platform) {
                window.location.href = window.restreamUrls.admin.accountConnect.replace('USERID', ownerId).replace('PLATFORM', platform);
            },
            async disconnectAccountAs(ownerId, platform, displayName) {
                if (!confirm('¿Desconectar la cuenta de ' + platform + ' del cliente ' + displayName + '? Los destinos ya configurados seguirán funcionando pero pasarán a modo manual.')) return;
                const url = window.restreamUrls.admin.accountDisconnect.replace('USERID', ownerId).replace('PLATFORM', platform);
                const r = await fetch(url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const body = await r.json().catch(() => ({}));
                this.flash = body.message || 'OK';
                this.flashKind = r.ok ? 'ok' : 'err';
                if (r.ok) {
                    this.connectedAccounts = this.connectedAccounts.filter((a) => !(a.owner_id === ownerId && a.platform === platform));
                }
            },
            openCreate() {
                if (!this.currentChannel) {
                    alert('No hay canales con owner asignado. Asigna un owner en /admin/channels para crear destinos de restream.');
                    return;
                }
                Alpine.store('modals').open('restream-target', { channel: this.currentChannel, mode: 'create', isAdmin: true, connectedAccounts: this.connectedAccounts, usedOutputs: this.usedOutputs, maxOutputs: this.maxOutputs });
            },
            openEdit(t) {
                const ch = this.channels.find((c) => c.id === t.channel_id) || { id: t.channel_id, display_name: t.channel?.display_name };
                Alpine.store('modals').open('restream-target', { channel: ch, mode: 'edit', target: t, isAdmin: true, connectedAccounts: this.connectedAccounts.filter((a) => a.owner_id === (t.user?.id || t.user_id)) });
            },
            async startOrStop(t) {
                if (t.status === 'live' || t.status === 'starting') {
                    await this.stop(t);
                } else {
                    await this.start(t);
                }
            },
            async start(t) {
                const url = window.restreamUrls.admin.start.replace('CID', t.channel_id).replace('TID', t.id);
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const body = await r.json().catch(() => ({}));
                this.flash = body.message || 'OK';
                this.flashKind = r.ok ? 'ok' : 'err';
                window.dispatchEvent(new CustomEvent('restream-targets-changed'));
            },
            async stop(t) {
                const url = window.restreamUrls.admin.stop.replace('CID', t.channel_id).replace('TID', t.id);
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const body = await r.json().catch(() => ({}));
                this.flash = body.message || 'OK';
                this.flashKind = r.ok ? 'ok' : 'err';
                window.dispatchEvent(new CustomEvent('restream-targets-changed'));
            },
            askRemove(t) {
                Alpine.store('modals').open('confirm', {
                    title: 'Inactivar destino',
                    message: '¿Inactivar el destino &quot;' + (t.name || '') + '&quot;? El slot quedará libre, pero la configuración se conserva.',
                    tone: 'warning',
                    iconName: 'archive',
                    confirmLabel: 'Sí, inactivar',
                    action: window.restreamUrls.admin.destroy.replace('CID', t.channel_id).replace('TID', t.id),
                    method: 'DELETE',
                    successEvent: 'restream-targets-changed',
                });
            },
            askInactivate(t) {
                Alpine.store('modals').open('confirm', {
                    title: 'Inactivar destino',
                    message: '¿Inactivar el destino &quot;' + (t.name || '') + '&quot;? Se detendrá si está corriendo y el slot quedará libre para crear otro destino. Puedes volver a iniciarlo después.',
                    tone: 'warning',
                    iconName: 'pause',
                    confirmLabel: 'Sí, inactivar',
                    action: window.restreamUrls.admin.deactivate.replace('CID', t.channel_id).replace('TID', t.id),
                    method: 'POST',
                    successEvent: 'restream-targets-changed',
                });
            },
            askForceDestroy(t) {
                Alpine.store('modals').open('confirm', {
                    title: 'Eliminar destino DEFINITIVAMENTE',
                    message: 'Esta acción NO se puede deshacer. Se borrará el registro &quot;' + (t.name || '') + '&quot;, sus credenciales RTMP y el vínculo con la cuenta OAuth.',
                    tone: 'danger',
                    iconName: 'trash',
                    confirmLabel: 'Eliminar para siempre',
                    requireText: 'ELIMINAR',
                    action: window.restreamUrls.admin.forceDestroy.replace('CID', t.channel_id).replace('TID', t.id),
                    method: 'DELETE',
                    successEvent: 'restream-targets-changed',
                });
            },
            init() {
                window.addEventListener('restream-targets-changed', async () => {
                    await this.refresh();
                });
                this.pollInterval = setInterval(() => this.refresh(), 10000);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'hidden') {
                        if (this.pollInterval) clearInterval(this.pollInterval);
                        this.pollInterval = null;
                    } else if (!this.pollInterval) {
                        this.pollInterval = setInterval(() => this.refresh(), 10000);
                    }
                });
            },
            async refresh() {
                const r = await fetch(window.location.href, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) {
                    const data = await r.json();
                    const freshTargets = data.targets || [];
                    const byId = Object.fromEntries(this.targets.map((t) => [t.id, t]));
                    this.targets = freshTargets.map((nt) => {
                        const old = byId[nt.id];
                        const merged = old ? { ...nt, _showLog: old._showLog, _logLines: old._logLines, _logInterval: old._logInterval, _latestStats: old._latestStats, _showCreds: old._showCreds, _streamKey: old._streamKey, _fullPushUrl: old._fullPushUrl, _sourceUrl: old._sourceUrl, _ffmpegCommand: old._ffmpegCommand } : nt;
                        merged._effectiveStatus = nt.effective_status || merged.status || 'idle';
                        return merged;
                    });
                    // Mirror the targets list into the live banner.
                    window.__restreamBannerTargets = this.targets.map((t) => ({
                        id: t.id,
                        platform: t.platform,
                        name: t.name,
                        effective_status: t._effectiveStatus,
                        next_ends_at: t.next_ends_at || null,
                        share_url: t.share_url || null,
                    }));
                    window.dispatchEvent(new CustomEvent('restream-banner-targets-updated'));
                }
            },
            openLog(t) {
                t._showLog = !t._showLog;
                if (t._showLog) {
                    this.fetchLog(t);
                    t._logInterval = setInterval(() => this.fetchLog(t), 5000);
                } else if (t._logInterval) {
                    clearInterval(t._logInterval);
                    t._logInterval = null;
                }
            },
            async fetchLog(t) {
                const url = window.restreamUrls.admin.log.replace('CID', t.channel_id).replace('TID', t.id);
                const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                const data = await r.json();
                t._logLines = (data.lines || []).map((line, i) => ({ ...line, i }));
                const statsLines = t._logLines.filter((l) => l.type === 'stats');
                if (statsLines.length > 0) {
                    const last = statsLines[statsLines.length - 1];
                    t._latestStats = {
                        bitrate: last.bitrate || 0,
                        fps: last.fps || 0,
                        frames_sent: last.frames_sent || 0,
                        uptime: last.uptime || 0,
                    };
                }
                this.$nextTick(() => {
                    const container = this.$refs['log-' + t.id];
                    if (container) container.scrollTop = container.scrollHeight;
                });
            },
            async revealCreds(t) {
                t._showCreds = !t._showCreds;
                if (t._showCreds && !t._streamKey) {
                    const url = window.restreamUrls.admin.show.replace('CID', t.channel_id).replace('TID', t.id);
                    const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (r.ok) {
                        const data = await r.json();
                        t._streamKey = data.stream_key || '';
                        t._fullPushUrl = data.full_push_url || '';
                        t._sourceUrl = data.source_url || '';
                        t._ffmpegCommand = data.ffmpeg_command || '';
                    }
                }
            },
            async copyText(text) {
                if (!text) return;
                try {
                    await navigator.clipboard.writeText(text);
                    this.flash = 'Copiado al portapapeles.';
                    this.flashKind = 'ok';
                } catch (e) {
                    this.flash = 'No se pudo copiar.';
                    this.flashKind = 'err';
                }
            },
        }"
    >
        @if($channels->isEmpty())
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 text-sm text-yellow-800">
                No hay canales con owner asignado. Crea un owner en <a class="text-rose-600 hover:underline" href="/admin/channels">/admin/channels</a> y habilita Restream desde la edición del canal antes de configurar destinos.
            </div>
        @else
            <x-restream-live-banner />
            <div class="bg-white rounded-lg ring-1 ring-gray-200 shadow-sm p-4 mb-5">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Canal de contexto</label>
                        <select onchange="window.location.href='{{ route('admin.restream.index') }}?channel_id='+this.value"
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50/50 transition">
                            @foreach($channels as $ch)
                                <option value="{{ $ch->id }}" @selected($currentChannelId === $ch->id)>{{ $ch->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <span class="px-3 py-1 text-sm rounded-full"
                              :class="{{ (int) $remainingSlots }} === 0 ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800'">
                            Slots ({{ optional($currentChannel)->display_name ?? '—' }}): {{ (int) $usedOutputs }}/{{ (int) $maxOutputs }}
                        </span>
                        <button type="button" @click="openCreate()"
                                @disabled(!$currentChannelId || (int) $remainingSlots === 0)
                                class="inline-flex items-center gap-2 px-3 py-2 bg-rose-600 text-white text-sm font-medium rounded-md hover:bg-rose-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            + Nuevo destino
                        </button>
                    </div>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600">
                    Re-transmite los canales a plataformas externas (Facebook, TikTok, YouTube, RTMP personalizado).
                </p>
            </div>

            <div class="bg-white rounded-lg ring-1 ring-gray-200 shadow-sm p-4 mb-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-800">Cuentas conectadas</h3>
                    <span class="text-[11px] text-gray-500 uppercase tracking-wider">Puedes conectar/desconectar en nombre de cualquier cliente</span>
                </div>
                <p class="text-xs text-gray-500 mb-3">Gestión de las cuentas OAuth de cada cliente. Al desconectar, los destinos que dependían de esta cuenta conservan sus credenciales RTMP y siguen transmitiendo; al reconectar, los destinos pueden re-vincularse manualmente para actualizar metadatos.</p>
                @if(session('status'))
                    <div class="mb-3 px-3 py-2 text-xs rounded bg-emerald-50 text-emerald-800">{{ session('status') }}</div>
                @endif
                @isset($errors)
                    @if($errors->has('restream'))
                        <div class="mb-3 px-3 py-2 text-xs rounded bg-red-50 text-red-800">{{ $errors->first('restream') }}</div>
                    @endif
                @endisset
                <template x-if="flash">
                    <div class="mb-3 px-3 py-2 text-xs rounded" :class="flashKind === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'" x-text="flash"></div>
                </template>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Cliente</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Plataforma</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Cuenta</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Conectado</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Expira</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="acct in connectedAccounts" :key="acct.id">
                                <tr>
                                    <td class="px-3 py-2 text-gray-700" x-text="acct.owner_display_name"></td>
                                    <td class="px-3 py-2 text-gray-700" x-text="acct.platform_label"></td>
                                    <td class="px-3 py-2 text-gray-700" x-text="acct.display_name"></td>
                                    <td class="px-3 py-2 text-gray-500" x-text="acct.connected_at ? new Date(acct.connected_at).toLocaleString() : '—'"></td>
                                    <td class="px-3 py-2 text-gray-500" x-text="acct.token_expires_at ? new Date(acct.token_expires_at).toLocaleString() : '—'"></td>
                                    <td class="px-3 py-2">
                                        <template x-if="acct.needs_reconnect">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs rounded bg-amber-100 text-amber-800" title="Refresh token expirado o revocado: hay que reconectar.">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                Reconexión requerida
                                            </span>
                                        </template>
                                        <template x-if="!acct.needs_reconnect">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs rounded bg-emerald-100 text-emerald-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                Conectado
                                            </span>
                                        </template>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="inline-flex flex-wrap gap-1.5 justify-end">
                                            <template x-if="acct.needs_reconnect">
                                                <button type="button" @click="connectAccountAs(acct.owner_id, acct.platform)" title="Iniciar OAuth para refrescar la conexión" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-sm hover:from-amber-500 hover:to-amber-700">
                                                    Reconectar
                                                </button>
                                            </template>
                                            <template x-if="!acct.needs_reconnect">
                                                <button type="button" @click="connectAccountAs(acct.owner_id, acct.platform)" title="Refrescar la conexión (re-consent)" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded bg-gradient-to-br from-indigo-400 to-indigo-600 text-white shadow-sm hover:from-indigo-500 hover:to-indigo-700">
                                                    Refrescar
                                                </button>
                                            </template>
                                            <button type="button" @click="disconnectAccountAs(acct.owner_id, acct.platform, acct.owner_display_name)" title="Forzar desconexión" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded bg-gradient-to-br from-red-400 to-red-600 text-white shadow-sm hover:from-red-500 hover:to-red-700">
                                                Desconectar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="connectedAccounts.length === 0">
                                <tr>
                                    <td colspan="7" class="px-3 py-4 text-center text-xs text-gray-500">
                                        Ningún cliente ha conectado una cuenta todavía. Usa el formulario de abajo para iniciar una conexión en nombre de un cliente.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-lg ring-1 ring-gray-200 shadow-sm p-4 mb-5">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Conectar una cuenta para un cliente</h3>
                <p class="text-xs text-gray-500 mb-3">Inicia el flujo OAuth en nombre de un cliente. Al confirmar, Google/Facebook abrirán la pantalla de consentimiento; tras completarla, la cuenta quedará asociada al cliente seleccionado (no al admin).</p>
                <form method="GET" id="admin-connect-form" @submit.prevent="
                    const userId = $event.target.user_id.value;
                    const platform = $event.target.platform.value;
                    if (!userId || !platform) { alert('Selecciona cliente y plataforma.'); return; }
                    window.location.href = window.restreamUrls.admin.accountConnect
                        .replace('USERID', userId)
                        .replace('PLATFORM', platform);
                " class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Cliente</label>
                        <select name="user_id" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50/50">
                            <option value="">— Selecciona un cliente —</option>
                            <template x-for="c in clients" :key="c.id">
                                <option :value="c.id" x-text="c.display_name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="min-w-[180px]">
                        <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Plataforma</label>
                        <select name="platform" required class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50/50">
                            <option value="">— Plataforma —</option>
                            <option value="youtube">YouTube (Google)</option>
                            <option value="facebook">Facebook</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 bg-rose-600 text-white text-sm font-medium rounded-md hover:bg-rose-500">
                            Conectar
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex items-center">
                    <div class="ml-auto text-xs text-gray-500">
                        {{ $targets->total() }} destino(s)
                    </div>
                </div>

                <template x-if="flash">
                    <div class="px-4 py-2 text-sm" :class="flashKind === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'" x-text="flash"></div>
                </template>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plataforma</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Programación</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <template x-for="t in targets" :key="t.id">
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-3 text-sm text-gray-700" x-text="t.platform"></td>
                                <td class="px-6 py-3 text-sm text-gray-700" x-text="t.name"></td>
                                <td class="px-6 py-3 text-sm">
                                    <span class="inline-flex items-center gap-1.5">
                                        <template x-if="t.status === 'live'">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs rounded bg-emerald-100 text-emerald-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                Activo
                                            </span>
                                        </template>
                                        <template x-if="t.status === 'starting'">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs rounded bg-amber-100 text-amber-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                Iniciando
                                            </span>
                                        </template>
                                        <template x-if="t.status === 'error'">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs rounded bg-red-100 text-red-800" :title="t.last_error || 'Error'">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                                Error
                                            </span>
                                        </template>
                                        <template x-if="t.status !== 'live' && t.status !== 'starting' && t.status !== 'error'">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-700">
                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                                Inactivo
                                            </span>
                                        </template>
                                        <span x-data="{
                                                _now: Date.now(),
                                                _timer: null,
                                                init() { this._timer = setInterval(() => this._now = Date.now(), 30000); },
                                                destroy() { if (this._timer) clearInterval(this._timer); },
                                                get scheduledMs() {
                                                    if (!t.scheduled_start_at) return 0;
                                                    return new Date(String(t.scheduled_start_at).replace(' ', 'T')).getTime();
                                                },
                                                get msLeft() { return this.scheduledMs - this._now; },
                                                get isFuture() { return this.scheduledMs > 0 && this.msLeft > 0; },
                                                get showCountdown() { return this.isFuture && (t.status === 'idle' || t.status == null); },
                                                get label() {
                                                    const m = Math.floor(this.msLeft / 60000);
                                                    if (m < 60) return m + 'm';
                                                    const h = Math.floor(m / 60);
                                                    if (h < 24) return h + 'h ' + (m % 60) + 'm';
                                                    const d = Math.floor(h / 24);
                                                    return d + 'd ' + (h % 24) + 'h';
                                                }
                                            }"
                                            x-show="showCountdown"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-sky-100 text-sky-800"
                                            :title="'Inicia automáticamente a las ' + (t.scheduled_start_at || '')">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Inicia en <span x-text="label" class="tabular-nums"></span>
                                        </span>
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 whitespace-nowrap">
                                    <template x-if="t.scheduled_start_at || t.scheduled_stop_at">
                                        <div class="space-y-0.5">
                                            <div x-show="t.scheduled_start_at" class="inline-flex items-center gap-1 text-xs" :title="'Inicio programado'">
                                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <span class="tabular-nums" x-text="(t.scheduled_start_at || '').replace('T', ' ')"></span>
                                            </div>
                                            <div x-show="t.scheduled_stop_at" class="inline-flex items-center gap-1 text-xs" :title="'Fin programado'">
                                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011 1v4a1 1 0 11-2 0v-4a1 1 0 011-1m1-4a1 1 0 11-2 0 1 1 0 012 0"/></svg>
                                                <span class="tabular-nums" x-text="(t.scheduled_stop_at || '').replace('T', ' ')"></span>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="!t.scheduled_start_at && !t.scheduled_stop_at">
                                        <span class="text-gray-300">—</span>
                                    </template>
                                    <div x-show="t.last_auto_start_at || t.last_auto_stop_at" class="mt-1 text-[10px] text-gray-400 leading-tight" :title="'Registro de ejecuciones automáticas del programador'">
                                        <div x-show="t.last_auto_start_at"><span class="text-emerald-600 font-semibold">✓ auto-inicio:</span> <span x-text="t.last_auto_start_at"></span></div>
                                        <div x-show="t.last_auto_stop_at"><span class="text-rose-600 font-semibold">✓ auto-fin:</span> <span x-text="t.last_auto_stop_at"></span></div>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <div class="inline-flex flex-wrap gap-1.5">
                                        <button type="button" @click="openEdit(t)" title="Editar destino" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded bg-gradient-to-br from-indigo-400 to-indigo-600 text-white shadow-sm hover:from-indigo-500 hover:to-indigo-700">
                                            Editar
                                        </button>
                                        <button type="button" x-show="t.share_url && t._effectiveStatus === 'live'" @click="navigator.clipboard.writeText(t.share_url); flash = 'Link copiado: ' + t.share_url; flashKind = 'ok'; setTimeout(() => flash = '', 4000);" title="Copiar link de la emisión de YouTube para compartir" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded bg-gradient-to-br from-red-500 to-red-700 text-white shadow-sm hover:from-red-600 hover:to-red-800">
                                            Compartir
                                        </button>
                                        <button type="button" @click="openLog(t)" title="Ver log del proceso" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded bg-gradient-to-br from-slate-500 to-slate-700 text-white shadow-sm hover:from-slate-600 hover:to-slate-800">
                                            <span x-text="t._showLog ? 'Ocultar log' : 'Log'"></span>
                                        </button>
                                        <button type="button" @click="revealCreds(t)" title="Ver credenciales (stream key / RTMP completo)" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded bg-gradient-to-br from-purple-500 to-purple-700 text-white shadow-sm hover:from-purple-600 hover:to-purple-800">
                                            <span x-text="t._showCreds ? 'Ocultar credenciales' : 'Credenciales'"></span>
                                        </button>
                                        <button type="button" @click="start(t)" x-show="!(t.status === 'live' || t.status === 'starting' || t.status === 'error' || t.pipeline_pid)" title="Iniciar" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-sm hover:from-emerald-500 hover:to-emerald-700">
                                            Iniciar
                                        </button>
                                        <button type="button" @click="stop(t)" x-show="t.status === 'live' || t.status === 'starting' || t.status === 'error' || t.pipeline_pid" title="Detener" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-sm hover:from-amber-500 hover:to-amber-700">
                                            Detener
                                        </button>
                                        <button type="button" @click="askInactivate(t)" x-show="t.enabled" title="Inactivar (libera el slot, conserva la configuración)" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded bg-gradient-to-br from-slate-400 to-slate-600 text-white shadow-sm hover:from-slate-500 hover:to-slate-700">
                                            Inactivar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="t._showLog" x-cloak>
                                <td colspan="5" class="px-6 py-2">
                                    <div class="bg-slate-950 border border-slate-800 rounded-xl overflow-hidden">
                                        <div class="flex items-center justify-between px-3 py-2 bg-slate-900 border-b border-slate-800">
                                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Log de restream</span>
                                            <span class="text-[10px] text-slate-500" x-text="t.last_heartbeat_at ? ('heartbeat: ' + t.last_heartbeat_at) : ''"></span>
                                        </div>
                                        <div x-show="t.status === 'live' && t._latestStats" x-cloak
                                             class="flex items-center gap-3 px-3 py-2 bg-slate-900 text-slate-100 border-b border-slate-800 text-[11px] font-mono">
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                                                <span x-text="(t._latestStats?.bitrate ?? 0) + ' kbps'"></span>
                                            </div>
                                            <div class="w-px h-3 bg-slate-700"></div>
                                            <div x-text="(t._latestStats?.fps ?? 0).toFixed(1) + ' fps'"></div>
                                            <div class="w-px h-3 bg-slate-700"></div>
                                            <div x-text="(t._latestStats?.frames_sent ?? 0) + ' frames'"></div>
                                            <div class="w-px h-3 bg-slate-700"></div>
                                            <div x-text="Math.floor((t._latestStats?.uptime ?? 0)/60) + 'm ' + ((t._latestStats?.uptime ?? 0)%60) + 's'"></div>
                                        </div>
                                        <div class="h-48 overflow-y-auto font-mono text-[11px] leading-relaxed p-3 space-y-1" :x-ref="'log-' + t.id">
                                            <template x-for="entry in (t._logLines || [])" :key="entry.i">
                                                <div :class="entry.type === 'stats' ? 'text-green-400' : (entry.type === 'watchdog' ? 'text-yellow-400' : (entry.type === 'daemon' ? 'text-blue-400' : 'text-slate-400'))"
                                                     x-text="entry.raw"></div>
                                            </template>
                                            <div x-show="!(t._logLines || []).length" class="text-slate-600 italic">Sin logs todavía...</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="t._showCreds" x-cloak>
                                <td colspan="5" class="px-6 py-2">
                                    <div class="bg-purple-50 border border-purple-200 rounded-xl p-3 space-y-2">
                                        <div>
                                            <label class="block text-[11px] font-bold text-purple-700 uppercase tracking-wider mb-1">Stream Key</label>
                                            <div class="flex items-center gap-2">
                                                <input type="text" readonly onclick="this.select()" class="flex-1 text-xs font-mono bg-white border border-purple-300 rounded px-2 py-1" :value="t._streamKey || 'Cargando…'">
                                                <button type="button" @click="copyText(t._streamKey)" class="px-2 py-1 text-xs font-semibold rounded bg-purple-700 text-white hover:bg-purple-800">Copiar</button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-purple-700 uppercase tracking-wider mb-1">RTMP completo (destino + stream key)</label>
                                            <div class="flex items-center gap-2">
                                                <input type="text" readonly onclick="this.select()" class="flex-1 text-xs font-mono bg-white border border-purple-300 rounded px-2 py-1" :value="t._fullPushUrl || 'Cargando…'">
                                                <button type="button" @click="copyText(t._fullPushUrl)" class="px-2 py-1 text-xs font-semibold rounded bg-purple-700 text-white hover:bg-purple-800">Copiar</button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-purple-700 uppercase tracking-wider mb-1">URL de origen (fuente que consume FFmpeg)</label>
                                            <div class="flex items-center gap-2">
                                                <input type="text" readonly onclick="this.select()" class="flex-1 text-xs font-mono bg-white border border-purple-300 rounded px-2 py-1" :value="t._sourceUrl || 'Cargando…'">
                                                <button type="button" @click="copyText(t._sourceUrl)" class="px-2 py-1 text-xs font-semibold rounded bg-purple-700 text-white hover:bg-purple-800">Copiar</button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-purple-700 uppercase tracking-wider mb-1">Comando FFmpeg (exacto, tal como lo ejecuta el daemon)</label>
                                            <div class="flex items-center gap-2">
                                                <input type="text" readonly onclick="this.select()" class="flex-1 text-xs font-mono bg-white border border-purple-300 rounded px-2 py-1 overflow-x-auto" :value="t._ffmpegCommand || 'Cargando…'">
                                                <button type="button" @click="copyText(t._ffmpegCommand)" class="px-2 py-1 text-xs font-semibold rounded bg-purple-700 text-white hover:bg-purple-800">Copiar</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </template>
                    <template x-if="targets.length === 0">
                        <tbody>
                            <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                Aún no hay destinos configurados en este canal. Haz clic en <strong>+ Nuevo destino</strong> para empezar.
                            </td></tr>
                        </tbody>
                    </template>
                </table>
            </div>

            <div class="mt-4">{{ $targets->links() }}</div>
        @endif
    </div>

    <x-restream-target-modal :media-images-json="$mediaImagesJson ?? '[]'" />
    <x-confirm-modal />
    <x-restream-urls />
</x-admin-layout>