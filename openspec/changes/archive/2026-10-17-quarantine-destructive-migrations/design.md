## Context

After the 2026-09-29 incident (a destructive migration wiped `users`; the operator restored from backup), `php artisan check:destructive-migrations` was added (or already existed — see `AGENTS.md` Database Safety Protocol) to lint every `up()` for `Schema::drop` / `dropIfExists`. That covers DDL but **not** data-level destruction:

```php
// DESTRUCTIVE — current lint misses this:
DB::table('users')->truncate();
DB::table('channels')->where('id', $id)->delete();
foreach ($rows as $r) { DB::table('media_items')->where('id', $r->id)->delete(); }
DB::statement('TRUNCATE TABLE users CASCADE');
```

A migration containing any of the above can be re-deployed on a fresh environment and wipe the corresponding tables without the lint flagging it. The `WithDataSafetySnapshot` trait catches *some* of these (it snapshots the table before destructive DDL), but only if the developer remembers to add the trait AND declare `$criticalTables` correctly. We have no automatic enforcement that ALL destructive data ops in migrations use the trait.

## Goals / Non-Goals

**Goals**
- A second lint pass (`--strict-data`) that parses every migration file and flags data-deletion patterns.
- A quarantine convention (`*_QUARANTINED_*.php`) for destructive migrations that should never run again.
- An artisan command to rename a file in place + audit the action.
- A pre-migrate guard that runs both lints before any DDL/DML executes.
- A spec-driven capability `migration-safety-lint` so future features can build on the same contract.

**Non-Goals**
- Detecting every conceivable dangerous SQL (parameterised `?` placeholders, dynamic table names, raw query strings via `DB::raw()`). The lint catches the patterns 95% of developers write by reflex; the remaining 5% must be caught by code review (out of scope for the lint).
- Re-running the offender against the production DB to "see what happens". Quarantining is a permanent path, never a debugging tool.
- Replacing `WithDataSafetySnapshot`. The trait stays as the *correct* way to write a destructive migration when one is genuinely needed; quarantine is for the migrations that should never run again.

## Decisions

### Decision 1 — Regex-based static analysis, no PHP-Parser

**Rationale.** Laravel migration files are small PHP scripts. A regex sweep for `DB::table\(['"]([\w.]+)['"]\)->(truncate|delete)\(`, `->delete\(\s*\d`, `DELETE FROM`, and `TRUNCATE TABLE` catches the standard patterns with very few false positives. Adding nikic/php-parser would add a dependency for a check that runs once per CI build.

**Alternatives considered.**
- (a) PHP-Parser AST walk. Rejected — overkill, slower, harder to test.
- (b) Run migrations in a transaction + diff row counts. Rejected — requires a sandbox DB, doesn't scale to thousands of migrations, and would only catch the *running* migration.

### Decision 2 — Quarantine by filename, not by configuration

**Rationale.** Laravel's migration discovery ignores non-numeric-prefixed files. A migration named `2026_07_15_999999_QUARANTINED_users_wipe.php` (with leading timestamp + `_QUARANTINED_` infix) is silently skipped by `php artisan migrate`. No configuration file needed; the convention is self-documenting in `git log` and the lint output.

**Alternative considered.**
- (a) A JSON registry (`config/quarantined_migrations.php`) listing the offenders. Rejected — adds runtime state that must be kept in sync with the filesystem, and a typo could disable a legitimate migration.

### Decision 3 — `db:quarantine-migration` writes an audit row, not a soft-delete

**Rationale.** Quarantine is destructive (it renames the file). An `audit_logs` row with `action='migration.quarantine'`, `before={'path': ..., 'class': ...}`, `after={'path': ..., 'reason': ...}` lets the operator trace every quarantine to a human reason. The original file content is preserved in `git log`, so the audit row only needs to capture *what was changed* and *why*, not the full migration body.

### Decision 4 — Pre-migrate guard runs in the artisan kernel

**Rationale.** Hooking the `migrate`, `migrate:fresh`, and `migrate:safe` commands at the kernel level means the lint runs regardless of how the operator invoked the migration (cron, CI, manual). The hook is opt-out via `--allow-quarantined` (run quarantined migrations anyway — for forensics) but defaults to blocking.

**Alternative considered.**
- (a) Just document the lint and rely on the operator running it. Rejected — the entire point of the change is to *prevent* the incident from happening again, which requires an automated guard, not a polite request.

### Decision 5 — Capability: `migration-safety-lint`

**Rationale.** The existing `migration-data-safety` capability covers the `WithDataSafetySnapshot` discipline. The new lint is a different concept (static analysis + quarantine workflow) and deserves its own spec so future PRs can reference it without coupling to the data-snapshot trait.

## Risks / Trade-offs

| Risk | Mitigation |
|---|---|
| Regex matches a non-destructive `->delete()` call (e.g., `Cache::forget(...)` doesn't match `DB::table`, but a custom helper called `delete_old_logs()` might) | Lint output names the file + line so the reviewer can confirm context. False positives are reviewable; missing destructive ops are not. |
| Quarantine rename breaks `composer install` if a deployer script does `php artisan migrate --force` and expects every file to run | Pre-migrate guard short-circuits with a clear error message: "X migration is quarantined and will not run. If this is unexpected, check the file's `_QUARANTINED_` prefix." |
| Hooking the kernel breaks existing CI tests that rely on `migrate:fresh` working without the lint passing | The lint exits non-zero only on *new* violations. Existing migrations that already pass the strict check are unaffected. The hook is also skipped when `--allow-quarantined` is passed (rare escape hatch). |
| Operator renames a destructive migration but forgets to add `WithDataSafetySnapshot` first, then it gets quarantined — losing the data it was supposed to migrate | Quarantine skips running the migration entirely, so the original "broken" state is preserved. The operator must then write a non-destructive follow-up migration that fixes the issue. Documented in the audit reason. |

## Migration Plan

1. **Backup** (`php artisan db:backup`) — mandatory before any schema-related change.
2. **Add the lint command** + tests covering the 5 patterns.
3. **Add the quarantine command** + tests.
4. **Add the kernel hook** + test that runs the lint on a sample migration and exits non-zero when a violation is found.
5. **Run the lint on the existing tree** to confirm exit 0 — every destructive migration in the codebase already passes the strict check. Document this in the commit message.
6. **No DB schema changes** — this change is purely additive tooling.

## Open Questions

- **Q1.** Should the lint also check for `Schema::drop` (which is currently caught by the original `check:destructive-migrations`)? Yes — both lints share the same code path so the operator runs one command and sees both kinds of violations.
- **Q2.** Should quarantined files be moved to a `database/migrations/quarantined/` subdirectory so `git ls-files database/migrations/` stays clean? Probably yes for the next migration, but it changes Laravel's discovery order. Defer to a follow-up if requested.
- **Q3.** Do we want a Slack / email notification when an attempted quarantine bypass is logged? Useful for ops; out of scope.