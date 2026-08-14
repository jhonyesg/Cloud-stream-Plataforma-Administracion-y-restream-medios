## Purpose
Define the MediaItem lifecycle: upload, storage on disk via channel root_path, rename, delete, uniqueness enforcement, and channel-deletion cascade behavior.
## Requirements
### Requirement: File system file storage via Channel root_path
The system SHALL persist media files on the local filesystem at `<channel.root_path>/<filename>`. The `MediaProvider` and `MediaFolder` abstractions are eliminated; `channel.root_path` is the sole source of truth for the absolute directory path. `MediaItem` SHALL reference `channel_id` directly (no `provider_id` or `folder_id`). The file lives at `channel.root_path + "/" + filename`. El escaneo SHALL limitarse al primer nivel de `root_path` (no recursivo): SHALL omitir subdirectorios (por ejemplo `__pycache__`).

#### Scenario: File written to channel root_path
- **WHEN** a user uploads `video.mp4` (10MB) to channel C whose `root_path = "/mnt/multimedia/Cine Dios"`
- **THEN** the file SHALL exist on disk at `/mnt/multimedia/Cine Dios/video.mp4`

#### Scenario: Upload requires channel with root_path
- **WHEN** a user uploads to channel C whose `root_path` is NULL
- **THEN** the server SHALL respond 422 with `{"message": "Channel has no root_path configured"}`

#### Scenario: Absolute path computed from channel
- **WHEN** the system needs the absolute path for a MediaItem (stream, thumbnail, delete, rename)
- **THEN** the path SHALL be computed as `MediaItem.channel.root_path + "/" + MediaItem.filename`, without consulting any provider or folder table

#### Scenario: Escaneo no recursivo
- **WHEN** `media:scan` procesa el `root_path` de un canal
- **THEN** SHALL iterar únicamente sobre las entradas de primer nivel (no recursión a subdirectorios) y SHALL omitir entradas que sean directorios (ej. `__pycache__`).

### Requirement: Rename file on disk updates MediaItem
The system SHALL, when `MediaItem.filename` is updated via `PUT /api/media-items/{id}`, rename the physical file on disk from the old path to the new path. If the rename fails (file missing, permission error, conflict), the DB update SHALL be rolled back and the response SHALL be 422.

#### Scenario: Rename file from `video_v1.mp4` to `video_v2.mp4`
- **WHEN** an authorized user calls `PUT /api/media-items/{id}` with `{"filename": "video_v2.mp4"}`
- **THEN** the server SHALL rename the file on disk to `video_v2.mp4` AND update `MediaItem.filename`. Both SHALL succeed.

#### Scenario: Rename to a name that already exists in the same channel folder
- **WHEN** an authorized user calls `PUT /api/media-items/{id}` with `{"filename": "existing.mp4"}` where another file with that name already exists in the same channel's `root_path` directory
- **THEN** the server SHALL respond 422 with `{"message": "File already exists at destination"}` and NO rename SHALL occur

#### Scenario: Rename propagation to playlists (auto-update via FK)
- **WHEN** `MediaItem.filename` is updated and that item is referenced by one or more `PlaylistItem` rows
- **THEN** no `PlaylistItem` row is modified (FK stays the same), but any UI reading `playlist.items[].media_item.filename` SHALL display the NEW name on next render (no cache on the playlist side)

### Requirement: Delete file on disk removes MediaItem (hard delete with CASCADE)
The system SHALL, when `DELETE /api/media-items/{id}` is called, perform a hard-delete of the `MediaItem` row AND unlink the physical file on disk. The `ON DELETE CASCADE` foreign keys on `playlist_items.media_item_id` and `schedule_block_items.media_item_id` SHALL automatically remove orphaned references. After deletion, the system SHALL renumber positions in affected playlists and recalculate their `total_duration_sec`. If the file is already missing on disk, the delete SHALL still succeed.

#### Scenario: Delete removes file and cleans playlist references
- **WHEN** an admin calls `DELETE /api/media-items/{id}` where the item is in playlist P at position 2
- **THEN** the file SHALL be unlinked from disk, the `media_items` row SHALL be hard-deleted, the `playlist_items` row SHALL be cascade-deleted, positions in P SHALL be renumbered, and `total_duration_sec` of P SHALL be recalculated

#### Scenario: Delete succeeds even if file already missing
- **WHEN** an admin calls `DELETE /api/media-items/{id}` and the file no longer exists on disk
- **THEN** the media item SHALL still be hard-deleted and a missing-file warning SHALL be logged. Response 200.

#### Scenario: Delete cleans schedule block references
- **WHEN** a media item that is referenced in `schedule_block_items` is deleted
- **THEN** the `schedule_block_items` rows SHALL be cascade-deleted automatically

### Requirement: No duplicate filenames per channel
The system SHALL enforce a unique constraint on `(channel_id, filename)` for media items. Attempting to upload or rename to an existing filename in the same channel SHALL be rejected with 422.

#### Scenario: Upload duplicate filename rejected
- **WHEN** a user uploads `video.mp4` to channel C and a `media_item` with `filename=video.mp4, channel_id=C` already exists
- **THEN** the server SHALL respond 422 with `{"message": "File already exists"}`

#### Scenario: Rename to existing filename rejected
- **WHEN** a user renames a media item to `existing.mp4` and another item in the same channel already has that filename
- **THEN** the server SHALL respond 422 and no rename SHALL occur

### Requirement: Channel deletion cascades to media items
When a channel is deleted, all `media_items` belonging to that channel SHALL be cascade-deleted. The `ON DELETE CASCADE` foreign key on `media_items.channel_id` SHALL ensure no orphaned media items remain.

#### Scenario: Delete channel removes all media
- **WHEN** an admin deletes channel C which has 60 media items
- **THEN** all 60 media items SHALL be deleted and their playlist_items references SHALL be cascade-deleted

### Requirement: Authorization on update/delete
The system SHALL enforce authorization on `PUT /api/media-items/{id}` and `DELETE /api/media-items/{id}`:
- Admin users: always allowed
- Client users: only allowed if the media item belongs to a channel the user owns

#### Scenario: Client tries to rename media from someone else's channel
- **WHEN** a client user (not owner of channel C) calls `PUT /api/media-items/{id}` where the item belongs to C
- **THEN** the server SHALL respond 403 and no changes SHALL occur

#### Scenario: Client tries to delete media from someone else's channel
- **WHEN** a client user (not owner of channel C) calls `DELETE /api/media-items/{id}` where the item belongs to C
- **THEN** the server SHALL respond 403 and the item SHALL remain in the database and on disk

### Requirement: Index includes play_url and thumb_url
The `GET /api/media-items` response SHALL include `play_url` and `thumb_url` for each item, computed from the media id and the request host.

#### Scenario: List endpoint returns media with URLs
- **WHEN** `GET /api/media-items?per_page=10` is called
- **THEN** each item in the response SHALL include `play_url` (e.g. `https://host/api/media-items/{id}/stream`) and `thumb_url` (e.g. `https://host/api/media-items/{id}/thumb`)

### Requirement: Filtrado de extensiones no audiovisuales en el escaneo
The `media:scan` command SHALL exclude from import any file whose extension is not in the allowlist. La allowlist SHALL incluir: extensiones de video (`mp4, webm, mov, mkv, avi, flv, wmv, m4v, mpg, mpeg, ts, 3gp`), imagen (`jpg, jpeg, png, webp, gif, bmp, svg, tiff, ico`), audio (`mp3, wav, aac, flac, ogg, m4a, wma, opus`), y el archivo especial `playlist.txt`. El comando SHALL excluir explícitamente: archivos que coincidan con `*.upload.tmp`, archivos con extensión en la denylist (`tmp, py, log, lock`), y archivos sin extensión reconocida (estos se importan con `kind='other'` solo si están en la allowlist o son `playlist.txt`).

#### Scenario: Scan omite scripts Python
- **WHEN** `media:scan` procesa un canal que contiene `archivo.py` y `pelicula.mp4`
- **THEN** SHALL registrar `pelicula.mp4` como `media_item` con `kind='video'` y SHALL NO registrar `archivo.py`.

#### Scenario: Scan omite logs
- **WHEN** `media:scan` procesa un canal que contiene `ffmpeg.log`
- **THEN** SHALL NO crear un `media_item` para ese archivo.

#### Scenario: Scan omite archivos .lock
- **WHEN** `media:scan` procesa un canal que contiene `redplanet.lock`
- **THEN** SHALL NO crear un `media_item` para ese archivo.

#### Scenario: Scan registra playlist.txt
- **WHEN** `media:scan` procesa un canal que contiene `playlist.txt` y no existe aún como `media_item`
- **THEN** SHALL registrar el archivo con `kind='other'` (es texto plano, no audiovisual, pero es un formato válido de playlist externa).

#### Scenario: Scan omite archivos .upload.tmp
- **WHEN** `media:scan` procesa un canal que contiene `video.mp4.123.upload.tmp`
- **THEN** SHALL NO crear un `media_item` para ese archivo.

### Requirement: Escaneo omite canales soft-deleted
The `media:scan` command SHALL skip channels whose `deleted_at` is not null. Use `Channel::query()` (no `withTrashed()`) al construir la lista de canales a escanear.

#### Scenario: Canal borrado se ignora
- **WHEN** un canal C tiene `deleted_at = '2026-07-10 12:00:00'` (no nulo)
- **THEN** `media:scan` SHALL NO procesar archivos de su `root_path` ni SHALL crear `media_items` para él.

