## ADDED Requirements

### Requirement: Zero CDN scripts in rendered HTML
The application SHALL NOT load any of Tailwind CSS, Alpine.js, or web fonts from a third-party CDN at runtime. Every script and stylesheet referenced in the rendered HTML SHALL resolve to a same-origin asset served from the project's own `public/` directory.

#### Scenario: No third-party hosts in the layout
- **WHEN** the browser renders `GET /admin/media` (or any other admin/client/auth page)
- **THEN** the HTML SHALL NOT contain any `<script src="https://cdn.tailwindcss.com">`, `<script src="https://unpkg.com">`, or `<link href="https://fonts.bunny.net">` tag. Every `<script>` and `<link rel="stylesheet">` SHALL use a relative path that resolves to `/js/...` or `/fonts/...` on the same origin.

#### Scenario: Tailwind play CDN runtime serves locally
- **WHEN** the browser loads `/js/tailwind.js`
- **THEN** the response SHALL be served by the application itself (same origin) and SHALL be the Tailwind play JIT engine so dynamic class interpolation (`bg-{{ $color }}-100`) keeps working.

#### Scenario: Alpine serves locally
- **WHEN** the browser loads `/js/alpine.min.js`
- **THEN** the response SHALL be served by the application itself (same origin) and SHALL be the Alpine.js v3 build.

#### Scenario: Figtree weights are local
- **WHEN** the browser renders `welcome.blade.php`
- **THEN** the page SHALL declare `@font-face` rules pointing to `/fonts/Figtree-400.woff2`, `/fonts/Figtree-500.woff2`, and `/fonts/Figtree-600.woff2` on the same origin. The HTML SHALL NOT contain any `fonts.bunny.net` or `fonts.googleapis.com` URL.

### Requirement: No production CDN warning in the browser console
The Tailwind play CDN engine surfaces a `cdn.tailwindcss.com should not be used in production` warning whenever it detects the canonical CDN URL. With the asset served locally this warning SHALL NOT fire.

#### Scenario: Console clean on page load
- **WHEN** the user opens DevTools and loads `/admin/media`
- **THEN** the console SHALL NOT contain the `cdn.tailwindcss.com should not be used in production` warning (the script's URL is the local asset, not the CDN).

### Requirement: No build step required
Adding a new Blade view or a new dynamic class SHALL NOT require running any build tool. The Tailwind play JIT engine handles new utilities at runtime; the Alpine bundle and font files are static and never change.

#### Scenario: New view with new utilities works without a build
- **WHEN** a developer adds a new Blade view under `resources/views/admin/` that uses a previously-unused utility class (e.g. `bg-teal-500`)
- **THEN** the page SHALL render correctly in the browser without any `npm` or build command being run, because the play JIT engine discovers the class on page load.