## Why

Cloudstream (cloudstream.mediaserver.com.co) is a SaaS for Colombian TV channels to broadcast their signal 24/7 over the internet. A recent SEO analysis shows the site captures only the transactional intent (≈30% of the addressable market) and has **zero** coverage of informational and consideration queries, which represent ≈70% of search volume in this niche. Concurrently, no on-page schema exists for rich results beyond a basic `Organization` + `Service` block, and the markup is not optimized for LLM ingestion (no `FAQPage` directly visible in HTML body, no `BreadcrumbList`, no `Article` for blog posts, missing `Service` + `Offer` per vertical, inconsistent pricing copy). We need a content + technical layer that (a) targets the long-tail keyword map from the analysis with new public landing pages and blog posts, and (b) makes every public page "card-ready" for Google rich results and easy to parse for LLMs (ChatGPT, Perplexity, Gemini, Claude) via clean semantic HTML and comprehensive JSON-LD.

## What Changes

- Add 7 new public landing pages, one per vertical + product surface, each with its own keyword, unique copy, and a per-page JSON-LD graph (`Service` + `Offer` + `FAQPage` + `BreadcrumbList`):
  - `/streaming-para-iglesias-colombia`
  - `/streaming-para-emisoras-de-radio-online`
  - `/streaming-para-canales-de-tv-regionales`
  - `/streaming-para-universidades-y-educacion`
  - `/streaming-para-productoras-de-contenido`
  - `/restream-facebook-youtube-tiktok`
  - `/servidor-rtmp-colombia`
- Add a `/blog` section with 10 pillar articles targeting informational/consideration long-tails. Each article lives at `/blog/{slug}`, uses an `Article` + `BreadcrumbList` + `FAQPage` schema graph, and includes a CTA block back to the relevant plan/landing page.
- Add a public `/preguntas-frecuentes` page consolidating the most-asked questions across verticals, with `FAQPage` schema.
- Add a public `/sobre-nosotros` page (company story, NIT, legal info, datacenter facts) with `Organization` + `AboutPage` schema. Helps EEAT.
- Refactor the existing `landing.blade.php` into a reusable Blade component set so every public page inherits the same `<head>`, nav, footer, and schema scaffolding without duplication.
- Add a unique `<h1>` to the home (missing today — flagged as a critical SEO defect).
- Unify the price copy between the visible HTML, the WhatsApp anchor text, and the JSON-LD `Offer.price` (today the $75.000 price is incorrectly struck-through at landing.blade.php:777).
- Replace `og:image` pointing to `/favicon.svg` with a real 1200×630 PNG/JPG asset under `public/og/`.
- Add a `ContactPoint` with `tel:+57 312 408 2557` and a `tel:` link on every CTA.
- Update `public/sitemap.xml` to include every new public URL with appropriate `lastmod` / `priority` / `changefreq`.
- Add a new `PublicSeoController` (or extend `RedirectController`) that serves the new routes from blade views and emits the per-page `<head>` + JSON-LD via a shared partial.

No breaking changes to admin/client panels. No DB migrations required (content is blade-rendered, not a CMS).

## Capabilities

### New Capabilities
- `public-landing-pages`: The vertical landing pages (iglesias, emisoras, canales regionales, universidades, productoras, restream, servidor RTMP) — shared layout, per-page copy, per-page schema graph, internal linking.
- `public-blog`: The `/blog` index + 10 pillar articles as static Blade files with `Article`/`BreadcrumbList`/`FAQPage` schema and CTA blocks.
- `public-faq`: The consolidated FAQ page with `FAQPage` schema, cross-linked from every landing page.
- `public-about`: The `/sobre-nosotros` page with `Organization` + `AboutPage` schema (company, NIT, datacenter, EEAT signals).
- `public-seo-sitemap`: The dynamic sitemap that lists every public route (home, landings, blog posts, FAQ, about) with priorities tuned per content type.

### Modified Capabilities
- `landing` (implicit — currently lives only in `resources/views/landing.blade.php`): no spec file exists today for the public marketing surface; the refactor introduces a shared layout component and fixes the H1/price/og:image/schema defects. Listed as "modified" because the home's on-page contract changes.

## Impact

- **Routes**: `routes/web.php` gains ~20 GET routes (7 landings + 10 blog posts + blog index + FAQ + About) plus the existing `/sitemap.xml` route becomes dynamic.
- **Views**: New directory `resources/views/public/` with subfolders `landings/`, `blog/`, `partials/`, `components/`. The 914-line `landing.blade.php` is split: `landing.blade.php` becomes a thin wrapper around a `<x-public-layout>` component, and the shared head/nav/footer/schema partials move to `partials/`.
- **Assets**: `public/og/home-1200x630.jpg` (new) plus per-landing OG images (optional in v1 — at minimum the home OG image is replaced).
- **Sitemap**: `public/sitemap.xml` becomes a route-served response, not a static file.
- **SEO**: Direct impact on Google Search Console metrics (indexed URLs, impressions, clicks) and on LLM citation frequency for the target verticals.
- **No DB changes.** No new dependencies. Pure Blade + a controller.
- **Out of scope** (tracked for follow-up changes, not in this one): blog CMS/database, comment system, author accounts, multilingual (`hreflang`), programmatic city pages, linkbuilding/PR, YouTube embeds, testimonials database.