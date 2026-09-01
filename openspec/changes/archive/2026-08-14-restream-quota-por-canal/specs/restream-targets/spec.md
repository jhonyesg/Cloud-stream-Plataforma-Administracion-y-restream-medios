# restream-targets Delta Specification

## MODIFIED Requirements

### Requirement: Active target count respects the user's quota

The system SHALL count only targets where `enabled = true` **for the target's channel** against that channel's `max_outputs` in `restream_quotas`. The check MUST run inside a DB transaction with row locking (`RestreamQuota::lockForUpdate()` on the `(user_id, channel_id)` row) to avoid race conditions when the user clicks "create" twice in quick succession.

#### Scenario: User at cap cannot create another active target
- **WHEN** a habilitated user with `max_outputs = 2` on channel `C` already has 2 active targets on `C` and submits a third on `C` with `enabled = true`
- **THEN** the server responds 422 indicating the cap is reached for that channel and no target is created

#### Scenario: User below cap can create a new active target
- **WHEN** a habilitated user with `max_outputs = 3` on channel `C` has 1 active target on `C` and submits a new target on `C` with `enabled = true`
- **THEN** the server creates the target and returns 201

#### Scenario: Inactive target does not consume a slot
- **WHEN** a user has 2 active targets on channel `C` and creates a new one on `C` with `enabled = false`
- **THEN** the server creates it, the slot count stays at 2/2 used on that channel, and the user can still enable it later if a slot is free

#### Scenario: Disabling frees a slot
- **WHEN** a user disables one of their active targets on channel `C`
- **THEN** `restreamUsedOutputsFor(C)` decreases by 1 and `restreamRemainingSlotsFor(C)` increases by 1

### Requirement: Target changes are audited

Every create, update (including `enabled` toggle), and delete of a `restream_targets` row SHALL produce one entry in `audit_logs` with the acting user, the `channel_id`, `before`/`after`, and `action` in `{create.restream_target, update.restream_target, delete.restream_target}`. Stream key plaintext SHALL NOT appear in `audit_logs.before`/`after`.

#### Scenario: Enable toggle is audited without leaking the key
- **WHEN** a client toggles `enabled` from false to true on a target
- **THEN** `audit_logs` contains a row with `action='update.restream_target'`, `channel_id`, `before={enabled:false, ...}`, `after={enabled:true, ...}` and the `stream_key` field is replaced by `stream_key_set: true` (or omitted entirely)

## ADDED Requirements

### Requirement: Quota guard validates per channel

The `RestreamQuotaGuard::assertCanEnable(User $user, Channel $channel)` SHALL be the single gate for enabling a target on a channel. It SHALL lock the `restream_quotas` row for `(user, channel)` and count enabled targets for that channel. Every create, update, and start endpoint (client and admin) SHALL invoke the guard with the channel of the route.

#### Scenario: Guard blocks target on unhabilitated channel
- **WHEN** `assertCanEnable` is invoked for a channel with no enabled `restream_quotas` row
- **THEN** it throws a quota exception that surfaces as 422/403 and no target is enabled

#### Scenario: Guard permits target within the channel cap
- **WHEN** `assertCanEnable` is invoked for a channel with `max_outputs = 2` and 1 active target
- **THEN** it completes without error and the target may be enabled

### Requirement: Client index reports per-channel counts

The client index endpoint `GET /client/channels/{channel}/restream-targets` SHALL return `used_outputs`, `max_outputs`, and `remaining_slots` computed for that channel only, alongside the channel object.

#### Scenario: Index reflects the channel quota
- **WHEN** a client calls the index for channel `C` with 1 active target and cap 2
- **THEN** the response includes `used_outputs = 1`, `max_outputs = 2`, `remaining_slots = 1`

## REMOVED Requirements

### Requirement: Admin user view shows the Restream section

The previous admin user detail view (`/admin/users/{user}`) showed a Restream section. Habilitation is now granted from the channel; this requirement is retired.