## MODIFIED Requirements

### Requirement: Timeline explícito 00:00-24:00 por día
El sistema SHALL construir, por cada `ScheduleBlock` activo, una lista ordenada de `program_timeline_items` que represente el día. Cada item SHALL tener `starts_at_sec`, `ends_at_sec`, `effective_duration_sec`, `kind`, `media_item_id`, `playlist_item_id` opcional, `cue_in/out` y `parent_content_id` opcional. La secuencia SHALL ser consecutiva y SHALL marcar overflow cuando exceda 86400 segundos. Una cuña interna SHALL ocupar su propio intervalo entre los dos segmentos de contenido.

#### Scenario: Timeline con cuña interna
- **WHEN** una playlist contiene película inicial, cuña de 45s y continuación de la película
- **THEN** la timeline SHALL generar tres filas consecutivas y la continuación SHALL comenzar 45s después del inicio de la cuña

### Requirement: Edición solo del futuro con política shift
La programación del día en curso SHALL ser editable solo para instancias futuras. Editar futuro SHALL desplazar los items posteriores sumando la duración efectiva insertada. El elemento activo SHALL quedar congelado y la inserción SHALL conservar el offset de reanudación del contenido interrumpido.

#### Scenario: Insertar cuña futura desplaza posterior
- **WHEN** ahora=14:25, el item activo termina a las 16:00 y se inserta una cuña de 45s a las 15:00
- **THEN** la cuña SHALL ocupar 15:00-15:00:45, el contenido SHALL reanudar desde su offset a las 15:00:45 y los programas posteriores SHALL desplazarse 45s

### Requirement: Versionado de timeline para auditoría
Cada mutación SHALL incrementar `timeline_version` y registrar en `audit_logs` el before/after completo de los segmentos, la cuña y los horarios recalculados.

#### Scenario: Auditoría de inserción de cuña
- **WHEN** se inserta una cuña en una timeline futura
- **THEN** `audit_logs` SHALL contener la secuencia anterior, la secuencia posterior y la nueva versión
