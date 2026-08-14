## ADDED Requirements

### Requirement: Upload size ceiling is 5 GB by default

The system SHALL accept uploads up to **5 GB** per file by default, aligned across all three layers: nginx, PHP, and Laravel.

#### Scenario: Default config advertises 5 GB
- **WHEN** the operator inspects `config('cloudstream.media.max_upload_size')` with no `.env` override
- **THEN** the value SHALL be `5368709120` (5 GB)

#### Scenario: Nginx accepts a 4 GB upload
- **WHEN** a client sends `POST /api/media-items/bulk-upload` with a 4 GB file
- **THEN** nginx SHALL NOT reject the request with 413 Content Too Large

#### Scenario: PHP accepts the multipart envelope
- **WHEN** PHP receives the multipart upload
- **THEN** `upload_max_filesize` SHALL be at least `5G` and `post_max_size` SHALL be at least `6G` so the full body fits

#### Scenario: Laravel validates against the same ceiling
- **WHEN** the upload controller validates the request
- **THEN** the `max:` rule SHALL match the configured `cloudstream.media.max_upload_size` (default 5 GB)

### Requirement: Webserver timeouts tolerate slow uploads

The system SHALL configure nginx timeouts so that large uploads of slow connections are not aborted mid-stream.

#### Scenario: Upload body timeout is at least 30 minutes
- **WHEN** nginx serves the media upload endpoint
- **THEN** `client_body_timeout` and `client_header_timeout` SHALL be at least `1800s` (30 minutes)

#### Scenario: Proxy timeouts (if applicable) match upload timeouts
- **WHEN** nginx proxies the request to php-fpm
- **THEN** `proxy_read_timeout` and `proxy_send_timeout` SHALL be at least `1800s`

### Requirement: Per-file validation remains at 20 files per request

The system SHALL keep `cloudstream.media.bulk_max_files = 20` so the total payload size stays bounded per request.

#### Scenario: Bulk upload of 21 files is rejected
- **WHEN** a client posts 21 files in one bulk-upload request
- **THEN** the server SHALL respond with a 422 error and no file SHALL be written