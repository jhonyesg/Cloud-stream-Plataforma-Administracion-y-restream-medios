## 1. Shared infrastructure

- [x] 1.1 Create `config/seo.php` with keys `plans` (plan1, plan2, plan3 with `price_cop`, `name`, `features`), `company` (name, nit, city, country, phone_e164, phone_display), `og_image`, and `public_urls` (array of `['path' => '...', 'type' => 'home|landing|blog|faq|about', 'lastmod' => 'YYYY-MM-DD', 'title' => '...', 'description' => '...', 'schema_data' => [...]]`).
- [x] 1.2 Create the `<x-public-layout>` Blade component in `resources/views/components/public-layout.blade.php` that accepts `title`, `description`, `canonical`, `ogImage`, and a `schema` array; renders `<head>` with meta/OG/Twitter/canonical and a single `<script type="application/ld+json">` built from the schema prop.
- [x] 1.3 Create `<x-public-schema>` partial in `resources/views/partials/public/schema.blade.php` that builds the JSON-LD `@graph` from a typed array (`['type' => 'home|landing|blog|faq|about', 'data' => […]]`).
- [x] 1.4 Create `<x-public-nav>` and `<x-public-footer>` partials in `resources/views/partials/public/` containing the shared navigation (Inicio, Planes, FAQ, Sobre nosotros, Blog) and footer (NIT, phone, WhatsApp, links to landings).
- [x] 1.5 Create `public/og/home-1200x630.jpg` (1200×630 JPG, ≤120 KB, Cloudstream logo + headline "Señal de TV en vivo por Internet en Colombia" + datacenter badge).
- [x] 1.6 Create `public/llms.txt` listing every public page with a one-line Spanish summary each.

## 2. Home page refactor (landing spec)

- [x] 2.1 Slim `resources/views/landing.blade.php` to delegate to `<x-public-layout>` and remove the inline `<head>`/OG/JSON-LD blocks.
- [x] 2.2 Add exactly one `<h1>` to the home page hero with text containing "señal de TV en vivo" and "Colombia".
- [x] 2.3 Replace the inline `$plan1Price` / `$plan2Price` PHP variables with `config('seo.plans.plan1.price_cop')` and `config('seo.plans.plan2.price_cop')`; update WhatsApp anchor text to read from the same config.
- [x] 2.4 Replace `<meta property="og:image" content="{{ asset('favicon.svg') }}">` with the new home-1200x630 asset.
- [x] 2.5 Add a `tel:+573124082557` link to the hero or contact section as a secondary CTA.
- [x] 2.6 Add `BreadcrumbList` and `ContactPoint` entities to the home JSON-LD `@graph`; ensure Offer prices match the visible plan cards.
- [x] 2.7 Unify the price copy: remove the spurious `<p class="text-lg text-slate-500 strike">$75.000/mes</p>` at landing.blade.php:777 (this is currently incorrectly struck-through).

## 3. Public SEO controller and routes

- [x] 3.1 Create `App\Http\Controllers\PublicSeoController` with methods `landing(string $slug)`, `blogIndex()`, `blog(string $slug)`, `faq()`, `about()`, and a shared `headData(string $path): array` helper that reads from `config('seo.public_urls')`.
- [x] 3.2 Register the new routes in `routes/web.php`:
  - `Route::get('/streaming-para-iglesias-colombia', [PublicSeoController::class, 'landing'])->name('public.landing.iglesias');`
  - plus 6 more for the other landings (one route per slug),
  - `Route::view('/blog', 'public.blog.index')->name('public.blog.index');` (or via controller),
  - 10 routes for `/blog/{slug}`,
  - `Route::get('/preguntas-frecuentes', [PublicSeoController::class, 'faq'])->name('public.faq');`,
  - `Route::get('/sobre-nosotros', [PublicSeoController::class, 'about'])->name('public.about');`.
- [x] 3.3 Replace the body of the existing `/sitemap.xml` route to iterate over `config('seo.public_urls')` and return a rendered XML response with `Content-Type: application/xml` and per-type priority/changefreq (home 1.0/weekly, landing 0.9/monthly, blog 0.7/monthly, faq/about 0.5/monthly).
- [x] 3.4 Delete the now-obsolete static `public/sitemap.xml` file (the route now serves it).
- [x] 3.5 Register a `/robots.txt` route (or keep as static if nginx serves it — verify) that includes `Sitemap: https://cloudstream.mediaserver.com.co/sitemap.xml` plus the existing `Disallow` lines for `/admin`, `/client`, `/profile`, `/login`, `/register`.

## 4. Vertical landing pages (7)

- [x] 4.1 Create `resources/views/public/landings/_layout.blade.php` shared chrome (hero, intro, features grid, pricing teaser, FAQ accordion, CTA strip, cross-links).
- [x] 4.2 Create `resources/views/public/landings/streaming-para-iglesias-colombia.blade.php` — unique `<h1>`, ≥120-word intro, vertical-specific FAQ (≥4 Q&A: horarios de misas, eventos especiales, donación de ofrendas en vivo, cobertura multi-sede).
- [x] 4.3 Create `resources/views/public/landings/streaming-para-emisoras-de-radio-online.blade.php` — FAQ: radio + video simultáneo, baja latencia, apps móviles, monetización con cuñas.
- [x] 4.4 Create `resources/views/public/landings/streaming-para-canales-de-tv-regionales.blade.php` — FAQ: licenciamiento, cobertura nacional, señales HD, integración con cable operadores.
- [x] 4.5 Create `resources/views/public/landings/streaming-para-universidades-y-educacion.blade.php` — FAQ: clases híbridas, grabación automática, protección con login, integración con LMS.
- [x] 4.6 Create `resources/views/public/landings/streaming-para-productoras-de-contenido.blade.php` — FAQ: multipantalla, monetización, OTT propio, branding personalizado.
- [x] 4.7 Create `resources/views/public/landings/restream-facebook-youtube-tiktok.blade.php` — FAQ: destinos soportados, autenticación, failover, monitoreo desde panel.
- [x] 4.8 Create `resources/views/public/landings/servidor-rtmp-colombia.blade.php` — FAQ: protocolo RTMP vs HLS, ingest desde OBS/vMix/Wirecast, bitrate recomendado, redundancia.

## 5. Blog index + articles (10)

- [x] 5.1 Create `resources/views/public/blog/index.blade.php` listing every entry in `config('seo.public_urls')` whose `type === 'blog'`, with title, excerpt, date, and link to the full article. Emit `Blog` + `BreadcrumbList` + `ItemList` JSON-LD.
- [x] 5.2 Create `resources/views/public/blog/_article.blade.php` shared chrome (title, date, author byline, body slot, FAQ accordion, CTA strip, "Artículos relacionados").
- [x] 5.3–5.12 Create the ten pillar articles at `resources/views/public/blog/{slug}.blade.php`, each ≥800 words, unique `<h1>`, `Article` JSON-LD with headline/description/datePublished/author/publisher, inline FAQPage JSON-LD at the end, and a CTA block linking to the most relevant landing or plan:
  - `cuanto-cuesta-transmitir-tv-internet`
  - `como-transmitir-canal-tv-internet`
  - `que-es-rtmp-hls`
  - `alternativas-a-obs-para-transmitir-24-7`
  - `como-elegir-servidor-de-streaming-en-colombia`
  - `streaming-para-iglesias-como-transmitir-misas-y-eventos-en-vivo`
  - `como-verificar-que-tu-pauta-publicitaria-se-emitio-en-tv`
  - `rtmp-vs-hls-vs-srt-diferencias`
  - `casos-de-uso-emisoras-colombianas-24-7`
  - `como-empezar-a-transmitir-tu-canal-en-5-minutos`

## 6. FAQ and About pages

- [x] 6.1 Create `resources/views/public/faq.blade.php` with ≥15 questions across 5 categories (Planes y precios, Streaming técnico, Restream, Cuñas y programador, Soporte). Use `<details>`/`<summary>`. Emit `FAQPage` JSON-LD with all 15 Q&A.
- [x] 6.2 Create `resources/views/public/about.blade.php` with ≥400 words covering Media Clouding SAS, NIT, Bogotá, datacenter americano, founding story, contact phone. Emit `AboutPage` JSON-LD referencing the `Organization` by `@id`.

## 7. Scaffolding and ops

- [x] 7.1 Add `php artisan blog:new {slug}` command that scaffolds a new blog blade file from `stubs/public/blog-article.stub` with the correct `<x-public-layout>` wrap, schema, and CTA placeholder.
- [x] 7.2 Add `php artisan sitemap:check` command that fetches every URL in `config('seo.public_urls')` from the local environment and asserts HTTP 200 + presence of the expected JSON-LD `@type`. Exits non-zero on any failure (CI-friendly).
- [x] 7.3 Clear caches after the changes: `php artisan view:clear && php artisan config:clear && php artisan route:clear && php artisan view:cache` (per AGENTS.md).

## 8. Tests

- [ ] 8.1 Add `tests/Feature/PublicSeoRoutesTest.php`: each of the 7 landing routes, the 10 blog routes, `/blog`, `/preguntas-frecuentes`, `/sobre-nosotros` returns HTTP 200 for an anonymous visitor.
- [ ] 8.2 Add `tests/Feature/PublicSeoHeadTest.php`: each public page contains exactly one `<h1>`, a non-empty `<title>`, a `<meta name="description">` of 120–165 chars, a `<link rel="canonical">`, an `og:image` meta that is NOT `favicon.svg`, and a `<script type="application/ld+json">` whose body parses as JSON.
- [ ] 8.3 Add `tests/Feature/PublicSeoSchemaTest.php`: home JSON-LD contains `Organization`, `WebSite`, `Service`, `Offer`, `FAQPage`, `BreadcrumbList`, `ContactPoint`; each landing JSON-LD contains `Service`, `Offer`, `FAQPage`, `BreadcrumbList`; each blog article JSON-LD contains `Article` (or `BlogPosting`); FAQ JSON-LD has ≥15 `Question` entries.
- [ ] 8.4 Add `tests/Feature/PublicSeoSitemapTest.php`: `/sitemap.xml` returns 200 with `application/xml`; the body contains a `<url>` entry for every entry in `config('seo.public_urls')`; no `<loc>` starts with `/admin`, `/client`, `/profile`, `/login`, `/register`, `/dashboard`, `/api`; home has `priority 1.0`, landings `0.9`, blog `0.7`, faq/about `0.5`.
- [ ] 8.5 Add `tests/Feature/PublicSeoPriceConsistencyTest.php`: on the home page, the visible plan-1 and plan-2 prices equal the prices embedded in the WhatsApp anchor URLs and equal the `Offer.price` values in the JSON-LD.
- [ ] 8.6 Add `tests/Feature/PublicSeoCrawlabilityTest.php`: `/robots.txt` contains `Sitemap: https://cloudstream.mediaserver.com.co/sitemap.xml` and `Disallow` lines for `/admin`, `/client`, `/profile`, `/login`, `/register`; no landing or blog URL appears under any `Disallow`.

## 9. Deploy and submit

- [x] 9.1 Run `php artisan migrate:safe` (per AGENTS.md — no migrations in this change, but the safety command confirms no schema drift).
- [x] 9.2 Verify locally: `php artisan route:list | grep public\.`, hit `/sitemap.xml` and every landing/blog URL with `curl -I`, confirm 200.
- [ ] 9.3 Deploy; in Google Search Console, re-submit `https://cloudstream.mediaserver.com.co/sitemap.xml`.
- [ ] 9.4 In Google Search Console, request indexing for the home + each new landing page (7) + each blog article (10) + FAQ + About = 20 URLs.
- [x] 9.5 Smoke test: confirm no regressions on `/`, `/login`, `/admin`, `/client`, `/dashboard` (auth-gated routes still work as before).