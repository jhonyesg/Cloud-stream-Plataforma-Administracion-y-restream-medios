## Context

The admin channel-edit modal (`resources/views/components/channel-edit-modal.blade.php`) inlines its own form (lines 113-267) instead of including the shared partial at `resources/views/admin/channels/partials/form.blade.php`. The inlined form omits the `storage_limit_gb` field that the create flow shows.

Backend support is complete:
- `app/Http/Requests/Admin/UpdateChannelRequest.php:29` validates `storage_limit_gb => ['nullable', 'numeric', 'min:0']`.
- `app/Http/Controllers/Admin/DashboardController.php:236` calls `resolveChannelStorageLimit($request)` to convert GB → bytes (null if empty).
- `app/Models/Channel` already has `storage_limit_bytes` and `used_bytes` in `$fillable` and not hidden.
- `GET /admin/channels/{channel}` (DashboardController::channelShow) returns the full channel JSON including `storage_limit_bytes` and `used_bytes`.

The fix is purely a UI change: add the field to the modal, include it in the PUT body, show the current usage as a hint.

## Goals / Non-Goals

**Goals**
- Admin can edit `storage_limit_bytes` of an existing channel from the channel-edit modal.
- Admin sees the current `used_bytes` next to the field so they don't accidentally set a limit below current usage.
- Empty input = unlimited (matches create flow).

**Non-Goals**
- Not adding a per-user quota UI (the data model has `users.storage_limit_bytes` but the existing UI does not expose it; out of scope).
- Not changing the validation rules.
- Not refactoring the edit modal to use the `partials/form.blade.php` (would risk breaking other inline state; out of scope).
- Not changing enforcement behavior.

## Decisions

### D1. Inline the field, do not import the partial
- The edit modal is heavily customized (Alpine state, `assignedUsers` widget, asynchronous loading pattern). Reusing the partial would require either extracting its fields to a slot or restructuring the modal. The targeted change is a single input block copied from the partial — low risk, easy to review.

### D2. Use `ch.storage_limit_bytes` to populate the input
- The modal already fetches the channel via `GET /admin/channels/{id}` on open and stores it in `ch`. So `ch.storage_limit_bytes` is always available when the form is rendered.
- Convert bytes → GB in JS: `Math.round((ch.storage_limit_bytes / (1024 ** 3)) * 10) / 10` (1 decimal).
- Empty string when `ch.storage_limit_bytes === null` (unlimited).

### D3. Show `ch.used_bytes` as a hint
- Same pattern as the create flow's "Usado actualmente: X" hint.
- Format using `User::humanBytes(ch.used_bytes)` style — but since this is JS, do `humanBytes(ch.used_bytes)` via a small helper, or call the existing XParking approach. Actually the simpler approach: render the hint server-side using Blade only if `ch` is loaded, but since `ch` is loaded via fetch, the hint has to be reactive. Use a small JS helper.

### D4. Send `storage_limit_gb` in the PUT body
- Add to the `data` object in `submit()`: `storage_limit_gb: this.ch.storage_limit_gb !== null ? (this.ch.storage_limit_gb / (1024 ** 3)).toFixed(1) : ''`.
- Empty string maps to `null` server-side via `resolveChannelStorageLimit` which already handles `''` and `null`.

### D5. No backend changes
- Validator + controller already support the field. Verified by reading `UpdateChannelRequest` and `DashboardController::channelUpdate`.

## Risks / Trade-offs

- **R1: A user who types `5` (GB) and then reloads sees `5.0` not `5`** — cosmetic, matches the partial.
- **R2: If the form is submitted while `ch` is still loading, the field is hidden** — handled by `x-show="loaded"` on the form.
- **R3: The hint requires JS to format bytes** — a tiny inline helper is fine; `User::humanBytes` is PHP. We'll inline a 5-line JS helper inside the `x-data`.
- **R4: An admin sets a limit below current `used_bytes`** — the upload controller will start rejecting new uploads. We surface `used_bytes` next to the field so the admin can spot this; we do NOT block the save (the user might want to set a hard limit and clean up). Not a regression — current behavior.

## Migration Plan

No migrations. No infrastructure changes.

Deploy:
1. `git pull`.
2. `php artisan view:clear && config:clear && route:clear`.

Rollback: git revert.

## Open Questions

- **Q1:** Should we add a "Recomendado: mínimo X GB" hint calculated as `used_bytes * 1.1`? Currently no — the save always succeeds. Confirm before adding.
- **Q2:** Should we add a tooltip on the eye icon explaining "vacío = ilimitado"? Already in the placeholder + caption copy. No.
