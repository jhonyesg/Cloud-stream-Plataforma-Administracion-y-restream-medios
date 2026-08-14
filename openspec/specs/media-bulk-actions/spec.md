# media-bulk-actions Specification

## Purpose
TBD - created by archiving change media-bulk-actions. Update Purpose after archive.
## Requirements
### Requirement: Media library exposes selection mode and a floating bulk action bar
The admin and client media grids SHALL render a "Seleccionar" toggle. While selection mode is on, every card SHALL display a circular checkbox the user can click to add/remove that item from a shared selection. A floating action bar pinned to the bottom of the viewport SHALL appear whenever the selection has at least one item, displaying the count and the available bulk actions.

#### Scenario: Toggle enters selection mode
- **WHEN** the user clicks "Seleccionar" on `/admin/media` or `/client/media`
- **THEN** every visible card SHALL render a circular checkbox in the top-right corner and the body SHALL get a bottom padding to make room for the (empty) floating action bar.

#### Scenario: Selecting a card highlights it
- **WHEN** the user clicks a card's checkbox while in selection mode
- **THEN** the card SHALL apply a 2px amber ring and the floating bar SHALL display "1 seleccionado" with the bulk action buttons enabled.

#### Scenario: Clearing the selection exits selection mode
- **WHEN** the user clicks "Limpiar" in the floating bar after selecting items
- **THEN** the selection SHALL be cleared, the floating bar SHALL show "0 seleccionados" with actions disabled, and a "Salir" link SHALL be visible so the user can leave selection mode.

### Requirement: Bulk delete endpoint
The system SHALL expose `POST /api/media-items/bulk-delete` accepting `{ ids: string[] }`. The server SHALL authorise each id individually through `MediaItemPolicy::delete()` (so a client can only delete items in channels they own). Items the caller cannot delete SHALL be counted as `skipped` and SHALL NOT cause the request to fail. The endpoint SHALL return `{ deleted: int, skipped: int, failed: int }` and SHALL soft-delete the rows via `MediaItem` and remove the files via `MediaStorageService::deleteFile()`.

#### Scenario: Admin bulk-deletes a selection
- **WHEN** an admin POSTs `{ ids: ["a", "b", "c"] }` and is authorised to delete all three
- **THEN** the response SHALL be `{ deleted: 3, skipped: 0, failed: 0 }`, the three rows SHALL be soft-deleted, and the underlying files SHALL be removed from disk.

#### Scenario: Client attempts to bulk-delete items they do not own
- **WHEN** a client POSTs `{ ids: ["a", "b"] }` and they own `a` but are only assigned (not owner of) `b`
- **THEN** the response SHALL be `{ deleted: 1, skipped: 1, failed: 0 }`. Item `a` SHALL be deleted; item `b` SHALL remain untouched.

#### Scenario: Empty selection is rejected
- **WHEN** a user POSTs `{ ids: [] }`
- **THEN** the server SHALL respond 422 with a validation error on `ids`.

### Requirement: Bulk thumbnail generation endpoint
The system SHALL expose `POST /api/media-items/bulk-thumbnails` accepting `{ ids: string[] }`. The server SHALL authorise each id individually through `MediaItemPolicy::view()` (any user who can view an item can request a thumbnail for it). For each authorised item, the server SHALL call `MediaThumbnailService::generateEager($item, force: true)`, which means the existing thumbnail file SHALL be deleted and regenerated even when the item already has a valid (non-placeholder) thumbnail. The endpoint SHALL return `{ processed: int, skipped: int, failed: int }`.

The floating bulk-action bar in the media library SHALL expose this action under a button labelled **"Regenerar miniaturas"** (with the busy state labelled **"Regenerando…"**), reflecting that the operation always overwrites the existing thumbnails of the selected items.

#### Scenario: Admin regenerates thumbnails for a selection
- **WHEN** an admin selects five video items (some of which already have a valid non-placeholder thumbnail) and triggers "Regenerar miniaturas" from the floating bulk-action bar
- **THEN** the server SHALL respond with counts that sum to five, the existing thumbnail file of every item that already had one SHALL be replaced by a freshly generated one, and every item's `thumb_path` SHALL be re-evaluated.

#### Scenario: Client regenerates thumbnails for assigned channel items
- **WHEN** a client selects two items in a channel they are assigned to and triggers "Regenerar miniaturas" from the floating bulk-action bar
- **THEN** the server SHALL accept the request and replace the existing thumbnails of those two items with freshly generated ones; items in channels the client is not associated with SHALL be counted as `skipped`.

### Requirement: Selection scope is per-page
The shared selection SHALL contain only the items the user explicitly checks on the current page. Navigating filters, changing page, or triggering a successful bulk action SHALL clear the selection. The selection SHALL NOT persist across page reloads.

#### Scenario: Selection clears on page change
- **WHEN** the user has selected three items and clicks the pagination "next" link
- **THEN** the new page SHALL render with no items selected and the floating bar SHALL show "0 seleccionados".

#### Scenario: Selection clears after a successful bulk action
- **WHEN** the user triggers a bulk delete and the server responds with `{ deleted: 5 }`
- **THEN** the client SHALL clear the selection, exit selection mode, and refresh the page to reflect the deletion.

