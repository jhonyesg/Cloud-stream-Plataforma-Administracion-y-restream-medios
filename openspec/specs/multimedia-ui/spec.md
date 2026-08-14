## Purpose
The multimedia library surface for `/admin/media` and `/client/media`, plus the shared components (filter bar, uploader, player, rename modal, media card) used to interact with `MediaItem` records.
## Requirements
### Requirement: Admin media library view
The system SHALL render `/admin/media` as a paginated table of all `MediaItem` records across the system, with the following features: filter by channel (dropdown), filter by kind, filter by status, filter by uploader/owner, search by filename (LIKE), and per-row actions (Play / Rename / Delete). Each row SHALL show thumbnail (or placeholder), filename, size, kind, status badge, owner channel, uploader, and a kebab menu for actions.

#### Scenario: Admin loads the media library
- **WHEN** an admin navigates to `/admin/media`
- **THEN** the rendered HTML SHALL contain a `<table>` with paginated MediaItem rows (default 25 per page), filter controls, and a `[+ Subir archivos]` button at the top

#### Scenario: Admin filters by channel
- **WHEN** an admin selects a channel from the filter dropdown
- **THEN** the table SHALL reload showing only MediaItems belonging to that channel (filter via `channel_id` query param)

#### Scenario: Admin filters by status pending
- **WHEN** an admin selects status = `pending` from the filter
- **THEN** the table SHALL show only items in `pending` status (typically newly uploaded files awaiting probing)

#### Scenario: Admin searches by filename
- **WHEN** an admin types `cine` in the search input
- **THEN** the table SHALL show only items whose filename LIKE `%cine%`

### Requirement: Admin media upload modal
The admin media library SHALL include an "Upload" button that opens a modal with drag-drop zone and multi-file picker (`<input type="file" multiple>`). Selecting one or more files SHALL trigger a `POST /api/media-items/bulk-upload` request with the selected channel as the destination.

#### Scenario: Admin uploads multiple files to channel C
- **WHEN** an admin opens the upload modal, drags 3 video files into the drop zone, and confirms
- **THEN** the page SHALL show progress indicators per file and then a success toast `3 archivos subidos correctamente`. The table SHALL refresh showing the 3 new items.

#### Scenario: Admin uploads with one file failing
- **WHEN** an admin uploads 3 files where one exceeds 500MB
- **THEN** the response SHALL show 2 successful uploads and 1 failure row with the error reason `Archivo demasiado grande`. The table SHALL refresh with the 2 successful items.

### Requirement: Admin rename modal
The admin media library SHALL include a per-row "Rename" action that opens a modal with the current filename (preserving the extension). Submitting SHALL send `PUT /api/media-items/{id}` with the new filename. The server SHALL rename the file on disk and update the MediaItem row.

#### Scenario: Admin renames a file
- **WHEN** an admin opens the rename modal for file `video_v1.mp4`, enters `video_v2.mp4`, and submits
- **THEN** the server SHALL rename the file on disk to `video_v2.mp4`, update `MediaItem.filename`, and the modal SHALL close with a success toast `Archivo renombrado correctamente`. The table SHALL reflect the new filename.

### Requirement: Admin delete confirmation
The admin media library SHALL include a per-row "Delete" action that opens the global `<x-confirm-delete-modal>` with a message naming the file. Submitting SHALL send `DELETE /api/media-items/{id}` and refresh the table.

#### Scenario: Admin deletes a file
- **WHEN** an admin clicks Delete on file `video_v1.mp4` and confirms
- **THEN** the file SHALL be removed from disk, the MediaItem row SHALL be soft-deleted, and a success toast SHALL show. The table SHALL refresh.

### Requirement: Admin preview player modal
The admin media library SHALL include a per-row "Play" action that opens a modal containing a `<video>` (or `<audio>`) element whose `src` is the `play_url` from the API. The `<video>` SHALL have `controls` enabled.

#### Scenario: Admin plays a video
- **WHEN** an admin clicks Play on a video media item
- **THEN** a modal SHALL appear with the video element loaded from `play_url`. The browser SHALL start streaming and the user SHALL be able to seek via the native controls.

### Requirement: Client media library view
The system SHALL render `/client/media` as a paginated view of media accessible to the authenticated user (channels they own OR are assigned to). The view SHALL show the same columns as admin but with action buttons limited to: Play, Rename (own media only), Delete (own media only). The Upload button SHALL only be visible for channels the user owns.

#### Scenario: Client loads their media library
- **WHEN** a client user (owner of channels C1, C2) navigates to `/client/media`
- **THEN** the page SHALL show only MediaItems from C1 and C2 (per `effectiveChannelIds()` filter). The `[+ Subir archivos]` button SHALL be visible for C1 and C2 only.

#### Scenario: Client cannot see other users' media
- **WHEN** a client loads `/client/media`
- **THEN** MediaItems belonging to channels the user does NOT own (and is not assigned to) SHALL NOT appear in the list

#### Scenario: Client tries to delete another user's media
- **WHEN** a client user clicks Delete on a media item belonging to a channel they do NOT own
- **THEN** the server SHALL respond 403 (the button SHALL be hidden in the UI but the server enforces it)

### Requirement: Sidebar nav item "Multimedia"
The `admin-sidebar` component SHALL include a new item "Multimedia" (with a folder/music icon SVG) that links to `/admin/media`. For client users, the sidebar SHALL also include a "Multimedia" item linking to `/client/media`. The active state SHALL highlight when on the corresponding route.

#### Scenario: Admin sees Multimedia in sidebar
- **WHEN** an admin is on any admin page
- **THEN** the sidebar SHALL include a "Multimedia" link between "Providers" and the user section, with the active style applied when on `/admin/media`

#### Scenario: Client sees Multimedia in sidebar
- **WHEN** a client user is on any client page
- **THEN** the sidebar SHALL include a "Multimedia" link

### Requirement: MediaCard component
The system SHALL provide `<x-media-card>` Blade Component that renders a single media item as a card: thumbnail (lazy-loaded), filename, kind icon, status badge, and an actions kebab menu (Play / Rename / Delete). When the component receives a `selectable` prop of `true`, the card SHALL also render a circular checkbox in the top-right corner that toggles the item's presence in the shared `$store.mediaSelection` set. When the shared selection contains the item's id, the card SHALL apply a 2px amber ring.

#### Scenario: MediaCard renders a video item
- **WHEN** the component is rendered with a video MediaItem
- **THEN** the HTML SHALL contain a `<video>`-icon thumbnail placeholder, the filename, a "video" badge, and a kebab menu button

#### Scenario: MediaCard responds to bulk selection
- **WHEN** the component is rendered with `selectable="true"` and `$store.mediaSelection.has(item.id)` is `true`
- **THEN** the rendered card SHALL apply a 2px amber ring and the checkbox SHALL render as checked.

### Requirement: MediaUploader component
The system SHALL provide `<x-media-uploader>` Blade Component that renders a drag-drop zone + `<input type="file" multiple>`. The component SHALL accept props: `channel-id`, `endpoint` (default `/api/media-items/bulk-upload`), `max-size` (default 500MB), and `available-channels` (collection used to render the "canal destino" select when `channel-id` is empty). When `available-channels` is provided, the select SHALL render exactly those channels and SHALL NOT query `Channel` directly.

#### Scenario: Drag-drop accepts files
- **WHEN** a user drags 3 files into the drop zone and releases
- **THEN** the drop zone SHALL highlight, the files SHALL be queued, and uploading SHALL start automatically with progress per file

#### Scenario: Files beyond size limit rejected client-side
- **WHEN** a user selects files where one is 600MB
- **THEN** the uploader SHALL display a warning toast `archivo.mp4 excede 500MB` and NOT upload that file

#### Scenario: Channel select is scoped for clients
- **WHEN** a client opens the uploader and `available-channels` is provided
- **THEN** the "canal destino" select SHALL list only channels in the client's `effectiveChannelIds()`. Channels the user does not own or is not assigned to SHALL NOT appear in the dropdown.

### Requirement: MediaRenameModal component
The system SHALL provide `<x-media-rename-modal>` Blade Component that renders a modal with an input pre-filled with the current filename. Submitting SHALL call `PUT /api/media-items/{id}` and close the modal on success.

#### Scenario: Rename modal preserves extension
- **WHEN** the modal opens with filename `video.mp4` and the user types `nuevo_video`
- **THEN** the input SHALL be pre-filled with `video.mp4` and the new value submitted SHALL be `nuevo_video.mp4` (extension preserved by default; user can override)

### Requirement: MediaPlayerModal component
The system SHALL provide `<x-media-player-modal>` Blade Component that renders a modal with the appropriate HTML5 element based on media kind: `<video controls>` for video/image, `<audio controls>` for audio. The component SHALL stop playback (pause + rewind to `currentTime = 0`) and release the media element's `src` when the modal is closed or when a different item is loaded, so that no audio or video continues to play in the background after the user closes the modal.

#### Scenario: Player modal renders video
- **WHEN** the component receives a video media item with `play_url`
- **THEN** the modal SHALL render `<video src="{play_url}" controls>` with a max-height container

#### Scenario: Player modal renders audio
- **WHEN** the component receives an audio media item
- **THEN** the modal SHALL render `<audio src="{play_url}" controls>` centered in the modal

#### Scenario: Closing the modal stops playback
- **WHEN** the user closes the player modal while a video or audio file is playing
- **THEN** the `<video>` / `<audio>` element SHALL be paused, its `currentTime` SHALL be reset to `0`, and its `src` SHALL be cleared so the browser stops decoding the stream.

#### Scenario: Switching items resets the previous stream
- **WHEN** the user opens a different media item while the modal is still open with a previous item playing
- **THEN** the previous `<video>` / `<audio>` element SHALL be paused and detached before the new item's stream is attached.

### Requirement: MediaFilterBar component
The system SHALL provide `<x-media-filter-bar>` Blade Component with filter controls: channel select, kind select, status select, search input.

#### Scenario: Filter bar emits filter changes
- **WHEN** the user changes the channel select to a different channel
- **THEN** the component SHALL trigger a query-param update on the parent URL (e.g. `?channel_id=...`) and reload the data

### Requirement: Floating menu button hides while bulk-selection mode is active
The thumb-zone floating menu button SHALL be hidden whenever the bulk-selection store reports selection mode is enabled (`$store.mediaSelection.enabled === true`), so it does not collide with the bulk action bar that occupies the bottom of the viewport during selection. When selection mode is exited (`$store.mediaSelection.enabled === false`), the floating button SHALL reappear at `bottom-6 right-6`.

#### Scenario: FAB hides when the user clicks "Seleccionar"
- **WHEN** the user clicks the "Seleccionar" toggle on `/admin/media` or `/client/media`
- **THEN** the floating menu button SHALL disappear and the bulk action bar SHALL appear at the bottom

#### Scenario: FAB reappears when the user exits selection
- **WHEN** the user clicks "Salir" or "Limpiar" in the bulk action bar
- **THEN** the bulk action bar SHALL disappear and the floating menu button SHALL reappear

#### Scenario: FAB is visible by default on a fresh page load
- **WHEN** a user navigates to `/admin/media` or `/client/media` without clicking "Seleccionar"
- **THEN** the floating menu button SHALL be visible at `bottom-6 right-6`

### Requirement: Channels module exposes Pantalla Virtual action
The admin and client channel index pages SHALL render, in the action column of each row, a "Pantalla Virtual" button that opens `<x-virtual-screen-editor-modal>` preloaded with that channel's virtual-screen configuration.

#### Scenario: Admin channel row has the Pantalla Virtual button
- **WHEN** an admin views `/admin/channels`
- **THEN** each row's actions column SHALL include a button labelled "Pantalla Virtual" that, when clicked, opens the editor modal with that channel's id

#### Scenario: Client channel row has the Pantalla Virtual button
- **WHEN** a client views `/client/channels`
- **THEN** the rows for channels the client owns SHALL include the "Pantalla Virtual" button; rows for assigned-only channels SHALL NOT (clients cannot configure them)

