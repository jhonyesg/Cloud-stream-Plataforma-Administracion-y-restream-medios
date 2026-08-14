## MODIFIED Requirements

### Requirement: Channel has an absolute root_path field
The system SHALL persist a `root_path` string field on the `channels` table representing an absolute filesystem path on the server (e.g. `/mnt/multimedia/cine-dios`). The path is independent of `MediaProvider` and `MediaFolder`.

#### Scenario: First upload auto-creates a MediaFolder bound to the channel
- **WHEN** a user uploads a file to a channel C whose `root_path = "/mnt/multimedia/cine-dios"` and no `MediaFolder` exists linking C to any provider
- **THEN** the server SHALL auto-create a `MediaFolder` row with `provider_id` = the system's default `MediaProvider` (or the explicit one if supplied), `rel_path = "cine-dios"` (computed from `root_path` minus the provider's `base_path`), `channel_id = C.id`, `name = "cine-dios"`. The file SHALL be written under that folder.

#### Scenario: Bulk upload reuses the same folder across multiple files
- **WHEN** a user uploads 3 files to channel C (with auto-created folder)
- **THEN** all 3 files SHALL be written under the SAME auto-created MediaFolder; the rel_path of the MediaFolder SHALL NOT change between uploads.

### Requirement: Channel edit modal exposes the root_path field
The system SHALL render a "Ruta" input and an "Explorar" button in the channel create/edit modal. The Explorer's selected path SHALL be persisted into `root_path` on submit.

#### Scenario: Saving channel with ruta via Explorer persists correctly
- **WHEN** the admin selects `/mnt/multimedia/cine-dios` via the Explorer and saves the channel
- **THEN** `Channel.root_path = "/mnt/multimedia/cine-dios"` and the system is ready to accept uploads to this channel via the multimedia UI