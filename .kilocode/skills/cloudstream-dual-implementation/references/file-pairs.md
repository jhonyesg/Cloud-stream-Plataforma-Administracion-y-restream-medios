# Admin / Client File Pairs

Every feature in Cloudstream has a parallel admin and client implementation. When a change touches a feature, the **entire pair** must change.

## View Pairs

| Domain | Admin view | Client view | Notes |
|---|---|---|---|
| Dashboard | `resources/views/admin/dashboard.blade.php` | `resources/views/client/dashboard.blade.php` | Different content; admin shows system stats, client shows user's channels. |
| Channels | `resources/views/admin/channels/index.blade.php` | `resources/views/client/channels/index.blade.php` | Most-touched pair. Both have view-mode toggle (cards/table), action buttons (Editar, Pantalla, Ver vivo). Admin also has "Nuevo canal" + "Archivar". |
| Media | `resources/views/admin/media/index.blade.php` | `resources/views/client/media/index.blade.php` | Admin sees all media; client sees only media from their assigned channels. |
| Scheduler | `resources/views/admin/scheduler/index.blade.php` | `resources/views/client/scheduler/index.blade.php` | Both show timeline grid + emission control. Client has no emission start/stop (per `AGENTS.md`: "Emission Module — Status: DISMOUNTED" — no client start/stop buttons). |
| Users | `resources/views/admin/users/index.blade.php` | **(does not exist)** | Admin-only. No pair needed. |
| Channel modal `x-data` | inline in `admin/channels/index.blade.php` | inline in `client/channels/index.blade.php` | Both define `editOpen`, `editId`, etc. Keep them in sync. |

## Controller Pairs

| Domain | Admin | Client |
|---|---|---|
| Dashboard | `app/Http/Controllers/Admin/DashboardController.php` | `app/Http/Controllers/Client/DashboardController.php` |
| Channels | `app/Http/Controllers/Admin/DashboardController.php::channels()` | `app/Http/Controllers/Client/ChannelController.php` |
| Media | `app/Http/Controllers/Admin/MediaController.php` | `app/Http/Controllers/Client/MediaController.php` |
| Scheduler | `app/Http/Controllers/Admin/ScheduleController.php` | `app/Http/Controllers/Client/ScheduleController.php` |
| Users | `app/Http/Controllers/Admin/DashboardController.php::users*()` | — |

## Route Pairs

In `routes/web.php`:

- `Route::middleware(['auth', 'role:admin'])->prefix('admin')` — admin group
- `Route::middleware(['auth', 'role:client'])->prefix('client')` — client group

When adding a new route, add to the appropriate group. If the feature exists in both, add to both. Note that route **names** must be unique even across groups (e.g., `client.channels.update` vs `admin.channels.update`).

## Layout Pair

| Admin | Client |
|---|---|
| `resources/views/components/admin-layout.blade.php` | `resources/views/components/client-layout.blade.php` |

Both wrap with `<x-{role}-layout active="...">`. Both include shared JS (Alpine, Plyr, HLS.js) in the `<head>`. When adding a global asset (CSS, JS library, meta tag), add to both.

## Modal Mounting (cross-reference)

See `modal-checklist.md` for the current per-view list of mounted modals.

## How to Use This Map

When you receive a change request, run through this checklist:

1. **Which domain?** (channels, media, scheduler, etc.) → table gives you the file pair.
2. **Is the change visual-only?** → edit both views with the same pattern.
3. **Is there a controller change?** → edit both controllers, but only the client needs data-scoping.
4. **Are new modals involved?** → check `modal-checklist.md` and mount everywhere needed.
5. **Are new routes?** → add to both route groups (or just one if admin/client only).