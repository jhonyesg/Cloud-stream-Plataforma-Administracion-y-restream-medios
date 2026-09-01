<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicSeoController extends Controller
{
    public function landing(Request $request, string $slug)
    {
        if (! in_array($slug, config('seo.landing_slugs', []), true)) {
            abort(404);
        }

        $viewMap = [
            'streaming-para-iglesias-colombia' => 'public.landings.iglesias',
            'streaming-para-emisoras-de-radio-online' => 'public.landings.emisoras',
            'streaming-para-canales-de-tv-regionales' => 'public.landings.canales-tv',
            'streaming-para-universidades-y-educacion' => 'public.landings.universidades',
            'streaming-para-productoras-de-contenido' => 'public.landings.productoras',
            'restream-facebook-youtube-tiktok' => 'public.landings.restream',
            'servidor-rtmp-colombia' => 'public.landings.servidor-rtmp',
        ];

        $meta = $this->headData('/' . $slug);
        return view($viewMap[$slug], ['meta' => $meta]);
    }

    public function blogIndex()
    {
        $articles = collect(config('seo.public_urls'))
            ->filter(fn ($u) => $u['type'] === 'blog')
            ->map(fn ($u) => array_merge($u, [
                'excerpt' => $this->excerptFor($u['path']),
                'date_published' => $u['lastmod'] ?? '2026-08-24',
            ]))
            ->values()
            ->all();

        $meta = $this->headData('/blog');
        $meta['data']['articles'] = $articles;
        return view('public.blog.index', ['articles' => $articles, 'meta' => $meta]);
    }

    public function blog(Request $request, string $slug)
    {
        if (! in_array($slug, config('seo.blog_slugs', []), true)) {
            abort(404);
        }

        $viewMap = [
            'cuanto-cuesta-transmitir-tv-internet' => 'public.blog.cuanto-cuesta-transmitir-tv-internet',
            'como-transmitir-canal-tv-internet' => 'public.blog.como-transmitir-canal-tv-internet',
            'que-es-rtmp-hls' => 'public.blog.que-es-rtmp-hls',
            'alternativas-a-obs-para-transmitir-24-7' => 'public.blog.alternativas-a-obs-para-transmitir-24-7',
            'como-elegir-servidor-de-streaming-en-colombia' => 'public.blog.como-elegir-servidor-de-streaming-en-colombia',
            'streaming-para-iglesias-como-transmitir-misas-y-eventos-en-vivo' => 'public.blog.streaming-para-iglesias-como-transmitir-misas-y-eventos-en-vivo',
            'como-verificar-que-tu-pauta-publicitaria-se-emitio-en-tv' => 'public.blog.como-verificar-que-tu-pauta-publicitaria-se-emitio-en-tv',
            'rtmp-vs-hls-vs-srt-diferencias' => 'public.blog.rtmp-vs-hls-vs-srt-diferencias',
            'casos-de-uso-emisoras-colombianas-24-7' => 'public.blog.casos-de-uso-emisoras-colombianas-24-7',
            'como-empezar-a-transmitir-tu-canal-en-5-minutos' => 'public.blog.como-empezar-a-transmitir-tu-canal-en-5-minutos',
        ];

        $meta = $this->headData('/blog/' . $slug);
        return view($viewMap[$slug], ['meta' => $meta]);
    }

    public function faq()
    {
        $meta = $this->headData('/preguntas-frecuentes');
        return view('public.faq', ['meta' => $meta]);
    }

    public function about()
    {
        $meta = $this->headData('/sobre-nosotros');
        return view('public.about', ['meta' => $meta]);
    }

    public function sitemap()
    {
        $urls = collect(config('seo.public_urls', []))->map(function ($u) {
            $type = $u['type'] ?? 'home';
            [$priority, $changefreq] = match ($type) {
                'home' => ['1.0', 'weekly'],
                'landing' => ['0.9', 'monthly'],
                'blog-index' => ['0.8', 'weekly'],
                'blog' => ['0.7', 'monthly'],
                default => ['0.5', 'monthly'],
            };

            return [
                'loc' => rtrim(config('seo.site_url'), '/') . $u['path'],
                'lastmod' => $u['lastmod'] ?? '2026-08-24',
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        })->all();

        $content = view('public.sitemap', ['urls' => $urls])->render();

        return response($content, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $site = rtrim(config('seo.site_url'), '/');
        $content = "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /admin\n"
            . "Disallow: /client\n"
            . "Disallow: /profile\n"
            . "Disallow: /login\n"
            . "Disallow: /register\n"
            . "Disallow: /dashboard\n"
            . "Disallow: /api\n\n"
            . "Sitemap: {$site}/sitemap.xml\n";

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function llms()
    {
        $site = rtrim(config('seo.site_url'), '/');
        $urls = config('seo.public_urls', []);
        $content = "# Cloudstream — llms.txt\n"
            . "# Servicio de señal de TV en vivo por Internet en Colombia para canales de TV, emisoras, iglesias y productoras.\n"
            . "# Empresa: Media Clouding SAS, Bogotá, Colombia.\n"
            . "# Contacto: contacto@cloudstream.mediaserver.com.co · +57 312 408 2557\n\n";

        foreach ($urls as $u) {
            $content .= '- ' . $site . $u['path'] . ': ' . $u['description'] . "\n";
        }

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    protected function headData(string $path): array
    {
        $entry = collect(config('seo.public_urls'))->firstWhere('path', $path);
        if (! $entry) {
            abort(404);
        }
        return [
            'title' => $entry['title'] ?? '',
            'description' => $entry['description'] ?? '',
            'canonical' => rtrim(config('seo.site_url'), '/') . $path,
            'lastmod' => $entry['lastmod'] ?? '2026-08-24',
            'data' => $entry['schema_data'] ?? [],
        ];
    }

    protected function excerptFor(string $path): string
    {
        $entry = collect(config('seo.public_urls'))->firstWhere('path', $path);
        $desc = $entry['description'] ?? '';
        return mb_strlen($desc) > 180 ? mb_substr($desc, 0, 177) . '...' : $desc;
    }
}
