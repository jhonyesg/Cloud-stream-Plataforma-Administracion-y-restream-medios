## Context

Cloudstream today has a single emission output per channel (one `VirtualScreen` per channel). There is no way for a client to re-broadcast that signal to external platforms like Facebook Live, TikTok Live, or YouTube Live. The user (admin of Cloudstream) wants a Restream module with a four-tier habilitation model:

- **Tier 1 — base/estándar (gratuito)**: 1 simultaneous output destination per user.
- **Tier 2 — 1.ª adición (pago)**: 2 destinations.
- **Tier 3 — 2.ª adición (pago)**: 3 destinations.
- **Tier 4 — 3.ª adición (pago)**: 4 destinations.

The base tier (1 destination) is included by default for every habilitated user; each step above the base is a paid upgrade that the admin must explicitly unlock. Only the admin role can grant or change habilitation, and only from the admin panel. Payment, billing, and commercial cycle are explicitly **out of scope** — the admin will simply adjust `max_outputs` and `enabled` for the user, mirroring the plan they have contracted externally.

The current emission engine is dismounted (see `AGENTS.md` — Emission Module dismounted 2026-07-20). This change therefore **only defines the configuration surface** (data + UI + APIs). Hooking `restream_targets` into a future playout engine is a separate change.

The dual-implementation rule applies: every UI surface must exist in both `admin/` and `client/`. For Restream, the split is:
- `admin/`: habilitation CRUD + read-only target list per user.
- `client/`: habilitation read-only + target CRUD scoped to `effectiveChannelIds()`.

## Goals / Non-Goals

**Goals:**
- Persist habilitation state (`restream_quotas`) and target configuration (`restream_targets`) in PostgreSQL.
- Enforce the tier cap (1/2/3/4) atomically when a user enables a target.
- Provide a clean admin UI to grant, edit, and disable habilitation per user, including a notes field.
- Provide a client UI (a tab/panel inside the channel view) to manage the user's own targets.
- Encrypt `stream_key` at rest; never echo plaintext except on explicit reveal action.
- Audit every change in `restream_quotas` and `restream_targets`.
- Block unhabilitated users from the Restream UI via middleware.

**Non-Goals:**
- The actual re-streaming playback pipeline (FFmpeg/GStreamer fanning out to RTMP). This change ships configuration only; the engine will consume it later.
- Payment, invoicing, plan management, or any commercial flow.
- Allowing clients to upgrade their own tier. Only admin can change `max_outputs` / `enabled`.
- Admin editing or deleting a client's targets.
- Real-time health monitoring / reconnection logic (only a `status` field exists, to be written by the future engine).
- Channel-side multi-output config in `virtual_screens` — Restream targets are a separate layer that lives on the user, not the channel.

## Decisions

### D1. Two tables, not one — habilitation vs. targets are independent lifecycles

`restream_quotas` is the **plan** (per user, one row). `restream_targets` is the **configuration** (many rows per user). They have different access control (admin-only vs. client-owned) and different read shapes (single object vs. collection). Mixing them into one row-per-target table would make `max_outputs` denormalized across N rows and force tier changes to walk every row.

Alternatives considered:
- *Single `restream_targets` table with `max_outputs` denormalized per row*: simpler migration, but tier changes require UPDATE on every row, and the "one plan per user" invariant becomes application-enforced instead of DB-enforced.
- *JSON column on `users`*: rejected — Cloudstream already has a strict relational model (`channels`, `media_items`, etc.) and the team consistently prefers tables over JSON.

### D2. Four fixed tiers as an enum-like constraint, not a free integer

`max_outputs` is validated against `{1, 2, 3, 4}` in the controller and asserted by a check constraint in the migration (`CHECK (max_outputs IN (1, 2, 3, 4))`). Tier `1` is the free base (default); tiers `2/3/4` are three paid additions on top of the base, one extra destination each. This makes the pricing model visible at the schema level and prevents off-policy tiers from sneaking in via direct DB writes.

Alternative considered: *free integer with a "default tier" config*. Rejected because the user explicitly stated four fixed levels (1 base + 3 paid additions); making it configurable later is an additive change (just drop the check) if needed.

### D3. `enabled` flag, not delete, for both quota and target soft-disable

Both `restream_quotas` and `restream_targets` use an `enabled` boolean. The system never hard-deletes rows from `restream_targets` — disable is `enabled = false`. Reasons: (a) preserves audit trail; (b) lets a user pause a target without losing its URL/key; (c) lets the engine distinguish "intentionally disabled" from "row gone".

Alternative considered: *hard delete with a soft-delete column*. More moving parts (SoftDeletes trait, deleted_at filtering everywhere) for the same outcome.

### D4. Stream key encrypted with `Crypt`, not hashed

The stream key must be **recoverable** by the engine (to push it into an RTMP URL), so it cannot be hashed. Laravel's `Crypt::encryptString` / `Crypt::decryptString` (AES-256-CBC via the app key) is the right primitive. The plaintext is never returned by list/show endpoints; only the create endpoint returns it once and a `[Mostrar clave]` action can reveal it with password re-confirmation.

Alternative considered: *store the URL+key as a single RTMP URL and treat the whole string as secret*. Rejected because it conflates two fields that the user wants to edit independently (URL host vs. key) and makes the `[Mostrar clave]` UX awkward.

### D5. Atomic slot check via `lockForUpdate` inside a transaction

When a client enables a target, the controller MUST count current active targets and compare to `max_outputs` inside a `DB::transaction` with `RestreamQuota::lockForUpdate()` on the user's quota row. Without the lock, two concurrent "enable" clicks can both pass the count check and overshoot the cap.

Alternative considered: *unique partial index `(user_id) WHERE enabled = true` with a generated column counting slots*. Powerful but PostgreSQL-specific and harder to debug; the transactional lock is simpler and matches the existing Laravel patterns in the codebase.

### D6. Habilitation lives in its own table, not as columns on `users`

Habilitation is a feature flag + tier. Storing it as `users.restream_enabled` + `users.restream_max_outputs` would couple it to the `users` table that already carries 12 columns. The team has consistently used dedicated tables for distinct domains (`audit_logs`, `emission_state`, `virtual_screens`). A `restream_quotas` row also gives us a natural place for `granted_by`, `granted_at`, and `notes` without further user-table churn.

Alternative considered: *two columns on `users`*. Rejected because it would force a migration on `users` and because the admin wants a separate `granted_by` audit field per habilitation event (we already track user-role changes in `audit_logs`; this fits the same pattern).

### D7. UI placement: section inside `/admin/users/{user}` and tab inside `/client/channels/{channel}`

The admin habilitation section is a partial (`_restream_section.blade.php`) embedded in the existing user detail view — no new top-level admin route. The client targets UI is a tab inside the channel detail view (alongside Emisión, Programación, etc.) so users don't get a fourth top-level menu item for a feature they may not have.

Alternative considered: *dedicated `/admin/restream` and `/client/restream` pages*. Rejected because admin's mental model is "users first, their modules second" and the client only has one channel detail view as the natural home for per-channel config.

### D8. Admin can read but not edit/delete targets

The engine reads `restream_targets`. If an admin could edit/delete them, we'd have two sources of truth for the client's configuration. Admins only see a diagnostic list per user.

Alternative considered: *full admin CRUD on targets*. Rejected because it bypasses the client's quota check and ownership semantics.

### D9. Single status field, not a state machine

`status ∈ {idle, active, error}` with `last_error` text. The state transitions will be driven by the future engine. The CRUD layer never sets `status = active` from a client request — only the engine can. This keeps the client UI simple (toggle `enabled`) while leaving room for the engine to write richer state later.

## Risks / Trade-offs

- **R1 — Unbounded `max_outputs` if the check constraint is dropped later** → Mitigation: the validation lives in the controller **and** in the DB check; even if one is bypassed, the other stops it. Document the constraint in `AGENTS.md`.
- **R2 — Race condition on enable if the lock is forgotten** → Mitigation: the slot check lives in a single `RestreamQuotaGuard::canEnableAnother()` service method; controllers MUST call it inside `DB::transaction(... lockForUpdate ...)`. Code review checklist item.
- **R3 — `stream_key` accidentally returned by a serializer** → Mitigation: `RestreamTarget` model overrides `toArray()` (or the controller manually picks fields) to redact `stream_key`. Add an explicit unit-test scenario for the list endpoint.
- **R4 — Disabling a quota while active targets exist leaves them orphaned** → Documented behavior: existing targets stay as-is, frozen at their current `enabled`/`status`. Re-enabling brings them back. If the user wants them cleaned up, they delete them manually.
- **R5 — Admin grants tier 4 to a user who then configures 4 targets, then admin downgrades to tier 2** → The downgrade is **blocked** by the spec (the user must first reduce active targets to ≤ 2). If the admin needs to force-downgrade, they must suspend/disable the user first.
- **R6 — A client sees Restream tab on a channel they don't own** → Mitigation: the controller checks `canAccessChannel($channel)` on every action; the tab itself is only rendered for channels in `effectiveChannelIds()`.
- **R7 — Engine integration is a separate future change** → Acceptable. The data model and UI ship first; when the engine returns, it only needs to read `restream_targets WHERE user_id = ? AND channel_id = ? AND enabled = true`.
- **R8 — No real-time feedback in the UI when the engine changes `status`** → Out of scope for this change. A future change will wire SSE/polling. For now, the `status` field is updated by the engine and surfaced on next page load.

## Migration Plan

1. **Backup**: `php artisan db:backup` (mandatory per `AGENTS.md` Database Safety Protocol).
2. **Migration**: `php artisan migrate:safe` (backup + migrate + verify) — runs the two new up-only migrations. No destructive `down()` is needed (both tables are `up()`-only). Even so, declare an empty `down()` that drops the tables for symmetry.
3. **Seed** (optional): leave tables empty; admin habilitates from the panel per the user's request.
4. **Smoke test**: admin grants habilitation (tier 1, base) to a test client; client creates one target; admin upgrades tier (tier 1 → 2) succeeds; client creates a second target; admin downgrades tier (2 → 1) blocked because 2 active targets > 1 cap; client disables one target; admin downgrades again succeeds.
5. **Rollback**: if something is wrong, the empty `down()` migrations drop the tables. No data loss in production (no rows yet in this rollout). Subsequent changes that have rows must use `WithDataSafetySnapshot` per the protocol.

## Open Questions

- **Q1**: Should the admin panel show the user's Restream quota on the users **index** (in the table) or only on the user detail? → Decision deferred to implementation; default: only on detail to keep the index lean.
- **Q2**: When a target's `enabled = true` and `status = 'error'` (engine reports failure), should the system auto-flip `enabled = false` after N retries? → Out of scope; the engine owns retry policy.
- **Q3**: Should we surface a per-platform help tooltip with the RTMP URL template (e.g. `rtmps://live-api-s.facebook.com:443/rtmp/`)? → Nice-to-have; the target form will accept arbitrary `destination_url` and only suggest templates in a placeholder.
