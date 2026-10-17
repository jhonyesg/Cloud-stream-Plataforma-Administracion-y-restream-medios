## Context

Cloudstream's public marketing surface today is a single 914-line `resources/views/landing.blade.php` served at `/` for unauthenticated guests (see `App\Http\Controllers\RedirectController::home`). It already ships a partial JSON-LD graph (`Organization`, `WebSite`, `Service` × 3, `AggregateOffer`, `FAQPage`) and the WhatsApp CTA, but it has the defects called out in the SEO analysis: no `<h1>`, generic title, inconsistent price copy between visible HTML and `Offer.price`, `og:image` pointing to `favicon.svg`, no `BreadcrumbList`, no `ContactPoint`. There is no blog, no landing-page-by-vertical surface, no `/sobre-nosotros`, no consolidated FAQ route.

The site is served by nginx with `root /www/wwwroot/cloudstream.mediaserver.com.co` (project root, **not** `public/`) and a `laravel.conf` extension that overrides `index` to `public/index.php` and forwards all requests to it. A static `public/sitemap.xml` exists but only carries the home URL. Last change we shipped a Laravel route `/sitemap.xml` that reads `public/sitemap.xml` and returns it with `application/xml` — this becomes the foundation for the dynamic sitemap.

Content will be authored in Blade (Spanish, es_CO). No CMS in this iteration.

## Goals / Non-Goals

**Goals:**
- Ship a public marketing surface that is crawlable, indexable, and "card-ready" for Google rich results AND parseable by LLMs (ChatGPT, Perplexity, Gemini, Claude).
- Cover the keyword map from the SEO analysis (7 landings + 10 blog posts + FAQ + About) with unique, helpful copy per page.
- Every public page emits: `<h1>` exactly once, semantic `<nav>`/`<main>`/`<article>`/`<footer>` landmarks, Open Graph + Twitter card meta, canonical URL, and a JSON-LD graph tailored to the page type.
- Fix the home's critical on-page defects: add `<h1>`, unify prices, replace `og:image`, add `tel:` + `ContactPoint` schema.
- Generate a dynamic `/sitemap.xml` that lists every public route with tuned priorities, plus a `/robots.txt` that references it.
- Keep the implementation light: no new DB tables, no new npm dependencies, no new backend services. Blade + a controller + a route file.

**Non-Goals:**
- Blog CMS / database / authoring workflow (tracked separately).
- Multilingual (`hreflang`) — the analysis recommended it as low-impact; deferred.
- Programmatic city pages (`/streaming-canales-tv-bogota`, etc.) — deferred; will be a follow-up once we measure the vertical landings.
- Linkbuilding, PR, Google Business Profile, YouTube embeds, testimonials database — all marketing-side, out of engineering scope here.
- Changes to admin or client panels (admin/client stay on the existing auth-gated surface).

## Decisions

### D1 — Blade components over a CMS
**Decision:** Render all new pages as static Blade files in `resources/views/public/`. No `posts` table, no Eloquent model.
**Why:** The analysis calls for ~20 new pages with copy that the team controls. A CMS would add a migration, an admin CRUD surface, and an authoring workflow that the team isn't ready to maintain. Blade files let us ship the keyword coverage today and revisit later if/when a non-engineer needs to publish.
**Alternative considered:** Filament/Statamic for blog authoring. Rejected: dependency surface + learning curve not justified by the current publishing cadence (the SEO plan calls for 2 articles/week authored by the team directly in Blade for now).

### D2 — Shared `<x-public-layout>` component
**Decision:** Extract a `<x-public-layout title="…" description="…" canonical="…" ogImage="…" schema="…">` Blade component that renders `<head>` (meta, OG, Twitter, canonical, JSON-LD) and the shared nav/footer. Each page passes its own title/description/schema array; the component is the single source of truth for the `<head>` block.
**Why:** The 914-line `landing.blade.php` today duplicates the head structure across its three plan cards and FAQ section. A component removes that duplication and guarantees every new page gets the same meta/schema treatment for free.
**Alternative considered:** A `SeoService` class that returns an array of meta tags rendered in the layout. Rejected: more indirection for the same outcome; Blade `@props` already gives us the ergonomics we need.

### D3 — JSON-LD strategy: one `@graph` per page, not multiple `<script>` tags
**Decision:** Each page emits a single `<script type="application/ld+json">` containing a `@graph` array of all entities relevant to that page (e.g. landing page = `Organization` + `WebSite` + `Service` + `Offer` + `FAQPage` + `BreadcrumbList`; blog post = `Organization` + `Article` + `BreadcrumbList` + `FAQPage`).
**Why:** Google explicitly recommends `@graph` for multi-entity pages, and LLM scrapers parse a single JSON blob more reliably than many. It also avoids `@id` collisions across separate script tags.
**Alternative considered:** One script per entity. Rejected: noisier DOM, harder to debug, no benefit.

### D4 — Dynamic sitemap served by Laravel, not regenerated by a command
**Decision:** Replace the static `public/sitemap.xml` with a route-served response (`Route::get('/sitemap.xml', ...)`) that enumerates every public URL from a single `config/seo.php` map at runtime.
**Why:** Adding a new blog post or landing page should not require running an artisan command + re-deploying. A route + config array means new entries are a one-line config change.
**Alternative considered:** `spatie/laravel-sitemap` package. Rejected: adds a dependency for something that is ~40 lines of code in our controller.
**Caveat:** The `public/sitemap.xml` file already has a route (added in a previous step). We will replace the route body to read from `config('seo.public_urls')` and return the assembled XML.

### D5 — `tel:` link on the WhatsApp CTA, not replacement
**Decision:** Keep the WhatsApp CTA as the primary conversion target (Colombian market norm per the analysis), but add a `tel:+573124082557` link as a secondary action and a `ContactPoint.telephone` in JSON-LD.
**Why:** Some visitors prefer a voice call, and `tel:` is a Google-recommended local signal. WhatsApp remains the dominant CTA.
**Alternative considered:** Replace WhatsApp with a contact form. Rejected: the analysis flagged WhatsApp conversion as a strength, not a weakness.

### D6 — Prices: single source of truth in `config/seo.php`
**Decision:** Move the `$plan1Price` / `$plan2Price` PHP variables from `landing.blade.php:525-528` to a `config('seo.plans')` array. The blade view, the WhatsApp anchor text, and the `Offer.price` in JSON-LD all read from this config.
**Why:** The SEO analysis flagged the price inconsistency (visible HTML says `$75.000/mes` struck-through, WhatsApp says `$75.000/mes`, plan card strike says `$150.000`). A single config eliminates drift.
**Alternative considered:** A `Plan` Eloquent model. Rejected: no admin needs to edit prices via UI yet; engineering-side config is fine.

### D7 — `og:image`: one canonical 1200×630 asset for the home, reused on landings
**Decision:** Create `public/og/home-1200x630.jpg` (Cloudstream logo + headline + datacenter badge). For the landings and blog posts in v1, reuse the home OG image — it is acceptable per Google's guidance. Per-page OG images are a v2 improvement.
**Why:** Ships today without needing 20+ graphic design deliverables. Per-page OG images can be a follow-up change once the copy team is ready.
**Alternative considered:** Auto-generate OG images with a PHP library (intervention/image). Rejected: server-side rendering is slow per-request and the visual quality won't beat a designed asset.

### D8 — Page copy authored in Spanish (es_CO), no i18n layer
**Decision:** All copy is hardcoded Spanish (es_CO locale, already declared in the home's `og:locale`). No `__()` translation calls.
**Why:** The product is Colombia-only per the analysis. A translation layer would be dead weight.
**Alternative considered:** Laravel localization with `es_CO.json`. Deferred until we actually ship a second locale.

## Risks / Trade-offs

- **Duplicate-content risk** between the 7 landings (similar structure, different vertical) → Mitigation: each landing has a unique `<h1>`, unique intro paragraph (≥120 words specific to the vertical), unique FAQ (≥4 Q&A), unique `Service.description`, unique CTA copy. The shared layout only carries nav/footer/head scaffolding.
- **Stale sitemap if `config/seo.php` is forgotten when adding a new page** → Mitigation: add a Pest test that asserts every route in `config('seo.public_urls')` returns 200, and that the sitemap XML contains every entry. Blocks CI if a route is registered in `web.php` but missing from the sitemap config.
- **JSON-LD drift between pages** → Mitigation: a `<x-public-schema :type="…" :data="…">` partial centralizes the schema templates. The component takes typed props (`'landing' | 'blog' | 'faq' | 'about' | 'home'`) and merges with page-specific data, so `@graph` composition is consistent.
- **OG image is generic for landings/blog in v1** → Mitigation: document the limitation; revisit when the team can produce designed assets per page.
- **No CMS means no publishing workflow** → Mitigation: ship a lightweight `php artisan blog:new {slug}` command that scaffolds a blade file from a stub with the correct schema + meta. Reduces copy-paste errors.
- **LLM citation is not directly measurable** → Mitigation: include a "Llms.txt" file at `/llms.txt` (proposed standard for LLM crawlers) listing the public pages with one-line summaries — cheap, increasingly crawled by GPTBot/PerplexityBot.

## Migration Plan

1. Create `config/seo.php` with `plans`, `public_urls`, `company` (NIT, address, phone), `og_image`.
2. Create `<x-public-layout>` component and move the shared head/nav/footer/script blocks from `landing.blade.php` into `partials/public/`.
3. Slim `landing.blade.php` to a thin view that uses the component. Fix H1, unify prices, replace OG image, add `tel:`.
4. Add `resources/views/public/landings/{slug}.blade.php` × 7 + `blog/{slug}.blade.php` × 10 + `blog/index.blade.php` + `faq.blade.php` + `about.blade.php`.
5. Add `App\Http\Controllers\PublicSeoController` (or methods on `RedirectController`) with `landing($slug)`, `blog($slug)`, `blogIndex()`, `faq()`, `about()`.
6. Register routes in `routes/web.php`.
7. Replace the static `public/sitemap.xml` body in the existing route with a dynamic response driven by `config('seo.public_urls')`. Delete `public/sitemap.xml`.
8. Add `public/og/home-1200x630.jpg`.
9. Add `public/llms.txt` with the page list.
10. Add `tests/Feature/PublicSeoTest.php` covering: each route 200, sitemap contains every URL, each page has exactly one `<h1>`, each page contains a JSON-LD script, each blog page emits `Article` schema.
11. Clear caches (`php artisan view:clear && config:clear && route:clear`) and verify with `php artisan route:list` and `php artisan sitemap:check` (custom command we add in this change).
12. Deploy; submit the sitemap to Google Search Console.

**Rollback:** revert the merge. All new pages are additive (no DB migrations, no panel changes). The existing `/` landing stays live because `landing.blade.php` is still served at `/` for guests.

## Open Questions

1. Do we want `/blog` or `/noticias` or `/aprende` for the content hub URL? (Affects keyword targeting — `/blog` is the strongest SEO signal internationally, `/aprende` is more on-brand for es_CO.) → **Proposed default: `/blog`.**
2. Do we want the blog posts to be static Blade (shipped now) or backed by a simple `posts` table so a future "publish from admin" flow can be added without a refactor? → **Proposed default: static Blade now, schema in blade files. Migrate to DB later if needed.**
3. Should the `og:image` for the home be JPG or PNG? (PNG better for logo crispness, JPG smaller for the same visual.) → **Proposed default: JPG, ~80KB target.**
4. Canonical URL: `https://cloudstream.mediaserver.com.co/` with trailing slash, or without? (Today robots.txt and JSON-LD use no trailing slash.) → **Proposed default: without trailing slash, matching the current convention.**