# playlist-editor Specification

## Purpose
TBD - created by archiving change playlist-editor-bulk-add-and-cumulative-timeline. Update Purpose after archive.
## Requirements
### Requirement: Library items show visual indicator when already in playlist
The library panel SHALL display a `✓` badge on items that are already in the playlist. Already-added items SHALL appear with reduced opacity to distinguish them from non-added items.

#### Scenario: Library item already in playlist shows badge
- **WHEN** a library item's `media_item_id` matches any item in the current playlist
- **THEN** the library card SHALL display a green `✓` badge in the corner AND reduce opacity to 50%

#### Scenario: Library item not in playlist shows no badge
- **WHEN** a library item is not in the current playlist
- **THEN** the library card SHALL display normally with full opacity and no badge

### Requirement: Click on library item toggles inclusion in playlist
Clicking a library item SHALL add it to the playlist if not present, or remove it from the playlist if present.

#### Scenario: Click on non-added item adds to playlist
- **WHEN** a user clicks a library item that is NOT in the playlist
- **THEN** the item SHALL be appended to the playlist (via `POST /api/playlists/{id}/items`) and the badge SHALL appear

#### Scenario: Click on already-added item removes from playlist
- **WHEN** a user clicks a library item that IS in the playlist (has badge)
- **THEN** the item SHALL be removed from the playlist (via `DELETE /api/playlists/{id}/items/{itemId}`) and the badge SHALL disappear

### Requirement: Timeline displays cumulative time on each block
Each item block in the timeline SHALL display its cumulative start time (HH:MM:SS format) above it. The first block starts at `00:00:00`, and subsequent blocks accumulate from there. The cumulative start SHALL be computed from the **effective duration** of each item (respecting `cue_in_sec`, `cue_out_sec`, and `start_sec` overrides), not from the raw media file duration. The calculation SHALL be identical regardless of whether the user is an administrator or a client user.

#### Scenario: First block starts at 00:00:00
- **WHEN** the playlist has at least one item
- **THEN** the first block SHALL have a time label "00:00:00" above it

#### Scenario: Subsequent blocks accumulate using effective duration
- **WHEN** a playlist has items with effective durations 90min, 45sec, 120min
- **THEN** the labels SHALL be "00:00:00", "01:30:00", "01:30:45", "03:30:45"

#### Scenario: Split item shows correct effective duration
- **GIVEN** a video of 10min that has been split at 3:17 by a cue insertion
- **WHEN** the playlist editor opens in the client view
- **THEN** the head item SHALL display duration 3:17 and the tail item SHALL display duration 6:43
- **AND** both SHALL use those effective durations for cumulative timeline calculation

#### Scenario: Loop wrap shows next iteration
- **WHEN** the playlist fits 3 times in 24h
- **THEN** the timeline SHALL show the second iteration with labels continuing from the accumulated effective duration

#### Scenario: Client and admin compute identical cumulative starts
- **GIVEN** the same playlist with splits and explicit start_sec values
- **WHEN** the editor opens in the admin view
- **AND** the editor opens in the client view
- **THEN** the `_scheduleStart` values for every item SHALL be identical in both views

### Requirement: Timeline highlights currently-playing item
When the playhead is at a position within an item, that item SHALL have a visual highlight (border, glow, or pulse) to indicate it's currently playing.

#### Scenario: Highlight follows playhead
- **WHEN** the playhead is moving and crosses into a new item's range
- **THEN** the previous item's highlight SHALL disappear and the new item's highlight SHALL appear

#### Scenario: Floating label shows currently-playing item
- **WHEN** the playhead is within an item
- **THEN** a floating label "▶ [filename]" SHALL appear above that item's block

### Requirement: Playlist editor opens as a fullscreen modal
The system SHALL provide a fullscreen modal (`maxWidth="full"` o 95vw) for editing playlists, replacing the smaller side panel. The modal SHALL have a header with the playlist name (editable) and stats (total duration, loops/day), a two-column body (playlist items left, library right), and a timeline at the bottom. When opening the modal, the client-side initialization code SHALL compute `duration_sec` and `_scheduleStart` using the same algorithm as the admin initialization code.

#### Scenario: Open fullscreen editor
- **WHEN** a user clicks "Editar playlist completa" on the scheduler side panel
- **THEN** a fullscreen modal SHALL open with the playlist loaded, showing items on the left and library on the right

#### Scenario: Open fullscreen editor in client view
- **WHEN** a client user opens the playlist editor
- **THEN** the editor SHALL load items with effective durations and cumulative start positions identical to those shown to an admin user for the same playlist

#### Scenario: Open fullscreen editor after cue insertion
- **GIVEN** a playlist where a cue has been inserted, creating a split content item
- **WHEN** the editor opens
- **THEN** the split items SHALL display their correct effective durations and positions
- **AND** the timeline SHALL render without gaps or overlaps caused by miscalculated durations

#### Scenario: Modal shows playlist name and stats
- **WHEN** the editor opens
- **THEN** the header SHALL show the playlist name (editable input) and live stats: total duration (e.g. "210 min") and loop count (e.g. "~6.8 loops/día")

### Requirement: Left column shows playlist items with drag-and-drop reordering
The left column SHALL display each playlist item with: thumbnail, filename, duration, color (blue=video, orange=cuña), and reorder controls. Items SHALL be reorderable by drag-and-drop, or by up/down buttons as fallback.

#### Scenario: Drag item to new position
- **WHEN** a user drags item from position 3 to position 1
- **THEN** the items SHALL be reordered and positions renumbered sequentially

#### Scenario: Reorder by buttons
- **WHEN** a user clicks the down arrow on item at position 1
- **THEN** the item SHALL swap with position 2

#### Scenario: Remove item from playlist
- **WHEN** a user clicks the ✕ button on an item
- **THEN** the item SHALL be removed and remaining items renumbered

### Requirement: Right column shows library with thumbnails for adding
The right column SHALL display all media items of the channel as a grid of thumbnails. Videos SHALL have a blue border, cuñas an orange/red border. The grid SHALL support search by filename and filter by kind. Clicking an item SHALL add it to the end of the playlist.

#### Scenario: Library shows all channel media
- **WHEN** the editor opens
- **THEN** the right column SHALL show a grid with thumbnails of all media items of the channel (videos and cuñas)

#### Scenario: Search filters library
- **WHEN** a user types "Dios" in the search box
- **THEN** only items with "Dios" in the filename SHALL be shown

#### Scenario: Filter by kind
- **WHEN** a user selects "Solo cuñas" filter
- **THEN** only items with kind=ad SHALL be shown

#### Scenario: Add item to playlist
- **WHEN** a user clicks a thumbnail in the library
- **THEN** the item SHALL be appended to the playlist and appear at the bottom of the left column

### Requirement: Drag-and-drop from library to playlist
A user SHALL be able to drag an item from the right column (library) directly into a position in the left column (playlist). Dropping shall insert the item at the target position and call the API.

#### Scenario: Drag from library to playlist middle
- **WHEN** a user drags a library item and drops it between item 2 and item 3 in the playlist
- **THEN** the dragged item SHALL be inserted at position 3, existing items at >= 3 SHALL shift to >= 4, and the playlist SHALL be persisted

### Requirement: Animated timeline visualizes how the playlist runs
Below the two columns, the editor SHALL display an animated timeline showing how the playlist runs from 00:00 to 24:00. Each item SHALL appear as a block scaled by its real duration. Videos in blue, cuñas in orange/red. A vertical playhead SHALL move across the timeline when playing, looping back to start when reaching the end of the playlist.

#### Scenario: Timeline shows blocks proportional to duration
- **WHEN** the editor has 3 items: Video A (90 min), Cuña (45 sec), Video B (120 min)
- **THEN** the timeline SHALL show 3 blocks with widths proportional to their durations (Video A occupies ~6.25% of 24h, Cuña barely visible, Video B ~8.3%)

#### Scenario: Playhead moves at selected speed
- **WHEN** a user clicks ▶ Play
- **THEN** a vertical playhead SHALL start moving from 00:00 toward 24:00 at the selected speed (1x = real-time scaled to fit, 2x = double, etc.)

#### Scenario: Loop visualization
- **WHEN** the playhead reaches the end of the last item
- **THEN** the playhead SHALL wrap back to 00:00 and continue moving, visually showing the playlist looping

#### Scenario: Speed control
- **WHEN** a user selects speed 4x
- **THEN** the playhead SHALL move 4x faster than 1x

#### Scenario: Pause and resume
- **WHEN** a user clicks ⏸ Pause
- **THEN** the playhead SHALL stop at its current position
- **WHEN** the user clicks ▶ Play again
- **THEN** the playhead SHALL resume from the same position

#### Scenario: Real-time clock display
- **WHEN** the playhead is at a certain position
- **THEN** the editor SHALL display the corresponding "wall clock" time (e.g. "01:30") near the playhead

### Requirement: Timeline updates live when playlist changes
When items are added, removed, or reordered, the timeline SHALL immediately recalculate the block positions and playhead path.

#### Scenario: Add item updates timeline
- **WHEN** a user adds a new item to the playlist
- **THEN** the timeline SHALL immediately show the new block and recalculate all subsequent block positions

#### Scenario: Reorder updates timeline
- **WHEN** a user drags an item to a new position
- **THEN** the timeline SHALL immediately update all block positions to reflect the new order

### Requirement: Modal closes on save with persisted changes
The editor SHALL have a "Guardar" button that closes the modal and persists all changes. Closing without saving SHALL discard pending changes and reload the page.

#### Scenario: Save and close
- **WHEN** a user clicks "Guardar" after making changes
- **THEN** all reorders/adds/removes SHALL be persisted to the API and the modal SHALL close

#### Scenario: Close without save
- **WHEN** a user clicks "Cancelar" or the X button
- **THEN** any unsaved reorder/add/remove SHALL be discarded (since each operation already calls the API immediately, "discard" means reverting by reloading the playlist from server)

