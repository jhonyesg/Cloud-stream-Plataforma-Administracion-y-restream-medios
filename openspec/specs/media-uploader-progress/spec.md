## ADDED Requirements

### Requirement: Uploader shows per-file progress bar

The `<x-media-uploader>` component SHALL render a per-file progress bar while each file is being uploaded, so the user can see exactly which file is uploading and how much remains.

#### Scenario: Progress bar updates during upload
- **WHEN** the user selects 3 files and clicks "Subir archivos"
- **THEN** each file row SHALL show a progress bar that updates from 0% to 100% as the file uploads, with the percentage value visible

#### Scenario: Per-file status states
- **WHEN** files are uploading
- **THEN** each file row SHALL display one of: `pending` ("En cola"), `uploading` ("Subiendo X%"), `done` ("Listo"), `error` ("Error: <message>")

#### Scenario: Cancel button aborts in-flight upload
- **WHEN** the user clicks the cancel button on a file that is currently uploading
- **THEN** the underlying XHR SHALL be aborted, the file row SHALL switch to `error` state with a retry option, and no other file SHALL be affected

#### Scenario: Failed file can be retried
- **WHEN** a file row is in `error` state
- **THEN** a retry control SHALL re-fire the upload for that file only, leaving already-uploaded files untouched

### Requirement: Per-file upload uses single-file endpoint sequentially

The uploader SHALL send each file to `POST /api/media-items/upload` via a separate `XMLHttpRequest`. Uploads SHALL proceed sequentially (one file at a time).

#### Scenario: Files upload sequentially
- **WHEN** 3 files are selected
- **THEN** file 1 uploads first; only after file 1 completes (success or error) does file 2 begin; only after file 2 does file 3 begin

#### Scenario: One file failure does not block others
- **WHEN** file 2 fails with a server error
- **THEN** file 3 SHALL still attempt to upload after file 2 settles

### Requirement: Submit button reflects aggregate progress

The "Subir archivos" button SHALL display the dynamic file count and the progress through the batch.

#### Scenario: Button shows file count
- **WHEN** 3 files are selected
- **THEN** the button label SHALL read "Subir 3 archivos"

#### Scenario: Button shows progress through batch
- **WHEN** 1 of 3 files has finished uploading
- **THEN** the button label SHALL read "Subiendo 1/3…"

#### Scenario: Modal closes when all files succeed
- **WHEN** every file in the batch reaches `done` state
- **THEN** the uploader modal SHALL close after a short delay and the page SHALL reload to show the new media items

#### Scenario: Modal stays open when any file fails
- **WHEN** at least one file in the batch is in `error` state
- **THEN** the modal SHALL remain open so the user can retry the failed files