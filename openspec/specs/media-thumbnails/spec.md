# media-thumbnails Specification

## Purpose
Defines how the cloudstream media library generates, caches, and serves thumbnails for media items, including eager generation on upload, lazy generation on demand, and failure handling.

## Requirements

### Requirement: Lazy thumbnail endpoint
The system SHALL expose `GET /api/media-items/{id}/thumb` that returns a JPEG thumbnail for the media item. The thumbnail SHALL be generated on-demand the first time it's requested and cached to `storage/app/public/media/thumbs/{media_id}.jpg`. Subsequent requests SHALL return the cached file.

#### Scenario: First thumbnail request for a video generates and caches
- **WHEN** a client requests `GET /api/media-items/{id}/thumb` for a video media item that has no cached thumbnail
- **THEN** the server SHALL generate a thumbnail (frame at 1s via ffmpeg if available, otherwise a placeholder with the filename and a "video" badge), save it to `storage/app/public/media/thumbs/{media_id}.jpg`, and return 200 with `Content-Type: image/jpeg`

#### Scenario: Cached thumbnail returned
- **WHEN** a client requests `GET /api/media-items/{id}/thumb` for a media item whose thumbnail is already cached
- **THEN** the server SHALL respond 200 with `Content-Type: image/jpeg` and the cached file content (no regeneration)

#### Scenario: Thumbnail for image media item
- **WHEN** a client requests `GET /api/media-items/{id}/thumb` for an `image/*` media item
- **THEN** the server SHALL generate a thumbnail (resize to max 400x400 preserving aspect ratio), cache it, and return it

#### Scenario: Thumbnail for audio media item
- **WHEN** a client requests `GET /api/media-items/{id}/thumb` for an `audio/*` media item
- **THEN** the server SHALL return a generic audio placeholder image (cached)

#### Scenario: Thumbnail generation failure falls back to placeholder
- **WHEN** ffmpeg is not available and the media is a video
- **THEN** the server SHALL return a generic video placeholder (gray background with play icon), cache it, and respond 200

#### Scenario: Thumbnail endpoint requires authentication
- **WHEN** an unauthenticated client requests the thumbnail
- **THEN** the server SHALL respond 401 or 403

### Requirement: Thumbnail cache management
The system SHALL manage thumbnail cache via:
- Storage path: `storage/app/public/media/thumbs/`
- Public URL: `APP_URL + /storage/media/thumbs/{id}.jpg` (via `php artisan storage:link`)
- Cache invalidation: when a MediaItem is deleted or its file changes (rename), the corresponding thumbnail file SHALL be removed from disk

#### Scenario: Thumbnail cache cleared on rename
- **WHEN** a media item's filename is changed via `PUT /api/media-items/{id}`
- **THEN** the server SHALL also delete the cached thumbnail at `storage/app/public/media/thumbs/{id}.jpg` (if it exists) so the next request regenerates

#### Scenario: Thumbnail cache cleared on delete
- **WHEN** a media item is deleted via `DELETE /api/media-items/{id}`
- **THEN** the server SHALL also delete the cached thumbnail at `storage/app/public/media/thumbs/{id}.jpg`

### Requirement: Eager thumbnail generation on upload

After a `MediaItem` is created via `POST /api/media-items/upload`, `POST /api/media-items/bulk-upload`, or `php artisan media:scan`, the system SHALL generate a thumbnail synchronously for items whose `kind` is `video`, `image`, or `ad` before responding. The `thumb_path` column SHALL be populated with the relative storage path on success.

#### Scenario: Video upload produces a real preview frame
- **WHEN** an admin uploads a video file via `POST /api/media-items/upload` and ffmpeg is available
- **THEN** the server SHALL extract a frame at 10% of the video duration, store it at `media/thumbs/{id}.jpg`, set `thumb_path` on the MediaItem, and return 201 with the item JSON including `thumb_url`

#### Scenario: Image upload produces a real preview
- **WHEN** an admin uploads an image file via `POST /api/media-items/upload`
- **THEN** the server SHALL resize the image to fit within 400×400 preserving aspect ratio, store it at `media/thumbs/{id}.jpg`, set `thumb_path`, and return 201

#### Scenario: Bulk upload triggers thumbnails per item
- **WHEN** an admin uploads 5 files via `POST /api/media-items/bulk-upload`
- **THEN** each video/image/ad item in the `uploaded` array SHALL have a generated thumbnail and `thumb_path` set; audio items SHALL keep `thumb_path: null` and rely on the placeholder

#### Scenario: media:scan generates thumbnails for newly imported items
- **WHEN** `php artisan media:scan` imports a new video, image or ad file
- **THEN** the command SHALL generate the thumbnail and set `thumb_path` on the new MediaItem row before moving to the next file

### Requirement: Video thumbnail uses 10% of duration with fallback ladder

The video thumbnail extraction SHALL use a robust frame selection strategy that does not depend on a keyframe at exactly 1 second.

#### Scenario: 30-second video uses 10% frame
- **WHEN** a video of duration ≥ 5 seconds is processed
- **THEN** the system SHALL attempt to extract a frame at `duration * 0.10` seconds (e.g., 3s for a 30s video)

#### Scenario: Short video falls back to middle frame
- **WHEN** the 10% seek fails (no keyframe, decode error)
- **THEN** the system SHALL retry at `duration * 0.50`

#### Scenario: Final fallback to first frame
- **WHEN** both percentage seeks fail
- **THEN** the system SHALL retry at 1.0 seconds, then at 0.0 seconds

#### Scenario: All attempts fail returns placeholder
- **WHEN** every frame seek attempt fails
- **THEN** the system SHALL fall back to the existing video placeholder SVG and set `thumb_path` to the placeholder path so the card does not show a broken image

### Requirement: Backfill command for existing items

The system SHALL expose `php artisan media:thumbs` that walks existing `MediaItem` rows missing a thumbnail and generates them.

#### Scenario: Backfill processes all items missing thumbs
- **WHEN** an admin runs `php artisan media:thumbs`
- **THEN** the command SHALL iterate every video/image/ad MediaItem whose `thumb_path` is null or whose thumbnail file is missing on disk, generate the thumbnail for each, and print a final summary `processed=N failed=M skipped=K`

#### Scenario: Backfill scoped to one channel
- **WHEN** an admin runs `php artisan media:thumbs --channel=mi-canal`
- **THEN** the command SHALL only process items of that channel (slug match)

#### Scenario: Backfill --force regenerates existing thumbs
- **WHEN** an admin runs `php artisan media:thumbs --force`
- **THEN** the command SHALL regenerate the thumbnail for every matching item, deleting the previous file before re-extracting

#### Scenario: Backfill --limit caps the run
- **WHEN** an admin runs `php artisan media:thumbs --limit=20`
- **THEN** the command SHALL process at most 20 items and exit cleanly with a summary

#### Scenario: Backfill --dry is non-destructive
- **WHEN** an admin runs `php artisan media:thumbs --dry`
- **THEN** the command SHALL print the list of items that would be processed and write nothing to disk or DB

### Requirement: UI button triggers backfill

The admin media library view SHALL expose a button that triggers the backfill API for the currently filtered scope.

#### Scenario: Button generates thumbnails for the current channel filter
- **WHEN** the admin clicks "Generar miniaturas" while a channel filter is active
- **THEN** the client SHALL call `POST /admin/media/thumbnails/generate` with `channel_id` from the active filter and display the result count in a toast

#### Scenario: Button refreshes when no items remain
- **WHEN** the backfill API returns `remaining === 0`
- **THEN** the client SHALL refresh the page so the new thumbnails appear in the grid

#### Scenario: API requires admin
- **WHEN** a non-admin user requests `POST /admin/media/thumbnails/generate`
- **THEN** the server SHALL respond 403

### Requirement: Bonus metadata populated during generation

When a thumbnail is generated from a video, the system SHALL also probe the source with `ffprobe` and persist the following columns on the MediaItem if they are currently null: `duration_sec`, `width`, `height`, `codec_video`, `codec_audio`, `bitrate_kbps`.

#### Scenario: ffprobe populates duration and dimensions
- **WHEN** a video thumbnail is generated and ffprobe returns valid metadata
- **THEN** the MediaItem SHALL have its `duration_sec`, `width`, `height`, `codec_video`, `codec_audio`, `bitrate_kbps` written if they were null

#### Scenario: ffprobe failure does not block thumbnail
- **WHEN** ffprobe fails or returns no metadata
- **THEN** the thumbnail SHALL still be generated and saved; only the metadata fields are skipped
### Requirement: Audio and other-kind uploads produce a placeholder thumbnail
When `MediaThumbnailService::generateEager()` is called for a `MediaItem` whose mime type is NOT `video/*` or `image/*` (e.g. `audio/*`, `application/*`) OR whose `kind` is NOT in `[video, image, ad]`, the service SHALL generate a placeholder SVG via `generatePlaceholder()`, write it to `media/thumbs/{id}.jpg`, set `thumb_path` on the row, and return `true`. The row's `status` SHALL transition from `pending` to `ready` (clearing `status_reason`).

#### Scenario: Audio upload produces a placeholder and is ready
- **WHEN** an admin uploads an `audio/mpeg` file via `POST /api/media-items/upload`
- **THEN** the server SHALL write an audio placeholder SVG to `media/thumbs/{id}.jpg`, set `thumb_path` on the row, set `status: ready`, and return 201 with the item JSON

#### Scenario: Other-kind upload produces a placeholder and is ready
- **WHEN** an admin uploads a file whose mime is `application/pdf` (or any non video/image mime) via `POST /api/media-items/upload`
- **THEN** the server SHALL write a generic "ARCHIVO" placeholder SVG to `media/thumbs/{id}.jpg`, set `thumb_path`, set `status: ready`, and return 201

### Requirement: Thumbnail generation failure fails the item with status_reason
When `generateEager()` cannot produce a real thumbnail for a `MediaItem` whose `kind` is `video|image|ad` — including the case where the underlying tool (ffmpeg / GD / Imagick) returned but only succeeded in producing a placeholder SVG fallback, or where the source file is missing on disk, or where the generator throws — the upload controller or any caller SHALL mark the item with `status: failed` and a descriptive `status_reason` instead of leaving it on `pending` or persisting a placeholder under `thumb_path`.

#### Scenario: Missing source file on disk fails the item
- **WHEN** an admin uploads a file but the source file is missing under `channel.root_path` at the moment `generateEager()` runs (storage race, channel misconfigured)
- **THEN** the server SHALL respond 201 with `status: failed` and `status_reason` mentioning the missing source

#### Scenario: ffmpeg throws fails the item
- **WHEN** an admin uploads a video file and ffmpeg raises an error during frame extraction
- **THEN** the server SHALL respond 201 with `status: failed` and `status_reason` containing the ffmpeg error message

#### Scenario: ffmpeg unavailable or all seek candidates fail falls back to placeholder is treated as a failure for video/image items
- **WHEN** `generateEager()` is called for a `video|image|ad` MediaItem and either (a) `ffmpeg` (or `gd`/`imagick` for images) is not on the server, or (b) every ffmpeg seek candidate returns no decodable frame, so the service wrote a placeholder SVG to `media/thumbs/{id}.jpg`
- **THEN** the placeholder SVG SHALL be removed from disk, `thumb_path` SHALL be set to `null`, the row SHALL be persisted with `status: failed` and `status_reason` such as `"thumbnail generation fell back to placeholder for video"` or `"thumbnail generation tool unavailable: ffmpeg"`, and `generateEager()` SHALL return `false`.

#### Scenario: Audio and other kind items keep their placeholder as a valid ready state
- **WHEN** `generateEager()` is called for a MediaItem whose `kind` is `audio` or `other` (no real thumbnail is expected)
- **THEN** the placeholder SVG SHALL be persisted under `thumb_path`, `status` SHALL transition to `ready`, and `status_reason` SHALL be `null`. This case is NOT considered a failure.

### Requirement: Lazy /thumb endpoint persists ready status
When `MediaThumbnailService::getOrGenerate()` is invoked from `MediaThumbnailController::show` and it ends up writing a new thumbnail file to `media/thumbs/{id}.jpg`, it SHALL distinguish between a real thumbnail (decoded frame for `video|image|ad`, or resized image) and a placeholder SVG. Only real thumbnails SHALL be persisted under `thumb_path` and SHALL cause `status` to transition to `ready`. Placeholder results for `audio|other` items continue to be persisted (placeholder IS the thumbnail for those kinds); placeholder results for `video|image|ad` items SHALL NOT be persisted and SHALL instead set `status: failed` with a descriptive `status_reason`.

#### Scenario: First /thumb hit on a pending video item that ffmpeg can decode produces a real thumbnail and flips status to ready
- **WHEN** a client requests `GET /api/media-items/{id}/thumb` for a `video` item whose `status` is `pending` and no thumbnail file exists on disk, and ffmpeg successfully decodes one of the seek candidates
- **THEN** the server SHALL write the real thumbnail JPEG to `media/thumbs/{id}.jpg`, persist `thumb_path` on the row, set `status: ready`, and return 200 with the image bytes

#### Scenario: First /thumb hit on a pending video item where ffmpeg all-fails marks the item as failed
- **WHEN** a client requests `GET /api/media-items/{id}/thumb` for a `video` item whose `status` is `pending`, ffmpeg is available but every seek candidate fails to decode a frame, so the service writes a placeholder SVG
- **THEN** the placeholder SHALL be removed from disk, `thumb_path` SHALL remain `null`, `status` SHALL become `failed`, `status_reason` SHALL be set to `"thumbnail generation fell back to placeholder for video"`, and the server SHALL return 200 with the placeholder SVG bytes for visual feedback

#### Scenario: /thumb hit on an already-ready item does not rewrite status
- **WHEN** a client requests `GET /api/media-items/{id}/thumb` for an item whose `status` is already `ready` and the cached file is present
- **THEN** the server SHALL return the cached file in 200 with no write to the database
