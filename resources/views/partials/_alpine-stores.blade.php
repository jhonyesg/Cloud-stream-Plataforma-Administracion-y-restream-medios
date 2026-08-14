@once
document.addEventListener('alpine:init', () => {
    // Contract: this is the single source of truth for Alpine stores shared
    // across the admin and client layouts. Do NOT register these stores
    // anywhere else. See openspec/specs/modal-store/spec.md.
    window.Alpine.store('modals', {
        // Stack entries are objects of shape: { name: string, payload: object, errors: object }.
        // Each entry owns its own payload and errors for its entire lifetime, including
        // when other modals are pushed on top. Do not mutate this.stack directly —
        // go through open() / close() / closeAll(), each of which re-projects the
        // mirrors via _syncTop().
        stack: [],
        // Mirrors of the top entry, exposed for backward compatibility with
        // existing consumers (the 7+ modal components that read $store.modals.payload
        // and $store.modals.errors directly).
        current: null,
        payload: {},
        errors: {},

        open(name, payload = {}) {
            const existing = this.stack.find(e => e.name === name);
            if (existing) {
                this.stack.splice(this.stack.indexOf(existing), 1);
            }
            this.stack.push({ name, payload, errors: {} });
            this._syncTop();
            document.body.classList.add('overflow-y-hidden');
        },

        close() {
            if (this.stack.length === 0) return;
            this.stack.pop();
            this._syncTop();
            if (this.stack.length === 0) {
                document.body.classList.remove('overflow-y-hidden');
            }
        },

        closeAll() {
            this.stack = [];
            this._syncTop();
            document.body.classList.remove('overflow-y-hidden');
        },

        has(name) {
            return this.stack.some(e => e.name === name);
        },

        // Single sync point between `stack` and the exposed mirrors.
        // Do NOT call from outside; mutations to `stack` MUST go through
        // open()/close()/closeAll() so this is invoked.
        _syncTop() {
            const top = this.stack[this.stack.length - 1] || null;
            this.current = top ? top.name : null;
            this.payload = top ? top.payload : {};
            this.errors = top ? top.errors : {};
        },
    });

    window.Alpine.store('mediaSelection', {
        ids: new Set(),
        enabled: false,
        get count() { return this.ids.size; },
        get list() { return Array.from(this.ids); },
        has(id) { return this.ids.has(id); },
        add(id) { this.ids.add(id); },
        remove(id) { this.ids.delete(id); },
        toggle(id) {
            if (this.ids.has(id)) this.ids.delete(id); else this.ids.add(id);
        },
        addMany(arr) { for (const id of arr) this.ids.add(id); },
        clear() { this.ids.clear(); },
        exit() { this.ids.clear(); this.enabled = false; },
        reset() { this.ids.clear(); },
    });

    window.Alpine.store('schedulerDialog', {
        confirm: null,
        alert: null,
        promptChoice: null,
        successToast: { show: false, message: '' },
        errorToast: { show: false, message: '' },
        _successTimer: null,
        _errorTimer: null,

        askConfirm(title, message, callback) {
            this.confirm = { title, message, callback };
        },
        closeConfirm(result) {
            if (this.confirm && this.confirm.callback) this.confirm.callback(result);
            this.confirm = null;
        },
        askAlert(message) {
            this.alert = { message };
        },
        closeAlert() {
            this.alert = null;
        },
        askPromptChoice(title, options, callback) {
            this.promptChoice = { title, options, callback };
        },
        closePromptChoice(value) {
            if (this.promptChoice && this.promptChoice.callback) this.promptChoice.callback(value);
            this.promptChoice = null;
        },
        flashSuccess(message) {
            if (this._successTimer) clearTimeout(this._successTimer);
            this.successToast = { show: true, message: message };
            var self = this;
            this._successTimer = setTimeout(function () { self.successToast = { show: false, message: '' }; }, 3000);
        },
        flashError(message) {
            if (this._errorTimer) clearTimeout(this._errorTimer);
            this.errorToast = { show: true, message: message };
            var self = this;
            this._errorTimer = setTimeout(function () { self.errorToast = { show: false, message: '' }; }, 4000);
        },
    });
});
@endonce
