# virtual-screen-editor Specification (retired)

## Purpose

Esta capacidad está **retirada** desde 2026-07-20. Sus requisitos fueron absorbidos por `emision-output-config`, que describe el editor visual de configuración de salida del playout de Emisión (canvas a la resolución configurada, drag/resize del logo, picker de biblioteca, preview FFmpeg, preview PNG con GD library) en el módulo conceptual correcto. Este archivo se conserva únicamente como referencia histórica del contrato OpenSpec anterior.

**Asimetría conocida** (deuda explícita, no resuelta en esta change):
- spec OpenSpec     →  `emision-output-config`
- nombre del modal  →  `<x-virtual-screen-editor-modal>` (Alpine store `virtual-screen-editor`)
- nombre del visor  →  `<x-virtual-screen-preview-viewer-modal>`

Ver `openspec/specs/virtual-screen-editor/CHANGELOG.md` para la línea cronológica y los archives que documentaron el editor.

## Requirements

### Requirement: La capacidad está retirada
La capacidad `virtual-screen-editor` no SHALL representar requisitos activos. Toda la funcionalidad de UI previa (drag/resize, picker, preview) vive en `emision-output-config`.

#### Scenario: Devtools lee este spec
- **WHEN** un dev lee `openspec/specs/virtual-screen-editor/spec.md`
- **THEN** SHALL entender inmediatamente que está retirada y SHALL ser dirigido a `openspec/specs/emision-output-config/spec.md`
