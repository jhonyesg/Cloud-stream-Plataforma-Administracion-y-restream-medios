## ADDED Requirements

### Requirement: Consolidated FAQ page
The system SHALL serve a public FAQ page at `/preguntas-frecuentes` that aggregates the most-asked questions across all verticals and product surfaces. The page SHALL contain at least 15 questions organized by category (Planes y precios, Streaming técnico, Restream, Cuñas y programador, Soporte). Each question SHALL be rendered inside `<details>`/`<summary>` HTML elements so the content is crawlable AND interactive.

#### Scenario: FAQ page returns 200
- **WHEN** a visitor requests `/preguntas-frecuentes`
- **THEN** the system returns HTTP 200 with the FAQ content visible in the HTML body (not lazy-loaded)

#### Scenario: FAQ page emits FAQPage JSON-LD
- **WHEN** the system renders `/preguntas-frecuentes`
- **THEN** the HTML contains a `<script type="application/ld+json">` whose body parses as JSON and contains `@type: "FAQPage"` with at least 15 `Question` entries, each with `name` and an `acceptedAnswer` containing `text`

### Requirement: FAQ controller
The system SHALL provide a `faq()` action on the public SEO controller that renders the FAQ blade view with the correct `<head>` populated (title, description, canonical, OG, Twitter, FAQPage JSON-LD).

#### Scenario: FAQ page is not auth-gated
- **WHEN** a visitor without an authenticated session requests `/preguntas-frecuentes`
- **THEN** the system returns HTTP 200

#### Scenario: FAQ page links back to the home and to relevant landings
- **WHEN** the system renders `/preguntas-frecuentes`
- **THEN** the footer or sidebar contains links to `/` and to the most relevant vertical landing pages (at minimum `/streaming-para-iglesias-colombia` and `/restream-facebook-youtube-tiktok`)