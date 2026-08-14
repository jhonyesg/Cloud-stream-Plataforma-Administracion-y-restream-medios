## ADDED Requirements

### Requirement: Playlists por canal
El sistema SHALL permitir crear playlists asociadas a canal via `channel_id`. Playlist SHALL tener `name`, `description`, `loop`, `is_default`. A partir de este cambio, playlist se considera origen editorial; su materialización a emisión pasa por `program_timeline_items` via `TimelineBuilder`. Playlist puede contener mismo `media_item_id` múltiples veces con posiciones distintas para soportar split de película para cuñas.

#### Scenario: Crear playlist con duplicado mismo media para cuña futura
- **WHEN** se agrega media M en posición 2 y también en posición 4 con cue distinta
- **THEN** SHALL permitirse porque soporta `película -> cuña -> reanudar`

### Requirement: Playlist default única por canal
El sistema SHALL permitir máximo una playlist con `is_default=true` por canal (constraint UNIQUE parcial). Toda nueva playlist SHALL tener `is_default=false` salvo que se indique lo contrario y sea la primera.

#### Scenario: Marcar playlist como default
- **WHEN** admin o cliente marca playlist P como `is_default=true` y ya existe otra default para el mismo canal
- **THEN** el sistema atómicamente desmarca la anterior y marca P (transacción)

#### Scenario: No se puede tener dos defaults
- **WHEN** se intenta guardar `is_default=true` en dos playlists del mismo canal simultáneamente
- **THEN** la segunda operación falla por violación del índice parcial

### Requirement: Items de playlist ordenados con cue points
`playlist_items` SHALL tener `playlist_id`, `media_item_id`, `position`, `cue_in_sec`, `cue_out_sec`, `transition_in` y un orden editorial inequívoco. `effectiveDuration()` SHALL calcular `cue_out - cue_in` o `duration - cue_in`. Validación `cue_out > cue_in` y dentro de duración. Un mismo `media_item_id` SHALL poder aparecer múltiples veces en misma playlist. Cuando un contenido sea interrumpido por una cuña, la posición de la cuña SHALL quedar entre los dos segmentos del contenido.

#### Scenario: Split película para cuña
- **WHEN** película duración 3600s, usuario crea item1 0-1800, item2 cuña, item3 1800-3600 misma película
- **THEN** SHALL ser válido y `TimelineBuilder` lo mapea directo sin split extra

#### Scenario: Split película para cuña
- **WHEN** una película de 3600s se corta en 1800s y se inserta una cuña de 45s
- **THEN** SHALL existir segmento inicial, cuña y segmento final en ese orden, con `cue_out_sec=1800` en el inicial y `cue_in_sec=1800` en el final

### Requirement: Recálculo automático de duración total
Se mantiene: `total_duration_sec` recalculado en transacción tras add/delete/reorder.

### Requirement: Playlist sirve como fuente para timeline builder
`TimelineBuilder` SHALL tomar una playlist y generar `program_timeline_items` secuenciales 00:00 en adelante, preservando orden editorial, cues y duplicados del mismo medio. Builder SHALL ser idempotente: regenerar desde la misma playlist produce la misma secuencia y no SHALL descartar cuñas persistidas.

#### Scenario: Builder idempotente
- **WHEN** se regenera timeline desde playlist P sin ediciones manuales de timeline
- **THEN** lista resultante SHALL ser igual a la anterior salvo IDs

#### Scenario: Builder respeta cue editorial
- **WHEN** playlist_item tiene `cue_in=10 cue_out=70`
- **THEN** timeline_item SHALL tener `effective_duration=60` y `cue_in/out` copiados

#### Scenario: Builder conserva interrupción
- **WHEN** se regenera la timeline desde una playlist que contiene segmento inicial, cuña y segmento final
- **THEN** la timeline SHALL conservar exactamente ese orden, SHALL copiar los offsets y SHALL producir intervalos consecutivos sin solapamiento

### Requirement: Loop por defecto
El sistema SHALL crear toda playlist nueva con `loop=true` salvo que el creador indique `loop=false` explícitamente.

#### Scenario: Playlist en bucle
- **WHEN** playlist tiene `loop=true` y se reproduce hasta el final
- **THEN** el worker de emisión (cambio futuro) la reinicia desde `position=1`

### Requirement: Eliminación de playlist
El sistema SHALL permitir eliminar una playlist vía `DELETE /api/playlists/{id}`. La operación SHALL:

- Borrar la playlist
- Borrar en cascada todos sus `playlist_items`
- Registrar la acción en `audit_logs`

#### Scenario: Eliminar playlist borra sus items
- **WHEN** admin elimina playlist P que tiene 10 items
- **THEN** las 10 filas de `playlist_items` se eliminan automáticamente

#### Scenario: No se elimina la playlist default de un canal con emisión activa
- **WHEN** se intenta eliminar la playlist con `is_default=true` y existe `emission_state(channel_id=C, status='live')`
- **THEN** el sistema rechaza con error "No se puede eliminar la playlist default mientras hay emisión activa"

### Requirement: API de playlists
El sistema SHALL exponer endpoints REST:

- `GET /api/playlists?channel_id=C` lista playlists del canal
- `GET /api/playlists/{id}` detalle con items ordenados
- `POST /api/playlists` crea
- `PUT /api/playlists/{id}` actualiza metadatos (no items)
- `DELETE /api/playlists/{id}` elimina
- `POST /api/playlists/{id}/items` agrega item al final
- `PUT /api/playlists/{id}/items/reorder` actualiza posiciones
- `DELETE /api/playlists/{id}/items/{itemId}` quita item

Todos SHALL respetar el scope por canal.

#### Scenario: Detalle de playlist incluye items
- **WHEN** cliente solicita `GET /api/playlists/{id}` para playlist propia
- **THEN** respuesta incluye `items[]` ordenados por `position` con `media_item` embebido

#### Scenario: Reordenar atómicamente
- **WHEN** cliente envía `PUT /api/playlists/{id}/items/reorder` con array `[3,1,4,2,5]` de IDs
- **THEN** el sistema reasigna posiciones 1-5 en el orden dado, dentro de una transacción; si falla, ninguna posición cambia
