## Purpose
Defines the admin-facing CRUD behavior for managing channels: creation, editing, archiving (reversible inactivation), deletion (soft delete), and the per-row/table actions exposed on the admin channels index.
## Requirements
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
The system SHALL accept `DELETE /admin/channels/{id}`. The `[Eliminar]` action SHALL open a confirmation modal naming the channel and explaining that the channel will be hidden but the record is recoverable (soft delete). Confirmation SHALL dispatch `DELETE`, which SHALL soft-delete the channel (set `deleted_at`) rather than removing the row from the database. On success the row SHALL disappear from the index.

#### Scenario: Confirmed channel delete removes the row
- **WHEN** the admin confirms deletion of a channel
- **THEN** the server SHALL respond 2xx, set `deleted_at` to the current timestamp, the modal SHALL close, and the row SHALL disappear from the table on next render

#### Scenario: Soft-deleted channel is excluded from default listings
- **WHEN** a channel has `deleted_at` set
- **THEN** `GET /admin/channels` SHALL NOT include that channel in its default results (no `withTrashed()`/`onlyTrashed()` call)

#### Scenario: Soft-deleted channel row is not physically removed
- **WHEN** an admin deletes a channel via this action
- **THEN** the row SHALL still exist in the `channels` table with all its original column values, only `deleted_at` changed

#### Scenario: Deleting does not cascade to related records
- **WHEN** an admin soft-deletes a channel that has related `playlists`, `virtual_screens`, or `restream_targets`
- **THEN** those related rows SHALL remain unchanged (no FK cascade fires, since no `DELETE FROM channels` statement is executed)

### Requirement: Non-admin users cannot reach admin channel endpoints
Users without `role=admin` SHALL receive 403 on any `POST/PUT/DELETE /admin/channels*`.

#### Scenario: Client cannot create a channel
- **WHEN** a user with `role=client` calls `POST /admin/channels`
- **THEN** the server SHALL respond 403 and no channel SHALL be created

### Requirement: Admin channels list excludes Pantalla Virtual action
The system SHALL NOT render the "Pantalla Virtual" button or include `<x-virtual-screen-editor-modal />` in the admin channels index view. The virtual screen editor is configured from the Emisión module, not from the Channels CRUD module.

#### Scenario: Admin channels index has no Pantalla Virtual button
- **WHEN** an admin loads `GET /admin/channels`
- **THEN** the rendered HTML SHALL NOT contain any button labeled "Pantalla Virtual" and SHALL NOT include the `<x-virtual-screen-editor-modal />` component

#### Scenario: Admin channels row actions are limited to channel CRUD
- **WHEN** an admin loads `GET /admin/channels`
- **THEN** the per-row actions SHALL include only "Pantalla Virtual" removal AND MAY include Pantalla Virtual relocation in Emisión module. The channels CRUD actions (Editar, Archivar) SHALL remain

### Requirement: Admin channels table view exposes Restream action

The admin channels index **table view** SHALL include a "Configurar Restream" action per row, alongside the existing Editar / Pantalla Virtual / Ver vivo / Archivar actions. The action SHALL open the `restream` modal (mounted in the same view) with the channel's `id` and `display_name`, matching the behavior of the cards view.

#### Scenario: Restream action present in table rows
- **WHEN** an admin loads `GET /admin/channels` with `viewMode = 'table'`
- **THEN** each channel row SHALL render a "Configurar Restream" button that opens the `restream` modal for that channel

#### Scenario: Restream action absent from client channels view
- **WHEN** a client loads `GET /client/channels`
- **THEN** the channels view SHALL NOT render a Restream quota button (quota habilitation is admin-only; clients manage targets from `/client/restream`)

### Requirement: Admin can archive a channel via confirmation modal
The system SHALL accept `PATCH /admin/channels/{id}/archive`. The `[Archivar]` action SHALL open a confirmation modal naming the channel and explaining that archiving is reversible (the channel stops appearing as active but the record is not removed). Confirmation SHALL dispatch `PATCH .../archive`. On success the row SHALL update to reflect `status = 'archived'` on next render; the row SHALL remain visible in the index.

#### Scenario: Confirmed channel archive updates status
- **WHEN** the admin confirms archiving of a channel
- **THEN** the server SHALL respond 2xx, set `status = 'archived'`, the modal SHALL close, and the row SHALL still be visible in the table showing the "archivado" badge on next render

#### Scenario: Archiving does not touch deleted_at
- **WHEN** the admin archives a channel
- **THEN** the channel's `deleted_at` column SHALL remain `NULL` and the row SHALL remain queryable through normal Eloquent queries

