## Context

The current restream stack is fully manual and platform-agnostic: `RestreamTarget` stores a `destination_url` + encrypted `stream_key`, `RestreamOrchestrator` builds an `ffmpeg` command from those two fields and spawns `restream_daemon/main.py`, which pushes and reports heartbeats back to Laravel. Nothing in this path cares which real-world platform the target is — it's just an RTMP sink. This is a strength: adding OAuth-based account connection does not require touching the push engine at all, only how `destination_url`/`stream_key` get populated and adding richer metadata (title/description/thumbnail/schedule) that the platform APIs need.

Both target platforms have a documented, self-serve Live API:
- **YouTube**: Live Streaming API v3. Flow: `liveBroadcasts.insert` (title, description, scheduled start time, privacy) → `liveStreams.insert` (creates the ingest endpoint, returns `cdn.ingestionInfo.ingestionAddress` + `streamName` i.e. the key) → `liveBroadcasts.bind` (links broadcast to stream) → optional `thumbnails.set`.
- **Facebook**: Graph API `POST /{page-or-user}/live_videos` with `title`, `description`, `planned_start_time` returns `stream_url` (which already embeds the key) directly in one call — simpler than YouTube's three-call dance.

TikTok has no equivalent self-serve API (Live Streaming access requires a manual TikTok partner approval process outside this app's control) — explicitly excluded from this change per the proposal.

## Goals / Non-Goals

**Goals:**
- Let a user connect a YouTube and/or Facebook account via OAuth2, scoped to Live streaming permissions only.
- Let a user create a scheduled (or immediate) live event on a connected platform from within this app, supplying title/description/thumbnail, without manually visiting YouTube Studio / Facebook Live Producer.
- Auto-populate `restream_targets.destination_url`/`stream_key` from the platform's API response for these connected targets.
- Auto-start the target at `scheduled_start_at` via the existing `RestreamOrchestrator`/supervisor path — no change to how ffmpeg is actually launched.

**Non-Goals:**
- TikTok integration (blocked on TikTok partner approval, tracked as a future change once/if access is granted).
- Building a generic "social media scheduler" — this only covers the live-broadcast creation needed to obtain a destination + credentials, not general content publishing.
- Changing `RestreamOrchestrator`'s ffmpeg command building or the Python daemon's push/heartbeat mechanism.
- Multi-account-per-platform-per-user in v1 (a user connects at most one YouTube and one Facebook account each; revisit if requested).

## Decisions

**1. New `restream_platform_accounts` table, not columns on `users`.**
A user may connect zero or one account per platform. Store `user_id`, `platform` (`youtube`|`facebook`), `platform_account_id`/`name` (for display), `access_token`/`refresh_token` (encrypted at rest — reuse the same `Crypt::encryptString` pattern already used for `RestreamTarget.stream_key`), `token_expires_at`, `scopes`, timestamps. Unique on `(user_id, platform)`.
Alternative considered: store tokens directly on `restream_targets`. Rejected — an account is reused across multiple targets/broadcasts over time; coupling tokens to a single target would force re-authenticating per target and complicate refresh.

**2. Broadcast metadata lives on `restream_targets`, not a separate table.**
Add nullable columns: `platform_account_id` (FK, nullable — null means manual/unconnected target), `title`, `description`, `thumbnail_path`, `scheduled_start_at`, `platform_broadcast_id` (the id YouTube/Facebook assigned, for later updates/lookups).
Alternative considered: a separate `restream_broadcasts` table 1:1 with targets. Rejected as unnecessary normalization for v1 — a target already represents "one destination configuration"; the broadcast is just richer configuration of the same thing, and 1:1 tables add join overhead without benefit here.

**3. Platform API calls happen synchronously on target create/update, not queued.**
Creating a YouTube broadcast is a 3-call round trip (a few hundred ms to ~1-2s typically) — acceptable inline in the request/response cycle with a reasonable timeout, matching the "Update stream details → Update" UX from the reference screenshot (user waits briefly, then sees confirmation). If external latency becomes a problem in practice, this can move to a queued job with polling — noted as an open question, not decided now.

**4. Auto-start via existing scheduler infrastructure.**
Add a `restream:launch-scheduled` Artisan command (or extend the existing supervisor tick) that queries `restream_targets` where `scheduled_start_at <= now() AND enabled = false AND status = 'idle'`, sets `enabled = true`, and lets the existing supervisor/orchestrator pick it up on its normal cadence — no new spawn path.

**5. OAuth callback controllers per platform, under `Client/Restream/`.**
`GET /client/restream/accounts/{platform}/connect` redirects to the platform's consent screen; `GET /client/restream/accounts/{platform}/callback` exchanges the code, stores the account row. Standard Laravel Socialite-style flow — evaluate using Socialite's Facebook/Google drivers (already handles token exchange/refresh boilerplate) vs. hand-rolled HTTP calls; Socialite is recommended to avoid re-implementing OAuth2 exchange and refresh logic.

## Risks / Trade-offs

- [Risk] Refresh tokens can be revoked externally (user revokes app access from their Google/Facebook account settings) leaving a stale connected-account row. → Mitigation: surface token errors from any API call as a "reconnect required" state on the account, don't silently fail target creation.
- [Risk] YouTube's 3-call broadcast creation can partially fail (e.g. `liveBroadcasts.insert` succeeds, `liveStreams.insert` fails) leaving an orphaned broadcast on YouTube's side. → Mitigation: wrap in a compensating rollback (delete the broadcast via API) on any step failure; log clearly for manual cleanup as a fallback.
- [Risk] Storing a second class of encrypted secret (OAuth tokens) increases blast radius if `APP_KEY` is ever compromised. → Mitigation: same encryption-at-rest posture already accepted for `stream_key`; no new risk class introduced, just more encrypted rows.
- [Risk] Platform API quota limits (YouTube Data API has a daily quota cost per call) could throttle heavy users. → Mitigation: out of scope to solve fully now; note it in tasks as a monitoring follow-up, not a launch blocker for a first version.
- [Risk] TikTok users will ask "why not TikTok" immediately after seeing YouTube/Facebook. → Mitigation: UI should clearly label TikTok as "Próximamente" / explain the partner-approval dependency rather than hiding it silently.

## Migration Plan

1. Add `restream_platform_accounts` table migration.
2. Add nullable columns migration on `restream_targets` (`platform_account_id`, `title`, `description`, `thumbnail_path`, `scheduled_start_at`, `platform_broadcast_id`) — fully backward compatible, existing manual targets keep working with these columns `NULL`.
3. Register OAuth app credentials for YouTube (Google Cloud Console) and Facebook (Meta for Developers) in `config/services.php` + `.env` — required before this can be tested end-to-end; flag as an external/manual setup dependency in tasks.
4. Ship OAuth connect/callback, broadcast-creation service, and UI behind normal deploy (no feature flag needed — it's purely additive; targets without a connected account behave exactly as before).
5. Rollback: revert the deploy; no destructive data migration since new columns are nullable and unused by existing rows.

## Open Questions

- Should broadcast creation be synchronous (current decision) or queued with a polling/websocket status update in the UI? Revisit if YouTube's 3-call latency proves noticeable in practice.
- Where should uploaded thumbnails be stored (existing media storage/quota system vs. a dedicated small bucket) — needs a look at how `storage-quota` capability already handles per-channel media before deciding; not blocking for the proposal/design level.
- Should a connected account be shareable across a user's multiple channels, or reconnected per channel? Current design assumes per-user (matches `restream_targets.user_id` scoping), not per-channel.
