# Proposal

## Why

The home page sells the YouTube restream module with static text and two screenshots, but the copy no longer matches how the product actually works: the real flow is *connect the account (OAuth) → create the destination/slot → program the emission window → auto start/stop*, and the two concepts that block a first-time user (how many simultaneous destinations their plan grants, and that title/privacy/thumbnail live in the *window* and not in the destination) are not expressible in prose. A real end-to-end screen recording of the flow exists and proves it works; turning it into a chaptered, sanitized walkthrough makes the module self-explanatory for any visitor, and reuses the same material for sales and support.

## What Changes

- **New reusable public walkthrough block** — a Blade component that renders a chaptered `<video>` plus a step list where each step seeks the video to its chapter. Data-driven so it can be reused on other public pages without duplicating markup.
- **The "Emite a YouTube en 4 pasos" section of the home page is replaced** by that walkthrough, and its four steps are rewritten to match real product behavior:
  1. *Conecta tu cuenta de YouTube* (Google OAuth, once per account),
  2. *Crea el destino* — the platform, "usar mi cuenta", and the fact that activating it **consumes one slot** of the plan's tier,
  3. *Programa la ventana de emisión* — start, end, time zone, title, privacy, thumbnail, keep-recording (multiple windows allowed),
  4. *Enciende y comparte* — manual or automatic start at the scheduled time, automatic stop at the end, the broadcast published on the YouTube channel with live chat, and the public link to share.
- **New sanitized media pipeline** — a script that cuts the source recording into one clip per chapter (cropped to the application area, 720p, `faststart`, no audio) and renders a poster per chapter. The source recording is never published.
- **Publication gate for the assets** — the walkthrough media must not contain third-party personal data (e-mail, personal account names, unrelated browser tabs/domains) or third-party broadcast content; a written sign-off is required before the assets land in `public/`.
- **Performance and accessibility rules** — posters + `preload="none"` (no LCP penalty), never autoplay with sound, the step text is always present in the DOM, `prefers-reduced-motion` disables autoplay, and the section degrades gracefully (text + poster) if the video file is missing.
- **SEO** — the home page gains a `VideoObject` in its JSON-LD graph plus `og:video` / `og:video:type` / `og:video:poster` metadata, and its `lastmod` is bumped.

Explicit **non-goals**: no change to the restream engine, the OAuth flow, the quota/tier logic or the admin/client panels; no re-cut of the vertical landing pages (the component is built to be reusable, but only the home page is wired in this change).

## Capabilities

### New Capabilities
- `public-video-walkthrough`: A reusable, data-driven walkthrough block for public pages that plays a local, sanitized, chaptered screen recording of a product flow, with step navigation, accessibility, performance budget and a reproducible asset pipeline.

### Modified Capabilities
- `landing`: The home page's YouTube section becomes the video walkthrough (four steps that describe the real flow, including the slot/tier concept), the walkthrough video is a local asset with a poster, the home page JSON-LD gains a `VideoObject` and the page advertises `og:video`.

## Impact

- **Views**: `resources/views/landing.blade.php` (section replaced), new `resources/views/components/public-video-walkthrough.blade.php`, `resources/views/components/public-layout.blade.php` (OG video meta), `resources/views/partials/public/schema.blade.php` (`VideoObject` in the `@graph`).
- **Config**: `config/seo.php` — `public_urls['/'].lastmod` bump, optional video meta values.
- **New assets**: `public/videos/<slug>/*.mp4` (one clip per chapter) and `public/images/demo/<slug>-step-N.jpg` (posters). Target total ≤ 6 MB.
- **New tooling**: `scripts/build-video-walkthrough.php` (ffmpeg cut/crop/scale/poster generation) plus a written sanitization checklist.
- **Build**: `npm run build:css` is required after the new Blade markup introduces Tailwind classes that are not yet in the static bundle (`public/css/tailwind.min.css` is a JIT snapshot — e.g. `line-clamp-2` and `text-rose-400` are currently absent). Followed by `php artisan view:clear`.
- **No API, database or schema changes.** No dependency changes (ffmpeg is already installed on the host and used by the media pipeline).
- **Operational**: the source recording (30.7 MB at the repository root) must be moved out of the versioned tree; it is client data and must not be committed.
