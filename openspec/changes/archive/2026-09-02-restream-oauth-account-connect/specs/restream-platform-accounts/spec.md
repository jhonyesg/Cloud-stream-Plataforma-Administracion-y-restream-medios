## ADDED Requirements

### Requirement: Platform account data model

The system SHALL persist connected platform accounts in a `restream_platform_accounts` table with: `id` (uuid), `user_id` (FK to `users.id`), `platform` (enum: `youtube`, `facebook`), `platform_account_id` (string, the external account/page id), `display_name` (string, for showing in the UI), `access_token` (encrypted at rest), `refresh_token` (encrypted at rest, nullable), `token_expires_at` (timestamp, nullable), `scopes` (text, space-separated granted scopes), timestamps. A unique compound constraint SHALL exist on `(user_id, platform)` — one connected account per platform per user.

#### Scenario: One account per platform per user
- **WHEN** a user who already has a connected `youtube` account attempts to connect another `youtube` account
- **THEN** the system SHALL replace the existing connection (re-authenticate updates the same row) rather than creating a second row

#### Scenario: Tokens are encrypted at rest
- **WHEN** an account is connected and tokens are stored
- **THEN** a raw `SELECT access_token, refresh_token FROM restream_platform_accounts` SHALL NOT return plaintext values

### Requirement: OAuth2 connect flow

The system SHALL expose `GET /client/restream/accounts/{platform}/connect`, which redirects the user to the platform's OAuth consent screen requesting only the minimum scopes needed for live-broadcast creation (YouTube: `youtube`, `youtube.force-ssl`; Facebook: `pages_read_engagement`, `publish_video` or the equivalent Live Video permission). On successful consent, `GET /client/restream/accounts/{platform}/callback` SHALL exchange the authorization code for tokens and persist/update the `restream_platform_accounts` row for that user.

#### Scenario: Successful connection
- **WHEN** a user completes the OAuth consent screen for `youtube`
- **THEN** the callback exchanges the code for an access/refresh token pair and creates (or updates) the user's `restream_platform_accounts` row with `platform = 'youtube'`

#### Scenario: User denies consent
- **WHEN** a user cancels or denies the OAuth consent screen
- **THEN** the callback SHALL redirect back to the restream UI with an error message and SHALL NOT create or modify any `restream_platform_accounts` row

#### Scenario: Unsupported platform is rejected
- **WHEN** a client requests `GET /client/restream/accounts/tiktok/connect`
- **THEN** the server responds 404 or 422 (TikTok is not a supported OAuth platform in this change)

### Requirement: Token refresh

The system SHALL automatically refresh an expired or near-expired `access_token` using the stored `refresh_token` before making any platform API call on behalf of the user. If the refresh fails (token revoked externally), the account row's usability SHALL be treated as invalid.

#### Scenario: Automatic refresh before an expired call
- **WHEN** a platform API call is about to be made and `token_expires_at` is in the past
- **THEN** the system refreshes the token first and stores the new `access_token`/`token_expires_at` before proceeding

#### Scenario: Revoked token surfaces a reconnect-required state
- **WHEN** a token refresh attempt fails because the user revoked access externally
- **THEN** the system SHALL surface a "reconnect required" status for that account rather than silently failing subsequent broadcast operations

### Requirement: Disconnect account

The system SHALL expose `DELETE /client/restream/accounts/{platform}` allowing a user to disconnect a connected account, which deletes the `restream_platform_accounts` row. Existing `restream_targets` that reference the disconnected account via `platform_account_id` SHALL have that reference cleared (set to `NULL`) and SHALL revert to requiring manual `destination_url`/`stream_key` entry; already-running targets SHALL NOT be stopped automatically by a disconnect.

#### Scenario: Disconnecting clears linked targets' account reference
- **WHEN** a user disconnects their `facebook` account that is linked to 2 existing targets
- **THEN** both targets' `platform_account_id` becomes `NULL`, and their existing `destination_url`/`stream_key` (already obtained from the prior broadcast creation) remain unchanged and continue to work if still running

#### Scenario: Disconnect does not stop active streams
- **WHEN** a user disconnects an account linked to a currently `active` target
- **THEN** the target keeps streaming; only future broadcast-management actions for that target require reconnecting the account

### Requirement: Admin full CRUD on any client's account connections

The system SHALL expose the same three OAuth actions under `/admin/users/{user}/restream-accounts/{platform}/...` that the client panel exposes, with admin able to **connect on behalf of**, **view the callback for**, and **disconnect** any user's account. The token row SHALL always be persisted under `{user}.id`, never under the admin's. The admin panel SHALL also expose `GET /admin/restream/accounts` (or equivalent index) listing every connected account across all users for at-a-glance diagnostics.

The OAuth handshake uses the same Google/Facebook app credentials as the client flow; the only configuration difference is an additional **admin redirect URI** registered with the platform (e.g. `https://.../admin/restream-accounts/youtube/callback` alongside the existing `https://.../client/restream/accounts/youtube/callback`). Both URIs share the same client id/secret.

#### Scenario: Admin connects a YouTube account on behalf of client X
- **WHEN** an admin opens `/admin/users/{X}/restream-accounts/youtube/connect` (where `X` is any existing client)
- **THEN** the server stores `target_user_id = X.id` in the session and redirects to Google's consent screen using the admin redirect URI; on successful consent, the callback persists the token in `restream_platform_accounts` with `user_id = X.id` (not the admin's id) and an `audit_logs` entry with `actor = admin`, `target = X.youtube`

#### Scenario: Admin force-disconnects client X's account
- **WHEN** an admin issues `DELETE /admin/users/{X}/restream-accounts/youtube`
- **THEN** the server deletes the `restream_platform_accounts` row for `(X, youtube)`, clears `platform_account_id = NULL` on every `restream_targets` row of user `X` that referenced it (preserving `destination_url`/`stream_key` so already-running targets are not interrupted), and writes an `audit_logs` entry with `actor = admin`, `target = X.youtube`

#### Scenario: Admin-initiated token does not become admin's
- **WHEN** an admin completes the OAuth handshake on behalf of client X
- **THEN** a raw `SELECT user_id FROM restream_platform_accounts WHERE platform_account_id = ?` returns `X.id`, never the admin's id

#### Scenario: Admin sees every connected account
- **WHEN** an admin opens `/admin/restream/accounts` (or its JSON equivalent)
- **THEN** the response lists every row in `restream_platform_accounts` with its owning user, platform, display name, connection timestamp, token expiry, and reconnect-required flag

#### Scenario: Admin force-disconnect does not stop active streams
- **WHEN** an admin disconnects an account currently referenced by a `status = 'live'` target
- **THEN** the target keeps streaming; only future broadcast-management actions for that target require reconnecting the account (the same invariant as the client disconnect)
