## ADDED Requirements

### Requirement: Blog index
The system SHALL serve a public blog index at `/blog` that lists every published article with its title, slug, excerpt (first 160 chars of the article body, stripped of HTML), publication date, and a link to the full article. The index page SHALL emit `Blog` + `BreadcrumbList` + `ItemList` JSON-LD.

#### Scenario: Blog index returns 200 and lists all articles
- **WHEN** a visitor requests `/blog`
- **THEN** the system returns HTTP 200 and the page lists every article registered in `config('seo.public_urls')` whose `type` is `blog`

#### Scenario: Blog index has noindex duplicate protection
- **WHEN** the system renders `/blog?page=2` (paginated index)
- **THEN** the page contains `<link rel="canonical" href="https://cloudstream.mediaserver.com.co/blog">` to consolidate pagination signals

### Requirement: Blog article page
The system SHALL serve each blog article at `/blog/{slug}` where `{slug}` matches one of the ten pillar slugs from the SEO content map. Each article page SHALL be at least 800 words, contain a unique `<h1>`, an `Article` JSON-LD block (`@type: Article` or `BlogPosting`) with `headline`, `description`, `datePublished`, `author` (`Organization: Cloudstream`), `publisher` (`Organization: Cloudstream` with logo), `mainEntityOfPage`, and at least one `FAQPage` JSON-LD block at the end. Each article SHALL end with a CTA block linking to the most relevant plan or vertical landing page.

The ten pillar slugs SHALL be:

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

#### Scenario: Each article returns 200 and has a unique H1
- **WHEN** a visitor requests `/blog/{slug}` for any of the ten pillar slugs
- **THEN** the system returns HTTP 200 and the page contains exactly one `<h1>` whose text differs from every other article's `<h1>`

#### Scenario: Each article emits Article JSON-LD
- **WHEN** the system renders any blog article
- **THEN** the HTML contains a `<script type="application/ld+json">` whose body parses as JSON and includes `@type: "Article"` (or `BlogPosting`) with `headline`, `description`, `datePublished`, `author`, `publisher`

#### Scenario: Each article has a CTA to a plan or vertical
- **WHEN** the system renders any blog article
- **THEN** the last `<section>` of the article body contains a link with `rel="nofollow"` (or `rel="sponsored"` if applicable) to either the home (`/`), a landing page, or the WhatsApp contact link, with anchor text containing a keyword from the target landing page

### Requirement: Blog article controller
The system SHALL provide a `blog(string $slug)` action on the public SEO controller that resolves a slug to one of the ten pillar blade views and returns the page with the correct `<head>` populated.

#### Scenario: Unknown blog slug returns 404
- **WHEN** a visitor requests `/blog/{slug}` and `{slug}` is not one of the ten pillar slugs
- **THEN** the system returns HTTP 404

#### Scenario: Blog article is not auth-gated
- **WHEN** a visitor without an authenticated session requests `/blog/que-es-rtmp-hls`
- **THEN** the system returns HTTP 200 (no login redirect)

### Requirement: Blog articles are crawlable
The system SHALL NOT block any blog URL in `robots.txt`. The dynamic sitemap SHALL list every blog article with `changefreq: monthly` and `priority: 0.7`.

#### Scenario: Blog URLs are listed in the sitemap
- **WHEN** a crawler requests `/sitemap.xml`
- **THEN** the response body contains a `<url>` entry for every registered blog article

#### Scenario: Blog URLs are not disallowed
- **WHEN** a crawler reads `/robots.txt`
- **THEN** no `/blog/` path appears under any `Disallow` directive