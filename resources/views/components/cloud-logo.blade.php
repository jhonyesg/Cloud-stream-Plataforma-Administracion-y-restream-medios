@props([
    'size' => 'w-9 h-9',
    'spin' => false,
])

@php
    $uid = 'cslogo-' . substr(md5(uniqid('', true)), 0, 8);
@endphp

<span {{ $attributes->class(['cs-logo inline-flex items-center justify-center rounded-xl shrink-0', $size, $spin ? 'cs-logo-spin' : '']) }}
      role="img"
      aria-label="Cloudstream"
      title="Cloudstream">
    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="relative w-full h-full">
        <defs>
            <linearGradient id="{{ $uid }}-cloud" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#a5b4fc"/>
                <stop offset="100%" stop-color="#22d3ee"/>
            </linearGradient>
            <linearGradient id="{{ $uid }}-stream" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="#f0abfc"/>
                <stop offset="100%" stop-color="#a5f3fc"/>
            </linearGradient>
        </defs>
        <path d="M20 42c-6.6 0-12-5.1-12-11.4 0-5.7 4.2-10.4 9.7-11.3C19.3 12 25.4 7 32.7 7c7.8 0 14.2 5.8 15 13.3.2 0 .4 0 .6 0 6.2 0 11.2 4.8 11.2 10.7S54.5 41.7 48.3 41.7H20z"
              fill="url(#{{ $uid }}-cloud)" opacity="0.95"/>
        <path d="M28 30 v18 l16 -9 z" fill="url(#{{ $uid }}-stream)" stroke="white" stroke-width="1.4" stroke-linejoin="round"/>
    </svg>
</span>

<style>
    .cs-logo-spin {
        position: relative;
        background: conic-gradient(from 140deg at 50% 50%, #6366f1, #a855f7, #22d3ee, #6366f1);
    }
    .cs-logo-spin::before {
        content: '';
        position: absolute;
        inset: 2px;
        border-radius: inherit;
        background: linear-gradient(135deg, #0b1230, #1e1b4b);
    }
    .cs-logo-spin > svg { position: relative; z-index: 1; }
    .cs-logo {
        animation: csLogoSpin 12s linear infinite;
    }
    @keyframes csLogoSpin { to { filter: hue-rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) {
        .cs-logo { animation: none; }
    }
</style>
