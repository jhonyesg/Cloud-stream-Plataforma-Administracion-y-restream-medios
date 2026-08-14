## ADDED Requirements

### Requirement: Profile modal opens from the topbar dropdown
The system SHALL open a modal named `profile-modal` when the user selects "Mi perfil" from the topbar dropdown. The modal SHALL contain a form with the current user's editable profile fields (display_name, username, email).

#### Scenario: Profile modal opens
- **WHEN** the user clicks "Mi perfil" in the topbar dropdown
- **THEN** the modal with name `profile-modal` SHALL be visible (`x-show="show"`) and SHALL contain a form bound to `PATCH /profile`

#### Scenario: Profile modal is dismissable
- **WHEN** the `profile-modal` is open
- **THEN** the user SHALL be able to close it by pressing `Escape`, clicking the backdrop, or clicking an explicit close affordance

### Requirement: Password modal opens from the topbar dropdown
The system SHALL open a modal named `password-modal` when the user selects "Cambiar contraseña" from the topbar dropdown. The modal SHALL contain three password fields: current password, new password, new password confirmation.

#### Scenario: Password modal opens
- **WHEN** the user clicks "Cambiar contraseña" in the topbar dropdown
- **THEN** the modal with name `password-modal` SHALL be visible and SHALL contain three password input fields and a submit button bound to `PUT /password`

### Requirement: Modals submit via fetch and update without full reload
The profile modal and password modal SHALL submit their forms via `fetch()` (Alpine) rather than a classic form POST. On a 2xx response, the modal SHALL close and the topbar SHALL reflect the updated display name without a full page reload.

#### Scenario: Successful profile update closes modal and updates topbar
- **WHEN** the user submits the profile form and the server responds 200 with `{ user: { display_name: "Nuevo" } }`
- **THEN** the profile modal SHALL close AND the topbar SHALL render "Nuevo" as the user's name on the next render

#### Scenario: Validation errors are displayed inline
- **WHEN** the profile or password submission fails with 422 and a JSON errors payload
- **THEN** the modal SHALL remain open and SHALL display field-level error messages under each input

#### Scenario: Network or server failure is reported
- **WHEN** the fetch rejects (network error) or the server responds with 5xx
- **THEN** the modal SHALL display a generic error banner and SHALL NOT close

### Requirement: Existing profile endpoints remain the source of truth
The modals MUST call the existing `ProfileController` endpoints (`PATCH /profile`, `PUT /password`). No new profile-related routes SHALL be added. The standalone `/profile` page SHALL remain accessible at its current URL for direct linking.

#### Scenario: PATCH /profile is the only profile update route
- **WHEN** a network inspection is performed during profile modal submission
- **THEN** the only profile-related request SHALL be `PATCH /profile` (or `PUT /password` for the password modal)

#### Scenario: Standalone profile page still renders
- **WHEN** an authenticated user navigates directly to `/profile`
- **THEN** the standalone profile edit page SHALL render successfully (no regression)