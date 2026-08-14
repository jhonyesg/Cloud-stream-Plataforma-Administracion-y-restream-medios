## ADDED Requirements

### Requirement: Admin channels index exposes create and per-row actions
The admin channels index view SHALL render a `[+ Nuevo canal]` button and per-row `[Editar]` / `[Eliminar]` actions. Both actions SHALL open modals.

#### Scenario: Create button is visible on channels index
- **WHEN** an admin loads `GET /admin/channels`
- **THEN** the rendered HTML SHALL contain a control labeled "Nuevo canal" whose click handler opens a modal (not a navigation)

#### Scenario: Row actions are rendered for every channel
- **WHEN** an admin loads `GET /admin/channels` with N channels
- **THEN** the rendered HTML SHALL contain N pairs of "Editar" / "Eliminar" controls (one pair per channel row)

### Requirement: Admin can create a channel via modal
The system SHALL accept `POST /admin/channels` with: `display_name`, optional auto-generated `slug` (or explicit), `owner_id` (required), `status` (`active|draft|suspended|archived`), `default_width` (int), `default_height` (int). On success the modal SHALL close and the row SHALL appear in the table.

#### Scenario: Successful channel creation
- **WHEN** an admin submits a valid channel payload to `POST /admin/channels`
- **THEN** the server SHALL respond 2xx, the row SHALL be visible on next render, and the modal SHALL close

#### Scenario: Validation errors surface inline
- **WHEN** the payload is invalid (e.g. `default_width < 1`, missing owner, slug already taken)
- **THEN** the server SHALL return 422 with field errors AND the modal SHALL remain open displaying them

### Requirement: Admin can update a channel via modal
The system SHALL accept `PUT /admin/channels/{id}` with the same field set. On success the modal SHALL close and the row SHALL reflect the new values.

#### Scenario: Updating status updates the row badge
- **WHEN** an admin changes a channel's status from "active" to "suspended" via the edit modal
- **THEN** the corresponding row badge SHALL render the "suspended" style on next render

### Requirement: Admin can delete a channel via confirmation modal
The system SHALL accept `DELETE /admin/channels/{id}`. The `[Eliminar]` action SHALL open a confirmation modal naming the channel. Confirmation SHALL dispatch `DELETE`. On success the row SHALL disappear.

#### Scenario: Confirmed channel delete removes the row
- **WHEN** the admin confirms deletion of a channel
- **THEN** the server SHALL respond 2xx, the modal SHALL close, and the row SHALL disappear from the table on next render

### Requirement: Non-admin users cannot reach admin channel endpoints
Users without `role=admin` SHALL receive 403 on any `POST/PUT/DELETE /admin/channels*`.

#### Scenario: Client cannot create a channel
- **WHEN** a user with `role=client` calls `POST /admin/channels`
- **THEN** the server SHALL respond 403 and no channel SHALL be created## ADDED Requirements

### Requirement: Admin channels list excludes Pantalla Virtual action
The system SHALL NOT render the "Pantalla Virtual" button or include `<x-virtual-screen-editor-modal />` in the admin channels index view. The virtual screen editor is configured from the Emisión module, not from the Channels CRUD module.

#### Scenario: Admin channels index has no Pantalla Virtual button
- **WHEN** an admin loads `GET /admin/channels`
- **THEN** the rendered HTML SHALL NOT contain any button labeled "Pantalla Virtual" and SHALL NOT include the `<x-virtual-screen-editor-modal />` component

#### Scenario: Admin channels row actions are limited to channel CRUD
- **WHEN** an admin loads `GET /admin/channels`
- **THEN** the per-row actions SHALL include only "Pantalla Virtual" removal AND MAY include Pantalla Virtual relocation in Emisión module. The channels CRUD actions (Editar, Archivar) SHALL remain