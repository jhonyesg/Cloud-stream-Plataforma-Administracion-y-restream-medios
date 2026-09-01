## Why

Today the admin "delete channel" action never deletes anything: `DashboardController@channelDestroy` only flips `status` to `archived`, yet the UI's confirmation modal still says "Sí, eliminar". Admins have no way to actually remove a channel record — inactivating (archiving) and deleting are the same button, which is confusing and blocks cleanup of channels that should truly go away.

## What Changes

- Clarify that the existing archive behavior (`status = 'archived'`) is the "inactivate" action — reversible, keeps the row and all its relations intact.
- Add a genuine "eliminar" (delete) action distinct from archive: a soft delete using Eloquent's `SoftDeletes`, already imported on `Channel` but unused (`deleted_at` is never set today). Deleted channels disappear from all normal listings/queries but the row remains recoverable in the database.
- Fix the confirmation modal copy so "Archivar" and "Eliminar" show distinct, accurate text instead of both funneling through the generic "Sí, eliminar" modal.
- **BREAKING**: none — this only adds a new action and corrects the wording of an existing one; the current archive behavior and its route are unchanged.

## Capabilities

### New Capabilities
(none)

### Modified Capabilities
- `admin-channel-crud`: the delete requirement is corrected to reflect two distinct admin actions — archive/inactivate (existing `status='archived'` behavior) and a new soft-delete action (`deleted_at` set via `SoftDeletes`) — instead of the current stale requirement that documents `DELETE` as removing the row outright.

## Impact

- `app/Http/Controllers/Admin/DashboardController.php` (`channelDestroy` and a new soft-delete action/route).
- `app/Models/Channel.php` (start actually using the existing `SoftDeletes` trait; global scope will exclude soft-deleted channels from default queries).
- `routes/web.php` (channel routes: keep/rename the archive route, add the new delete route).
- `resources/views/admin/channels/index.blade.php` and `resources/views/components/confirm-delete-modal.blade.php` (distinct copy/wiring for archive vs delete).
- `openspec/specs/admin-channel-crud/spec.md` (correct the stale delete requirement).
- No changes to the `channel_status` enum, and no changes to FK cascade behavior on related tables (`playlists`, `virtual_screens`, `restream_targets`, etc.) — soft delete does not trigger DB-level `CASCADE`/`SET NULL`, so those relations are out of scope for this change.
