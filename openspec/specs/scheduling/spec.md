## MODIFIED Requirements

### Requirement: Replicación de plantilla a otro mes
Clonar plantilla SHALL clonar también `program_timeline_items` generados para cada bloque, no solo bloques. `is_replicated=true` y `cloned_from_id` se mantienen. Si destino existe, se reemplaza dentro de transacción incluyendo timeline_items. `emission_state` no se toca.

#### Scenario: Replicar julio a agosto con timeline_items
- **WHEN** se replica plantilla T Julio con 10 timeline_items por día
- **THEN** nueva plantilla Agosto SHALL tener mismos timeline_items con fechas recalculadas para agosto

#### Scenario: Replicación reemplaza timeline existente
- **WHEN** destino ya tiene timeline_items
- **THEN** existente se borra y se reemplaza por copia de origen

### Requirement: API de scheduling
Se mantiene listado base y se añaden:
- `GET /api/schedule-templates/{id}/days/{day}/timeline` lista timeline 00-24 con starts_at y overflow
- `POST /api/schedule-templates/{id}/days/{day}/timeline/insert-cue` inserta cuña como interrupción con split
- `DELETE /api/schedule-templates/{id}/days/{day}/timeline/{timelineItemId}` borra solo si futuro
- `POST /api/schedule-templates/{id}/days/{day}/timeline/rebuild` regenera desde playlist (solo si canal offline o confirmación)

Todos respetan scope por canal. Ediciones de días live SHALL aplicar regla solo-futuro.

#### Scenario: Timeline del día expone shift overflow
- **WHEN** GET timeline día con 3 cuñas insertadas que suman 6 min overflow
- **THEN** respuesta SHALL incluir `overflow_sec=360` y `items[]` con `starts_at_sec` recalculados

#### Scenario: Insertar cuña en día live solo futuro
- **WHEN** canal live ahora=14:25 e intenta `insert-cue at_sec=14:00`
- **THEN** SHALL responder 422 y no modificar

#### Scenario: Insertar cuña en futuro live
- **WHEN** `insert-cue at_sec=15:00` cuando ahora=14:25
- **THEN** SHALL crear split si at_sec cae dentro de contenido y desplazar posteriores

### Requirement: Cuñas como media_items tipo ad siguen siendo fuente de catálogo
Cuñas siguen siendo `media_items.kind='ad'` con duración `duration_sec`. Inserción en timeline referencia `media_item_id` de cuña. Validación SHALL rechazar cuña con `status!=ready`.

#### Scenario: Cuña no ready rechazada
- **WHEN** se intenta insertar media status=processing como cuña
- **THEN** 422 "Cuña no disponible"