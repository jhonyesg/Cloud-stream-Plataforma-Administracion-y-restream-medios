# Proposal

## Why

The public home page sells the service but never shows **how the signal is actually delivered**. A prospect reads "protocolos RTMP + HLS" in the plan card and has to imagine the rest: which encoder protocols we accept, in how many formats the audience can consume the channel, whether the signal can be handed to a cable operator, a satellite decoder (Astra, Lastra, Cesbo) or a VLC laptop. That is exactly the scope the operator panel already computes per stream (the `PUBLICAR` / `CONSUMIR` matrix in the MediaServer Platform URL modal), but it lives behind an authenticated panel and therefore reaches nobody.

Publishing that matrix on the home page lets a prospect dimension the product before talking to sales: seven delivery formats, HTTPS + plain HTTP + SRT, the MPEG-TS path for set-top boxes and cable trunks. It has to be published **masked**: the showcase host (`streaming.mediaserver.com.co`) is not the production host (`canal.mediaserver.com.co`), the production IP is never printed, and the channel name is a placeholder (`tu-canal`) instead of a real customer's stream.

## What Changes

- **New home section** "Una señal. Todos los formatos." placed between "Planes y precios" and "Preguntas frecuentes", with a `formatos` anchor added to the public nav.
- **Two protocol cards**: `PUBLICAR` (RTMP `:1935`, SRT `:10080` — the encoders OBS, FMLE, vMix, Wirecast) and `CONSUMIR` (HLS, FLV, WebRTC and MPEG-TS over HTTPS *and* over plain HTTP, plus SRT read `:7386` — the players vMix, OBS, Astra, Lastra, FFplay, VLC).
- **Flow strip** "tu estudio → datacenter → tu audiencia" explaining how we handle the signal: one emission, ingest RTMP/SRT, seven outputs.
- **MPEG-TS callout** explaining why that format is the one set-top boxes and cable/satellite trunks speak — the differentiator for the Colombian broadcast market.
- **Copy control per row** that copies the URL template and states that `tu-canal` has to be replaced by the channel name.
- **New reusable Blade component** `resources/views/components/public-delivery-urls.blade.php` + **new config** `config/streaming_public.php` holding the showcase host, the ports and the example channel name — the only source of truth for the rendered URLs.
- **Leak guardrail**: no public view may render the production IP, the production streaming host or a real customer stream name; covered by a feature test.

**Non-goals**: no DNS record is created for `streaming.mediaserver.com.co` — the published URLs are **templates**, not live links, and the copy says so; the MediaServer Platform URL builder, the client panel, the restream module and the production delivery configuration are untouched.

## Capabilities

### New Capabilities
- `public-delivery-urls`: the public showcase of the emission and delivery protocols — the `PUBLICAR`/`CONSUMIR` matrix, the masked showcase host and placeholder channel, the copy controls, the MPEG-TS explanation and the rule that production endpoints are never published.

### Modified Capabilities
- `landing`: the home page gains the delivery-formats section between the pricing and the FAQ blocks, addressable through the public nav.

## Impact

- **Views**: `resources/views/landing.blade.php` (new section between the pricing and FAQ blocks), `resources/views/components/public-delivery-urls.blade.php` (new reusable component), `resources/views/components/public-nav.blade.php` (new `formatos` anchor).
- **Config**: `config/streaming_public.php` (new) — showcase host, ports, example channel name. `config/mediaserver.php` is **not** reused: its `public_host` holds the production IP and is never rendered.
- **Tests**: `tests/Feature/LandingDeliveryUrlsTest.php` (new) — format matrix, ordering on the page, copy controls, and the no-leak assertions.
- **Assets**: `public/css/tailwind.min.css` rebuilt with `npm run build:css` (the bundle is a static JIT build; new utility classes are dropped until it is regenerated).
- No API, database, schema or platform change.
