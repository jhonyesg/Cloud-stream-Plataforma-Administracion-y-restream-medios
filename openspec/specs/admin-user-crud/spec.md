## Purpose
Administrative CRUD for `users`: list, create, edit, delete, and self-protection rules. The admin users index is the source of truth for which user accounts exist and what role/status they have.
## Requirements
### Requirement: Admin users index exposes create and per-row actions
The admin users index view SHALL render a `[+ Nuevo usuario]` button at the top of the list and `[Editar]` / `[Eliminar]` action buttons on every row. Both actions SHALL open modals (no page navigation away from the index).

#### Scenario: Create button is visible on users index
- **WHEN** an admin loads `GET /admin/users`
- **THEN** the rendered HTML SHALL contain a button or link whose accessible name includes "Nuevo usuario" and whose click handler opens a modal (not a navigation)

#### Scenario: Row actions are rendered for every user
- **WHEN** an admin loads `GET /admin/users` and there are N users in the result set
- **THEN** the rendered HTML SHALL contain N action controls labeled "Editar" and N action controls labeled "Eliminar" (one pair per row)

### Requirement: Admin can create a user via modal
The system SHALL accept a `POST /admin/users` request with the user's `username`, `email`, `display_name`, `password`, `role`, `status` and optional `owner_id`. On success the server SHALL respond 2xx with a JSON payload representing the created user, and the modal SHALL close and the users list SHALL refresh (or re-render with the new row visible).

#### Scenario: Successful user creation
- **WHEN** an admin submits a valid payload to `POST /admin/users`
- **THEN** the server SHALL return 201 (or 200) with the created user JSON, the row SHALL appear in the table on next render, and the create modal SHALL close

#### Scenario: Validation errors surface in the create modal
- **WHEN** an admin submits an invalid payload (e.g. duplicate email, weak password, missing username) to `POST /admin/users`
- **THEN** the server SHALL return 422 with field errors AND the modal SHALL remain open and display those errors next to the corresponding inputs

### Requirement: Admin can update a user via modal
The system SHALL accept a `PUT /admin/users/{id}` (or `PATCH`) request with the same editable fields, where `password` is optional and omitted means "do not change". On success the modal SHALL close and the corresponding row SHALL reflect the new values.

#### Scenario: Updating display name updates the row
- **WHEN** an admin edits user `u1` changing `display_name` from "A" to "B" and submits via the edit modal
- **THEN** the server SHALL respond 2xx, the modal SHALL close, and the row for `u1` in the users table SHALL display "B"

#### Scenario: Omitted password keeps the existing one
- **WHEN** an admin updates a user without supplying a new password
- **THEN** the user's existing password hash SHALL remain unchanged after the request

### Requirement: Admin can delete a user via confirmation modal
The system SHALL accept a `DELETE /admin/users/{id}` request. The user list SHALL include a `[Eliminar]` action that opens a confirmation modal (`<x-confirm-delete-modal>`) showing the user name and requiring explicit confirmation before dispatching `DELETE`.

#### Scenario: Confirmation modal blocks accidental deletes
- **WHEN** an admin clicks `[Eliminar]` on a user row
- **THEN** a confirmation modal SHALL open showing the target user's name; the `DELETE` request SHALL NOT be dispatched until the admin clicks the confirm button

#### Scenario: Confirmed delete removes the row
- **WHEN** the admin confirms deletion in the modal
- **THEN** the server SHALL respond 2xx, the modal SHALL close, and the row SHALL disappear from the users table on next render

### Requirement: Non-admin users cannot reach admin user endpoints
Users without `role=admin` SHALL receive 403 (or redirect) when calling `POST/PUT/DELETE /admin/users*`. The `create-user` and `edit-user` modals SHALL NOT be rendered for non-admin viewers (defense in depth).

#### Scenario: Non-admin POST is rejected
- **WHEN** a user with `role=client` calls `POST /admin/users` directly
- **THEN** the server SHALL respond 403 (or a redirect), and no user SHALL be created

### Requirement: Self-deletion protection
The system SHALL reject attempts by an admin to delete their own account via `DELETE /admin/users/{id}` when `{id}` matches the requesting admin's id. The API SHALL return 422 with an error message.

#### Scenario: Admin cannot delete themselves
- **WHEN** an admin submits `DELETE /admin/users/{self.id}` for their own account
- **THEN** the server SHALL respond 422 with an error like "cannot delete your own account" and the user SHALL NOT be removed

### Requirement: User create and edit modals expose the password via reveal toggle
The admin user create modal and user edit modal SHALL render the `password` field through the shared `<x-password-input>` component so that admins can verify the password they typed before submitting.

#### Scenario: Create modal reveals the new password
- **WHEN** an admin opens the user create modal and clicks the eye toggle on the password field
- **THEN** the typed characters SHALL become visible, and clicking it again SHALL mask them.

#### Scenario: Edit modal reveal works without affecting the existing hash
- **WHEN** an admin opens the user edit modal, reveals the password field, and leaves it empty
- **THEN** the existing password hash SHALL remain unchanged on submit (the "Omitted password keeps the existing one" rule is preserved).

