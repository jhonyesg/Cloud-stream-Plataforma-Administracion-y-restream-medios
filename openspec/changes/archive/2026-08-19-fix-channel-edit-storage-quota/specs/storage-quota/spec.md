## ADDED Requirements

### Requirement: Admin can edit a channel's storage quota from the channel-edit modal
The admin channel-edit modal (`resources/views/components/channel-edit-modal.blade.php`) SHALL render a numeric input labeled "Límite de almacenamiento (GB)" pre-populated from the channel's current `storage_limit_bytes` (in GB, 1 decimal; empty when `NULL`). The modal SHALL send `storage_limit_gb` in the PUT body of `PUT /admin/channels/{channel}`. Empty string SHALL be interpreted as "unlimited" (NULL). The modal SHALL display the channel's current `used_bytes` formatted as human-readable bytes next to the field.

#### Scenario: Admin sets a limit on an existing channel
- **WHEN** an admin opens the edit modal for a channel with `storage_limit_bytes = 107374182400` (100 GB) and changes the field to `50`
- **THEN** after submitting, the channel's `storage_limit_bytes` SHALL be `53687091200` (50 GB)

#### Scenario: Admin removes the limit (sets unlimited)
- **WHEN** an admin opens the edit modal for a channel and clears the field, leaving it empty
- **THEN** after submitting, the channel's `storage_limit_bytes` SHALL be `NULL`

#### Scenario: Admin opens the modal and sees current usage
- **WHEN** an admin opens the edit modal for a channel with `used_bytes = 5368709120` (5 GB)
- **THEN** the modal SHALL display "Usado actualmente: 5 GB" next to the storage field

#### Scenario: Invalid input is rejected
- **WHEN** an admin submits a non-numeric value (e.g. `abc`) or a negative number
- **THEN** the server SHALL respond HTTP 422 with a validation error and the channel's `storage_limit_bytes` SHALL NOT change
