# Spec Delta

## MODIFIED Requirements

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

## ADDED Requirements

### Requirement: Home page shows the YouTube walkthrough with four steps
The home page SHALL render its "Emite a YouTube en 4 pasos" section as a video walkthrough with exactly four steps describing, in order: connecting the YouTube account, creating the destination, programming the emission window, and starting and sharing the emission.

#### Scenario: Four steps are rendered with the expected subjects
- **WHEN** a visitor requests `/`
- **THEN** the section renders exactly four step headings covering the connection of the account, the creation of the destination, the programming of the emission window, and the start and sharing of the emission

#### Scenario: Each step describes the real product behavior
- **WHEN** the text of the four steps is read
- **THEN** the connection step mentions Google OAuth, the destination step mentions the connected account and the slot, the programming step mentions start, end, time zone, title, privacy and thumbnail, and the sharing step mentions the automatic start, the automatic stop and the publication on the YouTube channel

#### Scenario: The slot concept is explained
- **WHEN** the text of the walkthrough section is read
- **THEN** it states that each active destination consumes one slot and that the number of slots depends on the contracted plan

#### Scenario: The section embeds the walkthrough video
- **WHEN** a visitor requests `/`
- **THEN** the section contains a `<video>` element and the four step items

### Requirement: Home page shows a screenshot for every step of the walkthrough
The home page SHALL render, for each of the four steps of the YouTube walkthrough, a screenshot of the interface that step shows, published as a first-party image next to the step's instructions.

#### Scenario: Each step shows its own screenshot
- **WHEN** a visitor requests `/`
- **THEN** each of the four steps exposes an image path of its own, and the four paths are distinct

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
