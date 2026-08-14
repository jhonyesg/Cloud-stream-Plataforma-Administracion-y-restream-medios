## ADDED Requirements

### Requirement: Visible "Nueva playlist" button on the scheduler
The scheduler SHALL expose a "Nueva playlist" button in the top toolbar, always visible regardless of the selected day, alongside the "Nueva plantilla" button. Clicking the button SHALL open a dedicated modal for creating a playlist.

#### Scenario: Button is always present
- **WHEN** a user navigates to the scheduler view with a channel and template loaded
- **THEN** the top toolbar SHALL display both "Nueva plantilla" and "Nueva playlist" buttons

#### Scenario: Opening the modal
- **WHEN** a user clicks "Nueva playlist"
- **THEN** a modal SHALL open with fields: name (required text input), channel (selectable, preselected to current channel), and an "Assign to selected day" checkbox (visible only when a day and template are present)

#### Scenario: Create playlist without day assignment
- **WHEN** the user fills a name and clicks "Crear y editar"
- **THEN** the system SHALL call `POST /api/playlists` with `{ channel_id, name }`, open the playlist editor fullscreen modal for the new playlist, and keep the scheduler view in the background

#### Scenario: Create playlist with day assignment
- **WHEN** the user fills a name, checks "Assign to day N", and clicks "Crear y asignar"
- **THEN** the system SHALL call `POST /api/playlists`, then `PUT /api/schedule-templates/{tid}/days/{N}` with the new `playlist_id`, and reload the scheduler so the calendar reflects the new assignment

#### Scenario: Empty name is rejected client-side
- **WHEN** the user submits the modal with an empty name
- **THEN** the system SHALL display a validation error and SHALL NOT call the API

#### Scenario: API error shows feedback
- **WHEN** `POST /api/playlists` returns non-2xx
- **THEN** the modal SHALL display the API error message and SHALL NOT close

### Requirement: Legacy inline playlist creation removed from the side panel
The scheduler side panel's day-assignment `<select>` SHALL NOT contain a "+ Crear nueva playlist..." option. The legacy `prompt()`-based creation flow SHALL be removed from `assignDay()`.

#### Scenario: Select only shows existing playlists
- **WHEN** a user opens the day-assignment dropdown in the side panel for an empty day
- **THEN** the options SHALL be: "-- Sin programar --", the existing playlists for the channel, and nothing else
