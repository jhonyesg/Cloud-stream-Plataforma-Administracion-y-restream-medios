## MODIFIED Requirements

### Requirement: Items de playlist ordenados con cue points
`playlist_items` SHALL tener `playlist_id`, `media_item_id`, `position`, `cue_in_sec`, `cue_out_sec`, `transition_in` y un orden editorial inequívoco. `effectiveDuration()` SHALL calcular `cue_out - cue_in` o `duration - cue_in`. La validación SHALL exigir `cue_out > cue_in` y valores dentro de la duración del medio. Un mismo `media_item_id` SHALL poder aparecer múltiples veces en la misma playlist. Cuando un contenido sea interrumpido por una cuña, la posición de la cuña SHALL quedar entre los dos segmentos del contenido.

#### Scenario: Split película para cuña
- **WHEN** una película de 3600s se corta en 1800s y se inserta una cuña de 45s
- **THEN** SHALL existir segmento inicial, cuña y segmento final en ese orden, con `cue_out_sec=1800` en el inicial y `cue_in_sec=1800` en el final

### Requirement: Playlist sirve como fuente para timeline builder
`TimelineBuilder` SHALL tomar una playlist y generar `program_timeline_items` secuenciales 00:00 en adelante, preservando orden editorial, cues y duplicados del mismo medio. Builder SHALL ser idempotente: regenerar desde la misma playlist produce la misma secuencia y no SHALL descartar cuñas persistidas.

#### Scenario: Builder conserva interrupción
- **WHEN** se regenera la timeline desde una playlist que contiene segmento inicial, cuña y segmento final
- **THEN** la timeline SHALL conservar exactamente ese orden, SHALL copiar los offsets y SHALL producir intervalos consecutivos sin solapamiento
