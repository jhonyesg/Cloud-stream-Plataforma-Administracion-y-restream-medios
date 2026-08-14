@props([
    'name',
    'title' => '',
    'subtitle' => '',
    'maxWidth' => '2xl',
    'icon' => null,
    'iconBg' => 'bg-indigo-100',
    'iconColor' => 'text-indigo-600',
])

@php
    $maxWidthClass = match($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
        '4xl' => 'sm:max-w-4xl',
        default => 'sm:max-w-2xl',
    };
@endphp

<div
    x-data="{
        modalName: @js($name),
        show: false,
        get isTop() {
            if (!$store.modals || !$store.modals.stack || $store.modals.stack.length === 0) return false;
            const top = $store.modals.stack[$store.modals.stack.length - 1];
            return top && top.name === this.modalName;
        },
        get isOpen() {
            if (!$store.modals || !$store.modals.stack) return false;
            return $store.modals.stack.some(e => e.name === this.modalName);
        },
        get zIndex() {
            if (!$store.modals || !$store.modals.stack) return 50;
            const idx = $store.modals.stack.findIndex(e => e.name === this.modalName);
            if (idx === -1) return 50;
            return 50 + idx * 10;
        },
        init() {
            this.$watch('isOpen', async (open) => {
                if (!open) {
                    this.show = false;
                    return;
                }
                if (this.isTop) {
                    this.show = true;
                    document.body.classList.add('overflow-y-hidden');
                    this.$nextTick(() => {
                        const first = this.$el.querySelector('input:not([type=hidden]), textarea, select, button');
                        if (first) first.focus({ preventScroll: true });
                    });
                } else {
                    this.show = false;
                }
            });
            this.$watch('isTop', (top) => {
                if (!top) {
                    this.show = false;
                } else if (this.isOpen) {
                    this.show = true;
                    document.body.classList.add('overflow-y-hidden');
                }
            });
        }
    }"
    x-on:keydown.escape.window="if (isTop) Alpine.store('modals').close()"
    x-show="show"
    :style="{ zIndex: zIndex, display: show ? '' : 'none' }"
    class="fixed inset-0 overflow-y-auto modal-open"
>
    {{-- Backdrop with blur --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="Alpine.store('modals').close()"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"
    ></div>

    {{-- Centering wrapper --}}
    <div class="fixed inset-0 flex items-center justify-center p-4 sm:p-6 pointer-events-none">
        <div
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-2 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-2 sm:scale-95"
            @click.stop
            class="relative w-full {{ $maxWidthClass }} bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 pointer-events-auto flex flex-col max-h-[calc(100vh-2rem)] overflow-hidden"
        >
            {{-- Header --}}
            <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4 shrink-0 bg-gradient-to-b from-gray-50/80 to-white">
                <div class="flex items-start gap-3 min-w-0">
                    @if($icon)
                        <div class="w-10 h-10 rounded-xl {{ $iconBg }} {{ $iconColor }} flex items-center justify-center shrink-0">
                            {!! $icon !!}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900 leading-tight">{{ $title }}</h3>
                        @if($subtitle)
                            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
                        @endif
                    </div>
                </div>
                <button
                    type="button"
                    @click="Alpine.store('modals').close()"
                    class="shrink-0 -mt-1 -mr-1 p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition"
                    aria-label="Cerrar"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body + Footer go here, in one consumer x-data scope --}}
            {{ $slot }}
        </div>
    </div>
</div>