@props([
    'title' => 'Cloudstream | Señal de TV en vivo por Internet en Colombia',
    'description' => 'Transmite tu canal de TV en vivo por Internet en Colombia. Servicio de señal streaming 24/7 con programador de contenido, cuñas publicitarias, logo, datacenter americano y restream a Facebook, YouTube y TikTok. Planes desde $80.000/mes.',
    'canonical' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'videoUrl' => null,
    'videoPoster' => null,
    'videoType' => 'video/mp4',
    'videoDuration' => null,
    'schema' => ['type' => 'home', 'data' => []],
])

@php
    $siteUrl = rtrim(config('seo.site_url'), '/');
    $currentPath = request()->path();

    if (! $ogImage && str_starts_with($currentPath, 'blog/') && $currentPath !== 'blog') {
        $blogSlug = basename($currentPath);
        $blogImg = public_path('images/blog/' . $blogSlug . '.jpg');
        if (file_exists($blogImg)) {
            $ogImage = '/images/blog/' . $blogSlug . '.jpg';
        }
    }

    $defaultOg = $siteUrl . config('seo.og_image');
    $ogImage = $ogImage ?: $defaultOg;
    if (! preg_match('#^https?://#', $ogImage)) {
        $ogImage = $siteUrl . $ogImage;
    }
    $canonical = $canonical ?: url()->current();
    $canonical = preg_replace('#^https?://[^/]+#', $siteUrl, $canonical);
@endphp

<!DOCTYPE html>
<html lang="es-CO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="{{ config('seo.company.brand') }}">
    <meta name="theme-color" content="#0a0f2c">

    <link rel="preconnect" href="https://www.googletagmanager.com">
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">

    <link rel="canonical" href="{{ $canonical }}">
    <link rel="alternate" hreflang="es-CO" href="{{ $canonical }}">
    <link rel="alternate" hreflang="es" href="{{ $canonical }}">
    <link rel="alternate" hreflang="x-default" href="{{ $canonical }}">

    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="keywords" content="señal de tv en vivo por internet, transmitir canal de tv por internet colombia, streaming para canales de tv, señal streaming 24/7, restream facebook youtube tiktok, programador de cuñas publicitarias, canal de tv online colombia, transmisión en vivo colombia, servicio de streaming para televisión, emitir señal de tv por internet, streaming para emisoras y canales, señal hls rtmp, cuñas publicitarias programadas, canal de televisión por internet">

    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:site_name" content="{{ config('seo.company.brand') }}">
    <meta property="og:locale" content="es_{{ config('seo.company.country_code') }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    @if ($videoUrl)
        <meta property="og:video" content="{{ str_starts_with($videoUrl, 'http') ? $videoUrl : url($videoUrl) }}">
        <meta property="og:video:url" content="{{ str_starts_with($videoUrl, 'http') ? $videoUrl : url($videoUrl) }}">
        <meta property="og:video:type" content="{{ $videoType }}">
        <meta property="og:video:secure_url" content="{{ str_starts_with($videoUrl, 'http') ? $videoUrl : url($videoUrl) }}">
        @if ($videoPoster)
            <meta property="og:video:poster" content="{{ str_starts_with($videoPoster, 'http') ? $videoPoster : url($videoPoster) }}">
        @endif
        @if ($videoDuration)
            <meta property="og:video:duration" content="{{ $videoDuration }}">
        @endif
    @endif

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/icon-180.png') }}">

    <link rel="stylesheet" href="{{ asset('css/tailwind.min.css') }}">
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtag/js?id=G-SGM93TMTYJ';
                document.head.appendChild(s);
                s.onload = function () {
                    window.dataLayer = window.dataLayer || [];
                    window.gtag = function () { dataLayer.push(arguments); };
                    gtag('js', new Date());
                    gtag('config', 'G-SGM93TMTYJ');
                };
            }, 2000);
        });
    </script>

    @include('partials.public.schema', ['schema' => $schema])
</head>
<body class="min-h-screen">
    <x-public-nav />

    <main class="relative z-10">
        {{ $slot }}
    </main>

    <x-public-footer />
</body>
</html>
