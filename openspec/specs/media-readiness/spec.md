## ADDED Requirements

### Requirement: Readiness contract for MediaItem.status
A `MediaItem` SHALL transition from `pending` to `ready` when **both** of the following conditions are true:

1. The source file exists on disk at `<channel.root_path>/<filename>`.
2. A thumbnail file (real JPEG frame, resized image, or placeholder SVG) exists at `media/thumbs/{id}.jpg` AND `thumb_path` on the row points to it.

If condition 1 is true but condition 2 cannot be satisfied because the underlying generator (ffmpeg/GD/Imagick) raises an unrecoverable error, the item MUST transition to `status: failed` with a non-null `status_reason` describing the cause, instead of staying on `pending`.

#### Scenario: File on disk plus thumbnail cached makes the item ready
- **WHEN** a `MediaItem` has the source file present under `channel.root_path/<filename>` AND a thumbnail file present at `media/thumbs/{id}.jpg` AND `thumb_path` is set on the row
- **THEN** the row's `status` SHALL be `ready` and `status_reason` SHALL be null

#### Scenario: File on disk without thumbnail stays pending
- **WHEN** a `MediaItem` has the source file present under `channel.root_path/<filename>` but no thumbnail file at `media/thumbs/{id}.jpg`
- **THEN** the row's `status` SHALL remain `pending`

#### Scenario: Thumbnail generation error fails the item
- **WHEN** the thumbnail generator raises an unrecoverable error while attempting to produce `media/thumbs/{id}.jpg`
- **THEN** the row's `status` SHALL be `failed` and `status_reason` SHALL describe the error (e.g. `thumbnail generation failed: <message>`)

#### Scenario: Lazy /thumb endpoint flips stuck pending items
- **WHEN** a client requests `GET /api/media-items/{id}/thumb` for an item whose `status` is `pending` and whose thumbnail file does not yet exist on disk
- **THEN** the server SHALL generate the thumbnail (or placeholder) AND persist `thumb_path` AND set `status: ready` on the row before returning the image

### Requirement: Single readiness rule inside MediaThumbnailService
The rules above SHALL be implemented by a single helper inside `App\Services\MediaThumbnailService`. Both `generateEager()` and `getOrGenerate()` MUST call that helper at the end of their execution paths so the readiness rule has exactly one source of truth.

#### Scenario: Both entry points share the same helper
- **WHEN** `generateEager()` finishes a successful generation (real frame or placeholder)
- **THEN** the helper SHALL be invoked once and the row SHALL be persisted only if `status` or `status_reason` actually changed

#### Scenario: Helper is a no-op when item is already ready
- **WHEN** the helper is invoked for a row whose `status` is already `ready`
- **THEN** the helper SHALL return without calling `save()`, so hot `/thumb` requests on ready items do not touch `updated_at`