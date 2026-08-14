@props([
    'name' => '',
    'label' => null,
    'color' => 'indigo',
    'required' => false,
    'autocomplete' => null,
    'placeholder' => null,
    'value' => null,
])

@php
    $palette = [
        'indigo'  => ['ring' => 'focus:ring-indigo-500/20',  'border' => 'focus:border-indigo-500',  'icon' => 'hover:text-indigo-600'],
        'emerald' => ['ring' => 'focus:ring-emerald-500/20', 'border' => 'focus:border-emerald-500', 'icon' => 'hover:text-emerald-600'],
        'amber'   => ['ring' => 'focus:ring-amber-500/20',   'border' => 'focus:border-amber-500',   'icon' => 'hover:text-amber-600'],
        'rose'    => ['ring' => 'focus:ring-rose-500/20',    'border' => 'focus:border-rose-500',    'icon' => 'hover:text-rose-600'],
        'sky'     => ['ring' => 'focus:ring-sky-500/20',     'border' => 'focus:border-sky-500',     'icon' => 'hover:text-sky-600'],
    ];
    $c = $palette[$color] ?? $palette['indigo'];
@endphp

<div x-data="{ show: false }">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}
            @if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div class="relative">
        <input
            :type="show ? 'text' : 'password'"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if($required) required @endif
            @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->except(['class'])->merge(['class' => "w-full px-3 py-2.5 pr-11 border border-gray-300 rounded-lg shadow-sm {$c['border']} focus:ring-2 {$c['ring']} transition"]) }}
        >

        <button
            type="button"
            @click="show = !show"
            :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            :aria-pressed="show.toString()"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 {{ $c['icon'] }} transition"
        >
            <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <svg x-show="show" style="display:none" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
            </svg>
        </button>
    </div>

    <p
        x-show="$store.modals && $store.modals.errors && $store.modals.errors.{{ $name }}"
        x-text="$store.modals && $store.modals.errors && $store.modals.errors.{{ $name }} ? ($store.modals.errors.{{ $name }}[0] || '') : ''"
        class="mt-1 text-sm text-red-600"
    ></p>
    {{ $slot }}
</div>