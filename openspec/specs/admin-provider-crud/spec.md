## ADDED Requirements

### Requirement: Admin providers index exposes create and per-row actions
The admin providers index view SHALL render a `[+ Nuevo provider]` button and per-row `[Editar]` / `[Eliminar]` actions. Both actions SHALL open modals.

#### Scenario: Create button is visible on providers index
- **WHEN** an admin loads `GET /admin/providers`
- **THEN** the rendered HTML SHALL contain a control labeled "Nuevo provider" whose click handler opens a modal

#### Scenario: Row actions are rendered for every provider
- **WHEN** an admin loads `GET /admin/providers` with N providers
- **THEN** the rendered HTML SHALL contain N pairs of "Editar" / "Eliminar" controls

### Requirement: Admin can create a media provider via modal
The system SHALL accept `POST /admin/providers` with: `name` (unique), `type` (e.g. `local`, `remote`, `s3` — validated against an allowlist to be defined in design), `base_path` (string, filesystem path or URL pattern). On success the modal SHALL close and the row SHALL appear.

#### Scenario: Successful provider creation
- **WHEN** an admin submits a valid provider payload to `POST /admin/providers`
- **THEN** the server SHALL respond 2xx, the row SHALL be visible on next render, and the modal SHALL close

#### Scenario: Duplicate name is rejected
- **WHEN** an admin submits a provider with a `name` that already exists
- **THEN** the server SHALL return 422 with a `name` field error and the modal SHALL remain open

### Requirement: Admin can update a media provider via modal
The system SHALL accept `PUT /admin/providers/{id}` with the same field set. On success the modal SHALL close and the row SHALL reflect the new values.

#### Scenario: Updating type updates the row badge
- **WHEN** an admin changes a provider's `type` via the edit modal
- **THEN** the row's type badge SHALL render the new value on next render

### Requirement: Admin can delete a media provider via confirmation modal
The system SHALL accept `DELETE /admin/providers/{id}`. The `[Eliminar]` action SHALL open a confirmation modal naming the provider. Confirmation SHALL dispatch `DELETE`. On success the row SHALL disappear.

#### Scenario: Confirmed provider delete removes the row
- **WHEN** the admin confirms deletion of a provider
- **THEN** the server SHALL respond 2xx, the modal SHALL close, and the row SHALL disappear on next render

#### Scenario: Provider referenced by media items blocks deletion
- **WHEN** an admin attempts to delete a provider that is referenced by one or more `MediaItem` records (FK constraint or guard in controller)
- **THEN** the server SHALL respond 422 (or 409) with an explanatory error and the modal SHALL remain open displaying that error

### Requirement: Non-admin users cannot reach admin provider endpoints
Users without `role=admin` SHALL receive 403 on any `POST/PUT/DELETE /admin/providers*`.

#### Scenario: Client cannot create a provider
- **WHEN** a user with `role=client` calls `POST /admin/providers`
- **THEN** the server SHALL respond 403 and no provider SHALL be created