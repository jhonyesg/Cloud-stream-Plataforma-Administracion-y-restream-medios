# public-delivery-urls Specification

## Purpose
Define the observable behavior of the public showcase of the emission and delivery protocols: the matrix of URLs a customer receives for publishing a signal (RTMP, SRT) and for consuming it (HLS, FLV, WebRTC, MPEG-TS, SRT), the masked showcase host and placeholder channel that keep production endpoints out of the public pages, the per-row copy control, and the explanations of the MPEG-TS path for decoders and cable trunks.

## Requirements

### Requirement: Public site publishes the emission and delivery protocol matrix
The system SHALL publish, on a public page, a delivery showcase composed of two blocks: an **emission** block that lists the protocols an encoder uses to send the signal to Cloudstream, and a **delivery** block that lists the protocols a viewer uses to receive it. Each block SHALL name the software compatible with it.

#### Scenario: Both blocks are rendered with their headings
- **WHEN** a visitor requests a public page that includes the showcase
- **THEN** the response contains an emission block titled "PUBLICAR" and a delivery block titled "CONSUMIR"

#### Scenario: The emission block lists the ingest protocols and their ports
- **WHEN** the text of the emission block is read
- **THEN** it lists an RTMP ingest URL on port 1935 and an SRT ingest URL on port 10080, and it names OBS, FMLE, vMix and Wirecast as compatible encoders

#### Scenario: The delivery block lists the playback protocols
- **WHEN** the text of the delivery block is read
- **THEN** it lists HLS, FLV, WebRTC, MPEG-TS and SRT, and it names vMix, OBS, Astra, Lastra, FFplay and VLC as compatible players

#### Scenario: The delivery block distinguishes the transport variants
- **WHEN** the delivery block is rendered
- **THEN** the HLS, FLV, WebRTC and MPEG-TS rows are offered over HTTPS and over plain HTTP, the HTTPS group is marked as the recommended one served through a reverse proxy with cache, and the SRT row is marked as the direct variant

### Requirement: Published URLs are built from a single public configuration source
The system SHALL build every published URL from a single public configuration source that declares the showcase host, the protocol ports and the example channel name, and SHALL NOT hardcode hosts, ports or channel names in the view.

#### Scenario: The configuration declares the showcase host, ports and example channel
- **WHEN** the public configuration is read
- **THEN** it declares the showcase host, the RTMP port, the SRT publish port, the SRT play port and the example channel name

#### Scenario: The rendered URLs follow the configuration
- **WHEN** a visitor requests a public page that includes the showcase
- **THEN** every rendered URL contains the configured showcase host and the configured example channel name, and the rendered ports match the configured ports

### Requirement: Published URLs never expose production endpoints or customer data
The system SHALL render the showcase URLs with a showcase host and a placeholder channel name. No published page SHALL contain the production streaming host, the production server IP address, the name of a real customer stream, a customer name or a stream key.

#### Scenario: The production server IP is not published
- **WHEN** the published HTML of every public page is inspected
- **THEN** it does not contain the production server IP address

#### Scenario: The production streaming host is not published
- **WHEN** the published HTML of every public page is inspected
- **THEN** it does not contain the production streaming host name

#### Scenario: A placeholder channel name is used instead of a customer stream
- **WHEN** the published HTML is inspected
- **THEN** every published URL uses the configured placeholder channel name and no customer stream name appears in the document

#### Scenario: No credential is published
- **WHEN** the published HTML is inspected
- **THEN** it contains no stream key, no account e-mail and no person's name

### Requirement: Published URLs are templates and the section says so
The system SHALL present the published URLs as the exact format the customer receives, SHALL state that the channel name and the stream key are delivered when the plan is activated, and the showcase host SHALL be different from the production streaming host.

#### Scenario: The section labels the URLs as templates
- **WHEN** the text of the showcase is read
- **THEN** it states that the displayed URLs are examples of the format that is delivered and that the real hosts and keys are handed over with the activation of the plan

#### Scenario: The showcase host differs from the production host
- **WHEN** the public configuration is read
- **THEN** the configured showcase host is not the production streaming host

### Requirement: Every protocol row exposes an accessible copy control
Each published protocol row SHALL expose a copy control that copies that row's URL template to the clipboard, SHALL carry an accessible label naming the protocol it copies, and the interface SHALL tell the user that the placeholder channel name has to be replaced.

#### Scenario: Each row has a labelled copy control
- **WHEN** a visitor requests a public page that includes the showcase
- **THEN** every protocol row exposes a copy control whose accessible label names the protocol

#### Scenario: The copy control copies the URL of its own row
- **WHEN** the user activates the copy control of a protocol row
- **THEN** the clipboard receives exactly the URL template rendered in that row

#### Scenario: The user is told to replace the placeholder
- **WHEN** the copy confirmation is shown
- **THEN** it states that the placeholder channel name has to be replaced by the channel name

### Requirement: The showcase explains the MPEG-TS path for decoders and cable trunks
The showcase SHALL explain that MPEG-TS is the format spoken by set-top decoders such as Astra, Lastra and Cesbo, that vMix can also consume the signal through this route, that it is the standard way to deliver a signal to a cable operator, a cable trunk or a satellite capture, and that it does not depend on SRT or on external plugins.

#### Scenario: The MPEG-TS explanation is present
- **WHEN** the text of the showcase is read
- **THEN** it names Astra, Lastra and Cesbo as the decoders, states that vMix can also consume the signal through it, and mentions the delivery to a cable operator, a cable trunk and a satellite capture

### Requirement: The showcase explains how the signal is handled end to end
The showcase SHALL describe the flow of the signal: the customer emits once from their own equipment, the signal enters the datacenter over RTMP or SRT, and Cloudstream re-publishes it in every delivery format.

#### Scenario: The flow is described
- **WHEN** the text of the showcase is read
- **THEN** it states that the customer emits from their own equipment with OBS, vMix or Wirecast, that the signal reaches the datacenter over RTMP or SRT, and that Cloudstream delivers it in the published formats

### Requirement: The showcase is a reusable public component
The showcase SHALL be renderable on any public page through a single component without editing the view, and the home page SHALL render it with its default content.

#### Scenario: The component renders standalone
- **WHEN** the showcase component is rendered on its own
- **THEN** it produces the complete showcase markup with both blocks and all protocol rows

#### Scenario: The home page renders the component
- **WHEN** a visitor requests the home page
- **THEN** the showcase markup is produced by the shared component and not by duplicated markup inside the home view
