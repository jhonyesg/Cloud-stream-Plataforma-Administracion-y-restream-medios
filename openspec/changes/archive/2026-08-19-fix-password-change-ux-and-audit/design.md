## Context

The current self-service password-change flow has two failure modes that have been observed in production:

1. **Silent drop.** `ProfileController@update` validates only `name` and `email` and uses `$user->fill($request->validated())`. If the user submits password fields to `PATCH /profile` (by clicking the wrong form button, or by reusing a stale tab), the password fields are silently discarded yet the response is `profile-updated` with a green "Saved." flash. The user is convinced the change applied.
2. **No observable signal.** `Auth\PasswordController@update` persists the new hash correctly via `Hash::make()` + the `hashed` cast on the `User` model, but it does not write `audit_logs`, does not send an email, and does not invalidate other sessions. The active session remains usable with the old "remember me" cookie. There is no surface that tells the user "this worked" beyond the modal closing — and the modal closes on both success and on a 422 with a generic error.

The same omission applies to admin-driven password resets (`Admin\DashboardController@userUpdate`): no audit row, no email, no session kill. The audit-log capability already declares that user-updates should be audited, but the password path was never actually wired.

The relevant code paths are:
- `app/Http/Controllers/Auth/PasswordController.php` (self-service)
- `app/Http/Controllers/Admin/DashboardController.php` (`userUpdate`, admin)
- `app/Http/Controllers/ProfileController.php` (profile info, the wrong-target endpoint)
- `app/Http/Requests/ProfileUpdateRequest.php` (the validator that silently drops password fields)
- `resources/views/components/password-modal.blade.php`, `profile-modal.blade.php`, `admin-topbar.blade.php`
- `resources/views/profile/partials/update-password-form.blade.php`, `update-profile-information-form.blade.php`

The `User` model already has the `password => 'hashed'` cast that handles double-hashing safely. The BcryptHasher's `isHashed()` check means `Hash::make()` in the controller is redundant but harmless. No change is needed there.

The `audit_logs` table already has the columns we need (`action`, `entity_type`, `entity_id`, `actor_id`, `before`, `after`, `ip`, `at`). No migration required.

The mail surface already uses Laravel's standard `Mail::send` / mailable infrastructure. `MAIL_MAILER` is configured in `.env` for production. No new env vars required.

## Goals / Non-Goals

**Goals**
- Make password-change success unambiguous: write `audit_logs`, send a mailable, and invalidate other sessions.
- Make password-change failure unambiguous: if the user mistakenly submits password fields to `/profile`, the response is HTTP 422 with a clear error, NEVER a green "Saved." flash.
- Make the two topbar modals visually distinct enough that a user cannot mistake them.
- Standardize the `audit_logs` writer call so future code paths (e.g. password reset via forgot-password flow) can reuse it.

**Non-Goals**
- Not adding MFA / 2FA.
- Not changing the password hash algorithm (still bcrypt via Laravel default).
- Not adding `password_change_required_at` / forced-rotation logic.
- Not auditing the forgot-password / reset-password flow (out of scope for this change; can be a follow-up).
- Not changing the login flow or the role/status enums.

## Decisions

### D1. Audit row is written in the controller, not via Eloquent observer
- An `UserObserver` would fire on every `save()` and would have to filter out the password-change path. Doing it explicitly in the controller is clearer and matches the existing pattern used by `ScheduleTemplateController`, `RestreamQuotaController`, etc. (verified during exploration).
- Trade-off: future code paths that change the password might forget to call `Audit::record`. Mitigation: the helper has a single, grep-able name; add a docblock note to `User::password` cast pointing to it.

### D2. Use `App\Support\Audit::record()` helper, not inline `AuditLog::create()`
- Other parts of the codebase already call `AuditLog` directly. We will add a thin helper to centralize the `actor_id`/`ip`/`at` plumbing and the `entity_id` cast. Existing call sites can be migrated opportunistically, not in this change.
- Alternative considered: keep using `AuditLog::create()` directly. Rejected because the gap that allowed this omission is precisely that the call is verbose and easy to forget.

### D3. Password change never logs the password itself, before or after
- `before` and `after` JSON MUST NEVER contain a `password` key. Even the hash is never stored in `audit_logs` — only `changed_by` ('self'|'admin') and `ip`.
- Rationale: audit logs are visible to admin users and persisted long-term. Defense in depth.

### D4. Session invalidation policy
- **Self-service:** regenerate the current session id (`$request->session()->regenerate()`) and rotate the remember-me token (`Auth::logoutOtherDevices(...)` is NOT applicable here since the user just proved they know the current password; instead we explicitly delete other persistent-login tokens). Other sessions of that user are deleted from the `sessions` table by `user_id`. The current device keeps the regenerated session.
- **Admin-driven:** delete ALL sessions of the affected user from the `sessions` table. The affected user will be forced to log in again on their next request.
- Alternative considered: `Auth::logoutOtherDevices($newPassword)` for self-service. Rejected because it requires re-hashing the password as a parameter and the `hashed` cast would then double-hash it inside `logoutOtherDevices` — must be the plaintext form, which is fine because it is in memory only, but it complicates the call site. Direct DB delete is simpler and equivalent.

### D5. Reject password fields on `/profile` with `prohibited` rules
- `ProfileUpdateRequest::rules()` adds `prohibited` rules for `password`, `current_password`, `password_confirmation`.
- If a user submits these fields to `/profile`, the validator returns 422 with a message like "The password field is prohibited." — the form does NOT navigate away and does NOT show `profile-updated`.
- The `update-profile-information-form.blade.php` does not include these fields, so legitimate users see no change.
- Alternative considered: adding a guard clause in `ProfileController::update` to ignore password fields silently. Rejected — the whole point is to make the wrong target loud.

### D6. Mailable uses Laravel's `Illuminate\Mail\Mailable` + Markdown view
- New `app/Mail/PasswordChangedNotification.php` (mailable class) + `resources/views/emails/password-changed.blade.php` (markdown view).
- Subject: "Tu contraseña fue actualizada" (self) / "Tu contraseña fue restablecida por un administrador" (admin).
- Body explains the change, the time, and the IP. Includes a "If you didn't do this, secure your account" link to `/forgot-password`.
- The mailable is queued via `ShouldQueue` (existing pattern in the project for any email that could be triggered during a request) so a slow SMTP server does not block the user.
- Alternative considered: synchronous send. Rejected — same as the rest of the project: SMTP is best-effort, not in the request critical path.

### D7. Topbar modals — visual distinction
- Each topbar item gets a distinct icon (profile silhouette vs lock), a distinct section label, and the profile modal gets a small "no incluye contraseña" hint at the bottom.
- The icon swap is purely cosmetic; the functional guarantee is D5 (rejection on `/profile`) and the success confirmation in the password modal.

### D8. Password modal shows a green confirmation state for 3s before closing
- On 200 OK, the modal sets `state='success'`, renders a green checkmark + "Tu contraseña fue actualizada. Las demás sesiones fueron cerradas." for 3 seconds, then closes and emits `crud-success`.
- The success message is bound to the user-visible signal they were missing.

### D9. No new migrations, no new tables
- `audit_logs` already exists with the right columns.
- `sessions` table already exists (Laravel's `database` session driver).
- `MAIL_*` env vars already configured.

## Risks / Trade-offs

- **R1: Existing integrations that hit `/profile` with password fields will start receiving 422.** → Mitigation: the only path that does this is the Breeze-generated form, which does not include password fields. No API client touches `/profile` (verified during exploration). Documented in the change.
- **R2: Self-service password change now kills other sessions, which may surprise users who are signed in on multiple devices.** → Mitigation: this is the desired security behavior, and the email + in-modal copy explains it. Users who legitimately need multi-device sessions can re-authenticate on each device.
- **R3: If `MAIL_MAILER=log` or SMTP is misconfigured, the mailable silently fails.** → Mitigation: the mailable is `ShouldQueue` and uses `Mail::queue` via the existing `Mailable` pattern; failures appear in `storage/logs/laravel.log`. The password change itself still succeeds (audit + DB write happens before the mail dispatch).
- **R4: The `prohibited` rule on `password` in `ProfileUpdateRequest` might break a custom frontend that previously injected a password field there.** → Mitigation: no such frontend exists in the codebase (verified); users who built something against the wrong endpoint were already in a broken state.
- **R5: A user who is offline when their session is killed (no other active devices) will be silently logged out on next request.** → This is the intended behavior. Audit row + email provide evidence.
- **R6: The admin-initiated mailable body reveals the admin's identity to the affected user.** → This is by design and required for audit transparency. The username of the admin will be in the email body.

## Migration Plan

This change is application-only. No DB migrations, no infrastructure changes, no cache flush required.

Deployment steps:
1. `git pull` (deploys the code).
2. `php artisan view:clear && php artisan config:clear && php artisan route:clear` (standard protocol per `AGENTS.md`).
3. `php artisan queue:restart` if the queue worker is using `php artisan queue:work` (the mailable uses `ShouldQueue`).

Rollback:
- `git revert` the merge commit. No data migration to reverse. Note: any `audit_logs` rows written by the new code will remain (they are good audit data); any mailable that was sent cannot be unsent.

## Open Questions

- **Q1.** Should the admin-reset email also be sent to the **admin** (cc) as a courtesy copy? Current design: no, only to the affected user. Confirm before implementation.
- **Q2.** Should we also kill the affected user's active "remember me" cookies? The plan kills `sessions` rows but persistent cookies still in the browser would re-authenticate. Killing `remember_token` in `users` is the cleanest fix. Currently the design rotates it on self-service but kills it on admin reset. Confirm.
- **Q3.** Should the `audit_logs` row include `entity_id` of the affected user (currently yes) or also the `actor_id` of the admin (currently yes via the helper). Confirm against the existing audit spec — yes, the existing pattern is `actor_id`.
- **Q4.** Do we want to track the password change in the `audit_logs` row with a timestamp of `password_changed_at` on the user model? Currently not. The audit_logs row has `at`. Confirm.
