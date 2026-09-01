@props([
    'size' => 'w-9 h-9',
    'spin' => false,
])

@php
    $uid = 'cslogo-' . substr(md5(uniqid('', true)), 0, 8);
@endphp

<span {{ $attributes->class(['cs-logo inline-flex items-center justify-center rounded-xl shrink-0 overflow-hidden', $size, $spin ? 'cs-logo-spin' : '']) }}
      role="img"
      aria-label="Cloudstream"
      title="Cloudstream">
    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="relative w-full h-full block">
        <defs>
            <linearGradient id="{{ $uid }}-bg" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#1e1b4b"/>
                <stop offset="100%" stop-color="#0c0a2e"/>
            </linearGradient>
            <linearGradient id="{{ $uid }}-cloud" x1="10" y1="8" x2="55" y2="50" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#818cf8"/>
                <stop offset="50%" stop-color="#3b82f6"/>
                <stop offset="100%" stop-color="#06b6d4"/>
            </linearGradient>
            <linearGradient id="{{ $uid }}-play" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#fef3c7"/>
                <stop offset="55%" stop-color="#f472b6"/>
                <stop offset="100%" stop-color="#a855f7"/>
            </linearGradient>
        </defs>
        <rect width="64" height="64" rx="14" fill="url(#{{ $uid }}-bg)"/>
        <g fill="none" stroke-linecap="round">
            <path d="M45 15 a4.5 4.5 0 0 1 0 9" stroke="#22d3ee" stroke-width="2.4" opacity="0.95"/>
            <path d="M49.5 10.5 a9.5 9.5 0 0 1 0 19" stroke="#a855f7" stroke-width="2.2" opacity="0.55"/>
        </g>
        <path d="M20 42c-6.6 0-12-5.1-12-11.4 0-5.7 4.2-10.4 9.7-11.3C19.3 12 25.4 7 32.7 7c7.8 0 14.2 5.8 15 13.3.2 0 .4 0 .6 0 6.2 0 11.2 4.8 11.2 10.7S54.5 41.7 48.3 41.7H20z"
              fill="url(#{{ $uid }}-cloud)"/>
        <path d="M14 26c0.5-5.5 5-10 10.5-10.7"
              fill="none" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
        <path d="M28 21L28 39L46 30Z"
              fill="url(#{{ $uid }}-play)" stroke="#ffffff" stroke-width="1.5" stroke-linejoin="round"/>
    </svg>
</span>

<style>
    .cs-logo-spin {
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