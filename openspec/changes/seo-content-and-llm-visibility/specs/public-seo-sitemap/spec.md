## ADDED Requirements

### Requirement: Dynamic sitemap.xml
The system SHALL serve `/sitemap.xml` as a route response (not as a static file) that returns a valid XML sitemap containing every public URL registered in `config('seo.public_urls')`. The response SHALL use `Content-Type: application/xml` and SHALL include the `xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"` namespace.

#### Scenario: Sitemap is served with correct content type
- **WHEN** a crawler requests `/sitemap.xml`
- **THEN** the response has `Content-Type: application/xml` and the body parses as valid XML containing a `<urlset>` root element

#### Scenario: Sitemap lists every public URL
- **WHEN** the system renders `/sitemap.xml`
- **THEN** the response body contains a `<url>` entry with `<loc>https://cloudstream.mediaserver.com.co/{path}</loc>` for every entry in `config('seo.public_urls')`

### Requirement: Per-URL priority and changefreq
The system SHALL assign each public URL a `priority` and `changefreq` based on its type. The home SHALL have `priority: 1.0, changefreq: weekly`. Vertical landing pages SHALL have `priority: 0.9, changefreq: monthly`. Blog articles SHALL have `priority: 0.7, changefreq: monthly`. FAQ and About pages SHALL have `priority: 0.5, changefreq: monthly`.

#### Scenario: Home has the highest priority
- **WHEN** the system renders `/sitemap.xml`
- **THEN** the `<url>` entry for `/` has `<priority>1.0</priority>` and `<changefreq>weekly</changefreq>`

#### Scenario: Landing pages have priority 0.9
- **WHEN** the system renders `/sitemap.xml`
- **THEN** every `<url>` entry whose path matches a landing page slug has `<priority>0.9</priority>` and `<changefreq>monthly</changefreq>`

#### Scenario: Blog posts have priority 0.7
- **WHEN** the system renders `/sitemap.xml`
- **THEN** every `<url>` entry whose path starts with `/blog/` and is not `/blog` itself has `<priority>0.7</priority>` and `<changefreq>monthly</changefreq>`

### Requirement: Sitemap lastmod is set per URL
The system SHALL include a `<lastmod>` element in every `<url>` entry, set to the publication date for blog articles and to the current date for the home, landings, FAQ, and about.

#### Scenario: Every URL has a lastmod
- **WHEN** the system renders `/sitemap.xml`
- **THEN** every `<url>` entry contains a `<lastmod>` element in ISO 8601 date format (`YYYY-MM-DD`)

### Requirement: Sitemap excludes auth-gated and admin routes
The system SHALL NOT include any URL under `/admin`, `/client`, `/profile`, `/login`, `/register`, `/dashboard`, `/api`, or any other auth-gated path in the sitemap.

#### Scenario: No auth-gated URL appears in the sitemap
- **WHEN** the system renders `/sitemap.xml`
- **THEN** no `<loc>` element starts with `/admin`, `/client`, `/profile`, `/login`, `/register`, `/dashboard`, or `/api`

### Requirement: robots.txt references the sitemap
The system SHALL serve `/robots.txt` with a `Sitemap: https://cloudstream.mediaserver.com.co/sitemap.xml` directive so crawlers can discover the sitemap automatically.

#### Scenario: robots.txt declares the sitemap location
- **WHEN** a crawler requests `/robots.txt`
- **THEN** the response body contains the line `Sitemap: https://cloudstream.mediaserver.com.co/sitemap.xml`

### Requirement: robots.txt disallows auth-gated paths
The system SHALL continue to disallow `/admin`, `/client`, `/profile`, `/login`, and `/register` in `robots.txt` so those paths are not crawled or indexed.

#### Scenario: Auth-gated paths are disallowed
- **WHEN** a crawler reads `/robots.txt`
- **THEN** the response body contains `Disallow: /admin`, `Disallow: /client`, `Disallow: /profile`, `Disallow: /login`, and `Disallow: /register`