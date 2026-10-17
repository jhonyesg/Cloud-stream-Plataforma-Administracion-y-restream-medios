# Design: restream-target-modal UX + Programar-inicio clarity

## Context

The `<x-restream-target-modal>` is the destination-creation form for the Restream module. The user opens it from `/client/restream` and `/admin/restream` via `Alpine.store('modals').open('restream-target', { channel, mode: 'create', isAdmin, connectedAccounts })`. Today the form is a flat list of 11+ raw inputs (`rounded-md border-gray-300`) with a single red per-field `<template x-if="errors.X">` block. There is no visual hierarchy: OAuth-vs-manual toggling happens through a small checkbox inside the page body; the scheduled-start field is a bare `<input type="datetime-local">` with one line of helper text.

Meanwhile, the **scheduled-start backend** is already complete and wired:

- DB: `restream_targets.scheduled_start_at` is a `datetime` cast.
- Controllers (`Client\RestreamTargetController`, `Admin\RestreamTargetController`) validate and persist it (`['sometimes', 'nullable', 'date']`).
- The modal's submit handler explicitly drops `scheduled_start_at` from the form payload when the user is NOT using an OAuth account (line 35 of `restream-target-modal.blade.php`) — it only makes sense for OAuth, which is correct.
- `YoutubeBroadcastService::create` POSTs `liveBroadcasts` with `scheduledStartTime` (RFC-3339) and `privacyStatus: 'unlisted'` + `enableAutoStart: true` + `enableAutoStop: true`; `FacebookBroadcastService::create` posts `status: 'SCHEDULED_UNPUBLISHED'` with `planned_start_time` as Unix seconds.
- `routes/console.php:14` schedules `Schedule::command('restream:launch-scheduled')->everyMinute()->name('restream-launch-scheduled')`.
- `App\Console\Commands\RestreamLaunchScheduledCommand` queries `restream_targets WHERE scheduled_start_at <= now() AND enabled = false`, flips `enabled = true`, and the existing orchestrator picks it up on its normal cadence.

What is **missing**:
- UX: the form is hard to scan, sections aren't delimited, errors aren't consolidated.
- Discoverability: the user can't tell from the row whether a target is scheduled or already live.
- Visibility: nothing on the row communicates "this target will auto-start in 2h 13m".

## Goals / Non-Goals

**Goals**
- Re-style the form so each input is grouped, larger, and the OAuth-vs-manual modes are visually distinct.
- Document the scheduled-start flow in the form so the user understands auto-enable will happen.
- Add a per-row countdown chip `Inicia en Xh Ym` for any target with `scheduled_start_at` in the future, computed client-side every minute via `setInterval`.
- Provide a reproducibility section so future sessions can pick up where this left off (test credentials + Playwright probe).

**Non-Goals**
- Do not change the backend flow. The cron `restream:launch-scheduled` already exists and runs every minute. Do not change `scheduled_start_at` semantics, the platform broadcast services, or the controllers.
- Do not add a new field, validation rule, or column.
- Do not introduce toast notifications for "your target was scheduled" — the toast bar from the existing `crud-success` event covers it.
- Do not change the engine UI (live indicators, log accordion, creds panel) — only the modal and the row's status chip area.

## Decisions

### Decision 1 — Three visually-delimited panels inside the modal

Inside the modal `<form>`:

1. **Identificación** — `<div>` with `rounded-xl border border-gray-200 bg-gray-50/40 p-4`. Contains the existing "Canal destino" header + `Plataforma` select + `Nombre` input.
2. **Conexión** — radio-like choice between:
   - **OAuth** (default when `canUseConnectedAccount` is true): big switch-style card with platform badge + connected account + green dot + "Usar mi cuenta de X conectada — crea la transmisión automáticamente".
   - **Manual RTMP**: card with `URL de destino`, admin-only `URL fuente`, `Stream Key`.
3. **Detalles de la transmisión** — only rendered when `useConnectedAccount && canUseConnectedAccount`. Contains `Título`, `Descripción`, `Miniatura`, `Programar inicio` (with the new info banner + helper text).
4. **Activación** — a footer-style bar inside the form with the "Activar al guardar (consume un slot)" checkbox aligned right.

This is implemented as plain `<div>` containers with utility classes (not `<fieldset>` / `<legend>`), because Alpine is already driving everything and we want to keep x-data scope intact.

### Decision 2 — Reusable input pattern via small inline template

Because the inputs differ in type (text/select/textarea/checkbox/file/datetime) and we want one consistent style, I'll create a tiny in-file helper using a partial Blade `@include` if it pays off, OR a `<template>` + Alpine render-function approach.

After evaluation: a partial Blade include is cheaper. `resources/views/partials/form-input.blade.php` will accept props `name, label, type, placeholder, hint, value, error`. **But** since most fields need `:value="target ? target.X : ''"` (dynamic), and the form is heavily Alpine-driven, I'll inline the markup and standardize classes rather than building a generic component.

### Decision 3 — Consolidated error banner at the top of the form

A single `<div x-show="Object.keys(errors).length > 0" class="bg-red-50 border border-red-200 rounded-xl p-3 mb-2">` lists `errors.general` and one row per field error (`Campo "X" debe …`) with a `data-field="name"` attribute and `document.querySelector('[data-field=name]').focus()` on click. Replaces the 7 individual `<template x-if="errors.X">` blocks.

### Decision 4 — Programar-inicio info banner

Direct copy under the field:
> ℹ️ Si programas el inicio, el destino se activará automáticamente a la hora indicada. Puedes dejarlo desactivado en el formulario y se encenderá solo. La hora es local del navegador.

The field stays a `<input type="datetime-local">` with `min` set to "now" (in user TZ) so the user can't pick a past time.

### Decision 5 — Per-row countdown chip (client-side)

A `<span x-data="{ ... }" x-init="setInterval(...)">` block in the row, computed once per minute. For targets with `scheduled_start_at` in the future AND `enabled = false`:

```html
<span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-sky-100 text-sky-800">
    <svg clock /> Inicia en 2h 13m
</span>
```

Once `enabled = true` and `status` is one of `idle/starting/live`, the chip disappears. Once `scheduled_start_at` is in the past, the chip disappears.

### Decision 6 — Playwright probe is reproducible

Append the probe pattern to the proposal so future sessions know:
- Login as `pruebas@pruebas.com / T3cn0l0g14` (or `admin@cloudstream.local / AdminP4ss!`).
- Navigate to `/client/restream` (or `/admin/restream`).
- Click `+ Nuevo destino` or `Archivar` (use JS `button.click()` to bypass overlap issues with hidden modals).
- Find the topmost modal by z-index (`Array.from(...).sort((a,b) => parseInt(b.style.zIndex) - parseInt(a.style.zIndex))`).
- Screenshot and assert computed styles + zero console errors.

## Risks / Trade-offs

- **[Risk] Visual redesign breaks existing Playwright snapshot tests** → Mitigation: there are no Playwright snapshot tests in CI today (visual probe is ad-hoc); the only contract is "0 console errors + button labels resolve to functions", which we maintain.
- **[Risk] `Programar inicio` info banner copy is too long for narrow modals** → Mitigation: wrap the banner text, allow it to be 2 lines; tested at `max-w-md` modal width.
- **[Risk] Countdown chip's `setInterval` leaks if the target is removed** → Mitigation: scope the `setInterval` to the row's x-data and use `Alpine.effect` with `$el` cleanup; on `Alpine.cleanup`, clear the interval.
- **[Risk] Cron `restream:launch-scheduled` is not exercised in the test** → Mitigation: a unit-level test against `RestreamLaunchScheduledCommand` is added — set `scheduled_start_at = now() - 1min` on a target with `enabled = false`, run the command, assert `enabled = true`.
- **[Risk] OAuth field-group diverges from controller logic on `data.delete('scheduled_start_at')` when not using OAuth** → Mitigation: keep the existing controller-side strip; just don't render the field if `!useConnectedAccount || !canUseConnectedAccount`. The modal currently does that.

## Migration Plan

1. Restyle `resources/views/components/restream-target-modal.blade.php` (panels + consistent input classes + consolidated errors + info banner).
2. Add countdown chip to client + admin restream views (symmetric).
3. Add unit test for `RestreamLaunchScheduledCommand` (without DB integration test framework, plain artisan-style test using `RefreshDatabase` if available, otherwise tinker-style).
4. `php artisan view:clear && npm run build:css` — many new Tailwind classes (panel borders, chip bg-sky-100, etc.).
5. Playwright verify: open modal, screenshot the form panels, screenshot a row with a scheduled target, confirm `errors.length === 0` in console.
6. Rollback: revert modal + 2 view files + drop unit test. No DB changes.

## Open Questions

- Should the countdown chip show on a target that's already `enabled = true` but whose `scheduled_start_at` is still in the future? → Decision: yes, as long as `status === 'idle'`, the chip says "Inicia en Xh Ym" — useful for "the cron hasn't flipped it yet".