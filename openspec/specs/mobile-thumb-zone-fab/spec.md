# mobile-thumb-zone-fab Specification

## Purpose
TBD - created by archiving change responsive-mobile-fab-menu. Update Purpose after archive.
## Requirements
### Requirement: Thumb-zone floating menu button on mobile
The admin and client layouts SHALL render a circular floating action button anchored at the bottom-right of the viewport, visible only when the viewport width is below the `md` breakpoint. The button SHALL open the same sidebar drawer as the topbar hamburger and SHALL be a minimum 56×56 px tap target.

#### Scenario: FAB is visible on mobile and hides on desktop
- **WHEN** the viewport is narrower than the `md` breakpoint (768 px)
- **THEN** a circular floating button SHALL be visible at `bottom-6 right-6` with an `aria-label="Abrir menú"` and a hamburger icon.

- **WHEN** the viewport is at least the `md` breakpoint
- **THEN** the floating button SHALL NOT be rendered (`md:hidden`).

#### Scenario: Tapping the FAB opens the drawer
- **WHEN** the user taps the floating button on mobile
- **THEN** the sidebar drawer SHALL slide in (the same `sidebarOpen` state that the topbar hamburger controls).

#### Scenario: FAB reads the active theme accent
- **WHEN** the user is on `/admin/users` (theme `sky`)
- **THEN** the FAB SHALL be rendered with the sky accent colour.

- **WHEN** the user is on `/admin/media` (theme `amber`)
- **THEN** the FAB SHALL be rendered with the amber accent colour.

### Requirement: FAB hides while bulk-selection mode is active
When the bulk-selection store reports selection mode is enabled, the floating menu button SHALL be hidden so it does not collide with the bulk action bar that also occupies the bottom of the viewport.

#### Scenario: FAB hides when the user clicks "Seleccionar"
- **WHEN** the user clicks the "Seleccionar" toggle on `/admin/media`
- **THEN** the FAB SHALL disappear (selection mode is now active and the bulk action bar will appear at the bottom).

#### Scenario: FAB reappears when the user exits selection mode
- **WHEN** the user clicks "Salir" in the bulk action bar (or clears the selection)
- **THEN** the FAB SHALL reappear at `bottom-6 right-6`.

### Requirement: Viewport meta supports safe-area insets
Both admin and client layouts SHALL declare `viewport-fit=cover` on the viewport meta tag so future safe-area-inset styling works on notched devices without further meta changes.

#### Scenario: Meta tag includes viewport-fit
- **WHEN** the page renders
- **THEN** the `<meta name="viewport">` tag SHALL include `width=device-width, initial-scale=1, viewport-fit=cover`.

### Requirement: Modal body scrolls internally on short phones
The `app-modal` component SHALL cap its inner panel's height so that the modal never exceeds the viewport, and SHALL let the body inside the modal scroll when the content is taller than the available space.

#### Scenario: Modal stays inside the viewport
- **WHEN** a modal opens on a viewport shorter than 600 px and the modal body is taller than the available space
- **THEN** the modal panel SHALL be capped at `calc(100vh - 2rem)` and the body inside the modal SHALL scroll independently of the page.

