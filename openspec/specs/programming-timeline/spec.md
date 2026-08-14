# programming-timeline Specification

## Purpose

Timeline explícito 00:00-24:00 por día construido a partir de `ScheduleBlock` activos, materializado como `program_timeline_items` ordenados con `starts_at_sec`/`ends_at_sec` y soporte de duplicados del mismo `media_item_id` con cues distintas. Cubre la edición solo del futuro con política shift, los horarios explícitos en la UI, el cambio automático de día a medianoche sin reiniciar pipeline, y el versionado de timeline para auditoría.

## Requirements

### Requirement: Timeline explícito 00:00-24:00 por día
El sistema SHALL construir, por cada `ScheduleBlock` activo (día del mes), una lista ordenada de `program_timeline_items` que representa el día completo. Cada item SHALL tener `starts_at_sec` (0..86400), `ends_at_sec`, `effective_duration_sec`, `kind`, `media_item_id`, `playlist_item_id` opcional, `cue_in/out`, `parent_content_id` opcional. El timeline SHALL cubrir 00:00-24:00 sin gaps; gaps se rellenan con fallback o se marca overflow si excede. La secuencia SHALL ser consecutiva y una cuña interna SHALL ocupar su propio intervalo entre los dos segmentos de contenido.

#### Scenario: Construcción desde playlist existente
- **WHEN** bloque del día tiene playlist P con 2 items de 3600s cada uno
- **THEN** timeline SHALL tener 2 items con `starts_at_sec=0,3600` y `ends_at_sec=3600,7200`

#### Scenario: Duración menor que 24h
- **WHEN** suma duraciones = 7200s
- **THEN** timeline SHALL tener 7200s cubiertos y resto hasta 86400 SHALL ser fallback o loop según configuración, visible en UI como vacío

#### Scenario: Mismo media_item duplicado con cues distintas
- **WHEN** se quiere película 0-1800 y 1800-fin alrededor de cuña
- **THEN** timeline permite dos filas con mismo `media_item_id` pero `cue_in/out` y `position` distintas

#### Scenario: Timeline con cuña interna
- **WHEN** una playlist contiene película inicial, cuña de 45s y continuación de la película
- **THEN** la timeline SHALL generar tres filas consecutivas y la continuación SHALL comenzar 45s después del inicio de la cuña

### Requirement: Edición solo del futuro con política shift
La programación del día en curso SHALL ser editable solo para instancias cuyo `starts_at_sec > now_sec`. Editar futuro SHALL recalcular `starts_at` posteriores sumando `effective_duration_sec`. Elemento activo SHALL quedar congelado (snapshot de filepath y offset al iniciar pipeline). Política por defecto SHALL ser `shift`; `rigid` queda para futuro. La inserción SHALL conservar el offset de reanudación del contenido interrumpido.

#### Scenario: Insertar cuña futura desplaza posterior
- **WHEN** ahora=14:25, item activo 14:00-16:00, usuario inserta cuña 15:00-15:02
- **THEN** película se pausa a las 15:00 en `resume_offset`, cuña 15:00-15:02, película reanudada 15:02-16:02, siguiente programa desplazado 2 min, UI muestra overflow si pasa 24h

#### Scenario: Insertar cuña futura desplaza posterior
- **WHEN** ahora=14:25, el item activo termina a las 16:00 y se inserta una cuña de 45s a las 15:00
- **THEN** la cuña SHALL ocupar 15:00-15:00:45, el contenido SHALL reanudar desde su offset a las 15:00:45 y los programas posteriores SHALL desplazarse 45s

#### Scenario: Intento editar pasado o activo
- **WHEN** usuario intenta cambiar item con `starts_at <= now_sec`
- **THEN** SHALL recibir error 422 "Solo futuro editable mientras el canal está al aire" y no se modifica timeline

#### Scenario: Recálculo transaccional
- **WHEN** se inserta item en posición 3 de 10
- **THEN** `starts_at_sec` de items 4..10 SHALL recalcularse en una transacción y version `timeline_version` incrementarse

### Requirement: Horarios explícitos en UI programación
La UI de programación SHALL mostrar por cada item su `starts_at` HH:MM:SS calculado y `effective_duration`. Al editar playlist del día, SHALL permitir insertar cuña en posición o a hora fija futura y ver preview del impacto (shift en minutos).

#### Scenario: Preview de inserción
- **WHEN** usuario arrastra cuña de 45s a posición entre película A y B
- **THEN** UI muestra "B se desplaza +45s, overflow 00:03:12" si excede 24h

### Requirement: Cambio automático a día siguiente a medianoche
Si canal queda live tras 24:00, al llegar 00:00 SHALL re-evaluar `ScheduledPlaylistResolver` para nuevo día y construir nuevo timeline, sin reiniciar pipeline RTMP, haciendo transición al primer item del nuevo día.

#### Scenario: Roll-over medianoche
- **WHEN** reloj llega 00:00:00 y existe bloque para nuevo día
- **THEN** SHALL precargar primer item nuevo día y hacer switch manteniendo pipeline

### Requirement: Versionado de timeline para auditoría
Cada mutación del timeline SHALL incrementar `timeline_version` en `schedule_blocks` o tabla hija y registrar en `audit_logs` con `action=timeline.edit` y snapshot `before/after`.

#### Scenario: Auditoría de inserción de cuña
- **WHEN** se inserta cuña
- **THEN** audit_logs contiene before (sin cuña) y after (con cuña y starts_at recalculados)

#### Scenario: Auditoría de inserción de cuña
- **WHEN** se inserta una cuña en una timeline futura
- **THEN** `audit_logs` SHALL contener la secuencia anterior, la secuencia posterior y la nueva versión