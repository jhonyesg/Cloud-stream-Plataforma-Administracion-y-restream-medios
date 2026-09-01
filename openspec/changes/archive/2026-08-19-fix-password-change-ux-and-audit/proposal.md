## Why

The self-service password-change flow has a silent-footgun UX and a missing audit/security trail:

1. The topbar exposes two visually similar modals ("Mi perfil" and "Cambiar contraseña") and the profile page exposes two visually-similar forms ("Profile Information" and "Update Password"). A user who types the new password into the wrong surface gets a green `profile-updated` flash from `ProfileController@update` while `validated()` silently discards the password fields — the user is convinced the change applied.
2. Even when the user picks the correct path (`PUT /password`), `Auth\PasswordController@update` writes no `audit_logs` row, sends no email, and does NOT invalidate the active session. The user has no signal the change succeeded, and a stolen session cookie remains valid against the new password.
3. Admin resetting someone else's password (`Admin\DashboardController::userUpdate`) is also not audited.

The fix is to make the correct outcome observable (audit + email + session invalidation) and to make the wrong surface visibly reject early (so a user submitting to `/profile` cannot see a green "Saved." while their password was silently dropped).

## What Changes

- **Add a `password.updated` audit log entry** for every successful password change, both self-service (`Auth\PasswordController@update`) and admin-driven (`Admin\DashboardController::userUpdate`). Entry shape: `action='update.user.password'`, `entity_type='user'`, `entity_id=<affected_user_id>`, `actor_id=<acting_user_id>`, `before=null` (we never store the prior hash), `after={changed_by:'self'|'admin', ip}`.
- **Add a `PasswordChangedNotification`** (mailable) sent to the affected user's email after a successful change. For admin-initiated changes the email body makes that explicit.
- **Invalidate active sessions** of the affected user after a successful password change. For self-service: keep the current session alive (so the user is not kicked out mid-task) but regenerate the session ID and rotate the remember-me token; other sessions of that user are killed. For admin-driven changes: kill ALL sessions of the affected user.
- **Reject password fields on the profile-info endpoint.** `ProfileUpdateRequest` will explicitly reject any `password`/`current_password`/`password_confirmation` field with HTTP 422 (use `prohibited`/`prohibited_if` rules). The `profile-updated` flash will only ever mean "name/email were saved".
- **Make the two topbar modals visually distinct.** "Mi perfil" gets a profile-shape icon and an explicit "no incluye contraseña" hint; "Cambiar contraseña" gets a lock icon and its own section header. The two modals MUST not look interchangeable.
- **Surface a non-silent confirmation** in the password change modal: on 200 the modal shows a green checkmark + "Tu contraseña fue actualizada. Las demás sesiones fueron cerradas." for ~3s before closing.
- **Add `audit_logs` writer helper** `App\Support\Audit::record(string $action, string $entityType, ?string $entityId, ?array $before, array $after, ?int $actorId = null)` to keep call sites uniform. (Already implied by the existing capability spec — this just standardizes the call.)

### Breaking changes
- None for end users. Existing valid `/profile` requests that only carry `name`/`email` continue to work unchanged.
- Sessions other than the current one are killed on self-service password change. This is a behavior change but is the desired security default.

## Capabilities

### New Capabilities
- (none)

### Modified Capabilities
- `auth-and-users`: add requirements for audit-on-password-change, post-change email notification, session invalidation policy, and rejection of password fields on the profile-info endpoint. See `specs/auth-and-users/spec.md` delta.

## Impact

- **Controllers modified**
  - `app/Http/Controllers/Auth/PasswordController.php` — add audit, dispatch mailable, invalidate other sessions, regenerate session id.
  - `app/Http/Controllers/Admin/DashboardController.php` (`userUpdate`) — add audit when password path is taken, dispatch mailable, kill all sessions of the affected user.
  - `app/Http/Controllers/ProfileController.php` — unchanged (request validation rules change moves the responsibility to the FormRequest).
- **Requests modified**
  - `app/Http/Requests/ProfileUpdateRequest.php` — add `prohibited` rules for `password` family.
- **Mailable added**
  - `app/Mail/PasswordChangedNotification.php` — no plaintext password, only notification.
- **Views / components touched**
  - `resources/views/components/admin-topbar.blade.php` — distinct icons + section labels for the two topbar items.
  - `resources/views/components/profile-modal.blade.php` — add explicit "no incluye contraseña" hint.
  - `resources/views/components/password-modal.blade.php` — show green confirmation state for ~3s.
  - `resources/views/profile/partials/update-profile-information-form.blade.php` and `update-password-form.blade.php` — clearer section headers.
- **Email surface**
  - `resources/views/emails/password-changed.blade.php` (new).
- **Mail config**
  - No new env vars. Uses existing `MAIL_*` drivers.
- **Database**
  - No new tables. No migrations. `audit_logs` already exists.
- **Routes**
  - No route changes.
- **Tests**
  - Feature tests for: audit-on-self-service, audit-on-admin-reset, session invalidation (self keeps current, admin kills all), mailable dispatched, `/profile` rejects password fields with 422.
