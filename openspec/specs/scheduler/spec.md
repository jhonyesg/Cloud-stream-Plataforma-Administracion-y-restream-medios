# scheduler Specification

## Purpose
TBD - created by archiving change scheduler-auto-activate-on-assign. Update Purpose after archive.
## Requirements
### Requirement: Asignar playlist a un día promueve el template a activo si está en draft
`PUT /api/schedule-templates/{template}/days/{day}` SHALL, además de crear/actualizar el `ScheduleBlock` con la playlist correspondiente, promover el `ScheduleTemplate` a `status='active'` cuando su `status` actual sea `'draft'`. Al promover, SHALL archivarse (`status='archived'`) cualquier otro template del mismo `(channel_id, year, month)` que esté activo. Una entrada en `audit_logs` SHALL registrar el cambio de status con `action='update.schedule_template.auto_activate'`.

#### Scenario: Primer día asignado sobre template draft
- **GIVEN** un ScheduleTemplate con `status='draft'`, sin ningún block previo, canal=C, año=Y, mes=M
- **WHEN** el operador hace `PUT /api/schedule-templates/{template}/days/20` con `playlist_id=P`
- **THEN** SHALL crearse el `ScheduleBlock(template_id={template}, day_of_month=20, playlist_id=P)`
- **AND** SHALL actualizarse el template a `status='active'`
- **AND** SHALL crearse un `audit_logs` con `action='update.schedule_template.auto_activate'`

#### Scenario: Ya hay otro template activo en el mismo mes
- **GIVEN** dos ScheduleTemplate del mismo (canal, año, mes): T1 con `status='active'` y T2 con `status='draft'`
- **AND** T2 sin blocks previos
- **WHEN** se asigna una playlist al día 15 sobre T2
- **THEN** SHALL archivarse T1 (`status='archived'`)
- **AND** SHALL promocionarse T2 a `status='active'`
- **AND** SHALL crearse un `audit_logs` por el archivado de T1 (`action='update.schedule_template.auto_archive_on_promote'`) y otro por la promoción de T2

#### Scenario: Asignar sobre template ya activo
- **WHEN** se hace una asignación sobre un template que ya está en `status='active'`
- **THEN** SHALL no cambiar el status del template (no se archiva a sí mismo, no se duplica el audit log)

#### Scenario: Borrar la última asignación (queda sin blocks)
- **WHEN** se hace `assignDay` con `playlist_id=null` y era la última asignación del template
- **THEN** SHALL borrarse el `ScheduleBlock`
- **AND** SHALL quedarse en `draft` (no se promueve porque no hay contenido). No cambia status.

#### Scenario: Replicación de bloques promueve a activo
- **WHEN** se hace `POST /api/schedule-templates/{template}/replicate` con `source_day=20` y `target_days=[21,22,23]`, sobre un template en `draft`
- **THEN** SHALL crearse los blocks en los días 21, 22, 23 replicando el block del día 20
- **AND** SHALL promocionarse el template a `status='active'`
- **AND** SHALL archivarse cualquier otro template activo del mismo (canal, año, mes)

### Requirement: Calendar view visualizes the weekly schedule
Se mantiene visualización mensual, pero cada día SHALL mostrar badge overflow y modo fallback si timeline excede 24h. Además SHALL mostrar marca "ahora" y siguiente cuña si aplica.

#### Scenario: Día actual marca ahora
- **WHEN** día actual = hoy y canal live
- **THEN** celda muestra línea hora actual según broadcast_clock

### Requirement: Schedule blocks define one playlist per day
Se mantiene UNIQUE por día, pero bloque SHALL disparar generación de `program_timeline_items` que es la representación 00:00-24:00 editable. Timeline es el que emite pipeline.

#### Scenario: Bloque genera timeline editable
- **WHEN** se asigna playlist a día
- **THEN** `TimelineBuilder` genera timeline 00:00 con starts_at calculados

### Requirement: Per-day hover actions are usable from the calendar cell
Se añade acción Insertar Cuña en celda.

#### Scenario: Insertar cuña desde hover
- **WHEN** usuario hover día con playlist y elige Insertar Cuña
- **THEN** abre modal insert-cue con at_sec futuro mínimo

