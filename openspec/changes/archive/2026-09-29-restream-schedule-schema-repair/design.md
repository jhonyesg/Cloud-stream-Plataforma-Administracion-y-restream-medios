## Context

The Cloudstream database was restored from a PostgreSQL dump after a destructive migration wiped the `users` table. The dump carried every domain table (users, channels, media_items, playlists, schedule_templates, schedule_blocks, virtual_screens, restream_targets, …) and every enum type (`user_role`, `user_status`, `channel_status`, `media_kind`, `media_status`, `block_kind`, `template_status`, `emission_status`, `transition_in`) along with it, but the dump's `migrations` ledger stopped at `2026_09_29_022700_add_thumbnail_media_id_to_restream_target_schedules`.

```
migrations ledger  (DB)              filesystem (database/migrations/)
─────────────────────────────         ────────────────────────────────
…                                       0001_01_01_000000_create_users_table          ← pending, dangerous
…                                       0001_01_01_000001_create_cache_table           ← pending, would throw
…                                       0001_01_01_000001_create_cloudstream_core_tables ← pending, dangerous (enum DROP)
…                                       0001_01_01_000002_create_jobs_table            ← pending, would throw
…                                       …
2026_09_29_022700_add_thumbnail_…        2026_09_29_022700_add_thumbnail_…               ← matched
                                        2026_09_29_034500_add_emission_overrides_…      ← pending, ADDITIVE only
```

`php artisan migrate` would therefore attempt to:

1. DROP TYPE `user_status` / `user_role` (rejected by Postgres: `cannot drop type … because other objects depend on it` — `users.role` and `users.status` reference them).
2. Then `Schema::create('users')` which throws "relation already exists" — the migration is marked failed and not recorded.

Net result: the `migrations` ledger stays out of sync and the real underlying bug (missing `schedule_title`/`schedule_description`/`platform_privacy`/`keep_recording` columns on `restream_target_schedules`) is never fixed because the migrator never gets past the scaffolding migrations.

Stakeholders:
- **Operator**: needs `pruebas` user working with `T3cn0l0g14`, all current modules functional, zero data loss.
- **Restream clients**: need the "Programar" modal on a restream target to create a new scheduled window without `SQLSTATE[42703]`.

## Goals / Non-Goals

**Goals**
- Bring the `migrations` ledger and live schema into agreement with zero data loss.
- Add the four missing columns on `restream_target_schedules` so the model/controller contract holds.
- Provide an audit-friendly way to "rebaseline" the ledger after a backup restore, so this incident is reproducible.
- Confirm `pruebas` / `T3cn0l0g14` works.

**Non-Goals**
- Migrating from PostgreSQL or any other DBMS.
- Reverting the destructive migration that wiped users (it's already happened; the dump is the source of truth).
- Changing application code (controllers, views, models, requests) — the code already assumes the columns exist.
- Adding new behavior to `restream_target_schedules` (the four columns are nullable; legacy rows stay NULL).
- Touching the legacy `scheduled_start_at` / `scheduled_stop_at` columns on `restream_targets` — they were dropped by `2026_09_28_091500_migrate_legacy_target_schedules_and_drop_columns`, which is recorded as applied.

## Decisions

### Decision 1 — Do not run the `0001_*` migrations as-is

**Rationale.** These migrations were authored for a greenfield DB. Running them on a partially-restored DB is unsafe: `Schema::create(...)` will throw "relation already exists" on every table, and the enum DROP/CREATE statements will fail with `cannot drop type … because other objects depend on it`.

**Alternatives considered.**
- (a) Edit the four `0001_*` files to add `Schema::hasTable(...)` guards and `DROP TYPE IF EXISTS … CASCADE` before CREATE. **Rejected** — touches files that are historical scaffolding and pollutes `git blame` for the restore incident.
- (b) Rename the four `0001_*` files to `9999_*` and prepend a guard. **Rejected** — same problem plus the timestamps lie about when they ran.
- (c) Skip the four files at the Laravel level by registering their names in the `migrations` table without running the bodies. **Chosen** — keeps source files pristine, the ledger reflects reality, and `migrate` will simply ignore them on subsequent runs because they're already recorded.

### Decision 2 — Manual ledger update with idempotent artisan command, not raw SQL

**Rationale.** AGENTS.md is strict about not running `migrate:fresh` and about wrapping destructive DDL in a transaction. A one-off `psql` command would skip both safety nets. A small artisan command (`db:mark-migrations-applied`) provides:

- **Pre-flight checks**: asserts the migration file exists on disk, asserts every table/enum the migration's `up()` body expects is already present in the live DB (via `pg_class` / `pg_type` lookups). Refuses to mark anything as applied if the assumption is wrong.
- **Transaction**: wrapped in `DB::transaction` so partial inserts cannot happen.
- **Idempotency**: `INSERT … ON CONFLICT (migration) DO NOTHING` so re-running is harmless.
- **Audit**: writes one `audit_logs` entry (`action='migrations.rebaseline'`) capturing operator, files marked, and the timestamp.

### Decision 3 — Run `2026_09_29_034500` via the standard migrator

**Rationale.** This is the only pending migration that should actually run DDL. It is purely additive (four nullable columns). After Decisions 1 + 2, `php artisan migrate` will see exactly one pending migration and execute it cleanly.

**Pre-check inside the artisan command** verifies each of the four columns does NOT already exist on `restream_target_schedules`. If they do (defensive), the command skips and reports.

### Decision 4 — Authentication verification before any password reset

**Rationale.** The `pruebas` user row exists with a valid bcrypt hash (`$2y$12$…`, length 60). AGENTS.md + security best practices forbid blindly re-hashing; the operator's claim that the password is `T3cn0l0g14` should be verified first.

**Procedure.**
```
php artisan tinker
>>> $u = App\Models\User::where('username','pruebas')->first();
>>> var_dump(\Illuminate\Support\Facades\Hash::check('T3cn0l0g14', $u->password));
```
- `true` → no change. Move on.
- `false` → ask operator explicitly before re-hashing. If confirmed, `Hash::make('T3cn0l0g14')` then `$u->save()`. The audit log records `action='user.password_reset.operator'` (not the old password, not the new one — only that the reset happened).

### Decision 5 — No application code changes

The model `RestreamTargetSchedule::$fillable`, the `StoreRestreamTargetScheduleRequest` validator, and the `RestreamTargetScheduleController::store` INSERT all reference `schedule_title`, `schedule_description`, `platform_privacy`, `keep_recording`. After Decision 3 these columns exist; the existing code works without edits.

If the operator reports additional symptoms in other modules ("todos los módulos"), the same `db:backup` + `db:mark-migrations-applied` + `migrate` sequence handles them — but those modules must first be triaged for similar schema/ledger drift.

## Risks / Trade-offs

| Risk | Mitigation |
|---|---|
| `db:mark-migrations-applied` is run against an actually empty DB and silently skips the real DDL | Pre-flight asserts every object the `up()` body would create exists. Refuses to proceed otherwise. |
| `034500` migration fails mid-DDL on a table with a different schema than expected | All four columns are added with `nullable()->after(...)`. Postgres rolls back the ALTER TABLE on any failure because it's a single statement. `db:backup` is mandatory beforehand per AGENTS.md. |
| Operator blindly re-hashes the `pruebas` password without consent | Verification step in tinker first. If `Hash::check` fails, **stop** and ask operator before persisting a new hash. |
| Other modules have the same drift but weren't reported | The sanity sweep at the end (`media_items` count, `channels` count, `playlists` count, `audit_logs` count, plus `check:destructive-migrations` green) catches obvious ones. A scripted smoke test of `admin/` and `client/` index pages that touch each table will surface latent drift. |
| Audit log row could be deleted by the same destructive migration if it recurs | Out of scope for this change. AGENTS.md is already explicit about `db:backup` first and `WithDataSafetySnapshot` on destructive migrations; the trust boundary lives there. |

## Migration Plan

1. `php artisan db:backup` — mandatory per AGENTS.md. Captures `storage/app/backups/cloudstream_backup_<timestamp>.sql` (≈700 KB; consistent with current dump size).
2. Capture row counts before any change: `media_items`, `channels`, `playlists`, `restream_targets`, `restream_target_schedules`, `users`, `audit_logs`. Store in a temp file.
3. `php artisan db:mark-migrations-applied 0001_01_01_000000_create_users_table 0001_01_01_000001_create_cache_table 0001_01_01_000001_create_cloudstream_core_tables 0001_01_01_000002_create_jobs_table` — adds four rows to `migrations` with `batch = MAX(batch)+1`, inside a transaction, after asserting every table/enum/index exists. Writes one `audit_logs` row.
4. `php artisan migrate` — should run only `2026_09_29_034500_add_emission_overrides_to_restream_target_schedules`, adding 4 nullable columns.
5. Verify: `\d restream_target_schedules` shows the four new columns; `SELECT migration FROM migrations WHERE migration LIKE '2026_09_29_034500%';` returns one row.
6. Verify `pruebas` auth in tinker. Reset hash only if `Hash::check` fails AND operator confirms.
7. Compare row counts to the snapshot from step 2. Anything different = rollback candidate.
8. `php artisan check:destructive-migrations` — must remain exit 0.

**Rollback.** Because the `034500` migration ships its own `down()` that drops the four columns, `php artisan migrate:rollback --step=1` reverts the only DDL change. The four `migrations` rows added in step 3 can be removed with `DELETE FROM migrations WHERE migration IN (...)`. If anything goes sideways beyond that, `storage/app/backups/cloudstream_backup_<timestamp>.sql` is the cold-restore path (it predates step 3 so it does not include the four ledger rows, but the live data + schema are otherwise consistent — running `php artisan migrate` against it after restoring will run the four `0001_*` migrations and fail as before; the release snapshot at `database/snapshots/release/production.json.gz` covers that path).

## Open Questions

- **Q1.** Does the operator have any post-restore custom data (media folders, virtual screens, virtual-screen editor layouts) created manually via tinker that are NOT in the dump? If yes, we should run a `php artisan tinker` sanity listing of `SELECT count(*) FROM media_folders` and `SELECT count(*) FROM virtual_screens` against the live DB and confirm both look reasonable before proceeding. *(Current live counts: media_items=128, channels=6, playlists=11, restream_targets=2. media_folders and virtual_screens pending verification.)*
- **Q2.** Are there any **other** modules besides restream schedules that the operator is hitting with the same `SQLSTATE 42703` symptom? If so, each is the same root cause (a missing `0xxx_add_*` migration) and the same fix path applies per migration. We should enumerate them in the design before executing.
- **Q3.** The destructive migration that wiped `users` is still in `database/migrations/`. AGENTS.md says never to `migrate:rollback` without checking, and `WithDataSafetySnapshot` is the safety net. Do we want a follow-up change that quarantines the offending migration file (rename to `…_DESTRUCTIVE_QUARANTINED_…`) so it can never be re-run accidentally on a fresh environment? *Out of scope for THIS change, but worth tracking.*