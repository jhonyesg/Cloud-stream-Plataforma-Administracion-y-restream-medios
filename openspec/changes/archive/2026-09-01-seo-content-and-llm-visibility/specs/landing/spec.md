## ADDED Requirements

### Requirement: Home page has exactly one H1
The system SHALL render the home page at `/` with exactly one `<h1>` element in the HTML body. The `<h1>` text SHALL contain the primary keyword phrase "señal de TV en vivo por Internet" and the location "Colombia".

#### Scenario: Home page has one H1 with the primary keyword
- **WHEN** a visitor (unauthenticated) requests `/`
- **THEN** the response body contains exactly one `<h1>` element and its text contains both "señal de TV en vivo" and "Colombia"

### Requirement: Home page meta tags are present and non-empty
The system SHALL render the home page with a `<title>`, `<meta name="description">`, `<meta property="og:title">`, `<meta property="og:description">`, `<meta property="og:image">`, `<meta property="og:url">`, `<meta name="twitter:card">`, and `<link rel="canonical">`. None of these elements SHALL be empty.

#### Scenario: Home page has a non-empty title under 70 characters
- **WHEN** a visitor requests `/`
- **THEN** the response body contains `<title>` with text between 30 and 70 characters inclusive

#### Scenario: Home page has a non-empty meta description between 120 and 165 characters
- **WHEN** a visitor requests `/`
- **THEN** the response body contains `<meta name="description" content="...">` whose `content` attribute is between 120 and 165 characters inclusive

#### Scenario: Home page og:image points to a real image asset
- **WHEN** a visitor requests `/`
- **THEN** the response body contains `<meta property="og:image" content="https://cloudstream.mediaserver.com.co/og/home-1200x630.jpg">` (or a similarly sized real image, never `favicon.svg`)

#### Scenario: Home page has a canonical link
- **WHEN** a visitor requests `/`
- **THEN** the response body contains `<link rel="canonical" href="https://cloudstream.mediaserver.com.co/">`

### Requirement: Home page emits comprehensive JSON-LD
The system SHALL emit a single `<script type="application/ld+json">` on the home page whose body is valid JSON containing a `@graph` with at least: `Organization` (Media Clouding SAS, NIT, Bogotá, `telephone: "+57 312 408 2557"`), `WebSite` (with `SearchAction` if applicable), `Service` (streaming de señal de TV), `Offer` × 3 (one per plan) inside an `AggregateOffer`, `FAQPage` (at least 6 questions), `BreadcrumbList`, and `ContactPoint` with `telephone` and `contactType: "customer support"`.

#### Scenario: Home JSON-LD parses and contains all required entities
- **WHEN** the system renders `/`
- **THEN** the `<script type="application/ld+json">` body parses as JSON and the `@graph` array contains entries for `Organization`, `WebSite`, `Service`, `Offer`, `FAQPage`, `BreadcrumbList`, and `ContactPoint`

#### Scenario: Home JSON-LD Offer prices match the visible HTML
- **WHEN** the system renders `/`
- **THEN** for every `Offer` in the JSON-LD `@graph`, the `price` field equals the corresponding price displayed in the visible plan card on the same page

### Requirement: Home page WhatsApp CTA uses a single price source
The system SHALL define plan prices in `config/seo.php` under the `plans` key and SHALL use those values both in the visible HTML and in the WhatsApp anchor URL.

#### Scenario: Plan 1 price is consistent across visible HTML and WhatsApp link
- **WHEN** the system renders `/`
- **THEN** the visible plan-1 price string equals the value embedded in the WhatsApp URL for that plan, and both equal `config('seo.plans.plan1.price_cop')`

#### Scenario: Plan 2 price is consistent across visible HTML and WhatsApp link
- **WHEN** the system renders `/`
- **THEN** the visible plan-2 price string equals the value embedded in the WhatsApp URL for that plan, and both equal `config('seo.plans.plan2.price_cop')`

### Requirement: Home page exposes a tel: link
The system SHALL render a `tel:+573124082557` link as a secondary CTA on the home page (visible in the hero or contact section).

#### Scenario: Home page has a tel: link
- **WHEN** a visitor requests `/`
- **THEN** the response body contains `<a href="tel:+573124082557">`

### Requirement: Home page no longer references favicon.svg as og:image
The system SHALL NOT use `/favicon.svg` as the `og:image` value on the home page.

#### Scenario: og:image is not the favicon
- **WHEN** a visitor requests `/`
- **THEN** `<meta property="og:image" content="...">` does NOT contain the substring `favicon.svg`

### Requirement: Home page uses a shared public layout component
The system SHALL render the home page through a `<x-public-layout>` Blade component that owns the shared `<head>` (meta, OG, Twitter, canonical, JSON-LD) and the shared nav/footer. The home blade file SHALL be reduced to content-only fragments passed to the component as slots and props.

#### Scenario: Home view delegates head meta to the layout component
- **WHEN** the home blade file is inspected
- **THEN** it uses `<x-public-layout :title="…" :description="…" :schema="…">` (or equivalent) and does NOT redefine `<meta name="description">`, `<meta property="og:*">`, or the `<script type="application/ld+json">` directly

#### Scenario: Layout component renders JSON-LD from a typed prop
- **WHEN** the layout component is rendered with `:schema="['type' => 'home', 'data' => […]]"`
- **THEN** the emitted `<head>` contains exactly one `<script type="application/ld+json">` whose body is the JSON-encoded `@graph` built from the typed `data` array