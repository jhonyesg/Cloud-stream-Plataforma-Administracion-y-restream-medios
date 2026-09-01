## Purpose

Define the admin-controlled habilitation system that decides which users have access to the Restream module and how many simultaneous output destinations each habilitated user may run. The habilitation is a per-user configuration record (one row per user) with a numeric tier cap; payment, billing, and commercial cycle are explicitly out of scope. Only the admin role can create, modify, or disable a user's habilitation, and only from the admin panel.

## ADDED Requirements

### Requirement: One habilitation row per user

The system SHALL persist habilitation state in a `restream_quotas` table keyed by `user_id` (unique). At most one row per `user_id` SHALL exist. A user without a row in `restream_quotas` SHALL be treated as **not habilitated** (cannot access the Restream module, cannot create `restream_targets`).

#### Scenario: User without row is not habilitated
- **WHEN** a user has no row in `restream_quotas`
- **THEN** `User->hasRestreamEnabled()` returns `false` and any request to a Restream-protected route returns HTTP 403

#### Scenario: One row per user is enforced
- **WHEN** an admin attempts to create a second `restream_quotas` row for the same `user_id`
- **THEN** the database rejects the insert via unique constraint and the API returns 422

### Requirement: Habilitation is admin-only

The system SHALL allow only users with `role='admin'` to read, create, update, or delete rows in `restream_quotas`. Clients (`role='client'`) SHALL NEVER receive a write endpoint for `restream_quotas`. Clients SHALL only be able to **read** their own quota (own row, read-only) to know how many slots they have left.

#### Scenario: Client cannot create a quota
- **WHEN** a user with `role='client'` calls `POST /admin/users/{u}/restream-quota`
- **THEN** the server responds HTTP 403 and no row is created

#### Scenario: Client can read own quota
- **WHEN** a client calls `GET /api/me/restream-quota`
- **THEN** the server responds 200 with `{ enabled, max_outputs, used_outputs, remaining_slots }` if a row exists, or `{ enabled: false }` if it does not

#### Scenario: Admin can grant habilitation
- **WHEN** an admin submits `POST /admin/users/{u}/restream-quota` with `{ enabled: true, max_outputs: 2 }`
- **THEN** the server creates the row, sets `granted_by = current_admin.id`, `granted_at = now()`, and returns 201 with the row payload

### Requirement: Four habilitation tiers

The system SHALL support exactly four numeric tiers for `max_outputs`: `1` (base/estándar, gratuito), `2` (1.ª adición, pago), `3` (2.ª adición, pago), `4` (3.ª adición, pago). Values outside `{1, 2, 3, 4}` SHALL be rejected with HTTP 422. The base level `1` represents the free standard quota; tiers `2/3/4` are paid upgrades, each adding one extra simultaneous destination.

#### Scenario: Tier 1 is the default base
- **WHEN** an admin grants habilitation without specifying `max_outputs`
- **THEN** the server defaults to `max_outputs = 1`

#### Scenario: Tier 2 (first paid addition) is allowed
- **WHEN** an admin grants habilitation with `max_outputs = 2`
- **THEN** the row is created with `max_outputs = 2` and the user may configure up to 2 active targets

#### Scenario: Tier 3 (second paid addition) is allowed
- **WHEN** an admin grants habilitation with `max_outputs = 3`
- **THEN** the row is created with `max_outputs = 3`

#### Scenario: Tier 4 (third paid addition) is allowed
- **WHEN** an admin grants habilitation with `max_outputs = 4`
- **THEN** the row is created with `max_outputs = 4`

#### Scenario: Invalid tier is rejected
- **WHEN** an admin submits `max_outputs = 5` (or `0`, or a non-integer)
- **THEN** the server responds 422 with a validation error and no row is created or updated

#### Scenario: Base user can already configure one target
- **WHEN** a habilitated user has `max_outputs = 1` and zero active targets
- **THEN** `restreamRemainingSlots()` returns `1` and the user can configure exactly one target without paying for an extra addition

### Requirement: Enabling and disabling

The system SHALL allow an admin to toggle `enabled` on a `restream_quotas` row. Disabling SHALL NOT delete the row or its `max_outputs` value; it SHALL only block new target creation and freeze existing active targets from being re-enabled until re-enabled.

#### Scenario: Admin disables a user
- **WHEN** an admin submits `PATCH /admin/users/{u}/restream-quota` with `{ enabled: false }`
- **THEN** the row is updated, `User->hasRestreamEnabled()` returns `false`, and any subsequent call to create or activate a `restream_target` for that user returns 403

#### Scenario: Disabling preserves the tier
- **WHEN** an admin re-enables a previously disabled user
- **THEN** the `max_outputs` value SHALL be exactly what it was before disable (no reset)

### Requirement: Tier downgrade cannot silently break existing targets

When an admin lowers `max_outputs` to a value smaller than the current count of active `restream_targets` for that user, the system SHALL reject the change with HTTP 422 and SHALL NOT modify the row. The admin must first deactivate or delete enough targets to fit the new tier before downgrading.

#### Scenario: Downgrade blocked when over limit
- **WHEN** a user has 3 active targets and `max_outputs = 3`, and the admin submits `max_outputs = 2`
- **THEN** the server responds 422 with a message indicating the user must first reduce to ≤ 2 active targets, and the row is unchanged

#### Scenario: Downgrade allowed when within limit
- **WHEN** a user has 1 active target and `max_outputs = 4`, and the admin submits `max_outputs = 2`
- **THEN** the server updates the row to `max_outputs = 2`

#### Scenario: Upgrade is always allowed
- **WHEN** an admin submits any `max_outputs` greater than the current value
- **THEN** the server updates the row regardless of how many active targets the user has

### Requirement: User helpers expose quota to the rest of the system

The `User` model SHALL expose:
- `User->restreamQuota(): ?RestreamQuota` returning the related row or `null`.
- `User->hasRestreamEnabled(): bool` returning `true` iff a quota row exists AND `enabled = true`.
- `User->restreamMaxOutputs(): int` returning `restreamQuota->max_outputs` if habilitated, else `0`.
- `User->restreamUsedOutputs(): int` returning the count of `restream_targets` where `user_id = this.id AND enabled = true`.
- `User->restreamRemainingSlots(): int` returning `max(0, restreamMaxOutputs() - restreamUsedOutputs())`.

These helpers SHALL be the single source of truth for "can this user create another target?".

#### Scenario: Disabled user has zero slots
- **WHEN** a user has a `restream_quotas` row with `enabled = false`
- **THEN** `restreamMaxOutputs()` returns `0` and `restreamRemainingSlots()` returns `0`

#### Scenario: Base-tier user at cap has zero slots
- **WHEN** a user with `max_outputs = 1` has 1 active target
- **THEN** `restreamRemainingSlots()` returns `0`

#### Scenario: Tier-2 user with 1 active target has 1 slot
- **WHEN** a user with `max_outputs = 2` has 1 active target
- **THEN** `restreamRemainingSlots()` returns `1`

### Requirement: Quota changes are audited

Every create, update, or delete of a `restream_quotas` row SHALL produce one entry in `audit_logs` with `action` in `{create.restream_quota, update.restream_quota, delete.restream_quota}`, the acting `user_id` (the admin), and full `before`/`after` JSON.

#### Scenario: Grant is audited
- **WHEN** an admin grants habilitation to user `u`
- **THEN** `audit_logs` contains a row with `action='create.restream_quota'`, `entity_id=u.id`, `before=null`, `after={enabled:true, max_outputs:2, granted_by:admin.id, ...}`

#### Scenario: Tier change is audited
- **WHEN** an admin changes `max_outputs` from 2 to 4
- **THEN** `audit_logs` contains a row with `action='update.restream_quota'`, `before={max_outputs:2}`, `after={max_outputs:4}`

### Requirement: Middleware blocks unhabilitated users from the Restream UI

The system SHALL provide a `EnsureRestreamEnabled` middleware that returns HTTP 403 with a flash message if `auth()->user()->hasRestreamEnabled()` is `false`. This middleware SHALL be applied to every client route that exposes Restream management (target CRUD UI).

#### Scenario: Unhabilitated client hits a Restream route
- **WHEN** a client without `restream_quotas` (or with `enabled = false`) calls `GET /client/channels/{c}/restream-targets`
- **THEN** the server responds 403 with a message indicating the module is not enabled for the user

#### Scenario: Habilitated client reaches the Restream UI
- **WHEN** a client with `enabled = true` calls `GET /client/channels/{c}/restream-targets`
- **THEN** the server renders the Restream management panel
