## 1. Backend — routes & controller

- [x] 1.1 Verify the `channels` table has a `deleted_at` column (check base migration); add a migration for it only if missing.
- [x] 1.2 In `routes/web.php`, add `PATCH /admin/channels/{channel}/archive` and repoint the existing `DELETE /admin/channels/{channel}` route to the new delete behavior.
- [x] 1.3 In `app/Http/Controllers/Admin/DashboardController.php`, rename the current `channelDestroy()` body into a new `channelArchive()` method (unchanged logic: `$channel->status = 'archived'; $channel->save();`).
- [x] 1.4 In the same controller, implement a new `channelDestroy()` that calls `$channel->delete()` (soft delete) and returns a 2xx JSON response.
- [x] 1.5 Grep the codebase for any other direct `channels` table queries (raw DB, `withTrashed()`/`onlyTrashed()` misuse) that could surface soft-deleted channels unexpectedly; fix if found.

## 2. Frontend — UI wiring

- [x] 2.1 Update `resources/views/admin/channels/index.blade.php` so the archive action calls `PATCH .../archive` and the (new or existing) delete action calls `DELETE .../{channel}`.
- [x] 2.2 Update `resources/views/components/confirm-delete-modal.blade.php` (or introduce a `mode` prop: `archive` | `delete`) so title/body/button text is accurate per action: archive = "Archivar canal" / "Sí, archivar" / reversible-explanation copy; delete = "Eliminar canal" / "Sí, eliminar" / hidden-but-recoverable copy.
- [x] 2.3 Ensure the delete action is not offered (or is a no-op) for a channel that is already soft-deleted — in practice this is automatic since soft-deleted channels won't appear in the index at all.

## 3. Spec & validation

- [x] 3.1 Run `openspec validate --strict channel-soft-delete-vs-archive` and fix any reported issues.
- [x] 3.2 Manually test in the admin UI: archive a channel (row stays, badge updates), delete a channel (row disappears, `deleted_at` set in DB), confirm related `playlists`/`virtual_screens`/`restream_targets` rows are untouched after a delete.
- [x] 3.3 Confirm no PHP process was run as `root` during local testing per project convention (avoid root-owned cache files breaking `www`-owned PHP-FPM).
