# restream-habilitation Delta Specification

## MODIFIED Requirements

### Requirement: One habilitation row per user

The system SHALL persist habilitation state in a `restream_quotas` table keyed by the compound `(user_id, channel_id)` (unique). At most one row per `(user_id, channel_id)` SHALL exist. A user without a row for a given channel SHALL be treated as **not habilitated** for that channel (cannot access the Restream module for it, cannot create `restream_targets` linked to it).

#### Scenario: User without row is not habilitated
- **WHEN** a user has no `restream_quotas` row for a given channel `C`
- **THEN** any request to a Restream-protected route for channel `C` returns HTTP 403 with a per-channel message, even if the user has a row for another channel

#### Scenario: One row per user is enforced
- **WHEN** an admin attempts to create a second `restream_quotas` row for the same `(user_id, channel_id)`
- **THEN** the database rejects the insert via unique constraint and the API returns 422

#### Scenario: Same user habilitated on two channels
- **WHEN** an admin creates a `restream_quotas` row for `(user, C1)` and another for `(user, C2)`
- **THEN** both rows exist; the user is habilitated on both channels with independent `max_outputs` values

### Requirement: Habilitation is admin-only

The system SHALL allow only users with `role='admin'` to read, create, update, or delete rows in `restream_quotas`. Clients (`role='client'`) SHALL NEVER receive a write endpoint for `restream_quotas`. Clients SHALL only be able to **read** their own quota per channel (read-only) to know how many slots they have left.

#### Scenario: Client cannot create a quota
- **WHEN** a user with `role='client'` calls the admin quota endpoint for any channel
- **THEN** the server responds HTTP 403 and no row is created

#### Scenario: Client can read own quota
- **WHEN** a client calls the Restream index for channel `C`
- **THEN** the server responds with `{ enabled, max_outputs, used_outputs, remaining_slots }` computed for channel `C`

#### Scenario: Admin can grant habilitation
- **WHEN** an admin submits the quota endpoint for channel `C` with `{ enabled: true, max_outputs: 2 }`
- **THEN** the server creates the row with `channel_id = C`, sets `granted_by = current_admin.id`, `granted_at = now()`, and returns 201 with the row payload

### Requirement: Four habilitation tiers

The system SHALL support exactly four numeric tiers for `max_outputs`: `1` (base/estándar, gratuito), `2` (1.ª adición, pago), `3` (2.ª adición, pago), `4` (3.ª adición, pago). Values outside `{1, 2, 3, 4}` SHALL be rejected with HTTP 422. The base level `1` represents the free standard quota; tiers `2/3/4` are paid upgrades, each adding one extra simultaneous destination **per channel**.

#### Scenario: Tier 1 is the default base
- **WHEN** an admin grants habilitation for a channel without specifying `max_outputs`
- **THEN** the server defaults to `max_outputs = 1`

#### Scenario: Tier 2 (first paid addition) is allowed
- **WHEN** an admin grants habilitation for a channel with `max_outputs = 2`
- **THEN** the row is created with `max_outputs = 2` and the user may configure up to 2 active targets on that channel

#### Scenario: Tier 3 (second paid addition) is allowed
- **WHEN** an admin grants habilitation for a channel with `max_outputs = 3`
- **THEN** the row is created with `max_outputs = 3`

#### Scenario: Tier 4 (third paid addition) is allowed
- **WHEN** an admin grants habilitation for a channel with `max_outputs = 4`
- **THEN** the row is created with `max_outputs = 4`

#### Scenario: Invalid tier is rejected
- **WHEN** an admin submits `max_outputs = 5` (or `0`, or a non-integer) for any channel
- **THEN** the server responds 422 with a validation error and no row is created or updated

#### Scenario: Base user can already configure one target
- **WHEN** a habilitated user has `max_outputs = 1` on channel `C` and zero active targets on `C`
- **THEN** `restreamRemainingSlotsFor(C)` returns `1` and the user can configure exactly one target on `C` without paying for an extra addition

### Requirement: Enabling and disabling

The system SHALL allow an admin to toggle `enabled` on a `restream_quotas` row for a channel. Disabling SHALL NOT delete the row or its `max_outputs` value; it SHALL only block new target creation on that channel and freeze existing active targets on that channel from being re-enabled until re-enabled.

#### Scenario: Admin disables a user
- **WHEN** an admin submits a PATCH disabling the `restream_quotas` row for `(user, C)`
- **THEN** the row is updated, and any subsequent call to create or activate a `restream_target` on channel `C` for that user returns 403

#### Scenario: Disabling preserves the tier
- **WHEN** an admin re-enables a previously disabled channel
- **THEN** the `max_outputs` value SHALL be exactly what it was before disable (no reset)

### Requirement: Tier downgrade cannot silently break existing targets

When an admin lowers `max_outputs` to a value smaller than the current count of active `restream_targets` **for that channel**, the system SHALL reject the change with HTTP 422 and SHALL NOT modify the row. The admin must first deactivate or delete enough targets on that channel to fit the new tier before downgrading.

#### Scenario: Downgrade blocked when over limit
- **WHEN** a channel has 3 active targets and `max_outputs = 3`, and the admin submits `max_outputs = 2`
- **THEN** the server responds 422 with a message indicating the user must first reduce to ≤ 2 active targets on that channel, and the row is unchanged

#### Scenario: Downgrade allowed when within limit
- **WHEN** a channel has 1 active target and `max_outputs = 4`, and the admin submits `max_outputs = 2`
- **THEN** the server updates the row to `max_outputs = 2`

#### Scenario: Upgrade is always allowed
- **WHEN** an admin submits any `max_outputs` greater than the current value on a channel
- **THEN** the server updates the row regardless of how many active targets the user has on that channel

### Requirement: User helpers expose quota to the rest of the system

The `User` model SHALL expose per-channel helpers:
- `User->restreamQuotaFor(?string $channelId): ?RestreamQuota` returning the related row for `(user, channelId)` or `null`.
- `User->hasRestreamEnabled(): bool` returning `true` iff at least one habilitation row exists AND `enabled = true` (any channel).
- `User->hasRestreamEnabledFor(?string $channelId): bool` returning `true` iff a quota row exists for that channel AND `enabled = true`.
- `User->restreamMaxOutputsFor(?string $channelId): int` returning the channel's `max_outputs` if habilitated, else `0`.
- `User->restreamUsedOutputsFor(?string $channelId): int` returning the count of `restream_targets` where `user_id = this.id AND channel_id = channelId AND enabled = true`.
- `User->restreamRemainingSlotsFor(?string $channelId): int` returning `max(0, restreamMaxOutputsFor(channelId) - restreamUsedOutputsFor(channelId))`.

These helpers SHALL be the single source of truth for "can this user create another target on this channel?".

#### Scenario: Disabled user has zero slots
- **WHEN** a user has a `restream_quotas` row with `enabled = false` for channel `C`
- **THEN** `restreamMaxOutputsFor(C)` returns `0` and `restreamRemainingSlotsFor(C)` returns `0`

#### Scenario: Base-tier user at cap has zero slots
- **WHEN** a user with `max_outputs = 1` on channel `C` has 1 active target on `C`
- **THEN** `restreamRemainingSlotsFor(C)` returns `0`

#### Scenario: Tier-2 user with 1 active target has 1 slot
- **WHEN** a user with `max_outputs = 2` on channel `C` has 1 active target on `C`
- **THEN** `restreamRemainingSlotsFor(C)` returns `1`

### Requirement: Quota changes are audited

Every create, update, or delete of a `restream_quotas` row SHALL produce one entry in `audit_logs` with `action` in `{create.restream_quota, update.restream_quota, delete.restream_quota}`, the acting `user_id` (the admin), the `channel_id`, and full `before`/`after` JSON.

#### Scenario: Grant is audited with channel
- **WHEN** an admin grants habilitation to `(user, channel)` 
- **THEN** `audit_logs` contains a row with `action='create.restream_quota'`, `channel_id=channel.id`, `before=null`, `after={user_id, channel_id, enabled:true, max_outputs:2, ...}`

#### Scenario: Tier change is audited
- **WHEN** an admin changes `max_outputs` from 2 to 4 on a channel
- **THEN** `audit_logs` contains a row with `action='update.restream_quota'`, `channel_id`, `before={max_outputs:2}`, `after={max_outputs:4}`

#### Scenario: Grant is audited
- **WHEN** an admin grants habilitation to a user (any channel)
- **THEN** `audit_logs` contains a row with `action='create.restream_quota'`, `channel_id`, `before=null`, `after={enabled:true, max_outputs:N, granted_by:admin.id, ...}`

### Requirement: Middleware blocks unhabilitated users from the Restream UI

The system SHALL provide an `EnsureRestreamEnabled` middleware that validates the **channel of the route**: it returns HTTP 403 with a flash message if there is no `restream_quotas` row for `(auth()->user()->id, route('channel')->id)` with `enabled = true`. This middleware SHALL be applied to every client route that exposes Restream management for a channel (target CRUD). The index route without a channel SHALL only require the user to have at least one habilitated channel.

#### Scenario: Unhabilitated client hits a Restream route
- **WHEN** a client without `restream_quotas` for channel `C` (or with `enabled = false`) calls `GET /client/channels/{c}/restream-targets`
- **THEN** the server responds 403 with a message indicating the module is not enabled for that channel

#### Scenario: Habilitated client reaches the Restream UI
- **WHEN** a client with `restream_quotas` for channel `C` and `enabled = true` calls `GET /client/channels/{c}/restream-targets`
- **THEN** the server renders the Restream management panel for that channel

## ADDED Requirements

### Requirement: Habilitation is granted from the channel

The system SHALL expose admin endpoints for `restream_quotas` scoped to a channel: `GET /admin/channels/{channel}/restream`, `POST /admin/channels/{channel}/restream`, `PATCH /admin/channels/{channel}/restream`, `DELETE /admin/channels/{channel}/restream`. The admin UI SHALL grant habilitation from the channel detail (pattern of the VirtualScreen "Pantalla" button), not from the global user list.

#### Scenario: Admin grants habilitation from the channel view
- **WHEN** an admin opens the Restream section of a client's channel and submits `{ enabled: true, max_outputs: 2 }`
- **THEN** a `restream_quotas` row for `(user, channel)` is created with those values

#### Scenario: Channel without habilitation shows grant control
- **WHEN** an admin opens the Restream section of a channel with no `restream_quotas` row
- **THEN** the section shows a grant form with tier selector (1/2/3/4)

#### Scenario: Habilitated channel shows tier control and target list
- **WHEN** an admin opens the Restream section of a habilitated channel
- **THEN** the section shows the current tier, an edit form (tier + enabled toggle + notes), and the list of targets for that channel

## REMOVED Requirements

(Empty — the global per-user habilitation requirement "One habilitation row per user" is modified in place; the admin/users view's Restream section is retired in `restream-targets`.)