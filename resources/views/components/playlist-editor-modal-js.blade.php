<script>
window.playlistEditor = () => ({
    playlistId: null,
    name: '',
    items: [],
    library: [],
    search: '',
    filter: 'all',

    isPlaying: false,
    speed: 1,
    playheadPosition: 0,
    totalDuration: 0,
    loopCount: 0,

    dropTargetIdx: null,
    dragSource: null,
    _rafId: null,
    _lastTick: null,

    previewItem: null,
    previewLoading: false,
    previewCurrentTime: 0,
    previewItemDuration: 0,
    isPreviewPlaying: false,
    previewMuted: false,
    previewVolume: 0.8,
    activePlayer: 'A',
    _lastActiveIdx: -1,
    _suppressPreviewSync: false,
    _readyForNext: false,

    virtualScreen: null,
    showLogoOverlay: true,

    toast: {
        show: false,
        message: '',
        saved: false,
        _callback: null,
        open(msg, callback) {
            this.message = msg;
            this._callback = callback;
            this.show = true;
        },
        confirm() {
            if (this._callback) this._callback();
            this.show = false;
            this._callback = null;
        },
        cancel() {
            this.show = false;
            this._callback = null;
        },
    },

    init(dispatch) {
        Alpine.store('playlistEditor', this);
        window.toast = {
            confirm: (msg, cb) => this.toast.open(msg, cb),
            success: (msg) => { this.toast.saved = true; setTimeout(() => this.toast.saved = false, 2000); },
        };
        this.$watch('items', () => {
            this.recalcTotals();
            const idx = this.activeItemIndex();
            if (idx !== this._lastActiveIdx) {
                this._syncPreviewToActive(idx, false);
            }
        });
    },

    get filteredLibrary() {
        const q = this.search.toLowerCase();
        return this.library.filter(m => {
            if (this.filter !== 'all' && m.kind !== this.filter) return false;
            if (q && !m.filename.toLowerCase().includes(q)) return false;
            return true;
        });
    },

    get statsText() {
        if (this.items.length === 0) return 'Playlist vacía';
        return this.items.length + ' items · ~' + this.loopCount.toFixed(1) + ' loops al día';
    },

    formatDur(sec) {
        sec = parseInt(sec) || 0;
        if (sec < 60) return sec + 's';
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return m + 'min' + (s > 0 ? ' ' + s + 's' : '');
    },

    formatClock(sec) {
        sec = parseInt(sec) || 0;
        if (sec < 0) return '—';
        sec = Math.floor(sec % 86400);
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    },

    formatTotalClock(sec) {
        sec = parseInt(sec) || 0;
        if (sec < 0) return '—';
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    },

    get timelineBlocksHtml() {
        if (this.items.length === 0) return '';
        const daySec = 86400;
        const active = this.activeItemIndex();
        const pos = this.playheadPosition % Math.max(1, this.totalDuration);
        let html = '';

        const blocks = this.items.map((item, idx) => ({
            item, idx,
            start: this.cumulativeStart(idx),
            end: this.cumulativeEnd(idx),
        })).filter(b => b.end > b.start);

        if (blocks.length === 0) return '';

        for (let loop = 0; loop < Math.min(4, Math.ceil(daySec / Math.max(1, this.totalDuration)) + 1); loop++) {
            const loopOffset = loop * Math.max(1, this.totalDuration);
            if (blocks[0].start + loopOffset >= daySec) break;
            for (const b of blocks) {
                const startSec = b.start + loopOffset;
                const endSec = b.end + loopOffset;
                if (startSec >= daySec) continue;
                const visualEnd = Math.min(endSec, daySec);
                if (visualEnd <= 0) continue;
                const leftPct = (startSec / daySec) * 100;
                const widthPct = ((visualEnd - startSec) / daySec) * 100;
                const isAd = b.item.kind === 'ad';
                const showLabel = (loop === 0);
                const labelText = this.formatClock(b.start);
                const isActive = (loop === 0 && active === b.idx);
                const bgColor = isAd
                    ? 'background-color: rgba(249, 115, 22, 0.85); border-left: 1px solid rgba(194, 65, 12, 0.9);'
                    : (b.item.split_from_id ? 'background-color: rgba(99, 102, 241, 0.85); border-left: 1px solid rgba(67, 56, 202, 0.9);'
                    : 'background-color: rgba(59, 130, 246, 0.85); border-left: 1px solid rgba(37, 99, 235, 0.9);');
                const activeRing = isActive ? 'outline: 2px solid #14b8a6; outline-offset: -2px; z-index: 5;' : '';
                const blockTitle = showLabel ? b.item.filename : '';

                html += `<div class="absolute top-1 bottom-1 rounded-sm overflow-hidden flex items-center px-1 text-[9px] font-medium text-white truncate pointer-events-none" style="left: ${leftPct}%; width: ${widthPct}%; ${bgColor} ${activeRing}" title="${this.escapeHtml(blockTitle)}">${blockTitle}</div>`;
                if (showLabel) {
                    html += `<div class="absolute text-[9px] font-mono font-bold text-gray-700 pointer-events-none whitespace-nowrap" style="left: ${leftPct}%; top: 1px; transform: translateX(-50%); background: rgba(255,255,255,0.9); padding: 0 3px; border-radius: 2px;">${labelText}</div>`;
                }
                if (isActive) {
                    html += `<div class="absolute pointer-events-none" style="left: ${leftPct}%; top: -18px; transform: translateX(-50%); background: #14b8a6; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.2); z-index: 10;">▶ ${this.escapeHtml(b.item.filename)}</div>`;
                }
            }
        }
        return html;
    },

    escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    },

    activeItemIndex() {
        if (this.items.length === 0) return -1;
        const hasSchedule = this.items.some(i => i.start_sec !== null && i.start_sec !== undefined);
        const pos = this.playheadPosition % Math.max(1, this.totalDuration);

        if (hasSchedule) {
            let bestIdx = -1;
            let bestStart = -1;
            for (let i = 0; i < this.items.length; i++) {
                const s = this.cumulativeStart(i);
                const e = this.cumulativeEnd(i);
                if (pos >= s && pos < e) return i;
                if (s <= pos && s > bestStart) { bestStart = s; bestIdx = i; }
            }
            return bestIdx;
        }

        let cumulative = 0;
        for (let i = 0; i < this.items.length; i++) {
            const dur = parseInt(this.items[i].duration_sec) || 0;
            if (dur === 0) continue;
            if (pos >= cumulative && pos < cumulative + dur) return i;
            cumulative += dur;
        }
        return -1;
    },

    _activeVideo() {
        return this.$refs['previewVideo' + this.activePlayer];
    },
    _inactiveVideo() {
        return this.$refs['previewVideo' + (this.activePlayer === 'A' ? 'B' : 'A')];
    },

    _clearPreview() {
        this.previewItem = null;
        this.previewLoading = false;
        this.previewCurrentTime = 0;
        this.previewItemDuration = 0;
        this.isPreviewPlaying = false;
        this._lastActiveIdx = -1;
        for (const p of ['A', 'B']) {
            const v = this.$refs['previewVideo' + p];
            if (v) { try { v.pause(); v.removeAttribute('src'); v.load(); } catch (e) {} }
        }
    },

    _loadIntoPlayer(player, item, offsetSec, autoplay) {
        const v = this.$refs['previewVideo' + player];
        if (!v || !item) return;
        this._suppressPreviewSync = (player !== this.activePlayer);
        try {
            v.muted = this.previewMuted;
            v.volume = this.previewVolume;
            v.src = '/api/media-items/' + item.media_item_id + '/stream';
            v.load();
        } catch (e) {}
        const sourceStart = parseFloat(item.cue_in_sec) || 0;
        const onReady = () => {
            try {
                const seekTo = sourceStart + Math.max(0, offsetSec);
                const maxSeek = (v.duration || seekTo) - 0.2;
                v.currentTime = Math.max(sourceStart, Math.min(seekTo, maxSeek));
            } catch (e) {}
            if (autoplay) {
                const p = v.play();
                if (p && typeof p.then === 'function') p.catch(() => {});
            }
            v.removeEventListener('loadedmetadata', onReady);
        };
        v.addEventListener('loadedmetadata', onReady);
    },

    _swapToPlayer(player) {
        const other = player === 'A' ? 'B' : 'A';
        const newActive = this.$refs['previewVideo' + player];
        const oldActive = this.$refs['previewVideo' + other];
        if (oldActive) { try { oldActive.pause(); } catch (e) {} }
        if (newActive) {
            newActive.muted = this.previewMuted;
            newActive.volume = this.previewVolume;
        }
        this.activePlayer = player;
    },

    _setActiveItem(idx, offsetSec, autoplay) {
        if (idx < 0 || idx >= this.items.length) {
            this._clearPreview();
            return;
        }
        const item = this.items[idx];
        const itemDur = parseInt(item.duration_sec) || 0;
        if (this._lastActiveIdx === idx && this.previewItem && this.previewItem.media_item_id === item.media_item_id) {
            const v = this._activeVideo();
            if (v && offsetSec > 0) {
                try { v.currentTime = Math.min(offsetSec, (v.duration || offsetSec) - 0.1); } catch (e) {}
                if (autoplay && v.paused) { const p = v.play(); if (p && p.then) p.catch(() => {}); }
            }
            this._suppressPreviewSync = false;
            return;
        }
        this.previewItem = item;
        this.previewItemDuration = itemDur;
        this.previewLoading = true;
        this.previewCurrentTime = offsetSec;
        this._lastActiveIdx = idx;
        const nextPlayer = this.activePlayer === 'A' ? 'B' : 'A';
        this._loadIntoPlayer(nextPlayer, item, offsetSec, autoplay);
        const onCanPlay = () => {
            this.previewLoading = false;
            this._suppressPreviewSync = false;
            const nv = this.$refs['previewVideo' + nextPlayer];
            if (nv) nv.removeEventListener('canplay', onCanPlay);
            this._swapToPlayer(nextPlayer);
            this.isPreviewPlaying = !!autoplay;
        };
        const nv = this.$refs['previewVideo' + nextPlayer];
        if (nv) nv.addEventListener('canplay', onCanPlay);
        if (autoplay) {
            const fallbackTimer = setTimeout(() => {
                if (this.previewLoading && this._lastActiveIdx === idx) {
                    this.previewLoading = false;
                    this._suppressPreviewSync = false;
                    this._swapToPlayer(nextPlayer);
                    this.isPreviewPlaying = true;
                }
            }, 1500);
            const nv2 = this.$refs['previewVideo' + nextPlayer];
            if (nv2) nv2.addEventListener('canplay', () => clearTimeout(fallbackTimer), { once: true });
        }
    },

    _syncPreviewToActive(idx, autoplay) {
        if (idx < 0 || idx >= this.items.length) {
            this._clearPreview();
            return;
        }
        const secInLoop = this.playheadPosition % Math.max(1, this.totalDuration);
        const offset = Math.max(0, secInLoop - this.cumulativeStart(idx));
        this._setActiveItem(idx, offset, autoplay);
    },

    previewItemByIndex(idx) {
        if (idx < 0 || idx >= this.items.length) return;
        if (this.totalDuration > 0) {
            this.playheadPosition = this.cumulativeStart(idx);
        }
        this._setActiveItem(idx, 0, true);
    },

    prevPlaylistItem() {
        if (this._lastActiveIdx <= 0) return;
        const idx = this._lastActiveIdx - 1;
        if (this.totalDuration > 0) this.playheadPosition = this.cumulativeStart(idx);
        this._setActiveItem(idx, 0, true);
    },

    nextPlaylistItem() {
        if (this._lastActiveIdx < 0 || this._lastActiveIdx >= this.items.length - 1) return;
        const idx = this._lastActiveIdx + 1;
        if (this.totalDuration > 0) this.playheadPosition = this.cumulativeStart(idx);
        this._setActiveItem(idx, 0, true);
    },

togglePreviewPlay() {
        if (this._lastActiveIdx < 0) return;
        const v = this._activeVideo();
        if (!v) return;
        if (v.paused || v.ended) {
            if (v.ended) { try { v.currentTime = 0; } catch (e) {} }
            this.speed = 1;
            if (!this.isPlaying) {
                this.isPlaying = true;
                this._lastTick = performance.now();
                const tick = (now) => {
                    if (!this.isPlaying) return;
                    if (this.speed === 1) {
                        const vv = this._activeVideo();
                        if (vv && !vv.paused && vv.duration) {
                            const idx2 = this._lastActiveIdx;
                            if (idx2 >= 0) {
                                const it2 = this.items[idx2];
                                const ss = parseFloat(it2?.cue_in_sec) || 0;
                                const within = (vv.currentTime || 0) - ss;
                                this.playheadPosition = this.cumulativeStart(idx2) + Math.max(0, within);
                            }
                        }
                    } else {
                        const dt = (now - this._lastTick) / 1000;
                        this._lastTick = now;
                        this.playheadPosition += dt * this.speed;
                        const newIdx = this.activeItemIndex();
                        if (newIdx !== this._lastActiveIdx && newIdx >= 0) {
                            this._setActiveItem(newIdx, 0, false);
                        }
                    }
                    this._rafId = requestAnimationFrame(tick);
                };
                this._rafId = requestAnimationFrame(tick);
            }
            const p = v.play();
            if (p && typeof p.then === 'function') p.catch(() => {});
            this.isPreviewPlaying = true;
        } else {
            v.pause();
            this.isPreviewPlaying = false;
            this.isPlaying = false;
            if (this._rafId) cancelAnimationFrame(this._rafId);
        }
    },

    stopPreview() {
        const v = this._activeVideo();
        if (!v) return;
        try { v.pause(); v.currentTime = 0; } catch (e) {}
        this.previewCurrentTime = 0;
        this.isPreviewPlaying = false;
    },

    seekPreview(deltaSec) {
        const v = this._activeVideo();
        if (!v || this._lastActiveIdx < 0) return;
        try {
            const dur = v.duration || this.previewItemDuration || 0;
            v.currentTime = Math.max(0, Math.min(dur, (v.currentTime || 0) + deltaSec));
            this.previewCurrentTime = v.currentTime;
            if (this.totalDuration > 0) {
                this.playheadPosition = this.cumulativeStart(this._lastActiveIdx) + (v.currentTime || 0);
            }
        } catch (e) {}
    },

    seekPreviewTo(sec) {
        const v = this._activeVideo();
        if (!v || this._lastActiveIdx < 0) return;
        try {
            v.currentTime = Math.max(0, sec);
            this.previewCurrentTime = v.currentTime;
            if (this.totalDuration > 0) {
                this.playheadPosition = this.cumulativeStart(this._lastActiveIdx) + sec;
            }
        } catch (e) {}
    },

    togglePreviewMute() {
        this.previewMuted = !this.previewMuted;
        for (const p of ['A', 'B']) {
            const v = this.$refs['previewVideo' + p];
            if (v) v.muted = this.previewMuted;
        }
    },

    toggleFullscreen() {
        const container = this.$refs.previewContainer;
        if (!container) return;
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        } else {
            container.requestFullscreen().catch(() => {});
        }
    },

    onVolumeChange() {
        for (const p of ['A', 'B']) {
            const v = this.$refs['previewVideo' + p];
            if (v) v.volume = this.previewVolume;
        }
    },

    onPreviewLoaded() {
        this.previewLoading = false;
        this._suppressPreviewSync = false;
    },

    onPreviewTimeUpdate() {
        const v = this._activeVideo();
        if (!v) return;
        this.previewCurrentTime = v.currentTime || 0;
        if (this._suppressPreviewSync && this.previewLoading) return;
        if ((this.isPlaying || this.isPreviewPlaying) && this.speed === 1 && this._lastActiveIdx >= 0 && !v.paused) {
            const idx = this._lastActiveIdx;
            const item = this.items[idx];
            const sourceStart = parseFloat(item?.cue_in_sec) || 0;
            const itemDur = parseFloat(item?.duration_sec) || 0;
            const effectiveCurrent = Math.max(0, (v.currentTime || 0) - sourceStart);
            if (itemDur > 0 && effectiveCurrent >= itemDur - 0.2) {
                const nextIdx = this._findNextItemIdx(idx);
                try { v.pause(); } catch (e) {}
                if (nextIdx >= 0) {
                    this.playheadPosition = this.cumulativeStart(nextIdx);
                    this._setActiveItem(nextIdx, 0, true);
                } else {
                    this.playheadPosition = this.cumulativeStart(idx) + itemDur;
                    this.isPreviewPlaying = false;
                    this.isPlaying = false;
                }
                return;
            }
            this.playheadPosition = this.cumulativeStart(idx) + effectiveCurrent;
        }
    },

    onPreviewEnded() {
        if (this._lastActiveIdx < 0) return;
        if ((this.isPlaying || this.isPreviewPlaying) && this.speed === 1) {
            const next = this._findNextItemIdx(this._lastActiveIdx);
            if (next >= 0) {
                this.playheadPosition = this.cumulativeStart(next);
                this._setActiveItem(next, 0, true);
            } else {
                this.isPreviewPlaying = false;
            }
        } else {
            this.isPreviewPlaying = false;
        }
    },

    seekTimelineFromEvent(e) {
        if (this.totalDuration === 0) return;
        const el = this.$refs.timelineEl;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        const x = Math.max(0, Math.min(rect.width, e.clientX - rect.left));
        const pct = x / rect.width;
        const daySec = 86400;
        this.playheadPosition = Math.floor(pct * daySec);
        const idx = this.activeItemIndex();
        if (idx >= 0) {
            this.speed = 1;
            this._syncPreviewToActive(idx, true);
        }
    },

    cumulativeStart(idx) {
        if (idx < 0 || idx >= this.items.length) return 0;
        return parseInt(this.items[idx]._scheduleStart) || 0;
    },

    cumulativeEnd(idx) {
        return this.cumulativeStart(idx) + (parseInt(this.items[idx]?.duration_sec) || 0);
    },

    _findNextItemIdx(idx) {
        const currentEnd = this.cumulativeEnd(idx);
        let bestIdx = -1;
        let bestStart = Infinity;
        for (let i = 0; i < this.items.length; i++) {
            if (i === idx) continue;
            const s = this.cumulativeStart(i);
            if (s >= currentEnd && s < bestStart) {
                bestStart = s;
                bestIdx = i;
            }
        }
        return bestIdx;
    },

    formatStartLabel(idx) {
        const sec = this.cumulativeStart(idx);
        const h = String(Math.floor(sec / 3600)).padStart(2, '0');
        const m = String(Math.floor((sec % 3600) / 60)).padStart(2, '0');
        const s = String(sec % 60).padStart(2, '0');
        return `${h}:${m}:${s}`;
    },

    get playheadPositionPercent() {
        if (this.totalDuration === 0) return 0;
        const secInDay = this.playheadPosition % 86400;
        const totalDurationInDay = this.totalDuration;
        const dayDuration = 86400;
        const loopsPerDay = Math.max(1, Math.floor(dayDuration / totalDurationInDay));
        const loopDuration = totalDurationInDay;
        const currentLoop = Math.floor(secInDay / loopDuration);
        const secInLoop = secInDay - (currentLoop * loopDuration);
        return (secInDay / dayDuration) * 100;
    },

    recalcTotals() {
        let maxEnd = 0;
        for (let i = 0; i < this.items.length; i++) {
            maxEnd = Math.max(maxEnd, this.cumulativeEnd(i));
        }
        this.totalDuration = maxEnd;
        this.loopCount = this.totalDuration > 0 ? 86400 / this.totalDuration : 0;
    },

    get exceeds24h() {
        return this.totalDuration > 86400;
    },

    get remainingSecondsInDay() {
        return Math.max(0, 86400 - (this.totalDuration % 86400));
    },

    get canAddMore() {
        if (this.totalDuration === 0) return true;
        return this.totalDuration < 86400;
    },

    itemOverflows(idx) {
        if (this.totalDuration <= 86400) return false;
        const cumulativeStart = this.cumulativeStart(idx);
        const dur = parseInt(this.items[idx].duration_sec) || 0;
        const cumulativeEnd = cumulativeStart + dur;
        return cumulativeEnd > 86400;
    },

async loadPlaylist() {
        const r = await fetch('/api/playlists/' + this.playlistId, { headers: { 'Accept': 'application/json' } });
        if (r.ok) {
            const data = await r.json();
            this.name = data.name;
            const mapped = (data.items || []).map(i => {
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
            const byPos = mapped.sort((a, b) => a.position - b.position);
            let cursor = 0;
            for (const it of byPos) {
                if (it.start_sec !== null && it.start_sec !== undefined) {
                    cursor = Math.max(0, parseInt(it.start_sec) || 0);
                }
                it._scheduleStart = Math.max(0, cursor);
                cursor += it.duration_sec;
            }
            this.items = byPos.sort((a, b) => {
                const sa = Math.max(0, a._scheduleStart || 0);
                const sb = Math.max(0, b._scheduleStart || 0);
                if (sa !== sb) return sa - sb;
                const aExplicit = a.start_sec !== null && a.start_sec !== undefined;
                const bExplicit = b.start_sec !== null && b.start_sec !== undefined;
                if (aExplicit && !bExplicit) return -1;
                if (!aExplicit && bExplicit) return 1;
                return (a.position || 0) - (b.position || 0);
            });
            this.recalcTotals();
        }
    },

    async rename() {
        if (!this.playlistId || !this.name.trim()) return;
        try {
            await fetch('/api/playlists/' + this.playlistId, {
                method: 'PUT',
                headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ name: this.name.trim() }),
            });
        } catch(e) {}
    },

    async addItem(m, position = null) {
        if (!this.playlistId) return;
        if (!this.canAddMore && !this.itemInPlaylist(m.id)) {
            alert('La playlist ya excede 24 horas. Elimina items para agregar más.');
            return;
        }
        try {
            const r = await fetch('/api/playlists/' + this.playlistId + '/items', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ media_item_id: m.id }),
            });
            if (r.ok) {
                const item = await r.json();
                this.items.push({
                    id: item.id,
                    media_item_id: m.id,
                    filename: m.filename,
                    kind: m.kind,
                    duration_sec: m.duration_sec,
                    position: item.position,
                });
                this.recalcTotals();
                if (position !== null) {
                    await this.moveItemTo(position, this.items.length - 1);
                }
            }
        } catch(e) { alert('Error al agregar'); }
    },

    confirmRemove(item, idx) {
        this.removeItemById(item.id);
    },

    async removeItemById(itemId) {
        try {
            await fetch('/api/playlists/' + this.playlistId + '/items/' + itemId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
            });
            await this.loadPlaylist();
            this._syncPreviewAfterScheduleChange();
        } catch(e) { alert('Error'); }
    },

    _scheduleStart(idx) {
        const item = this.items[idx];
        if (!item) return 0;
        if (item.start_sec !== undefined && item.start_sec !== null) return parseInt(item.start_sec) || 0;
        let cum = 0;
        for (let i = 0; i < idx; i++) {
            cum += parseInt(this.items[i].duration_sec) || 0;
        }
        return cum;
    },

    _scheduleEnd(idx) {
        return this._scheduleStart(idx) + (parseInt(this.items[idx]?.duration_sec) || 0);
    },

    get scheduleItems() {
        return this.items.map((it, idx) => ({
            id: it.id,
            index: idx,
            start: this._scheduleStart(idx),
            end: this._scheduleEnd(idx),
            filename: it.filename,
            kind: it.kind,
        })).sort((a, b) => a.start - b.start);
    },

    get currentSchedulePos() {
        if (this.totalDuration > 0) {
            return this.playheadPosition % this.totalDuration;
        }
        return this.playheadPosition;
    },

    insertCuePickerOpen: false,
    insertCuePicker: { hh: '08', mm: '00', ss: '00', media_item_id: '' },
    insertCuePickerSubmitting: false,
    insertCuePickerError: '',

    get insertCuePickerSec() {
        const h = parseInt(this.insertCuePicker.hh) || 0;
        const m = parseInt(this.insertCuePicker.mm) || 0;
        const s = parseInt(this.insertCuePicker.ss) || 0;
        return h * 3600 + m * 60 + s;
    },

    get insertCuePickerLabel() {
        const sec = this.insertCuePickerSec;
        const h = String(Math.floor(sec / 3600)).padStart(2, '0');
        const m = String(Math.floor((sec % 3600) / 60)).padStart(2, '0');
        const s = String(sec % 60).padStart(2, '0');
        return `${h}:${m}:${s}`;
    },

    openInsertCuePicker() {
        const pos = Math.min(86400, Math.max(0, Math.round(this.currentSchedulePos)));
        this.insertCuePicker = {
            hh: String(Math.floor(pos / 3600)).padStart(2, '0'),
            mm: String(Math.floor((pos % 3600) / 60)).padStart(2, '0'),
            ss: String(pos % 60).padStart(2, '0'),
            media_item_id: '',
        };
        this.insertCuePickerError = '';
        this.insertCuePickerOpen = true;
    },

    async submitInsertCue() {
        if (!this.insertCuePicker.media_item_id) {
            this.insertCuePickerError = 'Selecciona una cuña de la biblioteca.';
            return;
        }
        this.insertCuePickerSubmitting = true;
        this.insertCuePickerError = '';
        try {
            const r = await fetch('/api/playlists/' + this.playlistId + '/cues', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    media_item_id: this.insertCuePicker.media_item_id,
                    at_sec: this.insertCuePickerSec,
                }),
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(data.message || ('HTTP ' + r.status));
            this.insertCuePickerOpen = false;
            window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Cuña insertada a las ' + this.insertCuePickerLabel } }));
            await this.loadPlaylist();
            this._syncPreviewAfterScheduleChange();
        } catch (e) {
            this.insertCuePickerError = e.message || 'Error al insertar';
        } finally {
            this.insertCuePickerSubmitting = false;
        }
    },

    _syncPreviewAfterScheduleChange() {
        const idx = this.activeItemIndex();
        if (idx >= 0) {
            const secInLoop = this.playheadPosition % Math.max(1, this.totalDuration);
            const offset = Math.max(0, secInLoop - this._scheduleStart(idx));
            this._setActiveItem(idx, offset, this.isPreviewPlaying);
        } else if (this.items.length > 0) {
            this._setActiveItem(0, 0, this.isPreviewPlaying);
        }
    },

    async cleanupOrphanSplits() {
        if (!this.playlistId) return;
        try {
            const r = await fetch('/api/playlists/' + this.playlistId + '/cleanup-splits', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(data.message || ('HTTP ' + r.status));
            window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Splits limpiados: ' + (data.merged || 0) } }));
            await this.loadPlaylist();
            this._syncPreviewAfterScheduleChange();
        } catch (e) {
            window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: e.message || 'Error al limpiar splits' } }));
        }
    },

    async resetToSequential() {
        if (!this.playlistId) return;
        if (!confirm('¿Resetear los horarios a secuencial? Se borrará el start_sec de los videos que no formen parte de splits; las cuñas conservarán su horario. Es útil para limpiar datos inconsistentes de operaciones anteriores.')) return;
        try {
            const r = await fetch('/api/playlists/' + this.playlistId + '/reset-sequential', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(data.message || ('HTTP ' + r.status));
            window.dispatchEvent(new CustomEvent('crud-success', { detail: { message: 'Horarios reseteados: ' + (data.reset || 0) + ' items a secuencial' } }));
            await this.loadPlaylist();
            this._syncPreviewAfterScheduleChange();
        } catch (e) {
            window.dispatchEvent(new CustomEvent('crud-error', { detail: { message: e.message || 'Error al resetear' } }));
        }
    },

    itemInPlaylist(mediaItemId) {
        return this.items.some(i => i.media_item_id === mediaItemId);
    },

    async toggleItem(m) {
        if (!this.playlistId) return;
        const existing = this.items.find(i => i.media_item_id === m.id);
        if (existing) {
            await this.removeItemById(existing.id);
        } else {
            await this.addItem(m);
        }
    },

    async moveItem(idx, dir) {
        const newIdx = idx + dir;
        if (newIdx < 0 || newIdx >= this.items.length) return;
        await this.moveItemTo(newIdx, idx);
    },

    async moveItemTo(targetIdx, currentIdx) {
        if (targetIdx === currentIdx) return;
        const items = [...this.items];
        const [moved] = items.splice(currentIdx, 1);
        items.splice(targetIdx, 0, moved);
        this.items = items;
        await this.persistOrder();
    },

    async persistOrder() {
        const order = this.items.map(i => i.id);
        try {
            await fetch('/api/playlists/' + this.playlistId + '/items/reorder', {
                method: 'PUT',
                headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ order }),
            });
        } catch(e) { alert('Error al reordenar'); }
    },

    dragStart(item, e) {
        this.dragSource = { type: 'playlist', idx: this.items.indexOf(item) };
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', 'playlist');
    },

    libraryDragStart(m, e) {
        this.dragSource = { type: 'library', item: m };
        e.dataTransfer.effectAllowed = 'copy';
        e.dataTransfer.setData('text/plain', 'library');
    },

    libraryDragEnd() {
        this.dragSource = null;
        this.dropTargetIdx = null;
    },

    dragEnd() {
        this.dragSource = null;
        this.dropTargetIdx = null;
    },

    dragOver(idx, e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = this.dragSource?.type === 'library' ? 'copy' : 'move';
        this.dropTargetIdx = idx;
    },

    dragLeave(idx) {
        if (this.dropTargetIdx === idx) this.dropTargetIdx = null;
    },

    async dropFromLibrary(targetIdx, e) {
        e.preventDefault();
        if (!this.dragSource) return;

        if (this.dragSource.type === 'library') {
            const m = this.dragSource.item;
            await this.addItem(m, targetIdx);
        } else if (this.dragSource.type === 'playlist') {
            const fromIdx = this.dragSource.idx;
            const adjusted = fromIdx < targetIdx ? targetIdx - 1 : targetIdx;
            if (fromIdx !== adjusted) {
                await this.moveItemTo(adjusted, fromIdx);
            }
        }
        this.dragSource = null;
        this.dropTargetIdx = null;
    },

    togglePlay() {
        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
    },

    play() {
        if (this.totalDuration === 0) return;
        this.isPlaying = true;
        this._lastTick = performance.now();
        const idx = this.activeItemIndex();
        if (idx >= 0) this._syncPreviewToActive(idx, this.speed === 1);
        const tick = (now) => {
            if (!this.isPlaying) return;
            if (this.speed === 1) {
                const v = this._activeVideo();
                if (v && !v.paused && v.duration) {
                    const idx2 = this._lastActiveIdx;
                    if (idx2 >= 0) {
                        this.playheadPosition = this.cumulativeStart(idx2) + (v.currentTime || 0);
                    }
                }
            } else {
                const dt = (now - this._lastTick) / 1000;
                this._lastTick = now;
                this.playheadPosition += dt * this.speed;
                const newIdx = this.activeItemIndex();
                if (newIdx !== this._lastActiveIdx && newIdx >= 0) {
                    this._setActiveItem(newIdx, 0, false);
                }
            }
            this._rafId = requestAnimationFrame(tick);
        };
        this._rafId = requestAnimationFrame(tick);
    },

    pause() {
        this.isPlaying = false;
        if (this._rafId) cancelAnimationFrame(this._rafId);
        const v = this._activeVideo();
        if (v) { try { v.pause(); } catch (e) {} }
        this.isPreviewPlaying = false;
    },

    stop() {
        this.pause();
        this.playheadPosition = 0;
        const idx = this.activeItemIndex();
        if (idx >= 0) this._setActiveItem(idx, 0, false);
    },

    save() {
        this.pause();
        Alpine.store('modals').close();
        window.dispatchEvent(new CustomEvent('playlist-saved'));
        setTimeout(() => window.location.reload(), 300);
    },

    cancel() {
        this.pause();
        Alpine.store('modals').close();
        setTimeout(() => window.location.reload(), 100);
    },
});
</script>