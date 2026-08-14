# media-hygiene — Histórico de cambios

Capacidad activa: `openspec/specs/media-hygiene/spec.md`
Compactado el 2026-07-20 por el change `compact-archive-r1`.

## Línea cronológica

| Fecha | Archive | Diseño | Proposal | Tareas | Estado |
|---|---|---|---|---|---|
| 2026-07-15 | `db-hygiene-orphan-cleanup` | 124 | 43 | **0/35** | ⚠ archivado sin cerrar |

## Pendiente de decisión humana

El archive `db-hygiene-orphan-cleanup` está archivado **con 0 de 35 tareas cerradas**. La capacidad activa `media-hygiene` (96 líneas) ya existe y el comando `media:hygiene` está documentado en AGENTS.md, lo que sugiere implementación parcial o total.

Opciones a evaluar en un change dedicado:

1. **Reabrir y tachar**: auditar el código actual, marcar las tareas ya implementadas y dejar el resto explícitamente fuera de scope.
2. **Descartar definitivamente**: documentar que el archive fue reemplazado por la capacidad activa y archivarlo como "superseded-by `media-hygiene`".
3. **Fusionar**: integrar las tareas aún pendientes en una nueva propuesta sobre `media-hygiene`.

Recomendación: opción 2 (la más segura — el comando `media:hygiene` y el spec activo ya cumplen el contrato).

## Diseños ★ referencia (no eliminar)
- `db-hygiene-orphan-cleanup/design.md` (124).

## Spec activo
`openspec/specs/media-hygiene/spec.md`.
