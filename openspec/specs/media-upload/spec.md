## ADDED Requirements

### Requirement: Upload single file to a channel via multipart
The system SHALL accept `POST /api/media-items/upload` with `multipart/form-data`. The request SHALL include `channel_id` (UUID), and `file` (binary). The server SHALL persist the file under the resolved storage path, create a `MediaItem` row, run the eager thumbnail pipeline, and return 201 with the item JSON including `play_url` and `thumb_url`. The item's `status` in the response MUST reflect the readiness contract: `status: "ready"` when the thumbnail pipeline produces a thumbnail (real or placeholder), `status: "failed"` with a non-null `status_reason` when the pipeline cannot produce one, and `status: "pending"` only when the eager pipeline itself was skipped. The per-file size limit SHALL be governed by `config('cloudstream.media.max_upload_size')`, with a default of **5 GB**.

#### Scenario: Successful upload by channel owner
- **WHEN** a user authenticated as channel owner submits a valid `multipart/form-data` request to `POST /api/media-items/upload` with `channel_id` (owned), a `file` (≤ `cloudstream.media.max_upload_size`), and `kind` (optional, defaults to `video`)
- **THEN** the server SHALL respond 201 with a JSON `MediaItem` whose `filename`, `size_bytes`, `mime_type`, `channel_id`, `status: "ready"` (after the eager thumbnail pipeline produced a thumbnail or placeholder), `play_url`, and `thumb_url` are set

#### Scenario: Audio upload returns ready with placeholder thumbnail
- **WHEN** an admin uploads an `audio/mpeg` file via `POST /api/media-items/upload`
- **THEN** the server SHALL respond 201 with `status: "ready"`, `thumb_path` set to `media/thumbs/{id}.jpg`, and `thumb_url` pointing to the audio placeholder

#### Scenario: Video upload with ffmpeg failure returns failed with status_reason
- **WHEN** an admin uploads a video file and the eager ffmpeg-based thumbnail extraction raises an error
- **THEN** the server SHALL respond 201 with `status: "failed"` and a `status_reason` describing the ffmpeg error (the file is still on disk)

#### Scenario: Upload succeeds for a 4 GB video
- **WHEN** an admin uploads a video file of 4 GB (below the 5 GB ceiling) and the webserver limits are aligned
- **THEN** the server SHALL respond 201 without HTTP 413, the file SHALL be written to disk, and a `MediaItem` SHALL be created with eager thumbnail generation

#### Scenario: Upload fails when file exceeds size limit
- **WHEN** any user submits upload with a file larger than `config('cloudstream.media_max_upload_size')` (default 5 GB)
- **THEN** the server SHALL respond 422 with `{"message": "File too large"}` and no file SHALL be written to disk

#### Scenario: Upload fails when channel is not owned by user
- **WHEN** a non-admin user submits upload with `channel_id` of a channel they do NOT own
- **THEN** the server SHALL respond 403 and no file SHALL be written to disk

#### Scenario: Upload fails when channel has no root_path
- **WHEN** an admin submits upload with `channel_id` of a channel whose `root_path` is NULL
- **THEN** the server SHALL respond 422 with `{"message": "Channel has no root_path configured"}`

### Requirement: Upload multiple files in a single request
The system SHALL accept `POST /api/media-items/bulk-upload` with `multipart/form-data` containing `channel_id` and `files[]` (multiple binary parts). The server SHALL persist each file in turn (sequential) and return 201 with `{ uploaded: [...], failed: [...] }` listing each result individually so partial success is observable. Each file SHALL be subject to the per-file `cloudstream.media.max_upload_size` ceiling (default 5 GB) and the request SHALL be capped at `cloudstream.media.bulk_max_files` files (default 20).

#### Scenario: Bulk upload of 5 files succeeds
- **WHEN** a user submits 5 valid files in one request to `POST /api/media-items/bulk-upload` with valid `channel_id`
- **THEN** the server SHALL respond 201 with `{ uploaded: [5 MediaItem objects], failed: [] }` and all 5 files SHALL exist on disk

#### Scenario: Bulk upload with partial failure
- **WHEN** a user submits 3 files where the 2nd exceeds the size limit
- **THEN** the server SHALL respond 201 with `{ uploaded: [item1, item3], failed: [{filename: "...", reason: "File too large"}] }`. Files 1 and 3 SHALL exist on disk, file 2 SHALL NOT

#### Scenario: Bulk upload rejects non-owner
- **WHEN** a non-admin user submits bulk upload to a channel they don't own
- **THEN** the server SHALL respond 403 and no files SHALL be written

#### Scenario: Bulk upload accepts large files up to ceiling
- **WHEN** a user submits a bulk-upload with files totaling 6 GB and each file ≤ 5 GB
- **THEN** the server SHALL respond 201 with the uploaded array (not 413), provided nginx + PHP limits are aligned

### Requirement: Upload validation
The system SHALL validate uploads as follows:
- `channel_id`: required, UUID, exists in `channels`, owned by user (or user is admin)
- `kind`: optional, one of `video|ad|image|audio|other` (defaults to `video` based on mime type detection)
- `file`: required, ≤ 5GB, mime type in allowed list `video/mp4,video/webm,video/quicktime,image/jpeg,image/png,image/webp,audio/mpeg,audio/wav`

#### Scenario: Invalid mime type rejected
- **WHEN** a user uploads a file with `mime_type: application/x-msdownload`
- **THEN** the server SHALL respond 422 with `{"message": "Mime type not allowed"}`

### Requirement: Upload persistence survives temp-file cleanup
The `MediaStorageService::write()` method SHALL read the file size and mime type from the `UploadedFile` instance **before** invoking `move()`. The implementation SHALL NOT depend on calling `getSize()` or `getMimeType()` after the temp file has been relocated.

#### Scenario: Size is captured before move
- **WHEN** `MediaStorageService::write()` is called with an `UploadedFile`
- **THEN** the size and mime type SHALL be read from the uploaded file before `$file->move(...)` is invoked, so the original `/tmp/php...` temp path is still valid at read time

#### Scenario: No stat-failed error after move
- **WHEN** the upload completes successfully and the file is moved to its destination
- **THEN** no `SplFileInfo::getSize(): stat failed for /tmp/php...` warning or error SHALL appear in the logs