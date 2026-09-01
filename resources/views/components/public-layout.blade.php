@props([
    'title' => 'Cloudstream | Señal de TV en vivo por Internet en Colombia',
    'description' => 'Transmite tu canal de TV en vivo por Internet en Colombia. Servicio de señal streaming 24/7 con programador de contenido, cuñas publicitarias, logo, datacenter americano y restream a Facebook, YouTube y TikTok. Planes desde $75.000/mes.',
    'canonical' => null,
    'ogImage' => null,
    'ogType' => 'website',
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
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-SGM93TMTYJ"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-SGM93TMTYJ');
    </script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="{{ config('seo.company.brand') }}">
    <meta name="theme-color" content="#0a0f2c">
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

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/icon-180.png') }}">

    <script src="{{ asset('js/tailwind.js') }}"></script>
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>

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
