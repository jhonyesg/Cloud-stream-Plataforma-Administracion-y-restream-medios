# password-input-toggle Specification

## Purpose
TBD - created by archiving change modernize-modals-and-password-toggle. Update Purpose after archive.
## Requirements
### Requirement: Shared password input with reveal toggle
The system SHALL provide a reusable `<x-password-input>` Blade Component that renders a password `<input>` alongside a toggle button with an eye icon. Pressing the toggle SHALL switch the input's `type` between `password` and `text`, so the user can verify the value they typed. The toggle SHALL be a `<button type="button">` with an accessible label and SHALL never submit the surrounding form.

#### Scenario: Default state masks the value
- **WHEN** `<x-password-input name="password">` renders inside a form
- **THEN** the rendered HTML SHALL contain an `<input type="password" name="password">` and a `<button type="button">` with an accessible label that indicates the toggle will reveal the password.

#### Scenario: Toggle reveals the value
- **WHEN** the user clicks the toggle button
- **THEN** the input's `type` SHALL become `text` and the icon SHALL switch to the eye-off state, so the typed characters become visible on screen.

#### Scenario: Toggle hides the value again
- **WHEN** the user clicks the toggle button a second time
- **THEN** the input's `type` SHALL return to `password` and the icon SHALL switch back to the eye state.

#### Scenario: Toggle does not submit the form
- **WHEN** the toggle button is rendered inside a `<form>`
- **THEN** the button SHALL have `type="button"` so clicking it SHALL NOT trigger form submission or page navigation.

### Requirement: Password input exposes colour and error props
The `<x-password-input>` component SHALL accept a `color` prop (one of `indigo`, `emerald`, `amber`, `rose`, `sky`, defaulting to `indigo`) used for the focus ring and the toggle hover colour. It SHALL also render inline validation errors under the input by reading `$store.modals.errors[name]` so the same 422-error flow used by `<x-form-input>` keeps working.

#### Scenario: Colour prop is reflected on focus
- **WHEN** `<x-password-input color="amber">` is used
- **THEN** the rendered `<input>` SHALL have a focus ring colour matching the `amber` accent.

#### Scenario: Validation error renders under the field
- **WHEN** the form is submitted and the server returns 422 with an error keyed on the password input's `name`
- **THEN** the component SHALL render that error message under the input without any extra wiring in the consuming view.

