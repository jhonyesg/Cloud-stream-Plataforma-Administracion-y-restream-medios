# Spec Delta

## ADDED Requirements

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
