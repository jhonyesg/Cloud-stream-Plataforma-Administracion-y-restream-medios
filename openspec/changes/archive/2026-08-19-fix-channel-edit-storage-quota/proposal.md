## Why

The admin channel-edit modal does not expose the `storage_limit_gb` field even though the backend (`DashboardController::channelUpdate` + `UpdateChannelRequest` + `resolveChannelStorageLimit`) supports it. As a result an admin can set the quota when creating a channel but cannot edit it afterwards — to change it the admin must delete and recreate the channel, which loses history.

The fix is to add the storage field to the edit modal, send it in the PUT body, and show the current usage as a hint so the admin can decide.

## What Changes

- Add a `storage_limit_gb` numeric input to the channel-edit modal (same UI as the create modal: number input, min=0, step=0.1, placeholder "Ilimitado", empty = unlimited).
- Add a "Usado actualmente: X" hint next to the input, populated from `ch.used_bytes` already returned by `GET /admin/channels/{id}`.
- Include `storage_limit_gb` in the `data` object sent to `PUT /admin/channels/{id}` in the modal's `submit()`.
- The backend already handles this: `UpdateChannelRequest::rules()` has `'storage_limit_gb' => ['nullable', 'numeric', 'min:0']` and `DashboardController::channelUpdate` calls `resolveChannelStorageLimit($request)` to convert to bytes. No controller changes.

## Capabilities

### New Capabilities
(none)

### Modified Capabilities
- `storage-quota`: add a requirement that the admin channel edit modal MUST expose the storage limit field and MUST send it on PUT. See `specs/storage-quota/spec.md` delta.

## Impact

- **Views modified:** `resources/views/components/channel-edit-modal.blade.php` (add 1 input + 1 hint + 1 line in `submit()` data object).
- **Backend:** no changes. Validator + controller already handle the field.
- **Tests:** add a feature test that PUTs `/admin/channels/{channel}` with `storage_limit_gb` and asserts the column is updated.
- **No new migrations, no new tables, no new env vars.**
