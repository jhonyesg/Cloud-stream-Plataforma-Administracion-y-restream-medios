# Design

## Context

The MediaServer Platform already computes the full URL matrix per stream (`backend/services/url_builder.py`): `publish_urls()` returns the RTMP/SRT ingest endpoints and `play_urls()` returns HLS, FLV, WebRTC, MPEG-TS and SRT playback. The operator sees it in the platform panel's URL modal. The Laravel application has **no** equivalent: `config/mediaserver.php` exposes a `public_host` key, but nothing reads it, and `MediaserverMetricsAggregator::normalize()` drops the `urls` payload the platform API already returns.

Constraints that shape this change:

- The landing page is a **static marketing page** rendered by `resources/views/landing.blade.php` through `<x-public-layout>`. It has no channel context and no session.
- The production streaming host is `canal.mediaserver.com.co` (DNS → `15.204.8.139`) and the platform's `PUBLIC_DOMAIN` points at it. Those values live in the platform's env and in `MEDIASERVER_PUBLIC_HOST` / `MEDIASERVER_API_URL`; both are internal.
- Public views currently print **no** internal host, no IP and no customer data — that invariant has to survive.
- `public/css/tailwind.min.css` is a **static JIT build** scoped to `resources/views/**`. Any new utility class is silently dropped until `npm run build:css` runs.
- The page has no `<x-` component of its own yet; the design system vocabulary is `glass-card`, `title-grad`, `feature-check`, `btn-primary`, `btn-ghost`, `strike`, plus the Tailwind palette used across the page.

## Goals / Non-Goals

**Goals:**
- Publish the real protocol matrix on the home page, in one section, without exposing production endpoints.
- Keep the rendered URLs derivable from one configuration file, so the same builder can later serve the client panel with real per-channel values.
- Reuse the existing page design vocabulary so the section reads as part of the home page, not as a pasted widget.

**Non-Goals:**
- No live demo links: `streaming.mediaserver.com.co` is intentionally **not** created in DNS, so the published URLs are templates.
- No change to the MediaServer Platform, its URL builder, nginx or the production delivery configuration.
- No new page, route or sitemap entry: the section lives inside `/`.
- No per-client or per-channel dynamic data.

## Decisions

### D1 — Showcase host is `streaming.mediaserver.com.co`, and it does not resolve

The published host is a **display-only** name that currently has no DNS record.

Alternatives considered:

| Option | Why rejected |
|---|---|
| `canal.mediaserver.com.co` (production host) | Publishes the real delivery endpoint to the public page — exactly what this change must avoid. |
| `15.204.8.139` (the IP the operator panel shows) | Same, plus it exposes infrastructure details. |
| `tv.mediaserver.com.co` | Already resolves to a **different** server (`207.244.232.6`) — would send prospects to the wrong infrastructure. |
| `live.mediaserver.com.co` | Does not resolve either, but reads as a channel name, not a service; `streaming` matches the product. |
| A record + nginx `server_name` alias to the production vhost | Would make the published URLs work, but it **re-publishes production under a second name**: anyone could watch production channels by guessing a slug. Rejected on purpose. |

Consequence, accepted explicitly: the URLs do not resolve. The copy therefore frames them as the *format* that is delivered, and states that the real hosts and the stream key arrive with the activation of the plan.

### D2 — A new `config/streaming_public.php`, not `config/mediaserver.php`

`config/mediaserver.php` already has a `public_host` key, but it holds the production IP, it is read by nobody, and reusing it would put the IP one `config()` call away from a public Blade. A dedicated file makes the leak impossible by construction:

```php
return [
    'display_host' => 'streaming.mediaserver.com.co',  // display only, no DNS record
    'example_slug' => 'tu-canal',
    'ports' => ['rtmp' => 1935, 'srt_publish' => 10080, 'srt_play' => 7386],
];
```

The file is the single source of truth for the rendered URLs, so the future client-panel variant (real channel, real host) is a different binding of the same builder and not a second implementation.

### D3 — Placeholder channel `tu-canal`, never a customer stream

The platform panel screenshot that motivated this feature shows a **real customer stream** (`buenisimatv`). Publishing it would leak the customer's channel name and give away a working URL. Every published URL therefore carries the placeholder `tu-canal`, and the copy tells the visitor to replace it.

### D4 — One Blade component: `<x-public-delivery-urls />`

`resources/views/components/public-delivery-urls.blade.php` (anonymous component) instead of markup inlined in `landing.blade.php`, because the same block is wanted in the seven vertical landings (`resources/views/public/landings/`) and later in the client panel. The component receives optional props for the heading and the copy; the home page uses the defaults.

### D5 — Copy control per row, with a "replace the placeholder" confirmation

Each row ships a copy button that copies that row's template. `navigator.clipboard` requires a secure context, so the button falls back to a hidden `<textarea>` + `document.execCommand('copy')` when the Clipboard API is unavailable (older browsers, non-HTTPS). The confirmation toast states that `tu-canal` has to be replaced — that is the whole point of copying a template.

Accessibility: each button carries an `aria-label` naming the protocol (`"Copiar la URL RTMP"`), the URL text is `selectable` (`user-select: all`) so it can also be dragged, and the toast is announced with `role="status"`.

### D6 — Placement: between pricing and FAQ, with a `formatos` anchor

`landing.blade.php` ends the pricing block at line 322 and opens the FAQ block at line 324, so the section is inserted exactly at that seam. It gets `id="formatos"` and the public nav gains a `formatos` link between "Planes" and "Contacto". The heading is an `<h2>` — the home page keeps exactly one `<h1>`.

### D7 — Reuse the existing visual vocabulary, and rebuild Tailwind

The section uses the page's own classes (`glass-card`, `title-grad`, `feature-check`, `btn-ghost`, `text-slate-400`, the blue/emerald accents already used by the platform panel) instead of inventing new ones. Since `public/css/tailwind.min.css` is a static JIT build, the bundle is regenerated with `npm run build:css` **after** the Blade is written, and the new classes are verified to exist in the bundle (a missing class silently renders as "no background/color").

### D8 — The two blocks are stacked, not side by side

The emission block and the delivery block render in **one column** (`grid-cols-1`), the emission block first and the delivery block below it.

Alternatives considered:

| Option | Why rejected |
|---|---|
| Two columns (`lg:grid-cols-2`) | The delivery block carries nine protocol rows against the emission block's two: in a two-column layout the shorter card leaves a tall empty column and the URL rows have to truncate (`rtmp://…:1935/live/…`), which hides exactly the information the section exists to publish. |
| Two columns with the emission block on top and the delivery block spanning the full width | Keeps the asymmetry but still leaves the empty column next to the short card. |

Stacked, each row is wide enough to show its **full URL without truncation**, the section reads top-to-bottom in the same order as the signal flows (publish → consume), and the mobile layout is identical to the desktop one. The section also grows taller (~2000 px), which is the cost of publishing eleven URLs without hiding them.

## Risks / Trade-offs

- **[Prospect pastes a template into OBS and it fails]** → The URLs are explicitly labelled as examples of the delivered format; the note under the cards states that the real hosts and the stream key are handed over with the activation. Accept that the showcase is a catalogue, not a demo.
- **[A visitor believes the section is a live demo]** → Same wording plus the "reemplaza `tu-canal`" confirmation on copy. Revisit only if a dedicated demo host is ever created.
- **[New Tailwind classes silently dropped]** → `npm run build:css` is a mandatory task, and the verification greps the new classes in `public/css/tailwind.min.css`.
- **[The section breaks the "exactly one H1" rule]** → The heading is an `<h2>`; covered by the feature test.
- **[A future public page leaks the IP by copying this markup]** → The component reads only `config/streaming_public.php`, and the feature test asserts the absence of the IP and of the production host in the published HTML of every public page.
- **[Nine similar URLs read as keyword stuffing]** → All rows live in a single section of a single page, are labelled with their protocol, and carry no keyword stuffing; there is no separate indexable page.

## Migration Plan

Additive and reversible: create the config and the component, insert the section in `landing.blade.php`, add the nav anchor, rebuild the CSS bundle, clear the compiled views. Rollback = remove the section include, the nav anchor and the component (the config can stay). No database, no API, no platform change.

## Open Questions

- Whether the same section should be added to the seven vertical landings (`/streaming-para-iglesias-colombia`, etc.) — a follow-up change; the component is built for it.
- Whether a **real** demo channel should exist later, and in that case whether it deserves its own host rather than an alias of the production vhost.
- Whether the client panel should gain the same matrix with the customer's real values (the client currently sees only the hand-typed `public_hls_url`); the component and config are designed for it, but it is out of scope here.
