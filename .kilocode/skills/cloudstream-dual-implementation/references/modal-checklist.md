# Modal Mounting Checklist

Every Blade modal component (`<x-foo-modal />`) used by a view must be **mounted** in that view (or in its layout, if globally used). A button calling `$store.modals.open('foo', ...)` against a non-mounted modal is a silent failure — the click does nothing, no error in console.

## Why This Exists

In this project, the `live-viewer` modal was added to client views but missed in `admin/scheduler/index.blade.php`. The button was there, the controller data was there, the JS worked — but the modal wasn't mounted, so clicking "Ver vivo" did nothing. The user only discovered this after the client side was fully polished. A check against this list at every change would have caught it immediately.

## Current Per-View Mount Inventory

Run this command to regenerate this list after any modal-related change:

```bash
grep -rn "x-.*-modal" resources/views/ --include="*.blade.php" | sort
```

### Admin views

| View | Mounted modals |
|---|---|
| `admin/channels/index.blade.php` | `<x-channel-create-modal />`, `<x-channel-edit-modal />`, `<x-confirm-delete-modal />`, `<x-virtual-screen-editor-modal />`, `<x-virtual-screen-preview-viewer-modal />`, `<x-live-viewer-modal />` |
| `admin/scheduler/index.blade.php` | `<x-live-viewer-modal />` (added during the dual-implementation fix) |
| `admin/users/index.blade.php` | (own internal modals, none shared) |
| `admin/media/index.blade.php` | (uses global modals via the media-card x-data — `play-media`, `rename-media`, `confirm-delete`) |
| `admin/dashboard.blade.php` | (none — dashboard doesn't open modals) |

### Client views

| View | Mounted modals |
|---|---|
| `client/channels/index.blade.php` | `<x-virtual-screen-editor-modal />`, `<x-virtual-screen-preview-viewer-modal />`, `<x-live-viewer-modal />` (client has no shared `edit-channel` modal — it uses an inline form instead) |
| `client/scheduler/index.blade.php` | `<x-live-viewer-modal />` + many scheduler-specific modals (create-playlist, media-library-picker, playlist-editor-fullscreen, replicate, scheduler-dialogs) |
| `client/media/index.blade.php` | (same global modals as admin/media — `play-media`, `rename-media`, `confirm-delete`) |
| `client/dashboard.blade.php` | (none) |

## Workflow: Adding a New Modal-Triggering Button

When adding a new button that calls `$store.modals.open('foo', ...)`:

1. **Identify the target view(s).** Use `file-pairs.md` to find the admin/client counterpart.
2. **Verify the modal is mounted in each target.** Grep:
   ```bash
   grep -n "x-foo-modal" resources/views/admin/channels/index.blade.php
   grep -n "x-foo-modal" resources/views/client/channels/index.blade.php
   ```
3. **If missing, add it.** Place before the closing `</x-{role}-layout>` tag.
4. **Verify the modal name matches.** The string in `open('foo', ...)` must equal the `name="foo"` attribute on the modal component.
5. **Test in BOTH surfaces** by hard-refreshing and clicking the button in each.

## Common Pitfalls

- **Modal exists in `resources/views/components/` but isn't mounted** → the file is just an unused template. The modals store doesn't care about the file; it cares whether the component is in the rendered DOM.
- **Modal mounted in layout, not in view** → layout-level mounts are great for global modals. But view-specific modals (like channel-edit) MUST be in the view that uses them.
- **Mounting in `x-cloak` but never rendered** → the modals store uses `x-show` to display, but if the component is wrapped in `x-if` or removed from DOM, the store can't find it.
- **Wrong `name` attribute** → `<x-foo-modal name="bar" />` won't open when the button calls `open('foo', ...)`. Grep for the exact string.

## Self-Check Command

Run before declaring a change done:

```bash
# For each unique modal name in open() calls, verify the modal is mounted
grep -rhoE "modals\.open\('[^']+'" resources/views/ | sort -u | \
  while read line; do
    name=$(echo "$line" | sed -E "s/modals\.open\('([^']+)'.*/\1/")
    count=$(grep -rl "x-${name}-modal" resources/views/ 2>/dev/null | wc -l)
    if [ "$count" -eq 0 ]; then
      echo "⚠️  Modal '$name' is called but never mounted"
    fi
  done
```

If this prints any `⚠️`, the change is incomplete.