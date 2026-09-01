## Context

`Admin\DashboardController@channelDestroy` is bound to `DELETE /admin/channels/{channel}` and only sets `status = 'archived'`. The `Channel` model already declares `use SoftDeletes` but nothing in the codebase ever calls `->delete()`/`->forceDelete()`, so `deleted_at` is always `NULL`. The admin UI's row action already reads "Archivar canal" (per `openspec/specs/admin-channel-crud/spec.md` requirement "Admin channels list excludes Pantalla Virtual action", which already renamed the row action to "Archivar"), but it still opens the generic `confirm-delete-modal.blade.php`, whose submit button hardcodes "Sí, eliminar" — the wrong verb for what actually happens.

## Goals / Non-Goals

**Goals:**
- Give admins a real "eliminar" action that soft-deletes the channel (`deleted_at` set), hiding it from all normal queries while keeping the row recoverable.
- Keep the existing archive behavior working exactly as it does today, just under accurate naming end-to-end (route, controller method, modal copy).
- Make the two actions unambiguous in the UI: separate buttons, separate confirmation copy.

**Non-Goals:**
- No changes to the `channel_status` enum or its 4 values.
- No trash/restore UI in this change (soft-deleted channels are simply hidden; a future change can add a "papelera" view if needed).
- No changes to FK cascade behavior on related tables. Eloquent soft delete only sets `deleted_at` on the `channels` row — it does not touch `playlists`, `virtual_screens`, `restream_targets`, etc. Those CASCADE/SET NULL rules only fire on an actual `DELETE FROM channels`, which does not happen here.

## Decisions

**1. `DELETE /admin/channels/{channel}` becomes the real delete; archive moves to its own route.**
Today `DELETE` archives, which is misleading for any API consumer and for future maintainers. Reassign the verbs to match REST conventions already used elsewhere in this controller (`PUT` for update):
- `PATCH /admin/channels/{channel}/archive` → `channelArchive()` (renamed from `channelDestroy`, same body: `$channel->status = 'archived'; $channel->save();`).
- `DELETE /admin/channels/{channel}` → new `channelDestroy()` body: `$channel->delete()` (soft delete via the existing `SoftDeletes` trait).

Alternative considered: keep `DELETE` as archive and add a new route like `DELETE /admin/channels/{channel}/force` for the real delete. Rejected — it leaves the most obvious verb (`DELETE`) permanently misleading, and every future contributor has to relearn "DELETE doesn't delete here."

**2. No new column, reuse `SoftDeletes`.**
`Channel` already has the trait imported and (per migration) a `deleted_at` column exists but is unused. Turning it on is a one-line activation (already present) rather than new schema. Confirmed via prior exploration.

**3. Two distinct UI affordances instead of one shared modal.**
`confirm-delete-modal.blade.php` stays for the archive action (still a confirmation, just re-labeled: title "Archivar canal", button "Sí, archivar", explanatory text matching current archive-only semantics). A new lightweight confirmation (either a second instance of the same generic component with delete-specific props, or a small dedicated modal) is used for the delete action, with distinct copy ("Eliminar canal", "Esta acción ocultará el canal; sigue siendo recuperable desde soporte" or similar) so admins understand it's not identical to archiving.

Alternative considered: a single modal with a mode prop (`archive` | `delete`) swapping title/button/body text. This is simpler to implement and keeps one Blade component. Recommended for tasks.md since it avoids duplicating modal markup — `confirm-delete-modal.blade.php` already looks generic enough to accept a `mode` prop.

**4. Default query scoping.**
Once `SoftDeletes` is "live" (i.e., once a channel actually gets `deleted_at` set), Eloquent's default global scope on `Channel` already excludes soft-deleted rows from every `Channel::query()`/`Channel::all()`/relationship call without further code changes — this is automatic. No explicit `whereNull('deleted_at')` needs to be added anywhere.

## Risks / Trade-offs

- [Risk] Renaming the route from `DELETE /admin/channels/{channel}` (archive) to `PATCH .../archive` could break any external caller or JS still pointing at the old verb. → Mitigation: this is an admin-only, first-party UI action (confirmed no external API consumers in prior exploration); update the Blade view's fetch call in the same change.
- [Risk] Admins might expect "Eliminar" to be instantly permanent/irreversible, and be surprised the row still exists (soft delete). → Mitigation: confirmation copy explicitly states the channel becomes hidden but not permanently erased.
- [Risk] Forgetting to update relationship queries that bypass Eloquent's global scope (e.g., raw DB queries, `withTrashed()` misuse) could still surface deleted channels somewhere. → Mitigation: covered by a task to grep for direct `channels` table queries outside Eloquent.

## Migration Plan

1. No schema migration needed (`deleted_at` column already exists per the `SoftDeletes` trait already being present — verify column exists in the base migration before assuming; if absent, add a migration first).
2. Ship controller/route/view changes together (they're small and coupled) behind normal deploy — no feature flag needed, this is admin-only tooling.
3. Rollback: revert the commit; no data migration to undo since no existing rows will have `deleted_at` set until an admin uses the new action.

## Open Questions

- Should soft-deleted channels remain deletable a second time (no-op) or should the delete button disappear once already archived vs deleted? (Left for tasks.md / implementation judgment — default to hiding the delete action once a channel is already soft-deleted, since it'll no longer appear in the index at all.)
