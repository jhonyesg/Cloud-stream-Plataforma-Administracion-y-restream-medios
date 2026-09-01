## 1. External setup (manual, blocking)

- [ ] 1.1 Register an OAuth app in Google Cloud Console, enable YouTube Data API v3, obtain client id/secret, configure redirect URI.
- [ ] 1.2 Register an app in Meta for Developers, request Live Video permissions, obtain client id/secret, configure redirect URI.
- [x] 1.3 Add `youtube` and `facebook` OAuth credentials to `config/services.php` and `.env`/`.env.example`.

## 2. Data model

- [x] 2.1 Migration: create `restream_platform_accounts` (`id`, `user_id`, `platform`, `platform_account_id`, `display_name`, `access_token` encrypted, `refresh_token` encrypted nullable, `token_expires_at`, `scopes`, timestamps; unique `(user_id, platform)`).
- [x] 2.2 Migration: add nullable columns to `restream_targets` (`platform_account_id` FK, `title`, `description`, `thumbnail_path`, `scheduled_start_at`, `platform_broadcast_id`).
- [x] 2.3 Create `app/Models/RestreamPlatformAccount.php` with encrypted casts for tokens, `user()` relation, and a `needsReconnect()` helper.
- [x] 2.4 Update `app/Models/RestreamTarget.php` with the new columns, `platformAccount()` relation, and validation that `destination_url`/`stream_key` are only required when `platform_account_id` is null.

## 3. OAuth connect flow

- [x] 3.1 Evaluate and add Laravel Socialite (or equivalent) with Google + Facebook drivers.
- [x] 3.2 Implement `GET /client/restream/accounts/{platform}/connect` and `GET /client/restream/accounts/{platform}/callback`, scoped to `youtube`/`facebook` only (404 for other values).
- [x] 3.3 Implement token refresh logic (auto-refresh before an expiring/expired call) and a "reconnect required" state surfaced when refresh fails.
- [x] 3.4 Implement `DELETE /client/restream/accounts/{platform}` disconnect, clearing `platform_account_id` on linked targets without stopping active streams.

## 4. Platform broadcast services

- [x] 4.1 Create `app/Services/Restream/Platform/YoutubeBroadcastService.php`: `create()` (liveBroadcasts.insert → liveStreams.insert → liveBroadcasts.bind → optional thumbnails.set), `update()`, `delete()` (for rollback on partial failure).
- [x] 4.2 Create `app/Services/Restream/Platform/FacebookBroadcastService.php`: `create()` (`POST /{page-id}/live_videos`), `update()`, `delete()`.
- [x] 4.3 Wire broadcast creation/update into the existing target create/update controller actions (`Client/RestreamTargetController.php`): when `platform_account_id` is set, call the matching service and populate `destination_url`/`stream_key`/`platform_broadcast_id` from its response instead of accepting them from the request.
- [x] 4.4 Implement rollback-on-partial-failure for the YouTube 3-call sequence.

## 5. Scheduling

- [x] 5.1 Add `restream:launch-scheduled` Artisan command: find targets with `scheduled_start_at <= now() AND enabled = false`, set `enabled = true`.
- [x] 5.2 Register the command on the scheduler (e.g. every minute) in `routes/console.php` or `bootstrap/app.php`'s scheduling config.

## 6. UI

- [x] 6.1 Add "Conectar cuenta" UI in `resources/views/client/restream/index.blade.php` (or a new accounts section) showing connection status per platform (connected/disconnected/reconnect-required) with connect/disconnect actions.
- [x] 6.2 Extend the target creation/edit modal to offer "usar cuenta conectada" vs "ingresar manualmente" per platform, and when using a connected account, show title/description/thumbnail/scheduled-time fields instead of destination_url/stream_key fields (mirroring the reference "Update stream details" modal).
- [x] 6.3 Label TikTok as unavailable for account connection ("Próximamente") in the UI, keeping manual entry available.
- [x] 6.4 Handle and surface broadcast-creation errors (e.g. rollback failure, quota exceeded) inline in the modal.

## 7. Validation

- [x] 7.1 Run `openspec validate --strict restream-oauth-account-connect` and fix any reported issues.
- [ ] 7.2 Manual end-to-end test: connect a real YouTube test account, create a scheduled target, confirm `restream:launch-scheduled` auto-enables it at the scheduled time and the existing orchestrator/daemon starts pushing.
- [ ] 7.3 Manual end-to-end test: connect a real Facebook test page, create an immediate (non-scheduled) target, confirm it goes live.
- [ ] 7.4 Confirm disconnecting an account mid-stream does not interrupt an already-running target.
