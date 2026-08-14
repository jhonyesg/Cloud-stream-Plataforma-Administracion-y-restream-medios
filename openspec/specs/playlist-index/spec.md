## ADDED Requirements

### Requirement: Side panel shows a playlist index for the current channel
The scheduler SHALL render, in the right side panel, a list of every `playlist` belonging to the currently selected channel. Each playlist SHALL be displayed as a card showing: the playlist's color (matching its calendar legend swatch), its name, its item count, its total duration formatted as `Xmin Ys` (or `Xs` if under 60s), and the number of days in the current month's template where it is assigned (zero or more). Each card SHALL have an "Edit" button (pencil icon) that opens the existing playlist editor fullscreen modal preloaded with that playlist.

#### Scenario: Channel with playlists
- **WHEN** the scheduler loads with channel C and channel C has 3 playlists (A, B, D) and A is assigned to 2 days in the current month
- **THEN** the side panel SHALL display 3 cards, one per playlist, each showing name, item count, total duration, and days-used count, and A SHALL show "2 días"

#### Scenario: Channel with no playlists
- **WHEN** the scheduler loads with channel C and C has zero playlists
- **THEN** the side panel SHALL display an empty state message prompting the user to create a playlist with the top-bar "Nueva playlist" button

#### Scenario: Click edit opens the fullscreen editor with the playlist loaded
- **WHEN** the user clicks the edit (pencil) button on a playlist card
- **THEN** the system SHALL fetch `GET /api/playlists/{id}`, populate `Alpine.store('playlistEditor')` with the playlist's id, name, items, and the channel's media library, and open the playlist editor fullscreen modal
- **WHEN** the fetch fails
- **THEN** an alert SHALL be shown and the modal SHALL NOT open

### Requirement: Day-specific side panel features removed
The scheduler side panel SHALL NOT show: the "Día N de Mes" header, the playlist-rename input, the day-assignment `<select>`, the items list with thumbnails/move/remove controls, the duration/loop-count footer, or the "Editar playlist" / "Replicar a otras fechas" buttons. The fullscreen editor remains the single entry point for editing playlist items and renaming.

#### Scenario: Side panel no longer renders items list or rename input
- **WHEN** a user selects any day in the calendar
- **THEN** the side panel SHALL still render the playlist index unchanged (day selection SHALL NOT change the panel's content)

### Requirement: Playlist card exposes a Delete action
The "Mis Playlists" side panel in the scheduler SHALL render a Delete button on every playlist card, positioned directly below the existing Edit button. The Delete button SHALL be styled as a destructive action (red icon, trash glyph) and SHALL trigger a confirmation dialog before invoking the API. The Edit button, the card's metadata (color, name, item count, duration, days-used count), and the empty-state copy SHALL remain unchanged.

#### Scenario: Card with playlists shows Edit and Delete
- **WHEN** the scheduler side panel renders a list of playlists for the current channel
- **THEN** each card SHALL show the existing Edit (pencil) button at its current position AND a Delete (trash) button directly below it, both visible without hover

#### Scenario: Empty channel shows no delete buttons
- **WHEN** the current channel has zero playlists
- **THEN** the side panel SHALL render only the existing empty-state message and SHALL NOT render any Delete button

### Requirement: Confirm-before-delete uses the existing dialog helper
Clicking the Delete button SHALL open a confirmation dialog built on the existing `window.schedulerDialogStore().askConfirm(title, body, onConfirm)` helper used elsewhere on the page. The dialog body SHALL warn the user that any days assigned to the playlist will lose their assignment. The Delete API call SHALL be issued only when the user confirms; if the user cancels, no network request SHALL be made and the card SHALL remain unchanged.

#### Scenario: User cancels the confirmation
- **WHEN** the user clicks the Delete button and then clicks "Cancelar" in the confirmation dialog
- **THEN** the dialog SHALL close, no request SHALL be sent to the API, and the playlist card SHALL remain visible with both buttons intact

#### Scenario: User confirms the deletion
- **WHEN** the user clicks the Delete button and confirms in the dialog
- **THEN** the system SHALL send `DELETE /api/playlists/{id}` with the page's CSRF token and `Accept: application/json`, and SHALL surface a success toast before reloading the page

### Requirement: Server-side guard surfaced to the user
If the API responds with a non-2xx status, the system SHALL display the server-provided `message` field as an error toast and SHALL NOT reload the page. In particular, the existing `is_default && live emission` guard (HTTP 422) MUST be shown to the user verbatim.

#### Scenario: Default playlist with live emission cannot be deleted
- **WHEN** the user confirms deletion of a playlist whose `is_default` is true while the channel has an active emission
- **THEN** the API SHALL respond with HTTP 422, the system SHALL display the server-provided Spanish error message in an error toast, and the page SHALL NOT reload

#### Scenario: Network error
- **WHEN** the `DELETE /api/playlists/{id}` request fails to complete (network error)
- **THEN** the system SHALL display an "Error de red" error toast and the page SHALL NOT reload
