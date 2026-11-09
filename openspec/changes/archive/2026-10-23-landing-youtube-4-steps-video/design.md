# Design

## Context

See `proposal.md` for the motivation. What shapes the technical approach:

- **Today the home page sells the module with words only.** `resources/views/landing.blade.php:143-190` renders a "Emite a YouTube en 4 pasos" heading, four text cards and two static screenshots (`public/images/demo/restream-panel.jpg`, `public/images/demo/restream-schedule.jpg`). Those two screenshots are used **nowhere else** in the codebase.
- **The real flow (verified in code and in the recording).**
  1. `Cuentas conectadas` → `Youtube [Conectar]` → Google OAuth (`client.restream.accounts.connect`) → the account stays linked to the user.
  2. `+ Nuevo destino` → `x-restream-target-modal` (`resources/views/components/restream-target-modal.blade.php`), tab *Datos del destino*: platform, name, the "usar mi cuenta" toggle and `Activar al guardar (consume un slot)` — the slot budget comes from `restream_quotas.max_outputs` (tiers 1→4) enforced by `RestreamQuotaGuard`.
  3. Tab *Programación* → calendar → sub-modal *Nueva ventana de emisión*: start, end, duration presets, time zone, title, privacy, keep-recording, thumbnail, notes (multiple windows, no limit).
  4. `RestreamRunTargetSchedulesCommand` starts the target when the window opens and stops it when it closes; the broadcast is created in YouTube by the platform, appears on the channel with live chat, and its public link is what the visitor shares.
- **The published copy does not match that flow.** The current step 2 puts title/privacy/miniature in the destination (they actually live in the *window*), and the current step 4 talks about sharing a link without ever mentioning that the broadcast is created, started and stopped automatically.
- **A real end-to-end screen recording exists** (5:46, 1920×1080, 30 fps, H.264, 30.7 MB) and it contains the whole flow, including the YouTube live page with its real chat. **It cannot be published as-is**: it shows a personal Google account (name + e-mail), the customer's channel, unrelated browser tabs/domains, and third-party broadcast content.
- **Constraints of the public site.** The Tailwind bundle (`public/css/tailwind.min.css`) is a static JIT snapshot of the views — new utility classes are silently dropped until `npm run build:css` runs. Alpine 3 is already loaded on public pages (`resources/views/components/public-layout.blade.php:70`). There is no CSP, no video infrastructure, no CDN, and the public pages must stay anonymous and cacheable. ffmpeg/ffprobe are installed on the host and already used by the media pipeline.
- **Measured facts from the recording** (see `notes/` in this change): cropping the browser chrome (`crop=1920:960:0:120`) and re-encoding four useful chapters at 720p/CRF 26 without audio yields **3.3 MB in total** (298 KB / 266 KB / 507 KB / 2.0 MB). The weight problem therefore does not exist once the video is cut per chapter.

## Goals / Non-Goals

**Goals:**
- A first-time visitor can understand the whole YouTube flow (connect → destination/slot → window → auto start/stop) by watching the real product, without reading documentation.
- The block is data-driven and reusable by other public pages without duplicating markup.
- The page keeps its current performance profile: no LCP regression, ≤ 6 MB of media per page.
- The published media is provably free of client personal data and third-party content.

**Non-Goals:**
- No change to the restream engine, the OAuth flow, the quota/tier logic, or the admin/client panels.
- No narration and no audio track in this iteration (the source audio is unusable anyway: mean −45 dB, peak −20 dB).
- No vertical landing page (`/restream-facebook-youtube-tiktok`, etc.) is rewired in this change; the component is built so that it can be later.
- The public home page keeps exactly one `<h1>` and the existing SEO/CTA contract (`openspec/specs/landing/spec.md`).

## Decisions

**D1 — Media strategy: cut a sanitized master, prefer a clean re-record.**
The delivered asset is a *sanitized* recording. Two production paths, same deliverables:
- *Preferred*: re-record the flow with a Cloudstream demo Google account, a demo channel ("Canal Demo"), a demo client account, a clean browser profile (no personal tabs), 1280×720, deliberate pacing.
- *Fallback (implemented)*: sanitize the existing recording — crop to the application area, mask the account name/e-mail, cut the segment that shows third-party broadcast content, and drop the audio.
Both paths end at the same place: four clips + four posters, reviewed against the sanitization checklist.
*Alternative rejected*: publish the recording as-is (privacy + third-party content), or drop the video and use annotated screenshots (loses the proof that the emission actually reaches YouTube with chat, which is the most persuasive moment).
*Implemented outcome*: the whole YouTube portion of the recording (4:12–5:46) was **excluded** — it showed a third-party news programme with its live chat and the customer's own channel, name, avatar and subscriber count. The published proof is the "En vivo en YouTube" badge and the daemon stats of the Cloudstream panel. The sanitization checklist and the sign-off live in `notes/`.

**D2 — Un video continuo + un clip por capítulo + una captura por paso.**
Medición real: los cuatro capítulos suman **67 s y 1,3 MB** (22 s conectar · 15 s destino · 23 s ventana · 6,5 s en vivo), frente a 30,7 MB de la grabación original.
- **Video continuo** (`walkthrough-completo.mp4`, concatenación de los capítulos por *stream copy*): es lo que reproduce la home, para que el visitante vea el flujo entero de un tirón — conectar → destino → programación → en vivo — sin huecos.
- **Clip por capítulo**: se publican también por separado (`conectar-cuenta.mp4`, …) para reutilizarlos en redes, soporte y las landings verticales.
- **Captura por paso**: cada paso muestra su propio *print* de la interfaz (el poster del capítulo), con la instrucción escrita al lado. Es lo que el visitante lee sin reproducir nada, y lo que hace que la sección se vea uniforme paso a paso.
Los pasos hacen *seek* dentro del video continuo a su offset (0 / 22 / 37 / 60 s) y el poster del reproductor sigue al paso activo.
*Alternativa descartada*: un solo archivo de 30,7 MB (la grabación cruda) y un solo clip largo por paso sin capturas (el visitante no entiende qué está viendo en cada paso).

**D3 — Native `<video>` + Alpine for the chapter jumps.**
The public layout already loads Alpine 3; the step list is an Alpine component (`window.videoWalkthrough`) that sets `video.currentTime` to the chapter offset and calls `video.play()`. No video.js, no custom player, no build step.
*Alternatives rejected*: a JS media library (weight, complexity), plain inline `<script>` (duplicated when the block is reused).

**D4 — One reusable Blade component, data-driven.**
`<x-public-video-walkthrough :src="…" :poster="…" :chapters="[…]" />` in `resources/views/components/`, where each chapter is `{ id, start (seconds), title, text }`. The component owns the markup, the Alpine behaviour and the ARIA wiring; the *content* (chapter texts, titles) lives in the page/controller so it can be reused and later translated. The home page supplies the YouTube walkthrough data; other public pages can supply their own.

**D5 — Assets are static, first-party files, with an application route for the `.mp4`.**
`public/videos/youtube-walkthrough/*.mp4` (el video continuo + un clip por capítulo) y `public/images/demo/youtube-walkthrough/*.jpg` (la captura de cada paso). No database, no auth — the public site stays statically cacheable and the media is servable from any CDN later without code changes.
*Finding during implementation*: the nginx vhost of this site only serves a fixed list of extensions as static files (`css|js|jpg|jpeg|png|gif|ico|svg|woff…`); `.mp4` is not in it, so the clips were being answered with a 404 even though the files were in `public/`. Instead of editing the server configuration (outside the repository), the clips are delivered by `PublicVideoController` (`GET /videos/{slug}/{file}`), which validates the slug and the file name against `config('seo.walkthrough')` and returns a `BinaryFileResponse` with `Accept-Ranges: bytes` (so the browser can seek) and a long `Cache-Control`. The screenshots are still served statically by nginx.
*Alternative rejected*: storing the clips as `media_items` of a channel (the streaming endpoints are session-authenticated web routes — an anonymous visitor could not watch them; and it would mix public marketing assets with customer media).

**D6 — Encoding profile (the "walkthrough build script").**
`scripts/build-video-walkthrough.php` (or a shell wrapper) reads a chapter manifest and, for each chapter: `ffmpeg -ss <start> -t <duration> -i <source> -vf "crop=1920:960:0:120,scale=1280:640" -c:v libx264 -preset medium -crf 26 -pix_fmt yuv420p -movflags +faststart -an` plus a `-frames:v 1` poster at the chapter's first second. Rationale: the crop removes the browser chrome and the personal tabs; 2:1 keeps the application sidebar (it shows the visitor they are inside the *Restream* module); CRF 26 without audio is what produced the 3.3 MB measurement; `faststart` so the moov atom is at the front.
*Alternative rejected*: CRF 23 / 1080p (roughly double the weight for no visible gain on a screen recording that is soft to begin with).

**D7 — Silent video, no autoplay.**
The audio of the source is unusable, so the clips are muxed without audio (`-an`); that also removes the need for a caption track in this iteration and removes any autoplay-with-sound problem. The video is never autoplayed: it shows a poster and the native controls (`preload="none"`, `playsinline`, `muted`), so `prefers-reduced-motion` is honoured by construction.
*Future*: if a narrated version is produced, it needs a `captions` track (`.vtt`) and the muted-autoplay rule must be revisited — that is a separate change.

**D8 — Chapter data and copy.**
Four chapters, with the step texts rewritten to match the real flow (the text is normative in `specs/landing/spec.md`):

| # | Título | Contenido clave | Origen en el video |
|---|---|---|---|
| 1 | Conecta tu cuenta de YouTube | Google OAuth, una sola vez por cuenta; también Facebook | `0:05–0:30` |
| 2 | Crea el destino (tu slot) | plataforma, "usar mi cuenta", activar consume 1 slot del plan | `0:41–1:00` / `2:25–2:45` |
| 3 | Programa la ventana de emisión | inicio, fin, zona horaria, título, privacidad, miniatura, varias ventanas | `1:03–2:05` |
| 4 | Enciende y comparte | arranque manual o automático, stop automático, emisión en tu canal con chat, link público | `2:45–3:15` + `3:15–5:46` |

**D9 — SEO.** The JSON-LD partial (`resources/views/partials/public/schema.blade.php`) gains a `VideoObject` node in the `home` branch (name, description, thumbnailUrl, contentUrl, uploadDate, duration, embedUrl). The layout emits `og:video`, `og:video:type`, `og:video:poster` (and `og:video:url`) next to the existing OG tags. `config/seo.php` gets the `lastmod` bump for `/`.
*Note*: `og:video` is a Facebook/Open Graph property — Twitter uses `twitter:player` (iframe-only) and therefore cannot point at an MP4; the MP4 is exposed through `og:video*` and the JSON-LD only.

**D10 — The two existing screenshots stay, as the no-video fallback.**
`restream-panel.jpg` and `restream-schedule.jpg` are only used by this section, so they are kept and re-rendered as the static illustration under the walkthrough (visible when the media cannot load, useful as a `<noscript>`-ish fallback and for the social preview). The poster frames come from the video itself.

## Risks / Trade-offs

- **[Risk] The recording contains client personal data and third-party broadcast content.** → Mitigation: the sanitization checklist + written sign-off before anything lands in `public/`; the source file stays outside the repository (it must be removed from the working tree, which currently holds it at the repository root); D1's preferred path removes the problem at the source.
- **[Risk] Tailwind's static bundle silently drops new utility classes** (verified: `line-clamp-2` and `text-rose-400` are absent from `public/css/tailwind.min.css` today). → Mitigation: run `npm run build:css` after the markup changes and `php artisan view:clear` afterwards; verify each new class is present in the built CSS before declaring the section done.
- **[Risk] Native `<video>` differs across browsers** (inline playback on iOS, control rendering). → Mitigation: no autoplay, controls always visible, `playsinline`, `muted`, `preload="none"`.
- **[Risk] The walkthrough drifts from the UI** as the product changes. → Mitigation: the build script regenerates assets from a manifest; a `README` next to the assets records the source recording date and the app version it shows.
- **[Risk] Media weight on a shared host.** → Mitigation: 6 MB budget per page, `faststart`, `preload="none"`, poster-first; long-lived cache headers for `/videos/**`; CDN later without code changes (D5).
- **[Risk] Archiving is blocked**: `openspec/specs/landing/spec.md` begins with the delta header `## ADDED Requirements` instead of `## Requirements`, so the archive tool considers the main spec structurally invalid and refuses to merge this delta. 26 of the 73 main specs in this repository carry the same leftover header, i.e. it is a repo-wide pre-existing condition, not something this change introduced. → Mitigation: a task normalizes `openspec/specs/landing/spec.md` to the canonical `## Requirements` header (one line) before archiving; the repo-wide normalization of the other 25 specs is explicitly **out of scope** and left to a dedicated housekeeping change.
- **[Trade-off] Silent video** → less emotive than a narrated one, and the visitor gets no voice-over of the four steps. Accepted for now: the step texts carry the message and the video carries the proof. Revisit with a narrated + captioned master.
- **[Trade-off] Step texts live in the page, video chapters in the manifest** → two places to update when the copy changes. Accepted: the page copy must be translatable and readable without the video, the manifest is a build artefact.

## Migration Plan

1. Produce the sanitized master (D1) and generate the clips + posters with the build script; review against the sanitization checklist and record the sign-off.
2. Add the component, wire the home page section, extend the JSON-LD and the OG metadata, bump `lastmod`.
3. `npm run build:css` → `php artisan view:clear` → `php artisan view:cache` (verification) → clear config/route cache as usual.
4. Deploy: the new files under `public/` are additive; the landing section change is a single Blade file. No database migration, no config migration, no API change.
5. **Rollback**: revert the landing section (and optionally the component) — the media files can stay on disk unused; nothing else in the system depends on them.

## Open Questions

- Should the same walkthrough block be wired into `/restream-facebook-youtube-tiktok` and the blog? (The component supports it; only the home page is wired here.)
- The vertical landing pages carry the *same* copy drift about where title/privacy/thumbnail live — corrected here only for the home page. A follow-up change should align the copy across the vertical pages.
- Should a narrated + captioned master be produced for social reuse (Reels/Shorts/TikTok vertical cuts)?
- The site declares `hreflang` for `es` and `es-CO`; a second chapter data set in English could be added later without touching the component.
