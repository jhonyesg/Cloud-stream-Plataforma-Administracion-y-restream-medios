## MODIFIED Requirements

### Requirement: Restream target data model

(Extending the existing requirement in `openspec/specs/restream-targets/spec.md`. The data model and partial unique index remain unchanged; the controller-level validator SHALL distinguish soft-deleted rows that were aborted during a failed create from soft-deleted rows that the user (or admin) intentionally archived, and SHALL auto-purge the aborted ones so they never block a retry.)

#### Scenario: Soft-deleted target releases the triple

- **WHEN** a user's target `(channel_id=X, platform=youtube)` is soft-deleted and they create a new one with the same triple
- **THEN** the insert succeeds (no 500, no unique violation)

#### Scenario: Failed OAuth create does not leave an orphan row

- **WHEN** a client posts `POST /client/channels/{c}/restream-targets` with a connected `platform_account_id` for YouTube (or Facebook) and the `createPlatformBroadcast()` call throws `RestreamPlatformAccountException`
- **THEN** the controller hard-deletes the just-inserted row (via `forceDelete()`), the client receives 422 with the platform error message, and `restream_targets` contains zero rows for that `(user_id, channel_id, platform)` triplet — neither live nor soft-deleted
- **AND** the failed attempt is recorded in `audit_logs` with the originating `RestreamPlatformAccountException` message

#### Scenario: Retry after a failed OAuth create succeeds

- **WHEN** a previous `createPlatformBroadcast()` failed and was rolled back (so no soft-deleted row exists for the triplet)
- **AND** the client retries `POST /client/channels/{c}/restream-targets` with the same connected account after fixing the underlying issue (re-authorization, network, etc.)
- **THEN** the controller accepts the new request, returns 201, and persists exactly one live row for the triplet

#### Scenario: Validator auto-purges a pre-existing aborted-create orphan

- **WHEN** the `restream_targets` table already contains a soft-deleted row for `(user_id=U, channel_id=C, platform=youtube)` whose `stream_key IS NULL AND platform_broadcast_id IS NULL AND status = 'idle'` (an orphan from a previous failed `store()` rollback)
- **AND** the client posts a new `POST /client/channels/{c}/restream-targets` for the same triplet
- **THEN** the controller hard-deletes the orphan row, persists the new live row, and returns 201 (no 422 to the client)

#### Scenario: Validator auto-purges ALL aborted orphans even when a legitimate archive also exists

- **WHEN** the `restream_targets` table contains BOTH a soft-deleted row with `stream_key IS NULL AND platform_broadcast_id IS NULL AND status = 'idle'` (aborted orphan) AND a soft-deleted row with `stream_key` populated (legitimately archived) for the same `(user_id, channel_id, platform)` triplet
- **AND** the client posts a new `POST /client/channels/{c}/restream-targets` for the same triplet
- **THEN** the controller hard-deletes the aborted orphan, leaves the archived row intact, and responds 422 with the "archivado" message so the client can choose to restore or force-delete it

#### Scenario: Validator surfaces an actionable error for a user-archived target

- **WHEN** the `restream_targets` table contains a soft-deleted row for `(user_id=U, channel_id=C, platform=youtube)` with `stream_key` populated (i.e. the user or admin actually configured and then archived it)
- **AND** the client posts a new `POST /client/channels/{c}/restream-targets` for the same triplet
- **THEN** the controller responds 422 with message "Ya existe un destino archivado para esta plataforma en este canal. Restáuralo o elimínalo definitivamente para crear uno nuevo."
- **AND** the existing soft-deleted row remains unchanged (not auto-purged) — it carries credentials and/or a remote broadcast that the user may want to restore

#### Scenario: Validator surfaces an actionable error for an admin-archived target

- **WHEN** an admin previously called `DELETE /admin/channels/{c}/restream-targets/{t}` on a live target, leaving a soft-deleted row with `stream_key` and `platform_broadcast_id` populated
- **AND** the target's owner posts a new `POST /client/channels/{c}/restream-targets` for the same triplet
- **THEN** the controller responds 422 with the same "archivado" message
- **AND** the soft-deleted row remains intact until the owner (or admin) explicitly restores it via `POST .../restore` or hard-deletes it via `DELETE .../force`

#### Scenario: Parity between client and admin validators

- **WHEN** the same `(user_id, channel_id, platform)` triplet triggers the validator
- **THEN** `Client\RestreamTargetController::assertNoDuplicateTriple` and `Admin\RestreamTargetController::assertNoDuplicateTriple` SHALL reach the same outcome for each of the three cases (no existing row → pass; aborted-create orphan → auto-purge + pass; user-archived → 422 with the "archivado" message)

#### Scenario: Operator command purges aborted-create orphans from the live DB

- **WHEN** an operator runs `php artisan restream:purge-aborted-targets --apply` on a database that contains soft-deleted rows matching the abort signature (`stream_key IS NULL AND platform_broadcast_id IS NULL AND enabled = false AND status = 'idle'`)
- **THEN** every matching row is removed (hard delete) inside a single `DB::transaction` with row locks
- **AND** exactly one `audit_logs` row is written with `action='purge_aborted.restream_target'`, `entity_id=null`, and `metadata={purged_count: N, criteria: '…', channel_id: '…'|null}`
- **AND** running the same command without `--apply` (default dry-run) prints the candidate count and the candidate rows without mutating anything