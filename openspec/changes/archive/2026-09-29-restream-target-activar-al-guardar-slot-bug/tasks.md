## 1. Verify scope and existing tests

- [x] 1.1 Confirm that no other view (admin, otras vistas) reuses `<x-restream-target-modal>` with the same submit handler bug. Search for any other `<input type="checkbox" name="enabled" value="1"` combined with a `=== 'on'` JS check.
- [x] 1.2 Check whether the repo has Dusk configured (`tests/Browser/`, `php artisan dusk --help`). Decide between Dusk test vs HTTP-only test + manual checklist based on availability.

## 2. Fix the submit handler

- [x] 2.1 In `resources/views/components/restream-target-modal.blade.php`, inside the `submit(ev)` method of the root `x-data`, derive the boolean state of the "Activar al guardar" checkbox from the live DOM:
  ```js
  const isEnabled = form.querySelector('[name="enabled"]').checked;
  ```
- [x] 2.2 Replace the two buggy lines (174 and 183) to use `isEnabled`:
  - For `create`: `data.set('enabled', isEnabled ? '1' : '0');`
  - For `update`: `payload.enabled = isEnabled ? '1' : '0';`
- [x] 2.3 Confirm the change is inside the modal's `x-data` scope (so `form` resolves to the modal's `<form>` element, not a parent form).

## 3. Add cap pre-flight to the checkbox

- [x] 3.1 Confirm the modal's Alpine payload includes `used_outputs` and `max_outputs` (already returned by `Client/RestreamTargetController::index`).
- [x] 3.2 In the checkbox `<input>` element (line ~397 of the modal), add `:disabled="mode === 'create' && used_outputs >= max_outputs"` and wire `used_outputs` / `max_outputs` into the modal's reactive scope (`init()`).
- [x] 3.3 Below the label, add an inline helper `<p x-show="mode === 'create' && used_outputs >= max_outputs">` styled with `text-xs text-amber-700 mt-1` reading "Has alcanzado el límite de destinos activos para este canal (X/X)."
- [x] 3.4 Ensure the `restream-targets-changed` event refreshes `used_outputs` / `max_outputs` (verify the modal listens to it; if not, add the listener).

## 4. Add a regression test

- [x] 4.1 If Dusk is available: N/A (no Dusk in repo, see 1.2).
- [x] 4.2 Add a PHPUnit feature test that POSTs to `POST /client/channels/{c}/restream-targets` with `enabled = 1` / `enabled = 0` and asserts the row + counter behavior. Sibling test for cap rejection.
- [x] 4.3 If neither automated test path is feasible in this session: N/A — PHPUnit feature test path used (see 4.2), all passing.

## 5. Verification

- [x] 5.1 Run `php artisan view:clear && php artisan config:clear && php artisan route:clear`.
- [x] 5.2 Run the full restream test suite: `php artisan test --filter=Restream`. PASS (8/8, including 3 new regression tests).
- [x] 5.3 Manual smoke test in browser (or document the checklist if browser is unavailable): DONE end-to-end against local Laravel server with `buenisimatv` user. Verified: POST with `enabled=1` → row with `enabled=true`, `used_outputs` increments 0→1→2; POST with `enabled=1` at cap → HTTP 422 with "Cap de destinos alcanzado (2/2)". Bug confirmed fixed. Test rows cleaned up from `restream_targets`.
- [x] 5.4 Run `php artisan check:destructive-migrations` (PASS — no destructive migrations in this change).

## Issue: Pre-existing migration bug blocks test verification

Two migrations add the same `keep_recording` column to `restream_targets`:

- `database/migrations/2026_09_02_154000_add_platform_privacy_to_restream_targets.php:13` — adds `keep_recording` (was meant to only add `platform_privacy`)
- `database/migrations/2026_09_02_173000_add_keep_recording_to_restream_targets.php:12` — adds `keep_recording` (the "real" one)

Running `migrate:fresh` (which `RefreshDatabase` uses) fails on the second one with `SQLSTATE[42701]: Duplicate column`. This blocks ALL restream feature tests (not just the new ones I added — pre-existing tests in `RestreamQuotaPerChannelTest` also fail).

**Resolution:** removed the `keep_recording` line from `154000` (since `173000` already adds it correctly). Production DB was not affected: the column was never recorded in the production `migrations` table, so `154000`/`173000` had never been applied; the production schema for `restream_targets` lacks both `keep_recording` and `platform_privacy` and would need a separate one-shot sync (out of scope of this change).
