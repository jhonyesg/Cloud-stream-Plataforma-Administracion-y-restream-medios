## ADDED Requirements

### Requirement: Server-side filesystem browse endpoint
The system SHALL expose `GET /api/admin/fs/browse?path=<absolute>` (authenticated admin only) that returns the list of subdirectories of the given path, restricted to a configured browse root.

#### Scenario: Browse a directory within the root
- **WHEN** an admin calls `GET /api/admin/fs/browse?path=/mnt/multimedia`
- **THEN** the server SHALL respond 200 with `{ path: "/mnt/multimedia", parent: "/mnt", entries: [{name, path, type: "dir"}, ...] }`

#### Scenario: Browse the root
- **WHEN** an admin calls `GET /api/admin/fs/browse` (no path) or with `path` equal to the configured browse root
- **THEN** the server SHALL respond 200 with the top-level subdirectories of the browse root and `parent = null`

### Requirement: Browse root is sandboxed
The system SHALL reject any `path` that resolves outside the configured browse root (`config('cloudstream.fs_browse_root')`, default `/mnt/multimedia`).

#### Scenario: Path above the root is rejected
- **WHEN** an admin calls `GET /api/admin/fs/browse?path=/etc`
- **THEN** the server SHALL respond 422 (path outside browse root)

#### Scenario: Path with `..` that escapes the root is rejected
- **WHEN** an admin calls `GET /api/admin/fs/browse?path=/mnt/multimedia/../../etc`
- **THEN** the server SHALL respond 422 (path outside browse root)

### Requirement: Non-directory paths are rejected
The system SHALL reject paths that do not exist or that point to a file rather than a directory.

#### Scenario: Non-existent path is rejected
- **WHEN** an admin calls `GET /api/admin/fs/browse?path=/mnt/multimedia/nope`
- **THEN** the server SHALL respond 422 (path not found)

### Requirement: Browse returns directories only
The system SHALL return subdirectories only — files SHALL be excluded from the response payload.

#### Scenario: Response contains only directories
- **WHEN** an admin browses a folder containing both files and subdirectories
- **THEN** the response SHALL contain only `entries` with `type: "dir"` — no entries for files

### Requirement: Non-admin users cannot browse
The system SHALL respond 403 to any non-admin user calling the browse endpoint.

#### Scenario: Client role gets 403
- **WHEN** a user with `role=client` calls `GET /api/admin/fs/browse?path=/mnt/multimedia`
- **THEN** the server SHALL respond 403

#### Scenario: Unauthenticated request gets 401/redirect
- **WHEN** an unauthenticated request hits the endpoint
- **THEN** the server SHALL respond 401 (or redirect to login for web requests)

### Requirement: Filesystem explorer modal component
The system SHALL provide a Blade Component `<x-fs-explorer-modal>` that renders a modal with: a path breadcrumb, a directory listing, and a "Seleccionar" button. The modal listens for `open-modal` events with detail `{ name: 'fs-explorer', target: '<input-id>', current: '<path>' }`.

#### Scenario: Explorer opens with current path
- **WHEN** the admin opens the explorer from a channel form
- **THEN** the explorer modal SHALL appear and load the directory listing for the current input value (or browse root if empty)

#### Scenario: Clicking a directory navigates into it
- **WHEN** the admin clicks a directory entry in the explorer
- **THEN** the explorer SHALL fetch and display the new directory's listing

#### Scenario: Clicking breadcrumb segment navigates to that level
- **WHEN** the admin clicks a breadcrumb segment
- **THEN** the explorer SHALL load that directory

#### Scenario: Clicking "Seleccionar" closes the modal and updates the target input
- **WHEN** the admin clicks "Seleccionar" in the explorer
- **THEN** the modal SHALL close AND the target input field SHALL receive the current path value

#### Scenario: Clicking "Cancelar" closes the modal without changes
- **WHEN** the admin clicks "Cancelar" or presses Escape
- **THEN** the modal SHALL close AND no input value SHALL change

### Requirement: Browse root is configurable
The browse root SHALL be configurable via `config/cloudstream.php` (key `fs_browse_root`, default `/mnt/multimedia`). Operators can override via `.env` (`FS_BROWSE_ROOT`).

#### Scenario: Custom browse root is respected
- **WHEN** `FS_BROWSE_ROOT=/srv/media` is set in `.env`
- **THEN** the browse endpoint SHALL restrict results to `/srv/media` and below