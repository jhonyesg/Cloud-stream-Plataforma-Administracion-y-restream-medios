<script>
    window.schedulerDialogStore = function () {
        return window.Alpine && window.Alpine.store ? window.Alpine.store('schedulerDialog') : null;
    };
</script>

{{-- Confirm dialog --}}
<div x-show="$store.schedulerDialog.confirm" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[80] bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div @click.outside="$store.schedulerDialog.closeConfirm(false)"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full ring-1 ring-gray-900/5">
        <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900" x-text="$store.schedulerDialog.confirm?.title"></h3>
                <p class="text-sm text-gray-600 mt-1" x-text="$store.schedulerDialog.confirm?.message"></p>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" @click="$store.schedulerDialog.closeConfirm(false)"
                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                Cancelar
            </button>
            <button type="button" @click="$store.schedulerDialog.closeConfirm(true)"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-500 transition">
                Confirmar
            </button>
        </div>
    </div>
</div>

{{-- Alert dialog --}}
<div x-show="$store.schedulerDialog.alert" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     @click.outside="$store.schedulerDialog.closeAlert()"
     class="fixed inset-0 z-[80] bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full ring-1 ring-gray-900/5">
        <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-600 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm text-gray-700 flex-1" x-text="$store.schedulerDialog.alert?.message"></p>
        </div>
        <div class="flex justify-end">
            <button type="button" @click="$store.schedulerDialog.closeAlert()"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-500 transition">
                Aceptar
            </button>
        </div>
    </div>
</div>

{{-- Prompt choice dialog (for Cambiar playlist) --}}
<div x-show="$store.schedulerDialog.promptChoice" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     @click.outside="$store.schedulerDialog.closePromptChoice(null)"
     class="fixed inset-0 z-[80] bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full ring-1 ring-gray-900/5">
        <h3 class="text-base font-bold text-gray-900 mb-1" x-text="$store.schedulerDialog.promptChoice?.title"></h3>
        <p class="text-xs text-gray-500 mb-4">Selecciona una playlist o quita la asignación</p>
        <div class="space-y-2 max-h-80 overflow-y-auto">
            <template x-for="(opt, idx) in $store.schedulerDialog.promptChoice?.options || []" :key="opt.value">
                <button type="button" @click="$store.schedulerDialog.closePromptChoice(opt.value)"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg border border-gray-200 hover:border-teal-500 hover:bg-teal-50 transition text-left">
                    <span class="text-sm font-medium text-gray-900" x-text="opt.label"></span>
                    <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </template>
            <button type="button" @click="$store.schedulerDialog.closePromptChoice(null)"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg border-2 border-dashed border-gray-300 hover:border-red-400 hover:bg-red-50 transition text-left">
                <span class="text-sm font-bold text-red-600">— Quitar playlist —</span>
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
            </button>
        </div>
        <div class="flex justify-end mt-4">
            <button type="button" @click="$store.schedulerDialog.closePromptChoice(null)"
                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                Cancelar
            </button>
        </div>
    </div>
</div>

{{-- Success toast --}}
<div x-show="$store.schedulerDialog.successToast.show" x-cloak
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-x-4"
     x-transition:enter-end="opacity-100 translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-x-0"
     x-transition:leave-end="opacity-0 translate-x-4"
     class="fixed bottom-6 right-6 z-[70]">
    <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl shadow-2xl px-5 py-3.5 flex items-center gap-3 min-w-[260px]">
        <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <span class="text-sm font-semibold" x-text="$store.schedulerDialog.successToast.message"></span>
    </div>
</div>

{{-- Error toast --}}
<div x-show="$store.schedulerDialog.errorToast.show" x-cloak
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-x-4"
     x-transition:enter-end="opacity-100 translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-x-0"
     x-transition:leave-end="opacity-0 translate-x-4"
     class="fixed bottom-6 right-6 z-[70]">
    <div class="bg-gradient-to-r from-red-500 to-red-700 text-white rounded-xl shadow-2xl px-5 py-3.5 flex items-center gap-3 min-w-[260px]">
        <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
        <span class="text-sm font-semibold" x-text="$store.schedulerDialog.errorToast.message"></span>
    </div>
</div>
