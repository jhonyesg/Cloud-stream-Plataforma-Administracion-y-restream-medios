---
name: cloudstream-dual-implementation
description: This skill should be used when implementing or modifying any feature in the Cloudstream admin or client panels. It enforces the project's dual-implementation rule: every change must be applied symmetrically to both `admin/` and `client/` views, controllers, and routes. The only architectural difference between the two is data scope — admin sees ALL media, client sees only media they own or are assigned to. Failure to apply this rule has caused multiple re-do cycles (e.g., the live-viewer modal was wired into the client first, then later replicated in the admin scheduler, then the toggle had to be re-added in both). Load this skill before touching any view, controller, route, OpenSpec change, or feature task in this project.
---

# Cloudstream Dual-Implementation

## Overview

Cloudstream has two parallel interfaces — `admin/` and `client/` — that must evolve together. They are **not** independent products; they are two surfaces of the same domain. Treating them as separate code paths leads to drift, silent regressions, and rework. This skill provides the procedure to apply changes symmetrically the first time.

## Core Premise

> The **only** architectural difference between `admin/` and `client/` is data scope:
> - **Admin** = `Channel::query()` (or equivalent — sees all)
> - **Client** = `Channel::whereIn('id', $user->effectiveChannelIds())` (sees only owned + assigned)
>
> Everything else (UI patterns, components, modals, view logic, Alpine data, CSS classes) should be **identical** unless explicitly diverged.

## When to Load This Skill

Load this skill automatically when **any** of the following is true about the task:

- A view, controller, route, or modal is being created or modified under `resources/views/admin/` or `resources/views/client/`
- An OpenSpec change is being proposed that touches a feature visible to users
- The user requests a new feature, button, panel, or behavior in the app
- The user mentions "admin", "cliente", "client", or describes a feature both roles would use
- A bug report mentions something "missing" in one of the two panels
- A "Ver vivo", "Ver", or action button is being added — these MUST appear in both panels wherever they make sense

## Workflow

Apply the following six-step checklist to every change. Do not skip steps; do not finish the task with any step left incomplete.

### 1. Identify the feature and its scope

Ask: does this feature belong to admin only, client only, or both? Most features belong to both. If unsure, ask the user — but **default to "both"** unless the user explicitly restricts scope.

### 2. Map the affected files in BOTH surfaces

For each feature, identify the files that must change. Use `references/file-pairs.md` as the starting point — most features have an admin/client pair. If the feature is genuinely new (no pair exists yet), plan the pair explicitly:

- View pair: `resources/views/admin/<feature>/index.blade.php` ↔ `resources/views/client/<feature>/index.blade.php`
- Controller pair: `app/Http/Controllers/Admin/<Feature>Controller.php` ↔ `app/Http/Controllers/Client/<Feature>Controller.php`
- Routes: both groups in `routes/web.php` (admin role and client role)
- Layout: `resources/views/components/admin-layout.blade.php` ↔ `resources/views/components/client-layout.blade.php`

### 3. Apply changes to BOTH in the same commit/change

Do not finish the admin side first and "come back later" for the client. Open both files, apply the same pattern to both, then move to the next step. If a block of code is identical, copy it verbatim — divergence invites drift.

### 4. Respect data-scoping rules

The client must NEVER see data outside `effectiveChannelIds()`. Concretely:

- In any **client** controller index/show action, filter the query:
  ```php
  $channelIds = $user->effectiveChannelIds();
  $channels = Channel::whereIn('id', $channelIds)->...;
  ```
- Before any client **update/delete/store** action, call:
  ```php
  if (! $user->canAccessChannel($channel)) {
      abort(403, 'No tienes acceso a este canal.');
  }
  ```
- Admin controllers do NOT need this filtering (full access).

Read `references/data-scoping.md` for the full pattern library.

### 5. Mount all modals in BOTH layouts/parents

Every Blade modal component (`<x-foo-modal />`) used by a view must be mounted in that view. A common bug: a button calls `$store.modals.open('foo', payload)` but `<x-foo-modal />` is missing from the page → nothing happens, no console error.

**Checklist before finishing a view that opens a modal:**
- [ ] Each `@click="$store.modals.open('foo', ...)"` has a matching `<x-foo-modal />` in the same view (or in its layout, if globally used).
- [ ] The modal name passed to `open()` matches the `name` attribute on the modal component.
- [ ] When changing the modal's payload contract, update BOTH admin and client views that mount it.

Read `references/modal-checklist.md` for the current list of modals per view.

### 6. Add smoke-test tasks for BOTH surfaces

When writing OpenSpec tasks, always include a "verify in browser" task for both admin and client. Example:

```
- [ ] Smoke test admin: open /admin/channels → click "Ver vivo" → modal opens, plays HLS
- [ ] Smoke test client: open /client/channels → click "Ver vivo" → modal opens, plays HLS
```

Never close a change as "complete" without verifying the change in BOTH surfaces.

## Anti-Patterns to Avoid

These are the failure modes that have already cost rework in this project. Reject each one explicitly:

- ❌ "I'll do the admin side first, then the client." → **Always do both in the same change.**
- ❌ "The client doesn't need this button." → **Default to symmetric UI; remove explicitly only when the user says so.**
- ❌ "The modal is shared, no need to mount it in both." → **Modals must be mounted in every view that opens them.** Sharing the component file is not the same as mounting it.
- ❌ "I'll remember the data scoping for the client controller." → **Always verify by grepping for `effectiveChannelIds` and `canAccessChannel` in every new/modified client controller method.**
- ❌ "Skip the admin smoke test, the client proves it works." → **Tests are per-surface, not transitive.** A test that passes in the client does not prove the admin works.
- ❌ "I'll write the client first because the user is testing in the client." → **Even if the user is currently testing one surface, the change MUST land in the other surface before being called done.**

## Quick Self-Check Before Marking a Task Done

Run these greps before declaring any change complete:

```bash
# Find every view that opens a modal named "foo"
grep -rn "modals.open('foo'" resources/views/

# Find every view that mounts <x-foo-modal />
grep -rn "x-foo-modal" resources/views/

# For client controllers: verify scoping is in place
grep -n "effectiveChannelIds\|canAccessChannel" app/Http/Controllers/Client/
```

If any `modals.open('foo')` call has no matching `<x-foo-modal />`, the change is incomplete. If any client controller method that touches a channel lacks `canAccessChannel`, the change is insecure.

## Resources

### references/

- `file-pairs.md` — Map of admin/client file pairs and routes. Use as the starting point when mapping affected files for any change.
- `data-scoping.md` — Patterns and rules for restricting client data access via `effectiveChannelIds()` and `canAccessChannel()`.
- `modal-checklist.md` — Current list of modals per view. Use when adding a new modal-trigger button to ensure the modal is mounted everywhere it should be.

### scripts/

(none required — this skill is procedural knowledge, not automation)

### assets/

(none required)