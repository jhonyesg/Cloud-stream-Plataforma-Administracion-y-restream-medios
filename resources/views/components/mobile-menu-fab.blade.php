<button
    type="button"
    x-data="{
        get accent() {
            return (window.appTheme && window.appTheme.btn) ? window.appTheme.btn : 'bg-indigo-600 hover:bg-indigo-500';
        },
        get accentText() {
            return (window.appTheme && window.appTheme.text) ? window.appTheme.text : 'text-white';
        }
    }"
    x-show="$store.mediaSelection ? !$store.mediaSelection.enabled : true"
    @click="sidebarOpen = true"
    aria-label="Abrir menú"
    title="Abrir menú"
    class="md:hidden fixed bottom-6 right-6 z-30 w-14 h-14 rounded-full shadow-2xl ring-1 ring-black/10 active:scale-95 transition-transform flex items-center justify-center text-white hover:brightness-110 focus:outline-none focus:ring-4 focus:ring-white/30"
    :class="accent"
>
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>