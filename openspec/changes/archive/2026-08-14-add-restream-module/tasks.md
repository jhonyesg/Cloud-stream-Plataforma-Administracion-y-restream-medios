## 1. Setup & safety

- [x] 1.1 Run `php artisan db:backup` (mandatory per AGENTS.md before any migration)
- [x] 1.2 Confirm `php artisan check:destructive-migrations` passes baseline (no destructive migrations yet)

## 2. Database schema

- [x] 2.1 Create migration `2026_08_14_000001_create_restream_quotas_table.php` with columns: `id` (uuid pk), `user_id` (uuid fk unique), `enabled` (bool default false), `max_outputs` (smallint default 1), `granted_by` (uuid fk nullable to users), `granted_at` (timestamp nullable), `notes` (text nullable), timestamps. Add `CHECK (max_outputs IN (1, 2, 3, 4))` constraint and unique index on `user_id`.
- [x] 2.2 Create migration `2026_08_14_000002_create_restream_targets_table.php` with columns: `id` (uuid pk), `user_id` (uuid fk), `channel_id` (uuid fk), `platform` (enum string), `name` (string 80), `destination_url` (text), `stream_key` (text encrypted), `enabled` (bool default false), `status` (enum default idle), `last_error` (text nullable), `last_started_at` (timestamp nullable), `last_stopped_at` (timestamp nullable), `created_by` (uuid fk), timestamps. Add unique compound index `(user_id, channel_id, platform)`.
- [x] 2.3 Run `php artisan migrate:safe` (backup + migrate + verify) and confirm both tables exist in the live DB
- [x] 2.4 Empty `down()` migrations must drop the new tables (no `WithDataSafetySnapshot` needed — both are `up()`-only)

## 3. Models & helpers

- [x] 3.1 Create `App\Models\RestreamQuota` with `$fillable`, `casts` (enabled→bool, max_outputs→int), `user()` BelongsTo, and `grantedBy()` BelongsTo relations
- [x] 3.2 Create `App\Models\RestreamTarget` with `$fillable`, `$hidden = ['stream_key']`, `casts` (enabled→bool, last_*_at→datetime), `user()`, `channel()`, `createdBy()` relations, and a `scopeEnabled($q)` local scope
- [x] 3.3 Add to `App\Models\User`: `restreamQuota()` HasOne, `restreamTargets()` HasMany, `hasRestreamEnabled()`, `restreamMaxOutputs()`, `restreamUsedOutputs()`, `restreamRemainingSlots()` helpers per spec `restream-habilitation` §"User helpers expose quota"
- [x] 3.4 Add a `RestreamTarget::toArray()` override (or a `RestreamTargetResource`) that strips `stream_key` and emits `stream_key_set: bool` + `stream_key_last4: ?string` instead

## 4. Middleware & service

- [x] 4.1 Create `App\Http\Middleware\EnsureRestreamEnabled` that returns 403 with flash message if `auth()->user()->hasRestreamEnabled()` is false; register it in `app/Http/Kernel.php` under the `web` group with alias `restream.enabled`
- [x] 4.2 Create `App\Services\Restream\RestreamQuotaGuard` with `canEnableAnother(User $user): bool` and `enableTarget(User $user, RestreamTarget $target): void`; the enable path MUST run inside `DB::transaction` with `RestreamQuota::where('user_id',$user->id)->lockForUpdate()->first()` and raise a domain exception on cap exceeded

## 5. Admin panel — habilitation

- [x] 5.1 Create `App\Http\Controllers\Admin\RestreamQuotaController` with `show($userId)`, `store(Request $r, $userId)`, `update(Request $r, $userId)`, `destroy($userId)` — all gated by `EnsureRole:admin`. Validation rules: `enabled` boolean, `max_outputs` in:1,2,3,4 (default 1), `notes` string|nullable|max:500
- [x] 5.2 Add routes under existing `/admin/users/{user}/restream-quota` group in `routes/web.php` (GET, POST, PATCH, DELETE) inside the admin middleware group
- [x] 5.3 Create `resources/views/admin/users/_restream_section.blade.php` partial rendering: grant form (when no row), edit form (when row exists: tier radio 1/2/3/4 with labels "Base (gratis)", "+1 destino (pago)", "+2 destinos (pago)", "+3 destinos (pago)", enabled toggle, notes textarea), and a read-only table of the user's targets with `[Ver]` link to `/admin/restream-targets?user_id={u}`
- [x] 5.4 Mount the partial in `resources/views/admin/users/show.blade.php` (and `index.blade.php` if needed) — confirm by grep `restream_section` against the user view
- [x] 5.5 Verify the partial's form posts to `POST /admin/users/{user}/restream-quota` and the edit form to `PATCH /admin/users/{user}/restream-quota`

## 6. Admin panel — target visibility

- [x] 6.1 Create `App\Http\Controllers\Admin\RestreamTargetController` with `index(Request $r)` (filters by `?user_id=`, `?channel_id=`); NO `edit`, `update`, or `destroy` methods (admin is read-only here per design D8)
- [x] 6.2 Add route `GET /admin/restream-targets` inside the admin middleware group
- [x] 6.3 Create `resources/views/admin/restream_targets/index.blade.php` with columns: user, channel, platform, name, enabled, status, last_started_at, [Ver]
- [x] 6.4 Add a sidebar/nav entry under the admin Users menu (or a top-level "Restream → Targets" entry) that links to `/admin/restream-targets`

## 7. Client panel — target CRUD

- [x] 7.1 Create `App\Http\Controllers\Client\RestreamTargetController` with `index`, `store`, `show`, `update`, `destroy` — every method MUST call `$user->canAccessChannel($channel)` (abort 403 if false) and the `index` MUST scope to `auth()->user()->effectiveChannelIds()`
- [x] 7.2 Add routes under `/client/channels/{channel}/restream-targets` group in `routes/web.php` with middleware `[auth, EnsureRole:client, restream.enabled]` (plus channel-scope middleware if it already exists for this route group)
- [x] 7.3 Validation in `store`/`update`: `platform` in:facebook,tiktok,youtube,custom; `name` required|max:80; `destination_url` required|url; `stream_key` required|string|min:8|max:500; `enabled` boolean. Reject if cap reached by delegating to `RestreamQuotaGuard::canEnableAnother()` inside a transaction
- [x] 7.4 Create `resources/views/client/channels/_restream_panel.blade.php` partial with: a "Slots: X/Y" badge, list of existing targets (toggle enabled, edit, delete), and an `[+ Nuevo destino]` button that opens a modal
- [x] 7.5 Create `<x-restream-target-modal>` component (name=`restream-target`, payload includes channel + remaining slots) used by both the panel and the channel detail
- [x] 7.6 Mount `_restream_panel.blade.php` and `<x-restream-target-modal />` inside `resources/views/client/channels/show.blade.php` (or whichever view shows the channel detail). Confirm by grep `restream_panel` / `restream-target-modal` after mount
- [x] 7.7 Add an `EnsureRestreamEnabled` route alias usage to the controller's middleware stack; verify `php artisan route:list` shows the new routes with the middleware attached

## 8. Audit & encryption

- [x] 8.1 Confirm `stream_key` round-trips through `Crypt::encryptString` / `Crypt::decryptString` (write a tinker check during smoke test)
- [x] 8.2 In every controller that mutates `restream_quotas` or `restream_targets`, write one `audit_logs` entry with `before`/`after` JSON. For `restream_targets`, ensure `stream_key` is replaced by `{stream_key_set: true}` before serialization (no plaintext in `audit_logs`)
- [x] 8.3 Add a smoke-test tinker script that creates a user, grants quota, creates a target, toggles enabled, disables quota, and prints the `audit_logs` rows; commit the script under `scripts/restream-smoke.php` (gitignored, used only locally)

## 9. Dual-implementation smoke tests

- [x] 9.1 Admin smoke: log in as admin → open `/admin/users/{u}` → confirm the Restream section renders with the grant form → grant tier 1 (base) → confirm the section updates to show "Base (gratis)" and "Habilitado"
- [x] 9.2 Admin smoke: with tier 2 and 2 targets → try to downgrade to tier 1 (validation rejects because >1 active target) → upgrade to tier 4 (success)
- [x] 9.3 Admin smoke: open `/admin/restream-targets` → confirm the test user's targets are listed with `stream_key_set: true` (no plaintext)
- [x] 9.4 Client smoke: log in as the test client → open `/client/channels/{c}` → confirm the Restream panel renders a "Slots: 0/1" badge at base tier when at cap, "Slots: 1/2" when at tier 2 with one target
- [x] 9.5 Client smoke: as test client → try to POST a target to a channel NOT in `effectiveChannelIds()` → expect 403
- [x] 9.6 Client smoke: as test client with tier 1 → create a target → confirm `[+ Nuevo destino]` modal closes, list refreshes, badge updates to "Slots: 1/1" (cap reached)
- [x] 9.7 Client smoke: as test client → click `[Mostrar clave]` on a target → confirm re-auth prompt appears and the plaintext is shown once
- [x] 9.8 Edge smoke: suspend the test client (`status='suspended'`) → confirm any restream route returns 401/403 with "Cuenta suspendida"
- [x] 9.9 Edge smoke: as an unhabilitated client → confirm `GET /client/channels/{c}/restream-targets` returns 403 with the flash message from `EnsureRestreamEnabled`

## 10. Documentation & final checks

- [x] 10.1 Append a short "Restream Module" section to `AGENTS.md` under the existing Architecture Notes summarizing the two tables, the tier enum, and the admin-only habilitation rule (so future agents know not to look for the playout engine integration here)
- [x] 10.2 Run `php artisan view:clear && php artisan config:clear && php artisan route:clear`
- [x] 10.3 Run `php artisan view:cache` (then `view:clear`) to confirm the new Blade partials compile
- [x] 10.4 Run `php artisan check:destructive-migrations` to confirm no destructive migration slipped in
- [x] 10.5 Grep verification per AGENTS.md dual-implementation rule:
  - `grep -rn "modals.open('restream-target'" resources/views/` → must find at least one usage in client/channels
  - `grep -rn "x-restream-target-modal" resources/views/` → must match the open() call
  - `grep -n "effectiveChannelIds\|canAccessChannel" app/Http/Controllers/Client/RestreamTargetController.php` → must show scoping on every method
