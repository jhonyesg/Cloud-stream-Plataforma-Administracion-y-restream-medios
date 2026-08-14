# virtual-screen — Histórico de cambios

Capacidad activa: `openspec/specs/virtual-screen/spec.md`

## 2026-07-20 (tarde) — re-montaje de la capacidad

El sistema de emisión completo (daemon gst, supervisor, orchestrator, servicios,
rutas, vistas, config `emision.*`) fue desmontado y eliminado para
reestructurarlo desde cero. Como parte de ello:

- El spec `emision-output-config` (sucesor anterior de esta capacidad) fue
  **eliminado** junto con los demás 10 specs de emisión/playout.
- Esta capacidad `virtual-screen` **vuelve a ser la spec activa** que describe
  la configuración de salida del canal. El editor se reubicó a la vista de
  Canales (botón "Pantalla" por canal) y ya no depende de un módulo de emisión.
- El contenido anterior que decía "retirada, ver emision-output-config" fue
  reemplazado por la descripción activa actual en `spec.md`.

Las entradas previas a continuación quedan como trazabilidad histórica del
movimiento a `emision-output-config` (ya inexistente).

## 2026-07-20 — movimiento a `emision-output-config`
Movida el 2026-07-20 por el change `move-virtual-screen-to-emision`.

## Estado actual
La capacidad `virtual-screen` queda **vacía** (todos los requisitos marcados REMOVED) y se considera subsumida por `emision-output-config`.

## 2026-07-20 — movimiento a `emision-output-config`
**Motivo**: la tabla `virtual_screens` (mapeada por `App\Models\VirtualScreen`) almacena 100 % de los parámetros del playout engine (`FfmpegCommandBuilder` lee `width`, `height`, `fps`, `video/audio_bitrate_kbps`, `output_protocol`, `output_url`, `logo_*`). El campo `emission_state` es estado del motor. Conceptualmente es configuración de salida del playout de Emisión, no del canal.

**Sucesor**: `openspec/specs/emision-output-config/spec.md` (253 líneas, 15 Requirements).

**Asimetría conocida** (deuda explícita, no resuelta en esta change):
- spec OpenSpec     →  `emision-output-config`
- tabla PostgreSQL  →  `virtual_screens`
- clase Eloquent    →  `App\Models\VirtualScreen`
- URL API           →  `/api/virtual-screens/{id}`
- nombre del modal  →  `<x-virtual-screen-editor-modal>` (Alpine store `virtual-screen-editor`)
- texto UX botón   →  "Pantalla Virtual"

Alinear cualquiera de estos elementos al nuevo nombre se aborda en una change futura y separada si se decide.

## Línea cronológica de archives que tocaron esta capacidad

Cambios archivados que listaban `virtual-screen` como "Modified Capabilities":
- `2026-07-14-cloudstream-platform-v1` (semilla)
- `2026-07-14-multimedia-library-module`
- `2026-07-15-normalize-resolution-to-virtual-screen`
- `2026-07-15-extend_virtual_screens_with_logo_and_output_config` (migración)

Sus `proposal.md` quedan como trazabilidad histórica. Las Requirements que añadieron viven ahora en `emision-output-config`.
