## 1. External setup (manual, blocking)

- [x] 1.1 Register an OAuth app in Google Cloud Console, enable YouTube Data API v3, obtain client id/secret, configure redirect URI. (Google credentials configured in production — verified by user 2026-09-02.)
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

## 9. Target lifecycle: soft-delete + inactivate

- [x] 9.1 Migration: add `deleted_at` to `restream_targets` (Laravel SoftDeletes).
- [x] 9.2 Add `SoftDeletes` trait to `App\Models\RestreamTarget` — the existing `delete()` action now soft-deletes (preserves the row, frees the slot).
- [x] 9.3 Add `deactivate(Request|Channel, RestreamTarget)` action to `Client/RestreamTargetController` and `Admin/RestreamTargetController` — stops an active stream, sets `enabled = false`, `status = idle`, logs `deactivate.restream_target`; the slot becomes available immediately because `restreamUsedOutputsFor` counts `enabled = true` only.
- [x] 9.4 Add `restore(Channel, RestreamTarget)` action to both controllers — undoes soft-delete via `$target->restore()`, logs `restore.restream_target`.
- [x] 9.5 Register `POST /{prefix}/channels/{channel}/restream-targets/{target}/deactivate` and `.../restore` routes in both `client.restream.channels.*` and `admin.restream.*` groups.
- [x] 9.6 Update `resources/views/client/restream/index.blade.php` and `resources/views/admin/restream/index.blade.php`: replace the "Eliminar" button label with "Archivar" (clearer intent), add an "Inactivar" button (visible only when `t.enabled = true`) that calls the new endpoint, add the corresponding `deactivate()` / `restore()` Alpine handlers, and register both URLs in `resources/views/components/restream-urls.blade.php`.
- [x] 9.7 Update the destroy action's user-facing message to clarify it's a soft-delete ("archivado, se puede restaurar") rather than permanent removal.
- [x] 9.8 Functional smoke: tinker round-trip on an existing target — deactivate sets `enabled=false/status=idle`; delete marks `deleted_at`; `find()` returns null but `withTrashed()->find()` still returns the row; `restore()` brings it back. Verified.
- [ ] 9.9 Manual smoke in browser: open `/client/restream` → click "Inactivar" on `rtmp local` → confirm the slot badge updates to `0/1` → create a YouTube destination → confirm `1/1` again.
- [x] 9.10 Add `forceDestroy(Channel, RestreamTarget)` action to both controllers — permanently removes the row via `$target->forceDelete()` (bypasses SoftDeletes), stops any active stream, logs `force_delete.restream_target`. Registered as `DELETE /{prefix}/channels/{channel}/restream-targets/{target}/force`. UI: an "Eliminar" button (darker red) sitting next to "Archivar" with a double-confirm JavaScript prompt. Functional smoke via tinker: created a throwaway target, `forceDelete()` removed it from both the default scope and `withTrashed()` (true hard delete).
- [x] 9.11 Bugfix discovered during browser smoke: `@js($currentChannel ? [...] : null)` produced `JSON.parse('{\u0022id\u0022:...}')` with unicode-escaped quotes that broke Alpine v3's x-data initialization in some browsers — every method (including `accountFor`) evaluated as undefined. Replaced with `@json(...)` which emits a direct JSON object literal that Alpine handles natively. Applied to both `client/restream/index.blade.php` and `admin/restream/index.blade.php`.

## 8. Admin full CRUD on client account connections (dual-implementation, model B)

- [x] 8.1 Create `app/Http/Controllers/Admin/RestreamPlatformAccountController.php` with an `index()` action that returns a JSON listing of every connected account across all users.
- [x] 8.2 Add route `GET /admin/restream/accounts` (named `admin.restream.accounts.index`).
- [x] 8.3 Render a "Cuentas conectadas" diagnostic section in `resources/views/admin/restream/index.blade.php`.
- [x] 8.4 Add `redirect_admin` to `config/services.php` for both `youtube` and `facebook`, with env-driven overrides (`YOUTUBE_REDIRECT_ADMIN_URI`, `FACEBOOK_REDIRECT_ADMIN_URI`) and `.env.example` placeholders.
- [x] 8.5 Add `target_user_id` session-passing to `Client/RestreamPlatformAccountController::connect()` so the client and admin flows share the same callback handling pattern; default to `$request->user()->id` when no override is set (backward compatible with the existing self-connect flow).
- [x] 8.6 Update `Client/RestreamPlatformAccountController::callback()` to read `target_user_id` from session (default to `$request->user()->id`) and store the token under that user — keeps the existing self-connect UX identical but enables shared callback logic.
- [x] 8.7 Add `connect(Request, User $user, string $platform)` to `Admin/RestreamPlatformAccountController` — stores `restream_oauth_for_user = $user->id` and `restream_oauth_actor = 'admin'` in session, redirects to the platform consent screen using the admin redirect URI; if `$user->id === $request->user()->id`, redirects to the existing client connect flow (de-duplication).
- [x] 8.8 Add `callback(Request, string $platform)` (no `{user}` in URL — Google/Meta don't allow path variables in redirect URIs) — reads `restream_oauth_for_user` from session, persists the token under that user's id (never admin's), records `audit_logs` with `actor=admin`, clears session keys, redirects to `admin.restream.index`.
- [x] 8.9 Add `destroy(Request, User $user, string $platform)` — same semantics as the client disconnect (clear `platform_account_id` on linked targets, audit-log with `actor=admin`); returns JSON for AJAX requests and redirect for non-JSON.
- [x] 8.10 Register the three admin routes (`/admin/users/{user}/restream-accounts/{platform}/connect`, `/admin/restream-accounts/{platform}/callback`, `/admin/users/{user}/restream-accounts/{platform}` DELETE) inside the `admin.restream.*` route group with `auth` + `role:admin`.
- [x] 8.11 Convert the admin restream diagnostic table into an interactive one — added `Reconectar`/`Refrescar`/`Desconectar` buttons per connected account using `connectAccountAs(ownerId, platform)` and `disconnectAccountAs(ownerId, platform, name)` Alpine methods.
- [x] 8.12 Add a "Conectar una cuenta para un cliente" panel in the admin view — Alpine-rendered `<select>` of every `role:client` user + a platform select, which submits and redirects to the admin connect endpoint for the chosen user.
- [x] 8.13 Extend `resources/views/components/restream-urls.blade.php` with the new admin endpoints (`adminConnect(userId, platform)`, `adminDisconnect(userId, platform)`).
- [x] 8.14 Smoke test: functional render of the admin view with sample data showing both diagnostic + interactive tables; `view:cache` compiles; `route:list` shows the new endpoints under `auth` + `role:admin`; unsupported platform (`tiktok`) correctly returns 404 on `destroy`.
- [x] 8.15 Re-run `openspec validate --strict restream-oauth-account-connect` after the admin additions and resolve any new issues.
