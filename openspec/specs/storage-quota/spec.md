## ADDED Requirements

### Requirement: Per-user storage limit
The system SHALL store a per-user storage limit in `users.storage_limit_bytes` (BIGINT, nullable). A NULL value SHALL mean unlimited. The unit SHALL be bytes.

#### Scenario: New client user gets default 40 GiB limit
- **WHEN** an admin creates a new user with `role = 'client'` and does not specify `storage_limit_bytes`
- **THEN** the user SHALL be persisted with `storage_limit_bytes = 42949672960` (40 GiB)

#### Scenario: New admin user gets unlimited by default
- **WHEN** an admin creates a new user with `role = 'admin'` and does not specify `storage_limit_bytes`
- **THEN** the user SHALL be persisted with `storage_limit_bytes = NULL`

#### Scenario: Existing clients keep NULL until admin sets a limit
- **WHEN** the migration adding the column runs on an existing database
- **THEN** all existing users SHALL have `storage_limit_bytes = NULL` (unlimited) until an admin explicitly sets a value

#### Scenario: Admin edits user limit
- **WHEN** an admin submits an update with `storage_limit_bytes` set to a positive integer (in GB) or empty (null)
- **THEN** the user's `storage_limit_bytes` SHALL be stored as the corresponding byte count or NULL respectively

### Requirement: Used bytes counter
The system SHALL store the current storage usage in `users.used_bytes` (BIGINT NOT NULL DEFAULT 0). For a user U, the value SHALL equal the sum of `size_bytes` over all `media_items` whose `channel.owner_id = U.id`.

#### Scenario: Used bytes increments on successful upload
- **WHEN** an upload to a channel completes successfully and the channel's owner is user U
- **THEN** `U.used_bytes` SHALL be incremented by the uploaded file's `size_bytes` within the same database transaction that creates the `MediaItem`

#### Scenario: Used bytes decrements on media deletion
- **WHEN** a `MediaItem` is deleted and its channel's owner is user U
- **THEN** `U.used_bytes` SHALL be decremented by the item's `size_bytes` within the same database transaction

#### Scenario: Recompute command restores counters
- **WHEN** an admin runs `php artisan media:recompute-quotas`
- **THEN** for every user with `role = 'client'`, `used_bytes` SHALL be set to the current `SUM(size_bytes)` of media in channels they own, in a single transaction

### Requirement: Upload quota enforcement
The system SHALL reject uploads that would cause the channel owner's `used_bytes` to exceed `storage_limit_bytes`.

#### Scenario: Single upload rejected when owner is over quota
- **WHEN** an upload is submitted to a channel whose owner has `used_bytes + file.size > storage_limit_bytes`
- **THEN** the server SHALL respond 422 with a message indicating the limit and that files must be deleted to free space, and no `MediaItem` SHALL be created and no file SHALL be written to disk

#### Scenario: Single upload allowed when under quota
- **WHEN** an upload is submitted to a channel whose owner has `used_bytes + file.size ≤ storage_limit_bytes`
- **THEN** the upload SHALL proceed normally and the item SHALL be created

#### Scenario: Admin uploads bypass quota
- **WHEN** a user with `role = 'admin'` uploads to any channel
- **THEN** the quota check SHALL NOT be applied

#### Scenario: Bulk upload skips files that exceed quota
- **WHEN** a bulk upload contains files where the owner's `used_bytes + cumulative_uploaded_size + next_file.size > storage_limit_bytes`
- **THEN** the server SHALL respond 201 with `uploaded` containing the items that fit and `failed` containing entries with `reason: "quota_exceeded"` for the items that did not fit

#### Scenario: Unlimited owner accepts any upload
- **WHEN** an upload is submitted to a channel whose owner has `storage_limit_bytes = NULL`
- **THEN** the quota check SHALL NOT be applied regardless of `used_bytes`

### Requirement: Storage display in client media view
The system SHALL display a storage usage panel at the bottom of `resources/views/client/media/index.blade.php` showing the current user's `used_bytes` / `storage_limit_bytes`, the count of media items, and a progress bar.

#### Scenario: Client below quota sees remaining space
- **WHEN** a client with `used_bytes < storage_limit_bytes` views `/client/media`
- **THEN** the panel SHALL display human-readable used and limit, a progress bar in green (below 70%) or yellow (70-90%), and the message "Te quedan X"

#### Scenario: Client at or over quota sees warning
- **WHEN** a client with `used_bytes >= storage_limit_bytes` views `/client/media`
- **THEN** the panel SHALL display a red progress bar at 100% and the message "Has alcanzado tu límite. Elimina archivos para liberar espacio."

#### Scenario: Unlimited client sees no bar
- **WHEN** a client with `storage_limit_bytes = NULL` views `/client/media`
- **THEN** the panel SHALL display "Ilimitado" in place of the progress bar and SHALL NOT show a percentage

#### Scenario: Upload button disabled when over quota
- **WHEN** the client media view renders and the current user is over their quota
- **THEN** the upload action SHALL be disabled in the UI

### Requirement: Global storage display in admin dashboard
The system SHALL display the total bytes used across all media in the admin dashboard.

#### Scenario: Admin sees global total
- **WHEN** an admin views `/admin/dashboard`
- **THEN** a stats card labeled "Almacenamiento total" SHALL display the `SUM(size_bytes)` of all `media_items` in human-readable form, alongside the total media count

### Requirement: Storage formatting helper
The system SHALL provide a human-readable byte formatter (B, KB, MB, GB, TB) used by both client and admin displays and by the `media:recompute-quotas` command output.

#### Scenario: Bytes formatted with appropriate unit
- **WHEN** the formatter receives `1073741824`
- **THEN** it SHALL return `"1 GB"`

#### Scenario: Null input renders as unlimited
- **WHEN** the formatter receives `NULL`
- **THEN** it SHALL return `"Ilimitado"`
