# Tasks: Fix `@json()`-inside-attribute truncation in Restream

## 1. Controllers — pre-encode `currentChannel`

- [x] 1.1 In `app/Http/Controllers/Client/RestreamHomeController.php`, add `currentChannelJson` to the view data: `$currentChannel ? json_encode(['id' => $currentChannel->id, 'display_name' => $currentChannel->display_name], JSON_UNESCAPED_UNICODE) : 'null'`
- [x] 1.2 In `app/Http/Controllers/Admin/RestreamTargetController.php`, add the same `currentChannelJson` to the view data (symmetrically, per dual-implementation rule).

## 2. Views — `@json()` → `{{ }}` of pre-encoded string + escape inner `"` characters

- [x] 2.1 `resources/views/client/restream/index.blade.php` — replace the `currentChannel: @json(...)` line with `currentChannel: {{ $currentChannelJson ?? 'null' }},` and add a warning Blade comment.
- [x] 2.2 `resources/views/admin/restream/index.blade.php` — same replacement, same warning comment.
- [x] 2.3 `resources/views/client/restream/index.blade.php` line 99 — change the two literal `"` characters inside the `forceDestroy` confirm() string to `&quot;` so they don't close the surrounding `x-data="…"` HTML attribute. (Same Blade source has `confirm('…DEFINITIVAMENTE "' + … + '"?\n\n…');` — the embedded `"` between the single-quotes was the second truncation point discovered during verification.)
- [x] 2.4 `resources/views/admin/restream/index.blade.php` line 107 — same `&quot;` replacement in the symmetric `forceDestroy` confirm.
- [x] 2.5 ~~Scheduler `overflowByDay: @json(...)`~~ — **NOT APPLICABLE.** That `@json()` is inside a `<script>window.insertCueModalData = {}` block, not inside an HTML attribute, so it's already safe. No change needed.

## 3. Cache + verify

- [x] 3.1 Run `php artisan view:clear` so stale compiled views don't keep serving the broken output.
- [x] 3.2 Smoke-load `/client/restream` as the `pruebas` client (Playwright). **Result:** x-data attribute length 11566 chars; `accountFor`, `csrf`, `init`, `forceDestroy`, `currentChannel` all present; `Alpine.$data(el)` reports `channels_len=1, targets_len=1, accounts_len=1, currentChannel_type=object, has_accountFor_method=true`; console errors = **0**.
- [x] 3.3 Smoke-load `/admin/restream` as `admin@cloudstream.local`. **Result:** x-data length 12813 chars; `channels`, `targets`, `clients`, `accountFor`, `init`, `csrf`, `forceDestroy` all present; console errors = **0**.
- [x] 3.4 Smoke-load `/client/scheduler`. **Result:** zero console errors. (`overflowByDay` is in a `<script>` block, already safe — no fix needed.)

## 4. Rollback plan (reference)

- [x] 4.1 Document: revert the four view files and two controller view-data entries; `php artisan view:clear`. No DB / API / route changes to undo.
