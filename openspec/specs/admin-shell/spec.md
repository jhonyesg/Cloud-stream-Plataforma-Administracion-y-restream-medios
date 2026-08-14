## Purpose
The authenticated admin/client shell: sidebar navigation items (Inicio, Usuarios, Canales, Multimedia, Programación), the dark tech/sparks chrome language, per-section accent tinting, the cloud+play logo, and the thumb-zone floating menu button for mobile.
## Requirements
### Requirement: Sidebar nav items adapt to the user's role
The `admin-sidebar` SHALL render a "Multimedia" item linking to `/admin/media` when the authenticated user has `role=admin`. When the user has `role=client`, the same sidebar component SHALL render a "Multimedia" item linking to `/client/media`. The "Providers" item SHALL be removed from the sidebar.

#### Scenario: Sidebar shows Multimedia for admin
- **WHEN** an admin is authenticated and the layout renders
- **THEN** the sidebar SHALL contain a "Multimedia" link with `href="/admin/media"` and the folder/music icon

#### Scenario: Sidebar shows Multimedia for client
- **WHEN** a client user is authenticated and the layout renders
- **THEN** the sidebar SHALL contain a "Multimedia" link with `href="/client/media"`

#### Scenario: Sidebar highlights active item on media page
- **WHEN** an admin is on `/admin/media`
- **THEN** the "Multimedia" item in the sidebar SHALL have the active highlight class

#### Scenario: Sidebar no longer shows Providers
- **WHEN** an admin is authenticated and the layout renders
- **THEN** the sidebar SHALL NOT contain a "Providers" link

### Requirement: Sidebar icons are consistent
The "Multimedia" item SHALL use an icon SVG (folder/music related) consistent with the visual style of existing items (Inicio, Usuarios, Canales).

#### Scenario: Multimedia icon matches other sidebar items
- **WHEN** the sidebar renders
- **THEN** the "Multimedia" item SHALL display an SVG icon with `stroke-linecap="round" stroke-linejoin="round" stroke-width="2"` matching the visual treatment of the other items.

### Requirement: Chrome uses the dark tech/sparks visual language

The sidebar and topbar (the "chrome") of every authenticated layout SHALL render using the dark tech-language described below. The body's light theme, content cards, modals, toasts and dropdowns are NOT part of the chrome and stay unchanged.

#### Scenario: Sidebar background uses dark tech gradient
- **WHEN** an authenticated layout renders (admin or client)
- **THEN** the sidebar SHALL have a base background of `linear-gradient(180deg, #050816, #0a0f2c)` and SHALL include a `<x-tech-bg intensity="lite">` layer as the first decorative child of the aside, positioned `absolute inset:0 z-index:0 pointer-events:none`

#### Scenario: Topbar background uses dark tech gradient
- **WHEN** an authenticated layout renders (admin or client)
- **THEN** the topbar SHALL have a base background of `linear-gradient(90deg, #050816, #0a0f2c)` and SHALL include a `<x-tech-bg intensity="lite">` layer as the first decorative child of the header

#### Scenario: Chrome animation respects reduced motion
- **WHEN** the user OS reports `prefers-reduced-motion: reduce`
- **THEN** the animated layers of `<x-tech-bg>` SHALL set `display:none` and the static gradient SHALL remain visible
- **AND** the conic-gradient badge animation SHALL NOT run

### Requirement: Per-section accent color tints the chrome

The sidebar/topbar MUST consume the same `$active` value as today and SHALL expose the section accent as a CSS custom property `--accent` so the chrome can tint accordingly. The mapping SHALL be:

| `$active` | `--accent` value |
| --- | --- |
| `dashboard` | `#818cf8` (indigo) |
| `users` | `#38bdf8` (sky) |
| `channels` | `#34d399` (emerald) |
| `media` | `#fbbf24` (amber) |

#### Scenario: Accent is set on the chrome root
- **WHEN** any admin or client page renders with `$active` set to one of `dashboard | users | channels | media`
- **THEN** the sidebar `<aside>` and the topbar `<header>` SHALL set `style="--accent: <hex>"` using the mapped color above

#### Scenario: Active nav item shows accent glow
- **WHEN** a nav item in the sidebar is the active route
- **THEN** the item SHALL display a left bar in `--accent` and an inset background tint that uses `--accent` at ~6% opacity

#### Scenario: Topbar bottom glow uses accent
- **WHEN** the topbar renders
- **THEN** the 1px accent strip beneath the topbar SHALL be replaced with a 2px strip whose `background-color` is `--accent` and whose `box-shadow` is `0 0 12px var(--accent)`

### Requirement: Cloud+play logo replaces the "CS" badge

A shared `<x-cloud-logo>` SVG component SHALL replace every "CS" badge inside the sidebar and the topbar.

#### Scenario: Sidebar header shows cloud-logo
- **WHEN** the sidebar renders
- **THEN** the header area SHALL contain an `<x-cloud-logo size="sm">` element linked to `route('home')`, with no "CS" text badge remaining

#### Scenario: Topbar shows cloud-logo
- **WHEN** the topbar renders
- **THEN** it SHALL contain an `<x-cloud-logo size="sm">` element beside the page title, with no "CS" text badge remaining

### Requirement: Chrome animations are lite intensity

`<x-tech-bg intensity="lite">` SHALL cap its animation budget to avoid distracting from long-running admin content.

#### Scenario: Spark cap in lite mode
- **WHEN** `<x-tech-bg intensity="lite">` mounts
- **THEN** it SHALL generate at most 10 spark elements and SHALL NOT render speed streaks or diagonal bolts

#### Scenario: One aurora in lite mode
- **WHEN** `<x-tech-bg intensity="lite">` mounts
- **THEN** it SHALL render exactly one aurora element whose background uses `--accent` over a CSS `radial-gradient` mask

### Requirement: Existing sidebar state is preserved

Behavior unrelated to visuals MUST NOT change.

#### Scenario: sidebarCollapsed persists across reloads
- **WHEN** a user toggles the collapse state and reloads the page
- **THEN** `sidebarCollapsed` SHALL still be read from `localStorage('sb-collapsed')` and reflected in the rendered aside width

#### Scenario: Mobile hamburger and overlay still work
- **WHEN** the viewport is below the `sm` breakpoint and the user opens the hamburger
- **THEN** the existing overlay and `translate-x` transition SHALL behave exactly as before

### Requirement: Sidebar shows Programación item
The `admin-sidebar` SHALL render a "Programación" item linking to `/admin/scheduler` when the authenticated user has `role=admin`. When the user has `role=client`, the same sidebar component SHALL render a "Programación" item linking to `/client/scheduler`.

#### Scenario: Sidebar shows Programación for admin
- **WHEN** an admin is authenticated and the layout renders
- **THEN** the sidebar SHALL contain a "Programación" link with `href="/admin/scheduler"` and a calendar icon

#### Scenario: Sidebar shows Programación for client
- **WHEN** a client user is authenticated and the layout renders
- **THEN** the sidebar SHALL contain a "Programación" link with `href="/client/scheduler"`

#### Scenario: Sidebar highlights active item on scheduler page
- **WHEN** an admin is on `/admin/scheduler`
- **THEN** the "Programación" item in the sidebar SHALL have the active highlight class

### Requirement: Thumb-zone floating menu button on mobile
Both the admin layout (`x-admin-layout`) and the client layout (`x-client-layout`) SHALL render a circular floating action button (FAB) anchored at the bottom-right of the viewport, visible only when the viewport width is below the `md` breakpoint (768 px). The FAB SHALL open the same sidebar drawer as the topbar hamburger. The FAB SHALL be at least 56×56 px, SHALL use the active theme's accent colour, and SHALL hide whenever the bulk-selection store reports it is active.

#### Scenario: FAB visible on mobile
- **WHEN** the viewport is narrower than `md` (768 px)
- **THEN** a circular floating button SHALL be visible at `bottom-6 right-6` with `aria-label="Abrir menú"` and a hamburger icon

#### Scenario: FAB hidden on desktop
- **WHEN** the viewport is at least `md` wide
- **THEN** the floating button SHALL NOT be rendered

#### Scenario: Tapping the FAB opens the drawer
- **WHEN** the user taps the floating button on mobile
- **THEN** the sidebar drawer SHALL slide in (the same `sidebarOpen` Alpine state the topbar hamburger controls)

#### Scenario: FAB uses active theme accent
- **WHEN** the active theme is amber (media) or sky (users) or any other configured accent
- **THEN** the FAB SHALL be rendered with that accent colour as its background

### Requirement: Topbar is fixed-positioned at every breakpoint
The `<x-admin-topbar>` SHALL render its root `<header>` as `position: fixed !important` at every viewport size, anchored at `top-0`. The topbar SHALL remain visible while the page scrolls. On viewports ≥ `sm`, the left offset SHALL bind to the sidebar's collapsed state (`sm:left-64` expanded, `sm:left-16` collapsed) so the topbar slides horizontally as the user collapses the sidebar.

#### Scenario: Topbar stays visible while content scrolls on desktop
- **WHEN** a user is on a viewport ≥ `sm` and scrolls the main content
- **THEN** the topbar SHALL remain anchored at `top-0` of the viewport and SHALL NOT scroll away

#### Scenario: Topbar slides with the sidebar collapse animation
- **WHEN** the user clicks the sidebar collapse toggle and `sidebarCollapsed` flips
- **THEN** the topbar's left offset SHALL animate from `sm:left-64` to `sm:left-16` (or vice versa) within the same 200 ms transition as the sidebar

#### Scenario: Topbar is full-width on mobile
- **WHEN** the viewport is below `sm`
- **THEN** the topbar SHALL span from `left-0` to `right-0` (no `sm:left-*` class applies)

### Requirement: Page content is not hidden under the topbar
The `<main>` element in both `admin-layout.blade.php` and `client-layout.blade.php` SHALL include `pt-20` (5 rem) so page content starts below the fixed topbar (which is `h-16` ≈ 64 px tall) with breathing room.

#### Scenario: Page content starts below the topbar
- **WHEN** the page renders with a long scrollable list
- **THEN** the first row of content SHALL be visually below the fixed topbar (not underneath it)

### Requirement: Topbar carries no brand mark
The `<x-admin-topbar>` SHALL NOT render any `<a>` element wrapping the cloud logo or the brand wordmark. The sidebar header remains the only place where the cloud logo and the wordmark are rendered.

#### Scenario: Topbar contains only functional controls
- **WHEN** the topbar renders
- **THEN** it SHALL contain the collapse toggle, the role badge, and the user dropdown — and SHALL NOT contain a brand link or icon

### Requirement: Sidebar is fixed-positioned on desktop
On viewports at the `sm` breakpoint and above, the `<aside>` rendered by `admin-sidebar` SHALL be `position: fixed` (not `static`), anchored to `inset-y-0 left-0`. The sidebar SHALL remain visible while the page scrolls; it SHALL NOT scroll away with the content.

#### Scenario: Sidebar stays visible while content scrolls
- **WHEN** a user is on a viewport ≥ 640 px and scrolls the main content (e.g. a long channels list)
- **THEN** the sidebar SHALL remain anchored to the left edge and the navigation items SHALL stay reachable

#### Scenario: Collapse shrinks the sidebar and the main area
- **WHEN** the user clicks the collapse toggle and `sidebarCollapsed` becomes `true`
- **THEN** the sidebar SHALL shrink to `w-16` AND the main content wrapper SHALL reduce its left margin to match, so the layout stays flush

#### Scenario: Expand restores the layout
- **WHEN** the user clicks the collapse toggle again and `sidebarCollapsed` becomes `false`
- **THEN** the sidebar SHALL return to `w-64` AND the main content wrapper SHALL restore its left margin, both within the existing 200 ms transition

### Requirement: Brand wordmark lives only in the sidebar header
The topbar's home link SHALL NOT render the literal text "Cloudstream" anymore. The cloud logo `<x-cloud-logo>` SHALL stay as the visual anchor for the link. The sidebar header remains the only place that renders the brand wordmark text.

#### Scenario: Topbar has only the cloud logo on home link
- **WHEN** the topbar renders on any page
- **THEN** the home link SHALL contain `<x-cloud-logo>` and SHALL NOT contain any text node reading "Cloudstream"

#### Scenario: Sidebar still carries the brand
- **WHEN** the sidebar header renders
- **THEN** it SHALL continue to show the cloud logo AND the wordmark text `Cloudstream`

