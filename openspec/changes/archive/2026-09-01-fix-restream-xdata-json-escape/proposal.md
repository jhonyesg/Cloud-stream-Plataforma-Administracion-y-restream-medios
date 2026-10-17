# Proposal: Fix `@json()`-inside-attribute truncation in Restream (and scheduler)

## Why

Both Restream views (`client/restream/index.blade.php:12`, `admin/restream/index.blade.php:13`) emit the channel-context object with Blade's `@json($currentChannel ? [...])` directive **inside** an `x-data="..."` attribute. `@json()` outputs the JSON raw — the literal `"` characters in the value close the surrounding HTML attribute prematurely. The browser re-parses the tail of the string as additional HTML attributes, so by the time Alpine reads `x-data` it has been truncated at `currentChannel: {`. The object body (methods like `accountFor`, `csrf`, `start`, `stop`, …) never reaches Alpine, so every descendant binding fails with `accountFor is not defined`, `flash is not defined`, `targets is not defined`, plus the root `Unexpected token ';'` from parsing the truncated object literal.

The user sees the page "compile" partially: tables, badges, and slot indicators render statically and the toggles never react. The console is flooded with ~20 Alpine expression warnings on every visit.

The same `@json()` literal appears in `client/scheduler/index.blade.php:428` and `admin/scheduler/index.blade.php:478` (`overflowByDay: @json($overflowByDay)`) — **but** those are inside a `<script>` block (`window.insertCueModalData = {...}`), not inside an HTML attribute, so `@json()` is safe there and no change is needed. False alarm from initial grep — scheduler is unaffected.

The fix is one mechanical change per site: encode JSON in the controller (or wrap with `{{ ... }}` so Blade htmlspecialchars-escapes the `"`) so the attribute survives round-trip through the HTML parser.

## What Changes

- **Client Restream index** (`resources/views/client/restream/index.blade.php`):
  - Move the `currentChannel` JSON encoding from `@json(...)` inside the `x-data` attribute to a `$currentChannelJson` variable passed from the controller.
  - Render as `currentChannel: {{ $currentChannelJson ?? 'null' }},` so Blade's htmlspecialchars converts `"` to `&quot;` and the browser decodes it back to `"` for Alpine.
  - Change the two literal `"` characters inside the `forceDestroy` confirm() string to `&quot;` so they don't close the surrounding `x-data="…"` HTML attribute either.
- **Admin Restream index** (`resources/views/admin/restream/index.blade.php`): same three changes, applied symmetrically (dual-implementation rule).
- **Controllers** (only for Restream — scheduler already passes a string):
  - `app/Http/Controllers/Client/RestreamHomeController.php`: add `currentChannelJson` to the view data.
  - `app/Http/Controllers/Admin/RestreamTargetController.php`: add `currentChannelJson` to the view data.
- **No behaviour change**: the rendered JS payload is identical to today's intent; only the escape route changes.

## Discovery during implementation

After the first round of edits, Playwright verification showed the cascade of errors had shifted: the attribute still didn't reach the closing `}`. A second inspection found a **different root cause** in the same views: the `forceDestroy` confirm() call embeds a literal `"` between single-quotes (`'… ELIMINAR DEFINITIVAMENTE "' + …`), and that `"` was closing the surrounding HTML attribute as soon as the parser reached it (well after the `@json()` fix point). The second bug was masked by the first because the first one truncated even earlier. The fix is the same pattern (`"` → `&quot;`) and lands in the same files.

The scheduler's `overflowByDay: @json(...)` looked like the same anti-pattern at first glance, but on closer inspection it lives inside `<script>window.insertCueModalData = {...}</script>`, not inside an HTML attribute. `@json()` inside `<script>` is safe and no change is needed there.

## Capabilities

### New Capabilities
- (none)

### Modified Capabilities
- `restream-channel-context`: add a requirement that the rendered `x-data` attribute for the channel-context view must produce a syntactically valid Alpine payload (no truncation, no `"`-collision) so that all descendant bindings resolve.
- `restream-targets`: add a requirement that the Restream index UI mounts in the browser without Alpine expression errors in the console.

## Impact

- **Templates (4 files)**: `currentChannel` rendering line in each Restream view, plus a 2-char substitution on the `forceDestroy` confirm() string in each Restream view.
- **Controllers (2 files)**: 1 new view variable each (client + admin RestreamHome / RestreamTarget).
- **User-visible**: the Restream page now renders fully interactive (status badges, slot counter, log/credential toggles, "Cuentas conectadas" connect/reconnect buttons, modal target row counts). Console is clean.
- **Scheduler**: pre-emptive fix to the same anti-pattern; no current regression because the value is usually empty.
- **No DB / API / auth change.**
