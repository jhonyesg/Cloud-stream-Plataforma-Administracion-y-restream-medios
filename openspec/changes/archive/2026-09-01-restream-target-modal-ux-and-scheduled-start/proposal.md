# Proposal: restream-target-modal UX + Programar-inicio clarity

## Why

When creating a restream destination the user sees the modal screenshot they shared: 11+ raw inputs (select, text, textarea, checkbox, file, datetime-local) using the minimal `rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500` recipe. They feel "no tan geniales o fáciles o amigables para poderlos escoger" — the controls don't visually communicate hierarchy or what each field does.

Separately, the `Programar inicio (opcional)` field uses `datetime-local` with the single line "Si la dejas vacía, la transmisión se crea para empezar de inmediato al activar el destino." The user is confused about how scheduled start works because the rest of the app flows manually (start / stop from the row buttons). They want to know: is the scheduled-start logic in place? When does the target actually go live?

## What Changes

### A. UX of the form fields in `resources/views/components/restream-target-modal.blade.php`

Re-style and re-organize the form so each input is visually grouped, larger, with a consistent look, and the section that depends on `useConnectedAccount` (OAuth path) is clearly separated from the manual RTMP path.

Concretely:
- Wrap the body in a sectioned layout with `<details>`/cards or visually distinct panels: "Identificación" (Canal destino header + Plataforma + Nombre), "Conexión" (RTMP / OAuth toggle), "Detalles de la transmisión" (Título, Descripción, Miniatura, Programar inicio — only shown when OAuth account is used), "Activación" (Activar al guardar checkbox).
- Replace raw inputs with a consistent `x-restream-form-input` slot/pattern (a small reusable inline template — icon prefix, label, hint, error inline). Inputs get `py-2.5`, `text-sm`, `rounded-xl`, larger focus ring.
- Add a `<select>` chevron icon, larger height, focus ring stronger.
- Convert the "Usar mi cuenta de X conectada" checkbox into a prominent switch-style card with the platform badge (YouTube / Facebook) and the connected account name + green dot.
- Add a helper banner inside the OAuth section explaining the two modes:
  - **Inmediato** (default): checkbox off → "Activar al guardar" → orchestrator picks it up immediately.
  - **Programado** (`Programar inicio` filled): "Activar al guardar" stays off → cron `restream:launch-scheduled` auto-enables it at the indicated time. Show a small inline countdown placeholder.
- Replace the per-field red `<template x-if="errors.X">` block with a single banner at the top of the form that lists all errors grouped by field with anchor links to each input.
- Highlight the field that's wrong with `border-red-400 bg-red-50/40 focus:border-red-500 focus:ring-red-500`.

### B. Document + clarify the `Programar inicio` field

The scheduled-start logic is **already implemented end-to-end**:
- `scheduled_start_at` is part of the `restream_targets` table (model fillable + cast `datetime`).
- The `Client\RestreamTargetController` and `Admin\RestreamTargetController` accept and persist it.
- On creation, when an OAuth platform account is used, `YoutubeBroadcastService::create` posts the broadcast to YouTube with `scheduledStartTime` (ISO-8601); `FacebookBroadcastService::create` posts with `status: SCHEDULED_UNPUBLISHED, planned_start_time` (Unix seconds).
- `app/Console/Commands/RestreamLaunchScheduledCommand` is registered in `routes/console.php` to run **every minute**: it queries `restream_targets WHERE scheduled_start_at <= now() AND enabled = false`, sets `enabled = true`, and the existing orchestrator picks it up on its normal cadence.

What changes for the user:
- Add a persistent `info-banner` under the `Programar inicio` input: "Si programas el inicio, el destino se activará automáticamente a la hora indicada. Puedes dejarlo desactivado en el formulario y se encenderá solo."
- Add a small **countdown chip** on the target list row when `scheduled_start_at` is set and in the future: "Inicia en 2h 13m" with a clock icon. After the scheduled time passes and `enabled = true`, the chip disappears.
- Add a new spec scenario for `restream-targets` documenting the cron behavior.

### C. Reproducibility for future sessions

Append a "Reproducibility" section to this proposal with the test credentials and a Playwright probe snippet so the next agent session can pick up where this left off.

## Capabilities

### New Capabilities
- (none)

### Modified Capabilities
- `restream-targets`:
  - **ADDED** requirement: "The restream-target-modal form SHALL present inputs with consistent visual style, an info banner explaining the manual vs scheduled flow, and a consolidated error banner."
  - **ADDED** requirement: "The target list row SHALL show a countdown chip `Inicia en Xh Ym` when a target has `scheduled_start_at` in the future, and the chip SHALL disappear once the cron `restream:launch-scheduled` enables the target."
  - **ADDED** scenario: "A target with `scheduled_start_at = now() + 5min` and `enabled = false` SHALL auto-enable within ≤ 60 seconds of the scheduled time when `restream:launch-scheduled` runs."
- `restream-engine`: (no change) — the cron wiring is already in `routes/console.php`.

## Impact

- **Templates (2 files)**: `resources/views/components/restream-target-modal.blade.php` (form fields), `resources/views/client/restream/index.blade.php` and `resources/views/admin/restream/index.blade.php` (countdown chip in the list row).
- **Controllers / APIs**: none.
- **DB**: none.
- **Visibilidad**: better-looking modal, clearer copy, countdown chip on the row.

## Reproducibility (test credentials + Playwright probe)

Test credentials seeded in this environment:

| Role  | Email                       | Password       |
|-------|------------------------------|----------------|
| client| `pruebas@pruebas.com`        | `T3cn0l0g14`   |
| admin | `admin@cloudstream.local`    | `AdminP4ss!`   |

`pruebas` owns the channel "Prueba Stream" (uuid `a2810459-7d79-4e38-b32a-ca882667e515`) and one already-connected YouTube account "Efrain Suarez (Jhon Suarez)" — enough to exercise the OAuth + scheduled-start path without needing to re-do OAuth every session.

Playwright probe snippet (Node, ESM). Run from a temp dir that has Playwright available (e.g., `/tmp/pw`):

```js
import { chromium } from 'playwright';

const BASE = 'https://cloudstream.mediaserver.com.co';
const browser = await chromium.launch({
  headless: true,
  executablePath: '/usr/bin/google-chrome',
  args: ['--no-sandbox','--disable-dev-shm-usage','--ignore-certificate-errors']
});
const ctx = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1600, height: 900 } });
const page = await ctx.newPage();
const errors = [];
page.on('console', m => { if (['error','warning'].includes(m.type())) errors.push(m.text().slice(0,200)); });
page.on('pageerror', e => errors.push('PAGEERR: ' + e.message.slice(0,200)));

async function login(login, password) {
  await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="login"]', login);
  await page.fill('input[name="password"]', password);
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type="submit"]')]);
}

await login('pruebas@pruebas.com', 'T3cn0l0g14');
await page.goto(BASE + '/client/restream', { waitUntil: 'networkidle' });
await page.waitForTimeout(2500);
console.log('console errors:', errors.length);
// For targeted checks (button click via JS to bypass overlap issues):
await page.evaluate(() => {
  const b = Array.from(document.querySelectorAll('button')).find(x => x.textContent.trim() === 'Archivar');
  b?.click();
});
await page.waitForTimeout(600);
// Find topmost visible modal (other modals are mounted but hidden)
const top = await page.evaluate(() => {
  const ms = Array.from(document.querySelectorAll('div.fixed.inset-0.overflow-y-auto.modal-open'))
    .filter(m => window.getComputedStyle(m).display !== 'none');
  ms.sort((a,b) => parseInt(b.style.zIndex) - parseInt(a.style.zIndex));
  return ms[0];
});
// Screenshot the modal area:
await page.screenshot({ path: '/tmp/pw/modal.png', clip: { x: 500, y: 250, width: 600, height: 400 } });
await browser.close();
```

Things to watch when validating this change:
- After opening the modal, the inputs should be visually grouped (panels/sections), with helper text visible.
- The "Programar inicio" field should have an info banner explaining "el destino se activará automáticamente a la hora indicada".
- The target list row should show a "Inicia en Xh Ym" chip for any target with `scheduled_start_at` in the future.
- Console errors should remain at **0**.