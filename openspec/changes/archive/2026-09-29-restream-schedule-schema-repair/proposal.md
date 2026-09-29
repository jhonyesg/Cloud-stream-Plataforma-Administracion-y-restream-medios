## Why

After a destructive migration wiped the `users` table, the operator restored a PostgreSQL dump from `storage/app/backups/`. The dump carried the live data (users, channels, media_items, playlists, restream_targets, etc.) but its `migrations` ledger only went up to `2026_09_29_022700_add_thumbnail_media_id_to_restream_target_schedules`. Five migrations present in `database/migrations/` are therefore "pending" from Laravel's point of view:

- `0001_01_01_000000_create_users_table` — re-creates `users`, `password_reset_tokens`, `sessions`, and DROP/CREATE the `user_role` / `user_status` enums. **The DB already has these tables and the enums are in active use.** Running this would attempt to DROP TYPEs that are referenced by `users.role` / `users.status`, fail with `cannot drop type ... because other objects depend on it`, and leave the migration ledger inconsistent. It would also call `Schema::create('users')` against an existing table, which throws.
- `0001_01_01_000001_create_cache_table` — would create `cache` / `cache_locks` (they already exist; `Schema::create` throws).
- `0001_01_01_000001_create_cloudstream_core_tables` — would DROP / CREATE nine enums (`channel_status`, `provider_type`, `media_kind`, `media_status`, `transition_in`, `block_kind`, `template_status`, `emission_status`, `user_role`, `user_status`) that are all live in the restored DB and then try to create `channels`, `media_items`, `playlists`, etc. that already exist. **This is the most dangerous of the four** — it would attempt to drop enum types used by 128 `media_items`, 6 `channels`, 11 `playlists`.
- `0001_01_01_000002_create_jobs_table` — would create `jobs`, `job_batches`, `failed_jobs` that already exist.
- `2026_09_29_034500_add_emission_overrides_to_restream_target_schedules` — **additive only**: adds `schedule_title`, `schedule_description`, `platform_privacy`, `keep_recording` (all nullable) to `restream_target_schedules`. This is the migration the application code already assumes applied; skipping it is the root cause of the user's bug:

```
SQLSTATE[42703]: Undefined column: 7 ERROR: column "schedule_title" of relation
"restream_target_schedules" does not exist
  (Connection: pgsql, SQL: insert into "restream_target_schedules"
   ("name", "starts_at", "ends_at", "timezone", "notes", "thumbnail_path",
    "thumbnail_media_id", "schedule_title", "schedule_description",
    "platform_privacy", "keep_recording", "target_id", "id", "updated_at",
    "created_at") values (…))
```

The trigger from the UI is the client creating a new scheduled window for a restream target. The model (`App\Models\RestreamTargetSchedule`) declares `schedule_title`, `schedule_description`, `platform_privacy`, `keep_recording` as `fillable`, the request validates them, and the controller INSERTs them. The DB rejects the INSERT because the columns were never created.

The fix must (a) NOT touch the restored data (users, channels, media_items, playlists, restream_targets, etc.) and (b) bring the `migrations` ledger and the live schema back into agreement so `php artisan migrate` is clean going forward.

## What Changes

1. **Mark the four `0001_*` scaffolding migrations as already applied** in the `migrations` table without executing their `up()` bodies. The restored DB has every object those migrations would create, so the bodies must not run. We INSERT their migration names into `migrations` with the current `batch + 1`.
2. **Apply `2026_09_29_034500_add_emission_overrides_to_restream_target_schedules`** so the four nullable columns appear on `restream_target_schedules`. This restores the contract the model + controller already assume. After this, the restream "Programar" modal can create schedules without `SQLSTATE[42703]`.
3. **Verify the restored `users` row for `pruebas` authenticates with password `T3cn0l0g14`.** If `Hash::check('T3cn0l0g14', $user->password)` returns `false`, re-hash with `Hash::make('T3cn0l0g14')` and persist. This is the operator's stated acceptance criterion for the user mentioned.
4. **Sanity sweep** other modules the operator mentioned ("todos los módulos"): confirm `media_items`, `channels`, `playlists`, `restream_targets`, `audit_logs` are untouched (row counts unchanged before/after) and that `php artisan check:destructive-migrations` is still green.

No application code (controllers, views, models, requests) is modified — the schema is brought into sync with code that already shipped.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities
- `migration-data-safety`: adds a new requirement for `db:mark-migrations-applied`, the artisan command that records scaffolding migrations as already applied after a backup restore without re-running their DDL. See `specs/migration-data-safety/spec.md`.

## Impact

- **Code**: zero source-file changes. A new artisan command `db:mark-migrations-applied` performs the idempotent INSERT into `migrations`. The existing migration `2026_09_29_034500_add_emission_overrides_to_restream_target_schedules.php` runs unmodified.
- **Database**: additive only. Four nullable columns on `restream_target_schedules`. No rows in any table are modified, deleted, or migrated. Enums, sequences, triggers, foreign keys, and indexes are not touched.
- **APIs**: none. The schedule create/update endpoints (already shipping) start succeeding.
- **Backups / safety**: `php artisan db:backup` runs **first** per `AGENTS.md` (mandatory). A new file lands in `storage/app/backups/cloudstream_backup_<timestamp>.sql`. The new `mark-migrations-applied` artisan command is wrapped in `DB::transaction` and refuses to run unless the named migration files exist on disk and the named objects are already present in the live DB (idempotency check).
- **Operator-visible**: the `pruebas` user can log in with `T3cn0l0g14`; the client can create scheduled windows for a restream target without seeing the SQLSTATE error.
- **Risk**:
  - If the `0001_*` ledger entries are written before we confirm the live DB has every object, we risk marking one as applied that has not been physically restored. Mitigation: the command asserts each table/enum/index in the migration body exists via `pg_class` / `pg_type` lookup before INSERT.
  - The `034500` migration is the only one that actually runs DDL. It is purely additive (4 nullable columns). A defensive pre-check verifies the columns do not already exist.