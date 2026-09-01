## ADDED Requirements

### Requirement: Admin can archive a channel via confirmation modal
The system SHALL accept `PATCH /admin/channels/{id}/archive`. The `[Archivar]` action SHALL open a confirmation modal naming the channel and explaining that archiving is reversible (the channel stops appearing as active but the record is not removed). Confirmation SHALL dispatch `PATCH .../archive`. On success the row SHALL update to reflect `status = 'archived'` on next render; the row SHALL remain visible in the index.

#### Scenario: Confirmed channel archive updates status
- **WHEN** the admin confirms archiving of a channel
- **THEN** the server SHALL respond 2xx, set `status = 'archived'`, the modal SHALL close, and the row SHALL still be visible in the table showing the "archivado" badge on next render

#### Scenario: Archiving does not touch deleted_at
- **WHEN** the admin archives a channel
- **THEN** the channel's `deleted_at` column SHALL remain `NULL` and the row SHALL remain queryable through normal Eloquent queries

## MODIFIED Requirements

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
