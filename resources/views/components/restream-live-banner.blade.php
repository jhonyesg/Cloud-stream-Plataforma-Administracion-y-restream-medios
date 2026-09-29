@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                window.Alpine.data('restreamLiveBanner', (initialTargets) => ({
                    targets: initialTargets,
                    _tickerId: null,
                    init() {
                        this._tickerId = setInterval(() => {
                            this.targets = this.targets.map((t) => ({ ...t }));
                            this.$nextTick(() => {
                                                this.$root.querySelectorAll('[data-countdown]').forEach((el) => {
                                                    const endsAt = el.dataset.endsAt;
                                                    el.textContent = formatEndsIn(endsAt);
                                                });
                                            });
                        }, 1000);
                    },
                    destroy() {
                        if (this._tickerId) {
                            clearInterval(this._tickerId);
                            this._tickerId = null;
                        }
                    },
                    get active() {
                        return this.targets.filter((t) => t.effective_status === 'live' || t.effective_status === 'yt-no-data' || t.effective_status === 'yt-test-starting');
                    },
                    labelFor(status) {
                        return {
                            'live': 'En vivo en YouTube',
                            'yt-no-data': 'YouTube sin datos aún',
                            'yt-test-starting': 'YouTube iniciando',
                            'starting': 'Iniciando',
                            'idle': 'Inactivo',
                            'error': 'Error',
                            'stale': 'Sin señal',
                            'yt-complete': 'YouTube dice "completado"',
                            'yt-revoked': 'YouTube dice "revocado"',
                            'unknown': '—',
                        }[status] || '—';
                    },
                    badgeClass(status) {
                        return {
                            'live': 'bg-emerald-100 text-emerald-800',
                            'yt-no-data': 'bg-amber-100 text-amber-800',
                            'yt-test-starting': 'bg-amber-100 text-amber-800',
                            'starting': 'bg-amber-100 text-amber-800',
                            'stale': 'bg-amber-100 text-amber-800',
                            'idle': 'bg-gray-100 text-gray-700',
                            'error': 'bg-red-100 text-red-800',
                            'yt-complete': 'bg-slate-200 text-slate-800',
                            'yt-revoked': 'bg-red-100 text-red-800',
                            'unknown': 'bg-gray-100 text-gray-700',
                        }[status] || 'bg-gray-100 text-gray-700';
                    },
                    copyShare(t) {
                        if (!t.share_url) return;
                        navigator.clipboard.writeText(t.share_url).then(() => {
                            window.dispatchEvent(new CustomEvent('restream-banner-copied', { detail: { id: t.id } }));
                        });
                    },
                }));

                function formatEndsIn(endsAtIso) {
                    if (!endsAtIso) return '—';
                    const target = new Date(endsAtIso).getTime();
                    const now = Date.now();
                    const diff = target - now;
                    if (diff <= 0) return 'Finalizado';
                    const totalSec = Math.floor(diff / 1000);
                    const h = Math.floor(totalSec / 3600);
                    const m = Math.floor((totalSec % 3600) / 60);
                    const s = totalSec % 60;
                    if (h > 0) return `Termina en ${h}h ${String(m).padStart(2,'0')}m`;
                    if (m > 0) return `Termina en ${m}m ${String(s).padStart(2,'0')}s`;
                    return `Termina en ${s}s`;
                }
                window.formatEndsIn = formatEndsIn;
            });
        </script>
    @endpush
@endonce

<div x-data="restreamLiveBanner(window.__restreamBannerTargets || [])"
     x-init="window.__restreamBannerTargets = (window.__restreamBannerTargets || []); $watch('active', (v) => {})">
    <template x-if="active.length > 0">
        <div class="mb-5 rounded-xl border-2 border-emerald-300 bg-gradient-to-br from-emerald-50 via-white to-emerald-50 shadow-sm overflow-hidden">
            <div class="px-4 py-2 bg-emerald-100/60 border-b border-emerald-200 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Emitiendo ahora</span>
            </div>
            <div class="divide-y divide-emerald-100">
                <template x-for="t in active" :key="t.id">
                    <div class="px-4 py-3 flex flex-wrap items-center gap-3">
                        <span class="px-2 py-0.5 text-xs rounded font-medium capitalize bg-gray-100 text-gray-800" x-text="t.platform"></span>
                        <span class="font-semibold text-gray-800" x-text="t.name"></span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs rounded"
                              :class="badgeClass(t.effective_status)"
                              x-text="labelFor(t.effective_status)"></span>
                        <span class="ml-auto font-mono text-sm text-emerald-900"
                              :data-countdown="t.next_ends_at || ''"
                              :data-ends-at="t.next_ends_at || ''"
                              x-text="window.formatEndsIn ? window.formatEndsIn(t.next_ends_at) : (t.next_ends_at || '—')"></span>
                        <template x-if="t.share_url">
                            <button type="button"
                                    @click="copyShare(t)"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded bg-emerald-600 text-white hover:bg-emerald-700">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                Copiar link
                            </button>
                        </template>
                        <template x-if="t.share_url">
                            <a :href="t.share_url" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded bg-red-600 text-white hover:bg-red-700">
                                Abrir YouTube
                            </a>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>