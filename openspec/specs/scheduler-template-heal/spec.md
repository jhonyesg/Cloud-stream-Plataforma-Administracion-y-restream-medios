# scheduler-template-heal Specification

## Purpose
TBD - created by archiving change scheduler-heal-draft-template-on-read. Update Purpose after archive.
## Requirements
### Requirement: Schedule template in draft state with blocks is auto-promoted to active on read

`App\Services\Scheduler\TemplateActivator` SHALL expose `activateIfHealable(ScheduleTemplate $t): bool`. When invoked, the method SHALL promote `$t` from `status='draft'` to `status='active'` if and only if ALL of the following hold:
- `$t->status === 'draft'`
- `$t->blocks()->count() > 0`
- `$t->channel !== null` AND `$t->channel->status NOT IN ('suspended','archived')`

The method SHALL return `true` after a successful promotion and `false` otherwise (no-op case). The promotion SHALL be wrapped in `DB::transaction` and SHALL archive any other `ScheduleTemplate` of the same `(channel_id, year, month)` whose `status='active'`, the same way `ScheduleTemplateController::autoActivateIfNeeded` does today. The method SHALL be race-safe: two concurrent invocations SHALL result in exactly one promotion, thanks to the existing `UNIQUE INDEX uq_schedule_tmpl_active` and the early `status != draft → return` guard.

#### Scenario: Draft template with blocks and active channel gets promoted
- **WHEN** `activateIfHealable` is called on a template with `status='draft'`, `blocks_count=5`, and `channel.status='active'`
- **THEN** the method SHALL update the template to `status='active'`
- **AND** SHALL return `true`
- **AND** SHALL register an `audit_logs` entry with `action='update.schedule_template.heal'`, `before={status:'draft'}`, `after={status:'active'}`, `channel_id=$t->channel_id`

#### Scenario: Empty draft template is not promoted
- **WHEN** `activateIfHealable` is called on a template with `status='draft'` and `blocks_count=0`
- **THEN** the method SHALL NOT modify the template
- **AND** SHALL return `false`
- **AND** SHALL NOT register any `audit_logs` entry

#### Scenario: Draft template on suspended channel is not promoted
- **WHEN** `activateIfHealable` is called on a template with `status='draft'`, `blocks_count=5`, and `channel.status='suspended'`
- **THEN** the method SHALL NOT modify the template
- **AND** SHALL return `false`
- **AND** SHALL NOT register any `audit_logs` entry

#### Scenario: Already active template is a no-op
- **WHEN** `activateIfHealable` is called on a template with `status='active'`
- **THEN** the method SHALL NOT modify the template
- **AND** SHALL return `false`
- **AND** SHALL NOT register any `audit_logs` entry

#### Scenario: Archived template is a no-op
- **WHEN** `activateIfHealable` is called on a template with `status='archived'`
- **THEN** the method SHALL NOT modify the template
- **AND** SHALL return `false`
- **AND** SHALL NOT register any `audit_logs` entry

#### Scenario: Heal archives a competing active template in the same month
- **GIVEN** two `ScheduleTemplate` rows of the same `(channel_id, year, month)`: T1 with `status='active'` and T2 with `status='draft', blocks_count=4`
- **WHEN** `activateIfHealable(T2)` is called
- **THEN** T2 SHALL become `status='active'`
- **AND** T1 SHALL become `status='archived'`
- **AND** an `audit_logs` entry SHALL be registered for T2's promotion with `action='update.schedule_template.heal'`
- **AND** an `audit_logs` entry SHALL be registered for T1's archive with `action='update.schedule_template.auto_archive_on_promote'`

### Requirement: Scheduler view promotes draft templates before rendering

`Admin\ScheduleController::index` and `Client\ScheduleController::index` SHALL, after loading the `$template` for `(channel_id, year, month)` and before passing it to the view, invoke `TemplateActivator::activateIfHealable($template)` if `$template` is not null. The result of the heal SHALL be discarded (the call is fire-and-forget for the view layer); the controller SHALL then proceed normally with `$blocksByDay`, `$playlists`, `$mediaItems`.

#### Scenario: Client loads scheduler with inconsistent draft template
- **GIVEN** a channel with `status='active'` and a `ScheduleTemplate` for the current month with `status='draft'` and 4 blocks
- **WHEN** the client navigates to `/client/scheduler`
- **THEN** the controller SHALL load the template and call `activateIfHealable`
- **AND** the template SHALL be promoted to `status='active'` during the request
- **AND** the rendered Blade view SHALL display the "Active" badge instead of "Draft"

#### Scenario: Admin loads scheduler with empty draft template
- **GIVEN** a channel with `status='active'` and a `ScheduleTemplate` for the current month with `status='draft'` and 0 blocks
- **WHEN** the admin navigates to `/admin/scheduler`
- **THEN** the controller SHALL load the template and call `activateIfHealable`
- **AND** the template SHALL remain `status='draft'` (heal guard blocks empty templates)
- **AND** the rendered Blade view SHALL display the "Draft" badge

#### Scenario: Client loads scheduler for a suspended channel
- **GIVEN** a channel with `status='suspended'` and a `ScheduleTemplate` for the current month with `status='draft'` and 4 blocks
- **WHEN** the client navigates to `/client/scheduler`
- **THEN** the controller SHALL load the template and call `activateIfHealable`
- **AND** the template SHALL remain `status='draft'` (heal guard blocks suspended channels)

### Requirement: Emission start resolves draft templates by attempting heal before failing

`App\Services\ScheduledPlaylistResolver::resolveForNow` SHALL, when `resolveActiveTemplate` returns null, attempt a heal pass: query `ScheduleTemplate` for the same `(channel_id, year, month)` with `status='draft'` (instead of `'active'`), invoke `TemplateActivator::activateIfHealable($candidate)`, and if it returns `true` reuse that template. If the heal returns `false` (empty, channel suspended, race lost) or no draft candidate exists, the resolver SHALL throw `EmisionStartExceptionFactory::noActiveTemplate` as it does today.

#### Scenario: Emission starts despite inconsistent draft template
- **GIVEN** a channel with `status='active'` and a `ScheduleTemplate` for the current month with `status='draft'` and 4 blocks
- **WHEN** a client calls `POST /api/channels/{id}/emission/start`
- **THEN** the resolver SHALL attempt the heal and find a candidate draft
- **AND** SHALL successfully promote the candidate to `status='active'`
- **AND** SHALL continue resolution and spawn the daemon
- **AND** SHALL return `{status:'starting', timeline_version, items_count}` with HTTP 200

#### Scenario: Emission start fails when no healable template exists
- **GIVEN** a channel with `status='suspended'` and only a draft template with 4 blocks
- **WHEN** a client calls `POST /api/channels/{id}/emission/start`
- **THEN** the resolver SHALL attempt the heal
- **AND** the heal SHALL return `false` because the channel is suspended
- **AND** the resolver SHALL throw `EmisionStartExceptionFactory::noActiveTemplate` returning HTTP 422 with the existing message

### Requirement: Production snapshot seeder heals draft templates after restoring schedule_blocks

`Database\Seeders\ProductionDataSeeder::run` SHALL, after restoring `schedule_templates` and `schedule_blocks` from `database/snapshots/release/production.json.gz`, query for templates where `status='draft'` AND `id IN (SELECT DISTINCT template_id FROM schedule_blocks)`, and invoke `TemplateActivator::activateIfHealable` for each. The heal SHALL run inside the same `DB::transaction` per critical table, or in a final transaction after the per-table loops complete.

#### Scenario: Snapshot restore leaves a draft template curable
- **GIVEN** a release snapshot containing a `schedule_template` with `status='draft'` and 5 `schedule_blocks` rows for the same template
- **WHEN** `php artisan migrate:fresh --seed` runs
- **THEN** the seeder SHALL restore the rows first
- **AND** SHALL invoke `activateIfHealable` on the draft template
- **AND** the template SHALL be promoted to `status='active'`
- **AND** the resulting database state SHALL have the template as `active` (no client interaction needed)

### Requirement: Operator can manually heal draft templates via Artisan command

A new Artisan command `schedule-templates:heal-drafts` SHALL be registered. It SHALL accept three optional flags:
- `--channel=ID` — limit to a single channel
- `--dry-run` (default when no other action flag is given) — print what would change without modifying anything
- `--apply` — perform the heal
- `--json` — emit JSON output for piping

By default the command SHALL list all draft templates with `blocks_count > 0` and `channel.status NOT IN ('suspended','archived')`. When `--apply` is passed, the command SHALL invoke `TemplateActivator::activateIfHealable` for each. The command SHALL be idempotent: running it twice SHALL heal on the first run and no-op on the second.

#### Scenario: Operator runs heal in dry-run mode
- **WHEN** the operator runs `php artisan schedule-templates:heal-drafts`
- **THEN** the command SHALL list each healable template with `{template_id, channel_id, year, month, blocks_count, current_status}`
- **AND** SHALL NOT modify any template
- **AND** SHALL exit with code 0

#### Scenario: Operator runs heal with --apply
- **WHEN** the operator runs `php artisan schedule-templates:heal-drafts --apply`
- **THEN** the command SHALL invoke `activateIfHealable` for each listed template
- **AND** SHALL print a summary `Healed: N, Skipped: M, Errors: K`
- **AND** SHALL exit with code 0 on success, 1 if any error occurred

#### Scenario: Operator pipes JSON for audit dashboard
- **WHEN** the operator runs `php artisan schedule-templates:heal-drafts --dry-run --json`
- **THEN** the command SHALL emit a JSON array on stdout with `{template_id, channel_id, before, after, blocks_count}` per candidate
- **AND** the output SHALL be valid JSON parseable by `jq`

#### Scenario: Operator limits heal to a single channel
- **WHEN** the operator runs `php artisan schedule-templates:heal-drafts --channel={uuid} --apply`
- **THEN** the command SHALL only consider templates whose `channel_id` matches the given UUID
- **AND** other channels' draft templates SHALL be ignored

