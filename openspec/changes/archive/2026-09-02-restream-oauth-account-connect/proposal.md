## Why

The existing restream module (`restream_targets`, `RestreamOrchestrator`, the Python push daemon) requires the user to manually paste a `destination_url` + `stream_key` per platform, which they must first go generate inside YouTube Studio / Facebook Live Producer themselves. Restream.io-style tools instead let the user connect their platform account once (OAuth) and then create/schedule a live event — title, description, thumbnail — directly from the restreaming tool, which obtains the ingest URL and stream key automatically. Users have asked for that same experience so they don't have to leave the platform or hand-copy RTMP credentials.

## What Changes

- Add OAuth2 account connection for **YouTube** and **Facebook** (TikTok is explicitly out of scope: it has no self-serve public Live API for third parties, only a partner-approval program).
- Add a "broadcast" step in the restream target flow: once an account is connected, the user fills in title/description/thumbnail (and optionally a scheduled start time) and the system calls the platform's Live API to create the event, storing the platform's own event/broadcast id.
- The platform API response's ingest URL + stream key SHALL populate `restream_targets.destination_url` / `stream_key` automatically for OAuth-connected targets, instead of requiring manual entry. Manual entry (`platform = custom`, or `youtube`/`facebook` without a connected account) SHALL continue to work unchanged for users who don't connect an account.
- Add scheduled start: when a target has a `scheduled_start_at` in the future, the system SHALL automatically enable/start the target at that time (existing `RestreamOrchestrator` start mechanism, triggered by a new scheduler job) rather than requiring the user to click "start" manually.
- Add account lifecycle management: connect, view connection status/expiry, refresh token, disconnect.
- **BREAKING**: none — this is additive; existing manual-entry targets and the push engine are unaffected.

## Capabilities

### New Capabilities
- `restream-platform-accounts`: OAuth2 connection lifecycle for YouTube/Facebook accounts per user (connect, token storage/refresh, disconnect, expiry handling).
- `restream-platform-broadcast`: creating/updating a scheduled live event on a connected platform (title, description, thumbnail, scheduled time) and syncing the resulting ingest URL/stream key back into the linked `restream_target`.

### Modified Capabilities
- `restream-targets`: the target data model gains optional `platform_account_id`, `broadcast metadata` (title, description, thumbnail, scheduled_start_at), and `platform_broadcast_id`; `destination_url`/`stream_key` may now be system-populated (from the platform API) instead of always user-submitted for OAuth-connected targets.

## Impact

- New DB tables: `restream_platform_accounts` (encrypted OAuth tokens, similar pattern to the existing `stream_key` encryption on `RestreamTarget`), and new columns on `restream_targets`.
- New Laravel services under `app/Services/Restream/` (e.g. platform API clients for YouTube Data/Live Streaming API v3 and Facebook Graph API `live_videos`), OAuth redirect/callback controllers and routes.
- `app/Models/RestreamTarget.php` gains new relations/fields; `RestreamOrchestrator` is unaffected (still just consumes `destination_url`/`stream_key`, regardless of how they were populated).
- New scheduled command/queue job to auto-start targets at `scheduled_start_at`.
- UI: `resources/views/client/restream/index.blade.php` and the target modal(s) gain an "conectar cuenta" flow and broadcast metadata fields (title/description/thumbnail/schedule), matching the reference screenshot's "Update stream details" modal.
- New external dependencies: Google API client (or direct HTTP calls) for YouTube, Facebook Graph API HTTP calls; new OAuth credentials in `config/services.php` (client id/secret per platform, redirect URIs).

## Target lifecycle: soft-delete + inactivate + force-delete

Restream targets now support three non-conflicting lifecycle actions:

- **Inactivate** sets `enabled = false` and `status = idle`, stops any active stream, and **frees the slot immediately** so the user can create a new target on the same channel without admin intervention. The target's configuration (RTMP URL, stream key, broadcast metadata, OAuth account link) is preserved so it can be re-activated by editing it back to `enabled = true`.
- **Archive** (formerly "delete") soft-deletes the row by stamping `deleted_at`. The slot is freed, the row is hidden from default queries, but the row remains in the database and can be restored by an admin via `POST .../restore`. Use this when the user might want to come back to the configuration later.
- **Eliminar (definitivo)** permanently removes the row via `forceDelete()` — bypassing soft-delete entirely so the row does not exist in `withTrashed()` either. Use this for one-off cleanup when the user has accumulated many test/inactive targets and wants to slim the list. Requires a double-confirm JavaScript prompt before firing the request.

These three actions exist because the previous hard-delete was the only way to free a slot, which made users afraid of losing OAuth tokens and broadcast configuration when testing. With the trio, you can experiment freely without losing work.

## Admin surface (full CRUD on any client's account connections)

The OAuth account connection flow is exposed in **both** panels with the same capability surface — admin and client can each connect, refresh, and disconnect YouTube/Facebook accounts. The only difference is **scope**:

- A **client** can manage their own accounts only (one YouTube + one Facebook per `user_id`).
- An **admin** can manage any client's accounts (any `user_id` in the system). The token row is always persisted under the target `user_id`, never under the admin's — so broadcasts still stream from the client's social account, not from the admin's.

This pattern assumes the admin has been pre-authorized by the client (typical in an agency/support model where the admin completes the OAuth dance on the client's behalf using credentials the client has shared, or where the admin generates the OAuth URL and the client opens it on their own device). It does **not** require a separate "admin OAuth app" with broader scopes — both surfaces use the same Google/Facebook OAuth app credentials; the admin-side callback URL is simply an additional authorized redirect URI registered with the platform.

Why full CRUD and not read-only:
- Operators regularly need to **disconnect** a stale or compromised connection on behalf of a client (revoked refresh token, offboarded client, audit finding).
- Onboarding flows sometimes have the admin drive the OAuth consent on the client's device — being able to initiate + complete the handshake from the admin panel removes a step.
- TikTok/custom remain out of scope for account connection in **both** panels; manual RTMP entry stays unchanged.
