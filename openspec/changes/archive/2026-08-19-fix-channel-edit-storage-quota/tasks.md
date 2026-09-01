## 1. UI — channel-edit modal

- [x] 1.1 Added `storageLimitGb` getter/setter and `humanBytes()` helper inside `x-data`.
- [x] 1.2 Added the storage input block (numeric, min=0, step=0.1, placeholder "Ilimitado") between `description` and `root_path`. Includes "Usado actualmente: X" hint via `humanBytes(ch.used_bytes ?? 0)`.
- [x] 1.3 Updated `submit()` to include `storage_limit_gb` in the PUT body (empty string when null).
- [x] 1.4 Added inline error display for `storage_limit_gb` below the input.

## 2. Tests

- [x] 2.1 Created `tests/Feature/Admin/ChannelStorageQuotaTest.php` with 4 tests covering set, remove, invalid, and show-endpoint roundtrip.
- [x] 2.2 `php artisan test --filter=ChannelStorageQuotaTest` → 4 passed (11 assertions).

## 3. Verification

- [ ] 3.1 `php artisan view:clear && config:clear && route:clear`.
- [ ] 3.2 Manual smoke test: log in as admin → Canales → click Edit on a channel → confirm the "Límite de almacenamiento (GB)" field is visible, pre-populated, and shows "Usado actualmente: X" → change to a new value → save → confirm the new `storage_limit_bytes` in DB.
- [ ] 3.3 Run `php artisan tinker --execute="echo \App\Models\Channel::find('CHANNEL_ID')->storage_limit_bytes;"` to confirm.
