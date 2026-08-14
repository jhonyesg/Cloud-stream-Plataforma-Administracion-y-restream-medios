## MODIFIED Requirements

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

### Requirement: Playlist editor opens as a fullscreen modal
The system SHALL provide a fullscreen modal for editing playlists. When opening the modal, the client-side initialization code SHALL compute `duration_sec` and `_scheduleStart` using the same algorithm as the admin initialization code.

#### Scenario: Open fullscreen editor in client view
- **WHEN** a client user opens the playlist editor
- **THEN** the editor SHALL load items with effective durations and cumulative start positions identical to those shown to an admin user for the same playlist

#### Scenario: Open fullscreen editor after cue insertion
- **GIVEN** a playlist where a cue has been inserted, creating a split content item
- **WHEN** the editor opens
- **THEN** the split items SHALL display their correct effective durations and positions
- **AND** the timeline SHALL render without gaps or overlaps caused by miscalculated durations
