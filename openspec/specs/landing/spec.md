## Purpose

Define the observable behavior of the public home page (`/`): its single `<h1>`, its SEO head and social metadata, its JSON-LD graph, the pricing and contact calls to action, and the YouTube walkthrough section that explains the restream module to a first-time visitor.

## Requirements

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
The system SHALL emit a single `<script type="application/ld+json">` on the home page whose body is valid JSON containing a `@graph` with at least: `Organization` (Media Clouding SAS, NIT, Bogotá, `telephone: "+57 312 408 2557"`), `WebSite` (with `SearchAction` if applicable), `Service` (streaming de señal de TV), `Offer` × 3 (one per plan) inside an `AggregateOffer`, `FAQPage` (at least 6 questions), `BreadcrumbList`, `ContactPoint` with `telephone` and `contactType: "customer support"`, and a `VideoObject` describing the YouTube walkthrough video (name, description, thumbnail URL, content URL, upload date and duration).

#### Scenario: Home JSON-LD parses and contains all required entities
- **WHEN** the system renders `/`
- **THEN** the `<script type="application/ld+json">` body parses as JSON and the `@graph` array contains entries for `Organization`, `WebSite`, `Service`, `Offer`, `FAQPage`, `BreadcrumbList`, `ContactPoint` and `VideoObject`

#### Scenario: Home JSON-LD Offer prices match the visible HTML
- **WHEN** the system renders `/`
- **THEN** for every `Offer` in the JSON-LD `@graph`, the `price` field equals the corresponding price displayed in the visible plan card on the same page

#### Scenario: Home JSON-LD VideoObject points at the published walkthrough
- **WHEN** the system renders `/`
- **THEN** the `VideoObject` entry carries a `contentUrl` and a `thumbnailUrl` that are first-party paths of the walkthrough media actually referenced by the page

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

### Requirement: Home page shows a screenshot for every step of the walkthrough
The home page SHALL render, for each of the six steps of the YouTube walkthrough, a screenshot of the interface that step shows, published as a first-party image next to the step's instructions, and the six captures SHALL come from the same recorded session.

#### Scenario: Each step shows its own screenshot
- **WHEN** a visitor requests `/`
- **THEN** each of the six steps exposes an image path of its own, and the six paths are distinct

#### Scenario: The step screenshot exists on disk
- **WHEN** the image referenced by a step is resolved on the server
- **THEN** the file exists and is readable

### Requirement: Home page walkthrough media is a local asset with a poster
The home page SHALL reference the walkthrough video and its poster from first-party paths and SHALL NOT reference any third-party video host.

#### Scenario: Video and poster are first-party
- **WHEN** a visitor inspects the walkthrough markup on `/`
- **THEN** the `<video>` element's `src` and its `poster` attribute are paths on the site's own origin

### Requirement: Home page advertises the walkthrough video in social metadata
The home page SHALL emit `og:video`, `og:video:type` and `og:video:poster` metadata pointing at the walkthrough media.

#### Scenario: OG video metadata is present
- **WHEN** a visitor requests `/`
- **THEN** the response body contains `og:video`, `og:video:type` and `og:video:poster` with non-empty values

### Requirement: Home page shows the YouTube walkthrough with six steps
The home page SHALL render its "Emite a YouTube en 6 pasos" section as a video walkthrough with exactly six steps describing, in order: the Restream panel, connecting the YouTube account, creating the destination, programming the emission window, scheduling several windows for the same destination, and starting and sharing the emission.

#### Scenario: Six steps are rendered with the expected subjects
- **WHEN** a visitor requests `/`
- **THEN** the section renders exactly six step headings covering the panel, the connection of the account, the creation of the destination, the programming of the emission window, the scheduling of several windows, and the start and sharing of the emission

#### Scenario: Each step describes the real product behavior
- **WHEN** the text of the six steps is read
- **THEN** the connection step mentions Google OAuth, the destination step mentions the connected account and the slot, the programming step mentions start, end, time zone, title, privacy and thumbnail, the windows step states that a destination accepts several windows, and the sharing step mentions the automatic start, the automatic stop and the publication on the YouTube channel

#### Scenario: The slot concept is explained
- **WHEN** the text of the walkthrough section is read
- **THEN** it states that each active destination consumes one slot and that the number of slots depends on the contracted plan

#### Scenario: The section embeds the walkthrough video
- **WHEN** a visitor requests `/`
- **THEN** the section contains a `<video>` element and the six step items

### Requirement: Home page publishes no screenshot with third-party personal data
The system SHALL NOT publish on the public site any screenshot that contains third-party personal data (a person's name, a personal or customer account e-mail, credentials, or unrelated private content).

#### Scenario: The published home HTML carries no personal data
- **WHEN** a visitor requests `/`
- **THEN** the response body contains no person's name, no personal or customer account e-mail and no stream key

#### Scenario: The legacy unsanitized captures are not published
- **WHEN** the images referenced by the home page are listed
- **THEN** no reference exists to the removed captures that showed the customer's account name and e-mail

### Requirement: Home page shows the delivery formats section between pricing and FAQ
The home page SHALL render the public delivery showcase as its own section, positioned after the "Planes y precios" block and before the "Preguntas frecuentes" block, and the section SHALL be addressable through the `formatos` anchor that the public navigation links to.

#### Scenario: The section sits between pricing and FAQ
- **WHEN** a visitor requests `/`
- **THEN** the position of the delivery showcase in the document is after the position of the pricing block and before the position of the frequently-asked-questions block

#### Scenario: The section is addressable
- **WHEN** a visitor requests `/`
- **THEN** the showcase section exposes the identifier `formatos` and the public navigation contains a link whose target ends with `#formatos`

#### Scenario: The section does not add a second H1
- **WHEN** a visitor requests `/`
- **THEN** the response body still contains exactly one `<h1>` element

#### Scenario: The section keeps the published page free of production endpoints
- **WHEN** the published HTML of `/` is inspected
- **THEN** it contains neither the production server IP address nor the production streaming host name
