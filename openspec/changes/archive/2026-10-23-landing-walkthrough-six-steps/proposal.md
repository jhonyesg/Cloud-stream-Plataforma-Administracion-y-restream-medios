# Proposal

## Why

The YouTube walkthrough section published on the home page showed **six images** (four sanitized captures of the recorded flow plus two older static screenshots of the panel) but only **four steps**, so the visuals and the narrative did not line up: the visitor counted six pictures and read four instructions, and two of those pictures were not part of the flow at all. Those two older screenshots also carried the customer's personal name ("Efraín Suárez (Jhon Suarez)") and an account e-mail, i.e. they were publishing a third party's identity on a public marketing page. The section is therefore rebuilt as **six steps with one sanitized capture each**, in the order of the flow, and the two unsanitized screenshots leave the public site.

## What Changes

- **Six steps instead of four**, in the order of the real flow: the Restream panel → connect the YouTube account → create the destination and occupy a slot → program the emission window → schedule several windows per destination → turn it on and share it.
- **One sanitized capture per step**, all cut from the same recorded session (so they look uniform): the panel, the Google consent screen, the destination modal, the emission-window modal, the scheduling tab and the live destination with its daemon log. The video is re-cut with the same six chapters and the steps seek to their chapter.
- **The two static screenshots that leaked personal data are removed** from the home page and deleted from `public/images/demo/`, so the public site stops publishing a third party's name and e-mail.
- The JSON-LD `VideoObject` and the `og:video*` metadata are updated to the new video (73 s).

**Non-goals**: no change to the restream engine, the OAuth flow or the quota/tier logic; the other demo screenshots of the home (dashboard, multimedia, scheduler, restream) keep showing the platform's test account, which is a separate cleanup.

## Capabilities

### New Capabilities
- (none)

### Modified Capabilities
- `landing`: the YouTube walkthrough section of the home page becomes six steps with one capture each, and the requirement set gains an explicit ban on publishing screenshots that contain third-party personal data.

## Impact

- **Views**: `resources/views/landing.blade.php` (six steps, the two legacy screenshots removed), `scripts/video-walkthrough.source.json` (six chapters instead of four).
- **Media**: `public/videos/youtube-walkthrough/` (six clips + the continuous video, 73 s, 1,5 MB) and `public/images/demo/youtube-walkthrough/` (six captures). `public/images/demo/restream-panel.jpg` and `restream-schedule.jpg` are deleted.
- **Tests**: `tests/Feature/LandingYouTubeWalkthroughTest.php` covers the six steps and the absence of personal data in the published HTML.
- No API, database or schema changes.
