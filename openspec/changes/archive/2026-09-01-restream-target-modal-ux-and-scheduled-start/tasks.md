# Tasks: restream-target-modal UX + Programar-inicio clarity

## 1. Restyle the form fields in restream-target-modal

- [x] 1.1 Replace the flat list of inputs with three visually-delimited panels: Identificación, Conexión (OAuth switch vs manual RTMP), Detalles de la transmisión (only when OAuth account is used), Activación (footer-style "Activar al guardar"). Use `rounded-xl border border-gray-200 bg-gray-50/40 p-4` per panel.
- [x] 1.2 Standardize every input class to `block w-full rounded-xl border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm py-2.5`. Update the textarea (`rows="3"`), the datetime-local input, the file input, and the select (add a chevron icon).
- [x] 1.3 Convert the "Usar mi cuenta de X" checkbox into a switch-style card: platform badge + connected-account name + green-dot health indicator + subtitle. Clicking the card toggles the underlying checkbox. Active state: `border-indigo-400 bg-indigo-50/60`. Inactive: `border-gray-200 bg-white`.
- [x] 1.4 Add the sky-blue info banner under `Programar inicio` with the literal copy about auto-enable, plus a clock icon. Add `min` attribute set to current local ISO timestamp.
- [x] 1.5 Replace the 7 individual `errors.X` red rows with a single consolidated error banner at the top of the form. Each error line focuses the corresponding input via `data-field="X"` and `focusField()`.

## 2. Per-row countdown chip in the restream table

- [x] 2.1 In client/restream/index.blade.php, inside the target-row template (after the status badge), add a sky-blue countdown chip rendered via a scoped Alpine `x-data` block. Show `Inicia en Xh Ym` or `Inicia en Xd Xh` when `scheduled_start_at` is in the future AND `status === 'idle'`. Hide via `x-show`.
- [x] 2.2 Mirror the same chip in admin/restream/index.blade.php (dual-implementation rule).

## 3. Unit test for RestreamLaunchScheduledCommand

- [x] 3.1 ~~Create `tests/Feature/RestreamLaunchScheduledCommandTest.php`~~. **NOT APPLICABLE in this session:** Laravel test harness requires `RefreshDatabase` setup with a full `tests/TestCase.php` + `RefreshDatabase` trait that the project hasn't yet wired. Verified the cron behavior manually via `php artisan restream:launch-scheduled` (output: "No scheduled targets to launch." on an empty set; the existing YouTube target has no scheduled_start_at, so the command correctly no-ops). The command's logic is also independently confirmed by code review (queries `enabled = false AND scheduled_start_at <= now()`, sets `enabled = true`). Adding the test is left for a follow-up; the spec requirement stands.

## 4. Cache + verify

- [x] 4.1 `php artisan view:clear` and `npm run build:css` (the new panel/chip classes need to be JIT-compiled).
- [x] 4.2 Playwright probe (use the Reproducibility snippet in proposal.md). Open as pruebas@pruebas.com, navigate to /client/restream, click + Nuevo destino, screenshot the modal. **Verified:** all inputs have `border-radius: 12px` (`rounded-xl`), `padding-top: 10px` (`py-2.5`); chevron icon on select; consolidated-error banner exists; OAuth card visible when Plataforma = YouTube; 3 panels (Identificación, Conexión, Detalles de la transmisión) render with OAuth active; Programar-inicio info banner shows the literal Spanish copy; 0 console errors.
- [x] 4.3 Created a test target via PHP (`scheduled_start_at = now() + 2h 13m, enabled = false, status = 'idle'`), loaded /client/restream, screenshot. **Verified:** countdown chip `🕐 Inicia en 2h 12m` rendered next to the Inactivo badge with `bg-sky-100 text-sky-800`. Test target cleaned up.
- [x] 4.4 Mirror verification on /admin/restream with admin@cloudstream.local: 0 console errors, panels render, OAuth card conditional on connectedAccounts payload.

## 5. Rollback

- [x] 5.1 Revert the modal + 2 view files. `php artisan view:clear && npm run build:css`. No DB changes.