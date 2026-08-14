# client-channel-access Specification

## Purpose
TBD - created by archiving change client-multimedia-channel-scope. Update Purpose after archive.
## Requirements
### Requirement: Effective channel set is the single source of truth
The system SHALL define a user's "accessible channels" as the union of channels where the user is the `owner_id` OR is listed in the `channel_user` pivot. This set SHALL be exposed by `User::effectiveChannelIds()` and SHALL be the only check used by the multimedia module to decide which channels a non-admin user can see, browse, upload into, or stream from.

#### Scenario: Owner only
- **WHEN** a user owns channels `c1` and `c2` and is not assigned to any other channel
- **THEN** `effectiveChannelIds()` SHALL return exactly `[c1, c2]`

#### Scenario: Assigned only
- **WHEN** a user is assigned to channel `c3` via `channel_user` and owns no channels
- **THEN** `effectiveChannelIds()` SHALL return exactly `[c3]`

#### Scenario: Owner and assigned
- **WHEN** a user owns `c1` and is also assigned to `c3` and `c4`
- **THEN** `effectiveChannelIds()` SHALL return `[c1, c3, c4]` (no duplicates)

### Requirement: Access check covers both ownership and assignment
The system SHALL provide `User::canAccessChannel(Channel $channel): bool` returning `true` when the user is the channel's owner OR is listed in `channel_user` for that channel. Multimedia-flow authorization SHALL use this method and SHALL NOT compare `owner_id` directly.

#### Scenario: Owner passes the check
- **WHEN** the channel's `owner_id` equals the user's id
- **THEN** `canAccessChannel()` SHALL return `true`

#### Scenario: Assigned user passes the check
- **WHEN** the user has a row in `channel_user` for the channel
- **THEN** `canAccessChannel()` SHALL return `true` even though `owner_id !== user.id`

#### Scenario: Stranger is rejected
- **WHEN** the user has no relationship to the channel
- **THEN** `canAccessChannel()` SHALL return `false`

### Requirement: Multimedia flow uses effective channel scope
The system SHALL derive the channel set for every multimedia-flow query (media items, media folders, upload modal, upload endpoint, play URL, thumb URL) from `effectiveChannelIds()` for non-admin users. The admin role SHALL continue to bypass this filter and see all records.

#### Scenario: Client media listing is scoped
- **WHEN** a client assigned to `c3` calls `GET /api/media-items`
- **THEN** the response SHALL include only items whose `channel_id` is in the client's effective channel set

#### Scenario: Client folder listing is scoped
- **WHEN** a client assigned to `c3` calls `GET /api/media-folders`
- **THEN** the response SHALL include folders whose `channel_id = c3` (and folders with `channel_id IS NULL`)

#### Scenario: Admin folder listing is unfiltered
- **WHEN** an admin calls `GET /api/media-folders`
- **THEN** the response SHALL include all folders in the system

#### Scenario: Client upload is allowed on assigned channel
- **WHEN** a client assigned to `c3` POSTs to `/api/media-items/bulk-upload` with `channel_id = c3`
- **THEN** the server SHALL accept the upload and persist MediaItem rows under `c3`

#### Scenario: Client upload is rejected on a non-accessible channel
- **WHEN** a client not related to `c9` POSTs to `/api/media-items/bulk-upload` with `channel_id = c9`
- **THEN** the server SHALL respond `403`

#### Scenario: Assigned client can stream from assigned channel
- **WHEN** a client assigned to `c3` requests `play_url` for an item whose `channel_id = c3`
- **THEN** the streaming endpoint SHALL return 200

## ADDED Requirements

### Requirement: Client channels list excludes Pantalla Virtual action
The system SHALL NOT render the "Pantalla Virtual" button or include `<x-virtual-screen-editor-modal />` in the client channels index view. Clients configure the virtual screen from the Emisión module.

#### Scenario: Client channels index has no Pantalla Virtual button
- **WHEN** a client loads `GET /client/channels`
- **THEN** the rendered HTML SHALL NOT contain any button labeled "Pantalla Virtual" and SHALL NOT include the `<x-virtual-screen-editor-modal />` component