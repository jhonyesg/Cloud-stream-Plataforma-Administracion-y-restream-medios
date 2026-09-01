## ADDED Requirements

### Requirement: About page
The system SHALL serve a public `/sobre-nosotros` page that contains the company name (Media Clouding SAS), the NIT, the city (Bogotá, Colombia), the datacenter region (USA), the founding story (1 short paragraph), the contact phone (`+57 312 408 2557`), and the link to the WhatsApp CTA. The page SHALL be at least 400 words of unique Spanish copy.

#### Scenario: About page returns 200 and emits AboutPage JSON-LD
- **WHEN** a visitor requests `/sobre-nosotros`
- **THEN** the system returns HTTP 200 and the HTML contains a `<script type="application/ld+json">` whose body parses as JSON and contains `@type: "AboutPage"` referencing the `Organization` (Media Clouding SAS) by `@id`

#### Scenario: About page mentions NIT, city, and datacenter region
- **WHEN** the system renders `/sobre-nosotros`
- **THEN** the visible body text contains the strings "Media Clouding SAS", "Bogotá", "Colombia", and at least one mention of the datacenter region ("Estados Unidos" or "datacenter americano")

### Requirement: About controller
The system SHALL provide an `about()` action on the public SEO controller that renders the about blade view with the correct `<head>` populated.

#### Scenario: About page is not auth-gated
- **WHEN** a visitor without an authenticated session requests `/sobre-nosotros`
- **THEN** the system returns HTTP 200

#### Scenario: About page links to home and FAQ
- **WHEN** the system renders `/sobre-nosotros`
- **THEN** the footer contains links to `/` and `/preguntas-frecuentes`