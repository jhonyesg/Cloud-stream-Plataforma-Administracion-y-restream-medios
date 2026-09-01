## 1. Helper / mailable scaffolding

- [x] 1.1 `App\Support\Audit` helper replaced by existing `App\Models\AuditLog::record(string $action, string $entityType, ?string $entityId, ?array $before, ?array $after, ?string $channelId)` which already fills `user_id` (actor = `auth()->user()`), `ip`, `user_agent`, `at`. The call sites in this change use `AuditLog::record('update.user.password', 'user', $user->id, null, ['changed_by' => ..., 'ip' => request()->ip()])`.
- [x] 1.2 Created `app/Mail/PasswordChangedNotification.php` (mailable, `ShouldQueue`, `envelope()`+`content()` style, `emails.password-changed` markdown view).
- [x] 1.3 Created `resources/views/emails/password-changed.blade.php` (markdown). Subject via `envelope()` based on `changedBy`. Body contains: user display name, date/time, IP, link to `/forgot-password`. For `changed_by='admin'` includes the admin's username.

## 2. Reject password fields on /profile

- [x] 2.1 Edited `app/Http/Requests/ProfileUpdateRequest.php` — added `prohibited` rules for `password`, `password_confirmation`, `current_password` plus custom messages pointing to the "Cambiar contraseña" topbar entry.
- [x] 2.2 Verified `app/Http/Controllers/ProfileController.php` uses `$request->validated()` exclusively; no bypass.

## 3. Self-service password change (PUT /password)

- [x] 3.1 Edited `app/Http/Controllers/Auth/PasswordController.php`: wrapped in `DB::transaction`, writes `AuditLog::record('update.user.password', 'user', $user->id, null, ['changed_by' => 'self', 'ip' => request()->ip()])`, queues `PasswordChangedNotification`, deletes other `sessions` rows, rotates `remember_token`, regenerates session id (outside the transaction).
- [x] 3.2 Added imports: `App\Mail\PasswordChangedNotification`, `App\Models\AuditLog`, `Illuminate\Support\Facades\DB`, `Illuminate\Support\Facades\Mail`, `Illuminate\Support\Str`.

## 4. Admin password reset (PUT /admin/users/{user})

- [x] 4.1 Edited `app/Http/Controllers/Admin/DashboardController.php` `userUpdate()`: when password is non-empty, full flow (audit + mailable + delete all sessions + rotate remember_token) inside `DB::transaction`. When empty, original `unset($data['password'])` short-circuit preserved.
- [x] 4.2 Email + audit + session kill all run inside the same transaction as the user update, so partial failures roll back.

## 5. UI: topbar modals

- [x] 5.1 Edited `resources/views/components/admin-topbar.blade.php`: distinct icons (user outline for "Mi perfil", amber lock for "Cambiar contraseña") + subtitle texts under each dropdown item.
- [x] 5.2 Edited `resources/views/components/profile-modal.blade.php`: added the "Este formulario no cambia tu contraseña" hint at the bottom of the modal body.
- [x] 5.3 Edited `resources/views/components/password-modal.blade.php`: added `state` data property (`form|submitting|success|error`); on 200 shows a green checkmark + "Tu contraseña fue actualizada. Las demás sesiones fueron cerradas." for 3000ms before closing; on 422 sets `state='error'`.
- [x] 6.1 Edited `resources/views/profile/partials/update-profile-information-form.blade.php`: header now reads "Información de perfil" with a user icon and the helper text now clarifies "Este formulario no cambia tu contraseña."
- [x] 6.2 Edited `resources/views/profile/partials/update-password-form.blade.php`: header now reads "Cambiar contraseña" with a lock icon.

## 7. Tests

- [x] 7.1 Created `tests/Feature/Auth/PasswordChangeTest.php` with 8 tests covering audit row, mailable dispatch, other-session invalidation, remember_token rotation, and 4 cases of `/profile` rejecting password fields (empty, with value, confirmation missing, no password fields).
- [x] 7.2 Created `tests/Feature/Admin/UserUpdatePasswordTest.php` with 5 tests covering audit row, all-session kill, mailable dispatch, no-audit-when-password-empty, and remember_token rotation.
- [x] 7.3 `php artisan test --filter=PasswordChangeTest` → 8 passed. `php artisan test --filter=UserUpdatePasswordTest` → 5 passed. Both green.

## 8. Verification

- [x] 8.1 `php artisan check:destructive-migrations` → OK (no destructive migration in this change).
- [x] 8.2 `php artisan view:clear && config:clear && route:clear && view:cache` → all clean.
- [ ] 8.3 Manual browser smoke test (client): open "Mi perfil" → confirm "no incluye contraseña" hint is visible. Open "Cambiar contraseña" → change password → confirm green confirmation state appears, modal closes, DB has new hash. Open a second tab logged in as same user → refresh → confirm logged out (session killed).
- [ ] 8.4 Manual browser smoke test (admin): edit a user, set a new password. Confirm affected user receives the email (or see in `storage/logs/laravel.log` if `MAIL_MAILER=log`). Confirm `audit_logs` has the row.
- [ ] 8.5 `php artisan tinker --execute="echo count(\DB::table('audit_logs')->where('action','update.user.password')->get());"` → confirms audit row count after manual tests.
