## ADDED Requirements

### Requirement: Uploader accepts a preselected channel id

The `<x-media-uploader>` component SHALL accept a `preselectedChannelId` prop. When set, the channel select dropdown is hidden and the value is used as the upload target.

#### Scenario: Preselected channel hides the dropdown
- **WHEN** the component is rendered with `:preselectedChannelId="$channelId"`
- **THEN** the channel `<select>` element SHALL NOT be rendered, and the internal Alpine `channelId` state SHALL be initialized to that value

#### Scenario: No preselection shows the dropdown
- **WHEN** the component is rendered with `:preselectedChannelId="null"` (or not set)
- **THEN** the channel `<select>` SHALL be rendered and the user SHALL pick a channel before uploading

#### Scenario: Both `preselectedChannelId` and `channelId` are accepted
- **WHEN** a caller passes either prop
- **THEN** the component SHALL treat them as the same value (alias for backward compatibility)

### Requirement: Client layout auto-selects the user's first channel

The client media view SHALL auto-select the user's accessible channel in the uploader when exactly one channel is accessible.

#### Scenario: Single-channel client sees no dropdown
- **WHEN** a client with exactly one accessible channel opens the uploader
- **THEN** the channel select SHALL be hidden and uploads SHALL target that channel automatically

#### Scenario: Multi-channel client still sees the dropdown
- **WHEN** a client with two or more accessible channels opens the uploader
- **THEN** the channel select SHALL be rendered and the user SHALL pick explicitly

#### Scenario: Admin layout does not auto-select
- **WHEN** an admin user opens the uploader
- **THEN** the channel select SHALL always be rendered (no preselection)