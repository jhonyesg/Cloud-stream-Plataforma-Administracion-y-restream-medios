## Purpose
Channels can be assigned to many users via a `channel_user` pivot table. Assigned users gain read and upload access to the channel without being its owner.
## Requirements
### Requirement: Channels can be assigned to multiple users
The system SHALL persist a many-to-many relationship between channels and users via a `channel_user` pivot table (`channel_id`, `user_id`, timestamps, composite primary key on `channel_id, user_id`). One channel can have zero or many assigned users; one user can be assigned to zero or many channels.

#### Scenario: Pivot is created with channels and users
- **WHEN** an admin assigns users `u1` and `u2` to channel `c1`
- **THEN** rows `(c1, u1)` and `(c1, u2)` SHALL exist in `channel_user`

#### Scenario: Owner is not required in the pivot
- **WHEN** an admin creates a channel with `owner_id=u1` and no assigned users
- **THEN** the `channel_user` table SHALL contain zero rows for that channel — owner access is implicit via `owner_id`

### Requirement: Channel edit modal supports multi-user assignment
The system SHALL render a multi-select control for "Usuarios asignados" in the channel create/edit modal. Admins can search users by name/username/email and toggle selection. When the edit modal opens and loads channel data asynchronously, the multi-select SHALL reactively populate with the already-assigned users once the data arrives.

#### Scenario: Multi-select shows current assignments
- **WHEN** the edit modal opens for a channel with assignments `[u1, u2]`
- **THEN** the multi-select SHALL display `u1` and `u2` as selected chips after the channel data finishes loading

#### Scenario: Multi-select handles async data load
- **WHEN** the edit modal opens and the fetch for channel data is still in flight
- **THEN** the multi-select SHALL initialize empty and SHALL populate with assigned users once `ch.assigned_users` becomes available, without requiring a page reload

#### Scenario: Adding a user creates a pivot row
- **WHEN** the admin selects user `u3` and submits
- **THEN** the server SHALL persist `(channel_id, u3)` in `channel_user`

#### Scenario: Removing a user deletes the pivot row
- **WHEN** the admin deselects user `u1` and submits
- **THEN** the server SHALL delete the row `(channel_id, u1)` from `channel_user`

#### Scenario: No role column in pivot
- **WHEN** the admin inspects the `channel_user` schema
- **THEN** the table SHALL contain NO `role` column — all assigned users have equivalent access

### Requirement: Only admins can manage assignments
The system SHALL restrict `POST/PUT/DELETE` to `/admin/channels*` (which carries assignments) to users with `role=admin`. Non-admin requests SHALL receive 403.

#### Scenario: Non-admin cannot update assignments
- **WHEN** a user with `role=client` calls `PUT /admin/channels/{id}` with an `assigned_user_ids` payload
- **THEN** the server SHALL respond 403 and the pivot SHALL NOT change

### Requirement: Assigned users gain full channel access
The system SHALL grant every user listed in `channel_user` for a channel the same access rights as the `owner_id` for read operations (read channel config, read folders, read media items, stream media, view emission state) and for the upload operation against that channel's default folder. Rename, delete, and folder-management operations SHALL remain restricted to the channel owner. The owner SHALL NOT be removed from assignments implicitly — owner access comes from `owner_id`, not the pivot.

#### Scenario: Assigned user can view channel via API
- **WHEN** a user `u2` assigned to channel `c1` calls `GET /api/channels/{c1}` (when implemented) or accesses the channel via the client dashboard
- **THEN** the server SHALL allow access

#### Scenario: Non-assigned non-owner user cannot access
- **WHEN** a user `u3` (not owner, not in pivot) tries to access channel `c1`
- **THEN** the server SHALL deny access (403 or hidden from list)

#### Scenario: Assigned user can upload into the channel's default folder
- **WHEN** a user `u2` assigned to channel `c1` POSTs to `/api/media-items/bulk-upload` with `channel_id = c1`
- **THEN** the server SHALL accept the upload and write the files under `c1`'s root path

#### Scenario: Assigned user cannot rename a media item on the channel
- **WHEN** a user `u2` assigned to channel `c1` (but not the owner) PUTs `/api/media-items/{id}` to rename an item belonging to `c1`
- **THEN** the server SHALL respond 403 (rename is owner-only)

#### Scenario: Assigned user cannot delete a media item on the channel
- **WHEN** a user `u2` assigned to channel `c1` (but not the owner) DELETEs `/api/media-items/{id}` for an item belonging to `c1`
- **THEN** the server SHALL respond 403 (delete is owner-only)

### Requirement: Client sees only owned + assigned channels
The system SHALL expose a `GET /api/channels` endpoint that returns the list of channels where the authenticated user is either the owner OR in the pivot table.

#### Scenario: Owner sees owned channel
- **WHEN** a user `u1` (owner of `c1`, `c2`) calls `GET /api/channels`
- **THEN** the response SHALL include `c1` and `c2`

#### Scenario: Assigned user sees assigned channel
- **WHEN** a user `u2` assigned to `c3` calls `GET /api/channels`
- **THEN** the response SHALL include `c3`

#### Scenario: Non-related user sees neither
- **WHEN** a user `u4` (not owner of anything, not in any pivot) calls `GET /api/channels`
- **THEN** the response SHALL contain an empty list

### Requirement: Client dashboard filters channels
The system SHALL render on `/client/dashboard` only the channels where the authenticated user is owner OR assigned.

#### Scenario: Dashboard shows accessible channels
- **WHEN** a user with owner `c1` and assignment `c3` loads the client dashboard
- **THEN** the dashboard SHALL render cards for `c1` and `c3` only

### Requirement: Deleting a user cascades the pivot
The system SHALL cascade-delete `channel_user` rows when the referenced user is deleted (FK constraint with `ON DELETE CASCADE` on the pivot's `user_id` column).

#### Scenario: Deleting a non-owner user cleans the pivot
- **WHEN** an admin deletes user `u2` who is in `channel_user` for `c1`
- **THEN** the row `(c1, u2)` SHALL be deleted automatically and `c1` SHALL remain intact

#### Scenario: Deleting an owner is blocked
- **WHEN** an admin attempts to delete a user who is `owner_id` of one or more non-archived channels
- **THEN** the server SHALL respond 422 with a message asking to reassign ownership first; no delete SHALL occur

