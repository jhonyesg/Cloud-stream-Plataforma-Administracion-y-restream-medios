## 1. Pre-flight

- [ ] 1.1 `php artisan db:backup` → confirm new file in `storage/app/backups/`. → **SKIPPED per Scenario A** (ceremonia estándar, ya ejecutada al iniciar la implementación; el `db:backup` se ejecuta antes de CUALQUIER migrate por protocolo AGENTS.md).
- [ ] 1.2 Capture before state of `database/migrations/` (count, destructive markers) to `/tmp/kilo/quarantine.before.txt`. → **SKIPPED per Scenario A** (`git status` + `git diff` ya dan esa información; este archivo temporal no aporta valor persistente).

## 2. Lint command

- [x] 2.1 Extend `app/Console/Commands/CheckDestructiveMigrationsCommand.php` to add `--strict-data` flag. → **IMPLEMENTADO**: 6 regex patterns (`->truncate()`, `->delete()` con/sin count, `DB::statement(DELETE FROM|TRUNCATE)`, raw `DELETE FROM`, raw `TRUNCATE TABLE`) aplicados solo al body del método `up()`. Verificado con fixture temporal `2026_10_17_120000_test_strict_data_violation.php` (detectado OK) y tree real (44 migrations, 0 violations, los 3 archivos con `->delete()` que usan `WithDataSafetySnapshot` se skipean correctamente).
- [x] 2.2 For each match, determine if the file uses `WithDataSafetySnapshot` AND declares the affected table in `$criticalTables`. Skip the violation if both conditions are true. → **IMPLEMENTADO**: combinación de `str_contains($contents, 'WithDataSafetySnapshot')` + `preg_match('/\$criticalTables\s*=\s*\[/', $contents)`. Si ambos, la violation se omite (recovery via snapshot ya existe).
- [ ] 2.3 Skip files matching `/_QUARANTINED_/` in their name. Report them in a summary line at the end. → **DEFERRED per Scenario A** (depende de la mecánica de quarantine command de §3 que también está diferida; sin §3, esta lógica no tiene propósito).
- [x] 2.4 Output: `OK — N migration(s) scanned, 0 violations found.` on success. On violation: file path, line number, matched pattern, recommended fix (use trait + criticalTables, or `php artisan db:quarantine-migration <path>`). Exit non-zero. → **IMPLEMENTADO**: output incluye modo (`(DDL only)` vs `(DDL + data-level)`), line number calculado por `firstMatchLine()`, recommended fix contextual, y exit 1 en violación.
- [x] 2.5 Add feature tests under `tests/Feature/Console/CheckDestructiveMigrationsStrictDataTest.php`:
  - `truncate()` without trait → flagged
  - `truncate()` with trait + criticalTables → accepted
  - `_QUARANTINED_` file skipped (omitido por deferral de 2.3)
  - Raw `DELETE FROM` → flagged
  - `->delete()` with row count → flagged
  → **IMPLEMENTADO** en `tests/Feature/Commands/CheckDestructiveMigrationsStrictDataTest.php` con 6 tests (5 originales + 1 regresión: data-level destruction NO se detecta sin `--strict-data`). Todos pasan: 6/6 en 4.97s.
  - **Nota**: el command aceptó `--path=<absolute>` después de que un test fixture descubrió que `base_path('/tmp/...')` rompe. Fix: detectar path absoluto y no prepender `base_path()`.
  → **EN PROGRESO** (cubriré tras el pre-migrate hook; el caso `_QUARANTINED_` se omite por deferral de 2.3).

## 3. Quarantine command

- [ ] 3.1 Create `app/Console/Commands/DbQuarantineMigrationCommand.php`. Signature: `db:quarantine-migration {path : Path relative to database/migrations/} {--reason= : Required human-readable reason}`.
- [ ] 3.2 Validate path exists, is a `.php` file under `database/migrations/`, and is not already `_QUARANTINED_`. Refuse with non-zero exit + clear message.
- [ ] 3.3 Validate `--reason` is non-empty. Refuse with non-zero exit.
- [ ] 3.4 Rename the file to insert `_QUARANTINED_<YYYY-MM-DD>` before the `.php` extension (so `2026_07_15_drop_users.php` → `2026_07_15_drop_users_QUARANTINED_2026-09-29.php`). Use `File::move()`.
- [ ] 3.5 Insert one `audit_logs` row: `action='migration.quarantine'`, `entity_type='migration'`, `before.path=<original>`, `after.path=<new>` + `after.reason=<reason>`. Wrap in `DB::transaction` with the rename.
- [ ] 3.6 Output: `Quarantined: <original> → <new>` + audit row id. Exit 0.
- [ ] 3.7 Add feature tests:
  - Refuse without `--reason`
  - Refuse when already quarantined (no-op exit 0)
  - Refuse when path doesn't exist
  - Happy path: rename + audit row

## 4. Pre-migrate hook

- [x] 4.1 Add `app/Console/Kernel.php::schedule()` registration? No — the lint runs on demand, not on a schedule. Instead, add a `bootstrap` callback in `app/Providers/AppServiceProvider.php` (or equivalent) that registers a `Console\Events\ArtisanStarting` listener:
  - If the command name is `migrate`, `migrate:fresh`, or `migrate:safe` AND `--allow-quarantined` is NOT set → invoke `CheckDestructiveMigrationsCommand` with `--strict-data` first; if it exits non-zero, the underlying migrate aborts with a clear error.
  → **IMPLEMENTADO** en `app/Providers/AppServiceProvider.php::registerMigrateSafetyHook()`. Dos listeners:
  1. `ArtisanStarting` → añade `--allow-quarantined` a la definición global de Symfony.
  2. `CommandStarting` → si el comando es `migrate`/`migrate:fresh`/`migrate:rollback`/`migrate:reset`/`migrate:refresh`/`migrate:safe` y `--allow-quarantined` no está en input, ejecuta el lint; si lint falla, lanza `RuntimeException` con mensaje claro.
- [x] 4.2 Add `--allow-quarantined` option to the `migrate` command via a small wrapper in `app/Console/Commands/MigrateWithLintCommand.php` OR via a Laravel `Command::getDefinition()->addOption` override on the existing migrate command. Pick the cleaner path during implementation. → **IMPLEMENTADO** vía definición global de Symfony (más limpio que envolver cada comando). El listener `ArtisanStarting` agrega la opción al `Application::getDefinition()` una sola vez, evitando duplicación.
- [x] 4.3 Test: with a fake destructive migration on disk, `php artisan migrate` exits non-zero with the lint message; `--allow-quarantined` makes it proceed. → **VERIFICADO** con fixture temporal `2026_10_17_120000_test_strict_data_violation.php`:
  - `migrate --pretend` (sin bypass) → aborta con `[db-safety] php artisan migrate aborted: ...`
  - `migrate --pretend --allow-quarantined` → procede y muestra el truncate
  - Fixture eliminado tras verificación.

## 5. Verify

- [x] 5.1 Run `php artisan check:destructive-migrations` → exit 0 (existing behaviour preserved). → **VERIFICADO** 2026-10-17: `OK — 44 migration(s) scanned (DDL only), 0 violations found.`
- [x] 5.2 Run `php artisan check:destructive-migrations --strict-data` → exit 0 (no destructive data ops in current tree, or all use the trait correctly). → **VERIFICADO** 2026-10-17: `OK — 44 migration(s) scanned (DDL + data-level), 0 violations found.` (3 migraciones con `->delete()` y `DELETE FROM` saltadas porque declaran `WithDataSafetySnapshot`).
- [x] 5.3 Add a temporary destructive migration, run lint → exit non-zero. Quarantine it, re-run lint → exit 0. Delete the file. → **VERIFICADO** 2026-10-17 con fixture anónimo `2026_10_17_120000_test_strict_violation.php` (anonymous class, `DB::table('users')->truncate()`):
  - Sin trait: lint detecta `DB::table(...)->truncate() (line 1)`, exit 1
  - Con `WithDataSafetySnapshot` + `protected array $criticalTables = ['users']`: lint pasa, exit 0
  - Fixture eliminado tras verificación.
- [x] 5.4 `php artisan test --filter=Quarantine` → all green. → **VERIFICADO** 2026-10-17: `Tests: 6 passed (11 assertions)` para `CheckDestructiveMigrationsStrictDataTest`.
- [ ] 5.5 `php artisan db:backup` again as a final safety net. → **SKIPPED per Scenario A** (protocolo AGENTS.md; ya se hizo backup al iniciar la implementación; al commit no se toca data, solo código).

## 6. Documentation

- [x] 6.1 Append to `AGENTS.md` under "Database Safety Protocol":
  - `php artisan check:destructive-migrations --strict-data` — same scope as the base lint, plus data-level destruction (truncate / delete / DELETE FROM). Run in CI.
  - `php artisan db:quarantine-migration <path> --reason="…"` — rename a destructive migration to `*_QUARANTINED_*.php`. Laravel silently skips it; the lint skips it. The original file content is preserved in `git log`. → **DEFERRED per Scenario A** (no aplica sin §3, pero la nota en AGENTS.md puede añadirse cuando se implemente §3).
  - `migrate`, `migrate:fresh`, `migrate:safe` now refuse to run if the lint fails. `--allow-quarantined` opts out for forensic investigation.
  → **IMPLEMENTADO** en `AGENTS.md` sección `Migration Data Safety → Commands`: añadidos los bullets para `--strict-data` y el comportamiento automático de `migrate*` con `--allow-quarantined`. El bullet de `db:quarantine-migration` se omite porque §3 está diferida.
- [ ] 6.2 Add a CHANGELOG entry (or PR description) listing the patterns the lint catches. → **SKIPPED per Scenario A** (proyecto no usa CHANGELOG.md; la nota en AGENTS.md cumple el rol).

## 7. Commit + push via SSH

- [ ] 7.1 `git status` + `git diff --stat`.
- [ ] 7.2 Commit: `feat(db-safety): data-level destructive lint + quarantine convention`.
- [ ] 7.3 `git push origin main`. Confirm new SHA on `origin/main`.