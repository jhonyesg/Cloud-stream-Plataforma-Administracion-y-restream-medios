@props([
    'intensity' => 'lite',
    'accent' => '#818cf8',
    'scope' => null,
])

@php
    $uid = 'tbg-' . substr(md5(uniqid('', true)), 0, 8);
    $cap = match ($intensity) {
        'full' => 28,
        'lite' => 10,
        default => 0,
    };
@endphp

<div
    data-tech-bg
    data-intensity="{{ $intensity }}"
    data-cap="{{ $cap }}"
    @if($scope) data-scope="{{ $scope }}" @endif
    {{ $attributes->class(['tech-bg-layer pointer-events-none']) }}
    aria-hidden="true"
>
    @if($intensity !== 'none')
        <div class="grid-lines"></div>
    @endif

    @if($intensity === 'full')
        <div class="aurora a1"></div>
        <div class="aurora a2"></div>
        <div class="aurora a3"></div>
    @elseif($intensity === 'lite')
        <div class="aurora a1"></div>
    @endif

    @if($intensity !== 'none')
        <div class="sparks" data-sparks="{{ $uid }}"></div>
    @endif
</div>

<style>
    .tech-bg-layer {
        position: absolute;
        inset: 0;
        z-index: 0;
        overflow: hidden;
    }

    .grid-lines {
        position: absolute; inset: 0;
        background-image:
            linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
        background-size: 48px 48px;
        mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
        -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
        animation: gridShift 24s linear infinite;
        opacity: .6;
    }
    @keyframes gridShift {
        0% { background-position: 0 0, 0 0; }
        100% { background-position: 48px 48px, 48px 48px; }
    }

    .aurora {
        position: absolute;
        border-radius: 50%;
        filter: blur(60px);
        background: radial-gradient(circle, var(--accent, #818cf8) 0%, transparent 70%);
        opacity: .35;
        animation: auroraFloat 14s ease-in-out infinite alternate;
    }
    .aurora.a1 {
        width: 360px; height: 360px;
        left: -100px; top: -120px;
    }
    .aurora.a3 {
        width: 300px; height: 300px;
        right: -100px; bottom: -120px;
        animation-delay: -6s;
    }
    .aurora.a2 {
        width: 260px; height: 260px;
        left: 40%; top: 60%;
        opacity: .18;
        animation-delay: -3s;
    }
    @keyframes auroraFloat {
        0%   { transform: translate(0,0) scale(1); }
        50%  { transform: translate(30px,-20px) scale(1.08); }
        100% { transform: translate(-20px,20px) scale(.95); }
    }

    .spark {
        position: absolute;
        width: 3px; height: 3px;
        border-radius: 50%;
        background: #c7d2fe;
        box-shadow: 0 0 6px 2px rgba(199,210,254,.8), 0 0 10px 3px rgba(99,102,241,.4);
        opacity: 0;
        animation: sparkRise linear infinite;
    }
    @keyframes sparkRise {
        0%   { transform: translateY(0) translateX(0) scale(.6); opacity: 0; }
        10%  { opacity: 1; }
        50%  { transform: translateY(-50%) translateX(20px) scale(1); }
        90%  { opacity: 1; }
        100% { transform: translateY(-110%) translateX(-20px) scale(.4); opacity: 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        .tech-bg-layer .grid-lines,
        .tech-bg-layer .aurora,
        .tech-bg-layer .spark {
            display: none !important;
        }
    }
</style>
