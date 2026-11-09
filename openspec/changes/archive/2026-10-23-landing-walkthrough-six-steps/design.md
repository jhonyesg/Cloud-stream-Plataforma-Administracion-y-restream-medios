# Design

## Context

See `proposal.md` for the motivation. What shapes the approach:

- The section rendered **six images and four steps**: the four captures cut from the recorded session plus two older static screenshots (`restream-panel.jpg`, `restream-schedule.jpg`) that were left below as a "static fallback". The visitor saw pictures that did not correspond to any step, and the two legacy ones showed the customer's personal name and account e-mail — data of a third party published on a public marketing page.
- The recorded session already contains every moment the flow needs: the Restream panel, the Google consent screen, the destination modal, the emission-window modal, the scheduling tab and the live destination with its daemon log. So the six captures and the six chapters of the video can all come from the same material, and the section becomes visually uniform.
- The build pipeline (`scripts/build-video-walkthrough.php` + `scripts/video-walkthrough.source.json`) already supports per-chapter segments, per-segment crops, redaction boxes and posters; adding chapters is a manifest change, not new code.

## Goals / Non-Goals

**Goals:**
- Six steps, six captures, one per step, in the order of the flow.
- The public site stops publishing third-party personal data.
- The video keeps covering the whole flow (73 s) and each step seeks to its chapter.

**Non-Goals:**
- No re-recording: the same sanitized session is reused.
- The remaining demo screenshots of the home (dashboard, multimedia, scheduler, restream) keep showing the platform's own test account; that cleanup is out of scope.

## Decisions

**D1 — Six chapters in the manifest, one per step.**
The manifest gains an `el-panel` chapter (the panel before connecting) and a `varias-ventanas` chapter (the destination's *Programación* tab, where the client adds more windows). The rest keep their segments. The build script computes the cumulative offsets, so the step ↔ chapter mapping stays automatic.
*Alternative rejected*: reuse the two legacy screenshots as steps 5 and 6 — they are unsanitized, they show an older version of the UI and they break the visual uniformity.

**D2 — Captures are the chapter posters, cut from the same session.**
Each step's image is the poster of its chapter, so image and video can never drift apart: the same moment, the same redaction boxes, the same crop. The posters are 40-70 KB each; the whole section stays under 2 MB.
*Alternative rejected*: taking separate screenshots later with a different account — that is what produced the leak in the first place.

**D3 — The legacy captures are deleted, not just unlinked.**
They live in `public/`, which is served directly by nginx; leaving them on disk would keep a copy of a person's name and e-mail one `curl` away from anyone. They are unversioned files, so deleting them is enough.

**D4 — The "no personal data" rule becomes a requirement, not a habit.**
A scenario asserts that the published HTML contains no personal name, no account e-mail and no stream key, so a future screenshot cannot reintroduce the leak silently.

## Risks / Trade-offs

- **[Risk] A screenshot can leak personal data again** → Mitigation: the redaction boxes are declared in the manifest (not hand-painted), the sanitization checklist is part of the change, and the new test fails if the published HTML contains personal data.
- **[Risk] The video grows with every chapter** → Mitigation: the budget is 6 MB per page; the continuous video is 1,5 MB and the whole section 1,8 MB.
- **[Trade-off] Six steps are more scrolling than four** → Accepted: each step is a self-contained card with its own capture, and the visitor can jump straight to one from the video.
- **[Trade-off] The panel appears twice** (as step 1 and behind the modals) → Accepted: step 1 is the only place that explains the module as a whole (channel, slots, connected accounts, destinations).

## Migration Plan

1. Update the manifest with the six chapters and re-run the build script (regenerates clips, posters and the continuous video).
2. Update the home page: six steps with their captures and texts, and remove the two legacy screenshots.
3. Delete the two legacy files from `public/images/demo/` and rebuild the Tailwind bundle.
4. **Rollback**: restore the four-step section and the two files; the media is additive.

## Open Questions

- Whether the same six-step treatment should be applied to the vertical landing pages (`/restream-facebook-youtube-tiktok`), which still show the old four-step text.
- Whether the other demo screenshots of the home should be re-cut with a sanitized account (they currently show the platform's test account, `pruebas@pruebas.com`).
