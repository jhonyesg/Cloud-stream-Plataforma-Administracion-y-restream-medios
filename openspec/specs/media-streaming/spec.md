## ADDED Requirements

### Requirement: Stream endpoint with HTTP Range support
The system SHALL expose `GET /api/media-items/{id}/stream` that serves the file from disk with HTTP Range support for video seek. The response SHALL include `Content-Type` matching the file's mime type, `Content-Length`, `Accept-Ranges: bytes`, and `Cache-Control: private, max-age=3600`.

#### Scenario: Full file request (no Range header)
- **WHEN** a client requests `GET /api/media-items/{id}/stream` without a `Range` header
- **THEN** the server SHALL respond 200 with `Content-Type: <mime>`, `Content-Length: <size>`, `Accept-Ranges: bytes`, and the entire file body

#### Scenario: Partial content request with Range header
- **WHEN** a client requests with `Range: bytes=0-1023` (first 1024 bytes)
- **THEN** the server SHALL respond 206 Partial Content with `Content-Range: bytes 0-1023/<total>`, `Content-Length: 1024`, `Accept-Ranges: bytes`, and only the first 1024 bytes in the body

#### Scenario: Range request for video seek
- **WHEN** a client requests with `Range: bytes=1048576-` (from 1MB to end)
- **THEN** the server SHALL respond 206 with the partial body starting at byte 1048576 and a proper `Content-Range` header

#### Scenario: Stream requires authentication and ownership
- **WHEN** an unauthenticated client requests the stream
- **THEN** the server SHALL respond 401 (or 403 for web routes)

- **WHEN** a client user requests a stream for a media item they do NOT have access to (not owner of channel, not assigned)
- **THEN** the server SHALL respond 403

#### Scenario: Stream 404 when file missing on disk
- **WHEN** a request is made for a media item whose physical file no longer exists
- **THEN** the server SHALL respond 404 with `{"message": "File not found on disk"}`

### Requirement: MediaItemController::show returns play_url
The `MediaItemController::show` and `::index` responses SHALL include `play_url` (absolute URL pointing to `/api/media-items/{id}/stream`) and `thumb_url` (absolute URL pointing to `/api/media-items/{id}/thumb`). These URLs SHALL be signed with the user's auth token so they don't require additional login.

#### Scenario: Play URL included in show response
- **WHEN** `GET /api/media-items/{id}` is called by an authorized user
- **THEN** the response body SHALL include `play_url` (e.g. `https://host/api/media-items/{id}/stream`) and `thumb_url` (e.g. `https://host/api/media-items/{id}/thumb`)