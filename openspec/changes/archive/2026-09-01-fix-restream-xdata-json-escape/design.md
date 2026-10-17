# Design: Fix `@json()`-inside-attribute truncation

## Context

The Restream (and scheduler) Blade views pre-encode large JSON arrays (`channels`, `targets`, `connectedAccounts`) in the controller and inject them with `{{ $fooJson ?? '[]' }}` inside the `x-data` attribute. That works because `{{ }}` runs `htmlspecialchars()` on the output, so every `"` in the JSON becomes `&quot;`, the HTML attribute survives a round-trip through the browser's parser, and Alpine reads back decoded `"` as part of valid JS.

The two Restream views also try to inject a **single nested object** (`currentChannel`) and they did it inline with `@json($currentChannel ? ['id' => …, 'display_name' => …] : null)` inside the same `x-data="…"` attribute. `@json()` outputs raw `json_encode` — it does NOT call `htmlspecialchars`. The literal `"` characters in `{"id":"…","display_name":"…"}` end the HTML attribute at the first one, the browser continues to interpret the rest of the string as additional attributes, and Alpine reads a truncated `x-data` value that stops at `currentChannel: {`.

Two scheduler views (`client/scheduler/index.blade.php`, `admin/scheduler/index.blade.php`) have the literal `overflowByDay: @json($overflowByDay)`, but on inspection it lives inside a `<script>` block (the `window.insertCueModalData` initializer), not inside an HTML attribute. `@json()` inside `<script>` is fine, so no fix is needed there.

The fix has to be applied **symmetrically** to both `client/` and `admin/` per the project's dual-implementation rule, and the Restream `currentChannel` payload is computed in the controller so it can be pre-encoded alongside the existing `*Json` strings.

## Goals / Non-Goals

**Goals**
- Restream index page mounts in the browser with **zero Alpine expression errors** in the console.
- The `x-data` attribute survives HTML encoding round-trip and contains the full payload (channels, targets, connectedAccounts, currentChannel, **plus all methods**: `csrf`, `accountFor`, `connectAccount`, `disconnectAccount`, `openCreate`, `openEdit`, `startOrStop`, `start`, `stop`, `remove`, `inactivate`, `forceDestroy`, `init`, `refresh`, `openLog`, `fetchLog`, `revealCreds`, `copyText`).
- Same fix applied to the matching scheduler pages so the latent bug doesn't fire once a real `overflowByDay` arrives.

**Non-Goals**
- No changes to the Alpine payload shape (no renamed keys, no new methods, no removed methods).
- No DB / API / auth / route changes.
- No touch to `@json()` usages that are already safe (inside `<script>…</script>`, inside single-quoted attributes like `@click='…'`).
- No bundle or caching strategy changes beyond the standard `view:clear`.

## Decisions

### Decision 1 — Encode `currentChannel` in the controller, render with `{{ }}`

We already encode `targets`, `channels`, `connectedAccounts` server-side (controller) and inject them as `$*Json` strings rendered via `{{ }}`. The cleanest fix is to follow the same convention for `currentChannel`: the controller builds `$currentChannelJson` (either `'{"id":"…","display_name":"…"}'` or `'null'`), the view consumes `currentChannel: {{ $currentChannelJson ?? 'null' }},`.

**Why not keep `@json()` in the view?**
Two options to keep `@json()` but escape the attribute:
- `currentChannel: {{ \Illuminate\Support\Js::from(...) }}` — produces `currentChannel: {…}` with JS-escaped string values, then Blade's `{{ }}` html-escapes the surrounding `"`. This works but introduces a second mental model (Js::from vs. raw json_encode) for what is otherwise a uniform pipeline.
- `currentChannel: {{ is_string($currentChannel ? json_encode([...]) : null) }}` — redundant: we're encoding in PHP just to immediately decode in JS.

The pre-encoded-string approach matches every other JSON payload in the same view and reads consistently.

**Why not swap the wrapping quotes to single quotes?**
The rest of the `x-data` attribute body contains literal `"` in JS strings (e.g. `'csrf-token'` inside `:value=…`), so flipping the outer wrap to `'` would break those. The pre-encoded approach sidesteps the quoting debate.

### Decision 2 — Don't introduce a custom Blade directive

Tempting to write a `@jsonAttr($var)` that wraps `e(json_encode($var))` once and reuse it everywhere. Out of scope: the project doesn't yet have any custom Blade directives, adding one for a four-line fix would be more code than it removes, and the convention we already follow is `pre-encode in controller + {{ }} in view`.

### Decision 3 — Same fix applied to scheduler (not just Restream)

The scheduler's `overflowByDay` is the only `@json()`-inside-`x-data` usage besides the restream ones. Pre-emptive fix is cheap (1 line × 2 files) and matches the proposal scope.

## Risks / Trade-offs

- **[Risk] HTML-attribute value length grows** → The same JSON content, with `"` replaced by `&quot;`, is roughly 6× longer per quote. For our payload sizes (current JSON ~880 chars in attribute → ~1.1 KB after escaping) this is well below any browser limit.
  - **Mitigation**: none needed; current numbers are tiny.
- **[Risk] View caching caches the now-cleared pre-encoded string** → Laravel blade view cache would not change semantics (the encoded string is deterministic for a given input), so no cache bust is required beyond the standard `view:clear` that ships in the change tasks.
  - **Mitigation**: `php artisan view:clear` after deploy (already in AGENTS.md's common-commands checklist).
- **[Risk] Future contributor reintroduces `@json(...)` inside a double-quoted attribute** → Recurrence of the same bug.
  - **Mitigation**: leave a one-line comment above each `currentChannel: {{ … }}` line in the two Restream views pointing to this change ("DO NOT switch this to @json, see change fix-restream-xdata-json-escape").
- **[Risk] Existing blade view cache serves the broken `@json()` output until cleared** → Documented in AGENTS.md "verify views" checklist; included in tasks.

## Migration Plan

1. Apply template/controller changes (4 view files + 2 controller files).
2. `php artisan view:clear`.
3. Manually re-verify in the browser via Playwright: load `/client/restream` as `pruebas`, confirm zero Alpine warnings in the console, confirm `accountFor`, `flash`, `targets` resolve and the "Cuentas conectadas" connect/reconnect buttons render with the correct emerald/amber state.
4. Repeat for `/admin/restream` (admin login) and for the two scheduler views.
5. Rollback: revert the four view files and two controllers; `php artisan view:clear`. No DB changes to roll back.

## Open Questions

- (none — the fix is mechanical and localized)
