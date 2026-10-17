## 1. Service extraction

- [x] 1.1 Create `app/Services/Scheduler/TemplateActivator.php` with public method `activateIfHealable(ScheduleTemplate $t): bool`. Implement the guards: `status === 'draft'`, `blocks_count > 0`, `channel.status NOT IN ('suspended','archived')`. Implement the transactional promotion (archive any competing active template, promote `$t`, write `audit_logs` with `action='update.schedule_template.heal'`). Catch `UniqueConstraintViolationException` (Postgres SQLSTATE 23505) and return `false` without re-throwing — this is the race-loss case.
- [x] 1.2 Refactor `app/Http/Controllers/Api/ScheduleTemplateController.php`: delete the private `autoActivateIfNeeded` method (lines 285-326) and replace its three call sites (`assignDay` line 199, `replicate` line 239, `replicateRange` line ~239 area) with `$this->activator->activateIfHealable($scheduleTemplate)`. Inject the new service in the constructor. Confirm the existing `autoActivateIfNeeded` semantics are preserved (same audit action names `update.schedule_template.auto_activate` and `update.schedule_template.auto_archive_on_promote` for assignDay-driven promotions — those are NOT renamed to `.heal`).
- [x] 1.3 Write a feature test `tests/Feature/Services/TemplateActivatorTest.php` covering all five scenarios from the spec (draft with blocks + active channel, empty draft, suspended channel, already active, archived, and competing active).

## 2. Heal on scheduler view (admin + client)

- [x] 2.1 In `app/Http/Controllers/Admin/ScheduleController.php::index`, after the `$template = ScheduleTemplate::where(...)` block and the `$blocksByDay` build (around line 41), inject `TemplateActivator` in the constructor and add `if ($template) { $this->activator->activateIfHealable($template); $template->refresh(); }`.
- [x] 2.2 Same change in `app/Http/Controllers/Client/ScheduleController.php::index`, after the equivalent `$template` load (around line 49).
- [x] 2.3 Write a feature test `tests/Feature/Controllers/ScheduleControllerHealTest.php` covering: (a) admin loads scheduler with draft+blocks → template is now active; (b) admin loads scheduler with draft+empty → unchanged; (c) admin loads scheduler with draft+suspended channel → unchanged; (d) same three scenarios for client.

## 3. Heal on emission start

- [x] 3.1 In `app/Services/ScheduledPlaylistResolver.php`, modify `resolveActiveTemplate` (or wrap it in a new private helper called from `resolveForNow`) so that when the active query returns null, it runs a second query for `status='draft'` and tries `$this->activator->activateIfHealable($candidate)`. Inject `TemplateActivator` in the constructor. If the heal returns true, re-run the active query to fetch the now-active template and return it. If false, return null and let `resolveForNow` throw the existing `noActiveTemplate`.
- [x] 3.2 Write a feature test `tests/Feature/Services/ScheduledPlaylistResolverHealTest.php` covering: emission start succeeds for draft+blocks+active channel; emission start still fails (422) for draft+blocks+suspended channel; emission start still fails for empty draft; emission start still fails when no template exists at all.

## 4. Heal in ProductionDataSeeder

- [x] 4.1 In `database/seeders/ProductionDataSeeder.php`, after the `foreach ($ordered as $table)` loop that inserts `schedule_templates` and `schedule_blocks`, add a final transaction that queries `ScheduleTemplate::where('status','draft')->whereHas('blocks')->get()` and invokes `TemplateActivator::activateIfHealable` on each. Log the count healed.
- [ ] 4.2 Verify with a manual test: place a snapshot at `database/snapshots/release/production.json.gz` that contains a draft+blocks template, run `php artisan migrate:fresh --seed`, confirm the template ends in `active` and an `audit_logs` entry exists with `action='update.schedule_template.heal'`. (Manual verification — performed by operator before deploy per design.md Migration Plan.)

## 5. Artisan command

- [x] 5.1 Create `app/Console/Commands/Scheduler/HealDraftTemplates.php` extending `Illuminate\Console\Command`. Signature: `schedule-templates:heal-drafts {--channel=*} {--apply} {--dry-run} {--json}`. Implement the four scenarios from the spec: default dry-run listing, `--apply` performs heal, `--json` emits structured JSON, `--channel=ID` limits scope. Inject `TemplateActivator`. Use `$this->table()` for human output and `json_encode` for `--json` output.
- [x] 5.2 Register the command in `app/Console/Kernel.php` (or wherever commands are auto-discovered via `composer.json`'s `autoload` — confirm the project's auto-discovery convention). Run `php artisan list` to verify it shows up.
- [x] 5.3 Write a feature test `tests/Feature/Commands/HealDraftTemplatesTest.php` covering the four scenarios from the spec.

## 6. Documentation

- [x] 6.1 Update `AGENTS.md` "Architecture Notes" section to mention the new `TemplateActivator` service and the four heal call sites (scheduler view, emission start, snapshot seeder, artisan command). One short paragraph.
- [x] 6.2 Update the "Migration Data Safety" section of `AGENTS.md` to mention that `ProductionDataSeeder` now self-heals draft templates at the end of restoration, so `db:list-snapshots` and `db:restore-snapshot` should be expected to leave the system with `active` templates when blocks exist.

## 7. Verification

- [x] 7.1 `php artisan check:destructive-migrations` exits 0 (no destructive migrations added).
- [x] 7.2 `php artisan test --filter='TemplateActivator|ScheduleControllerHeal|ScheduledPlaylistResolverHeal|HealDraftTemplates'` all green.
- [x] 7.3 `php artisan schedule-templates:heal-drafts --dry-run` against production-like data shows the expected count.
- [x] 7.4 `php artisan schedule-templates:heal-drafts --apply` then `--dry-run` again shows zero healable candidates (idempotent).
- [ ] 7.5 Manual smoke: client loads scheduler with the original screenshot scenario (Prueba Stream, Sept 2026, draft + viernes blocks) → badge becomes "Active", "Iniciar transmisión" button works without manual re-assign. (Manual — operator validates in browser.)
