## 1. Backup

- [x] 1.1 Run `php artisan db:backup` before any view change (no DB writes planned, but AGENTS.md mandates it before any operation).

## 2. Live-viewer modal component

- [x] 2.1 Create `resources/views/components/live-viewer-modal.blade.php` with:
  - Modal shell reusing `<x-app-modal name="live-viewer" maxWidth="3xl">`.
  - Alpine `x-data` with state: `channelId`, `channelName`, `slug`, `hlsUrl`, `status`, `loading`, `error`, `playing`.
  - `$watch('$store.modals.current')`: when `=== 'live-viewer'`, fetch `/api/virtual-screens/{id}` and `/api/channels/{id}/emission/status` in parallel, resolve URL, attempt to load `https://cdn.jsdelivr.net/npm/hls.js@1.5.13` (inject `<script>` once, cache in `window.__hlsLoading`), then attach to `<video>`.
  - On `hls.js` load failure → fallback to native `<video src={hls_url} controls>`.
  - Public URL field with copy-to-clipboard button.
  - "Abrir en nueva pestaña" button (`target="_blank"`).
  - "Configurar pantalla virtual" CTA when `output_protocol !== 'hls'` or `output_url` empty.
  - Cleanup on close: `video.pause()`, `hls.destroy()` if instance, `removeAttribute('src')`.

## 3. Admin channels list — icon actions

- [x] 3.1 Edit `resources/views/admin/channels/index.blade.php` lines 71-99: replace the 3 text buttons (Editar / Pantalla / Archivar) with circular icon buttons matching the calendar cell pattern (`w-9 h-9 rounded-lg bg-gradient-to-br ... hover:scale-110`).
- [x] 3.2 Add a 4th icon button "Ver vivo" (`sky-400→sky-600` gradient, eye icon) between Pantalla and Archivar. Click handler: `$store.modals.open('live-viewer', { channel_id, channel_name, slug })`.
- [x] 3.3 Hide Archivar icon when `status === 'archived'`; show a small "Archivado" muted badge instead (current behavior preserves compatibility).
- [x] 3.4 Include `<x-live-viewer-modal />` once at the bottom of the view.

## 4. Admin scheduler — Ver vivo button

- [x] 4.1 Edit `resources/views/admin/scheduler/index.blade.php` lines 90-113 (emission control block): add a 5th button "Ver vivo" after "Log de emisión" in the same flex row.
- [x] 4.2 Bind disabled state to `status === 'offline'` or `'error'`; enabled when `'live'` or `'starting'`. Use the same `:disabled` pattern as Detener.
- [x] 4.3 Click handler: `$store.modals.open('live-viewer', { channel_id: '{{ $currentChannel }}', channel_name: '{{ addslashes($ch->display_name) }}', slug: '{{ $ch->slug }}' })` (resolve `$ch` via the channel select, or pass `$currentChannelSlug` from controller if already exposed; check existing controller view-data first).
- [x] 4.4 Verify the Alpine `emissionControl` component already exposes `status` reactively; if not, wire the disabled binding via `x-effect` or `$watch`.

## 5. Admin smoke test

- [x] 5.1 In browser: open `/admin/channels`, click "Ver vivo" on an active channel → modal opens, HLS plays, URL panel shows.
- [x] 5.2 In browser: open `/admin/channels`, click "Ver vivo" on a channel without HLS configured → empty state with "Configurar pantalla virtual" CTA appears and opens the editor.
- [x] 5.3 In browser: open `/admin/scheduler`, with status `offline` verify the "Ver vivo" button is disabled; click "Iniciar transmisión", wait for `live`, verify the button enables; click it → modal opens.
- [x] 5.4 Verify "Abrir en nueva pestaña" opens the `.m3u8` URL in a new tab.
- [x] 5.5 Verify modal closes cleanly: video stops, no console errors about HLS instance leak.

## 6. Admin validation

- [x] 6.1 Run `openspec validate admin-live-viewer-modal --strict` and fix any reported issues.
- [x] 6.2 Run `php artisan check:destructive-migrations` (no migrations added, should still pass).

## 7. Client channels — Ver vivo icon

- [x] 7.1 Edit `resources/views/client/channels/index.blade.php` lines 108-128: alongside the existing "Editar" and "Pantalla" buttons (only visible for owner), add a 3rd circular icon button "Ver vivo" (`sky-400→sky-600` gradient) that opens the live-viewer modal with `{ channel_id, channel_name, slug }`.
- [x] 7.2 Show the "Ver vivo" icon for ALL channels the client has access to (owner AND assigned), not only owner — viewing is a read-only operation. Restructure the actions cell so it renders consistently: owner-only Editar/Pantalla on the left, always-on Ver vivo on the right.
- [x] 7.3 Include `<x-live-viewer-modal />` once at the bottom of the view (before `</x-client-layout>`).

## 8. Client scheduler — Ver vivo button

- [x] 8.1 Edit `app/Http/Controllers/Client/ScheduleController.php`: expose `currentChannelSlug` and `currentChannelName` in the view-data, same pattern as admin.
- [x] 8.2 Edit `resources/views/client/scheduler/index.blade.php` lines 132-138 (after "Log de emisión"): add the same "Ver vivo" button (sky-styled), bound to the existing `emissionControl` Alpine component's `status`. Disable when `'offline'`, enable when `'live'` or `'starting'`.
- [x] 8.3 Include `<x-live-viewer-modal />` once at the bottom of the view.

## 9. Client smoke test

- [x] 9.1 In browser: open `/client/channels`, verify the "Ver vivo" icon appears for every channel and opens the live-viewer modal.
- [x] 9.2 In browser: open `/client/scheduler`, with status `offline` verify the "Ver vivo" button is disabled; click "Iniciar transmisión", wait for `live`, verify the button enables; click it → modal opens.
- [x] 9.3 Verify "Abrir en nueva pestaña" opens the `.m3u8` URL in a new tab from the client view.

## 10. Validation (extended)

- [x] 10.1 Re-run `openspec validate admin-live-viewer-modal --strict`.
- [x] 10.2 Re-run `php artisan view:cache` and `view:clear`.
- [x] 10.3 `php -l` on all newly modified files (`client/channels/index.blade.php`, `client/scheduler/index.blade.php`, `app/Http/Controllers/Client/ScheduleController.php`).

## 11. Backend — public_hls_url field on Channel

- [x] 11.1 Create migration `add_public_hls_url_to_channels` (additive, no WithDataSafetySnapshot): `$table->string('public_hls_url', 500)->nullable()->after('slug')`. Run `php artisan migrate:safe`.
- [x] 11.2 Update `app/Models/Channel.php` — add `'public_hls_url'` to `$fillable`.
- [x] 11.3 Update `app/Http/Requests/Admin/StoreChannelRequest.php` and `UpdateChannelRequest.php` — add `'public_hls_url' => ['nullable', 'string', 'max:500', 'url']`.
- [x] 11.4 Update `app/Http/Controllers/Admin/DashboardController.php` `channels()` method — include `'public_hls_url'` in the paginate select.
- [x] 11.5 Update `app/Http/Controllers/Api/Client/ChannelController.php` — add `'public_hls_url'` to the select.
- [x] 11.6 Update `app/Http/Controllers/Api/VirtualScreenController.php` — modify `enrichScreen()` to denormalize `public_hls_url` from the channel.

## 12. Admin form — public_hls_url field

- [x] 12.1 Edit `resources/views/admin/channels/partials/form.blade.php` — add a new full-width field "URL pública HLS (player)" bound to `name="public_hls_url"` with placeholder `https://canal.tudominio.com/live/{slug}.m3u8` and helper text explaining it's the URL served by nginx-rtmp that viewers consume. Include red error message span bound to `$store.modals.errors.public_hls_url`.

## 13. Live-viewer modal — prefer channel.public_hls_url

- [x] 13.1 Edit `resources/views/components/live-viewer-modal.blade.php` — in the `load()` method, after fetching `/api/virtual-screens/{id}`, read the denormalized `public_hls_url` (e.g., `vsData.channel?.public_hls_url`). If present and non-empty, set `this.hlsUrl` and `this.hasHls = true` immediately. Only fall back to the existing logic (`output_protocol === 'hls' && output_url`) if the denormalized field is empty.
- [x] 13.2 Update the empty-state CTA: if `public_hls_url` is empty (regardless of `output_url`), the "Configurar" button should open the **channel edit modal** (`edit-channel`) instead of `virtual-screen-editor`. Update the message text accordingly.

## 14. Validation (extended #2)

- [x] 14.1 `php -l` on all modified PHP files.
- [x] 14.2 `php artisan view:cache && view:clear`.
- [x] 14.3 `openspec validate admin-live-viewer-modal --strict`.
- [x] 14.4 Manual smoke: open `/admin/channels` → Editar Cine Dios → set `https://canal.mediaserver.com.co/live/cinedios.m3u8` → save → click "Ver vivo" → modal plays the HLS URL.

## 15. Client inline form — fix Edit button + add public_hls_url

- [x] 15.1 The original client view called `$store.modals.open('edit-channel', ...)` but the modal is only mounted in admin. This was a pre-existing bug that my icon refactor exposed. Fix: change the Edit button click handler to call the existing inline `openEditChannel(id, ch)` function (which was dead code before — the inline form is at lines 147-186 of `client/channels/index.blade.php`).
- [x] 15.2 Add `public_hls_url` field to the inline form (between "Ruta" and the action buttons), bound to `x-model="editHlsUrl"`. Include error span bound to `editErrors.public_hls_url`.
- [x] 15.3 Update `x-data` to include `editHlsUrl` state, populate from `ch.public_hls_url`, and submit it in `saveEditChannel()` body.
- [x] 15.4 Update `$chJson` to include `'public_hls_url'` in the `only()` select.
- [x] 15.5 Update `app/Http/Controllers/Client/ChannelController.php` `update()` validation to accept `'public_hls_url' => ['nullable', 'string', 'max:500', 'url']`.
- [x] 15.6 Update `live-viewer-modal.blade.php` empty-state CTA: remove `$store.modals.open('edit-channel', ...)` (broken on client). Replace with a copyable "Plantilla sugerida" URL input (`{tu-dominio}/live/{slug}.m3u8`) and clear instructions telling the user to edit the channel.

## 16. Debug redirect-stripping-UUID bug

- [x] 16.1 Server returns 405 with `r.url=/client/channels` (no UUID) despite fetch being called with `/client/channels/{id}`. Fix: replaced the inline form with a real `<form method="POST" :action="'/client/channels/' + editId">` + hidden `_token` + `_method=PUT`. The fetch POSTs to `form.action` (bound at form creation from editId) so the URL is the action attribute, not a JS-computed string. CSRF token comes from body `_token` field; `_method=PUT` lets Laravel's `HandleMethodOverrides` middleware convert to PUT before routing.

## 17. Player rewrite — use Plyr.io (the proven recipe from mediaserver.com.co/canal-cine-dios)

- [x] 17.1 Replace custom HLS.js config + watchdog with the exact recipe that works on `https://mediaserver.com.co/canal-cine-dios/`:
  - Load Plyr CSS (`https://cdn.plyr.io/3.6.8/plyr.css`)
  - Load Plyr polyfilled JS (`https://cdn.plyr.io/3.6.8/plyr.polyfilled.js`)
  - Load HLS.js `@latest` from jsdelivr
  - Use `<video id="liveViewerVideo" playsinline></video>` (no `controls`, no `autoplay`, no `muted` — Plyr handles UI)
  - Initialize Plyr first, attach HLS.js to `player.media` (not raw video) inside `player.on('ready')`
  - Add a "Live" custom button that sets `player.currentTime = player.duration` on click
  - HLS.js config: NO custom config, just `new Hls()` with defaults (the original site proves defaults work for nginx-rtmp streams)
- [x] 17.2 Verify in browser: open Ver vivo on Cine Dios → video plays fluid, no frame freezes, "Live" button appears in Plyr controls.