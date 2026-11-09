# Spec Delta

## REMOVED Requirements

### Requirement: Home page shows the YouTube walkthrough with four steps
**Reason**: La sección mostraba seis imágenes (cuatro capturas del video más dos capturas estáticas antiguas que no correspondían a ningún paso) y solo cuatro pasos, de modo que las capturas no coordinaban con la narrativa.
**Migration**: La sustituye el requisito "Home page shows the YouTube walkthrough with six steps", que describe el mismo flujo en seis pasos y le asigna a cada uno su propia captura.

## MODIFIED Requirements

### Requirement: Home page shows a screenshot for every step of the walkthrough
The home page SHALL render, for each of the six steps of the YouTube walkthrough, a screenshot of the interface that step shows, published as a first-party image next to the step's instructions, and the six captures SHALL come from the same recorded session.

#### Scenario: Each step shows its own screenshot
- **WHEN** a visitor requests `/`
- **THEN** each of the six steps exposes an image path of its own, and the six paths are distinct

#### Scenario: The step screenshot exists on disk
- **WHEN** the image referenced by a step is resolved on the server
- **THEN** the file exists and is readable

## ADDED Requirements

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
