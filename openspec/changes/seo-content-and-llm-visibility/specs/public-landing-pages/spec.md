## ADDED Requirements

### Requirement: Vertical landing pages
The system SHALL serve seven public landing pages, one per vertical or product surface, at the following URLs. Each page SHALL have a unique `<h1>`, a unique introductory paragraph of at least 120 words specific to the vertical, a unique FAQ section with at least 4 questions, and a JSON-LD `@graph` containing `Organization`, `WebSite`, `Service`, `Offer`, `FAQPage`, and `BreadcrumbList` for that page.

- `/streaming-para-iglesias-colombia`
- `/streaming-para-emisoras-de-radio-online`
- `/streaming-para-canales-de-tv-regionales`
- `/streaming-para-universidades-y-educacion`
- `/streaming-para-productoras-de-contenido`
- `/restream-facebook-youtube-tiktok`
- `/servidor-rtmp-colombia`

#### Scenario: Each landing page is reachable and returns 200
- **WHEN** a visitor requests any of the seven URLs above
- **THEN** the system returns HTTP 200 with `Content-Type: text/html; charset=UTF-8` and the page contains exactly one `<h1>` element

#### Scenario: Each landing page has a unique H1
- **WHEN** the system renders any two distinct landing pages
- **THEN** the text content of their `<h1>` elements differs

#### Scenario: Each landing page emits per-page JSON-LD
- **WHEN** the system renders any landing page
- **THEN** the HTML contains exactly one `<script type="application/ld+json">` whose body parses as valid JSON and contains a `@graph` array with at least `Organization`, `Service`, `Offer`, `FAQPage`, and `BreadcrumbList`

#### Scenario: Each landing page links back to the home and the FAQ
- **WHEN** the system renders any landing page
- **THEN** the footer contains a link to `/` and to `/preguntas-frecuentes` with descriptive anchor text

### Requirement: Landing page controller
The system SHALL provide a `PublicSeoController` (or equivalent) with a `landing(string $slug)` action that resolves a slug to one of the seven landing blade views and returns the page with the correct `<head>` (title, description, canonical, OG, Twitter, JSON-LD) populated.

#### Scenario: Unknown landing slug returns 404
- **WHEN** a visitor requests a landing URL whose slug does not match any registered landing
- **THEN** the system returns HTTP 404

#### Scenario: Canonical URL is set per landing
- **WHEN** the system renders the page at `/streaming-para-iglesias-colombia`
- **THEN** the HTML contains `<link rel="canonical" href="https://cloudstream.mediaserver.com.co/streaming-para-iglesias-colombia">`

### Requirement: Landing pages are crawlable and not auth-gated
The system SHALL NOT require authentication, session, or any middleware other than the global web middleware to view a landing page. The route SHALL NOT appear in `robots.txt` `Disallow`.

#### Scenario: Anonymous visitor can view a landing page
- **WHEN** a visitor without an authenticated session requests `/restream-facebook-youtube-tiktok`
- **THEN** the system returns HTTP 200 and the full page body (not a login redirect)

#### Scenario: Landing pages are not disallowed for crawlers
- **WHEN** a crawler reads `/robots.txt`
- **THEN** no landing page URL appears under any `Disallow` directive