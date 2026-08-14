# virtual-screen-editor — Histórico de cambios

Capacidad activa: `openspec/specs/virtual-screen-editor/spec.md`
Movida el 2026-07-20 por el change `move-virtual-screen-to-emision`.

## Estado actual
La capacidad `virtual-screen-editor` queda **vacía** (todos los requisitos marcados REMOVED) y se considera subsumida por `emision-output-config`.

## 2026-07-20 — movimiento a `emision-output-config`
**Motivo**: el editor visual (canvas, drag/resize del logo, picker de biblioteca, preview FFmpeg, preview PNG con GD library) es la UI de configuración de salida del playout de Emisión. El preview PNG ("Vista previa") consume `logo_media_item_id`, `logo_x/y/w/h`, `logo_opacity`, `width`, `height` — todos parámetros de emisión. La frase "algo vital para emitir" del usuario resume por qué pertenece al módulo Emisión.

**Sucesor**: `openspec/specs/emision-output-config/spec.md`. Requirements equivalentes:
- "Punto de entrada al editor en la lista de canales" (era entry-point)
- "Editor renderiza canvas a la resolución configurada" (era canvas)
- "El usuario puede arrastrar el logo" (era drag)
- "El usuario puede redimensionar el logo" (era resize)
- "Fuente del logo es media existente o nuevo upload" (era picker)
- "El preview del comando FFmpeg refleja el estado actual" (era FFmpeg command preview)
- "Vista previa retorna una imagen PNG con overlay del logo" (era testPreview)
- "El modal expone 'Vista previa' (no 'Probar emisión')" (era modal rule)
- "Save persiste la configuración" (era save)

**Asimetría conocida** (deuda explícita, no resuelta en esta change):
- spec OpenSpec     →  `emision-output-config`
- nombre del modal  →  `<x-virtual-screen-editor-modal>` (Alpine store `virtual-screen-editor`)
- nombre del visor  →  `<x-virtual-screen-preview-viewer-modal>`

Alinear los nombres de componentes UI al nuevo spec se aborda en una change futura si se decide.

## Línea cronológica de archives que tocaron esta capacidad

Cambios archivados que listaban `virtual-screen-editor` como "Modified Capabilities":
- `2026-07-15-virtual-screen-visual-editor` (semilla)
- `2026-07-15-virtual-screen-preview-as-image`
- `2026-07-15-dedicated-virtual-screen-preview-viewer`

Sus `proposal.md` quedan como trazabilidad histórica. Las Requirements que aportaron viven ahora en `emision-output-config`.

## Nota de compactación
Esta capacidad también fue compactada en `compact-archive-r1` (archivado el 2026-07-20) bajo `openspec/specs/virtual-screen-editor/CHANGELOG.md`. Este CHANGELOG reemplaza al anterior introduciendo el movimiento de capacidad.
