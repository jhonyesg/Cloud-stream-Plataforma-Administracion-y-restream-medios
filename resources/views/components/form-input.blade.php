@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'autocomplete' => null,
    'color' => 'indigo',
    'options' => null,
    'placeholder' => null,
    'rows' => 3,
])

@php
    $palette = [
        'indigo'  => ['ring' => 'focus:ring-indigo-500/20',  'border' => 'focus:border-indigo-500'],
        'emerald' => ['ring' => 'focus:ring-emerald-500/20', 'border' => 'focus:border-emerald-500'],
        'amber'   => ['ring' => 'focus:ring-amber-500/20',   'border' => 'focus:border-amber-500'],
        'rose'    => ['ring' => 'focus:ring-rose-500/20',    'border' => 'focus:border-rose-500'],
        'sky'     => ['ring' => 'focus:ring-sky-500/20',     'border' => 'focus:border-sky-500'],
    ];
    $c = $palette[$color] ?? $palette['indigo'];
    $baseClass = "w-full px-3 py-2.5 border border-gray-300 rounded-xl shadow-sm {$c['border']} focus:ring-2 {$c['ring']} transition";
    $isSelect  = $type === 'select';
    $isTextarea = $type === 'textarea';
@endphp

<div class="mb-4">
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1.5">
        {{ $label }}
        @if($required)<span class="text-red-500">*</span>@endif
    </label>

    @if($isSelect)
        <select
            name="{{ $name }}"
            id="{{ $name }}"
            @if($required) required @endif
            {{ $attributes->merge(['class' => $baseClass]) }}
        >
            @if($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach(($options ?? []) as $optValue => $optLabel)
                @if(is_array($optLabel))
                    <option value="{{ $optValue }}" @selected(old($name, $value) == $optValue)>{{ $optLabel['label'] ?? $optValue }}</option>
                @else
                    <option value="{{ $optValue }}" @selected((string) old($name, $value) === (string) $optValue)>{{ $optLabel }}</option>
                @endif
            @endforeach
        </select>
    @elseif($isTextarea)
        <textarea
            name="{{ $name }}"
            id="{{ $name }}"
            rows="{{ $rows }}"
            @if($required) required @endif
            {{ $attributes->merge(['class' => $baseClass]) }}
        >{{ old($name, $value) }}</textarea>
    @else
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if($required) required @endif
            @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            {{ $attributes->merge(['class' => $baseClass]) }}
        >
    @endif

    <p
        :class="$store.modals && $store.modals.errors && $store.modals.errors.{{ $name }} ? '' : 'hidden'"
        x-text="$store.modals && $store.modals.errors && $store.modals.errors.{{ $name }} ? ($store.modals.errors.{{ $name }}[0] || '') : ''"
        class="mt-1 text-sm text-red-600"
    ></p>
    {{ $slot }}
</div>