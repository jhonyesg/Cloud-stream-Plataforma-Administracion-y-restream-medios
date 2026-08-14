## Purpose
The content scheduler module: organizes channel programming into monthly schedule templates with per-day playlist blocks, calendar visualization, and cross-day replication.
## Requirements
### Requirement: Schedule template covers a calendar month
The system SHALL organize programming into `schedule_templates` scoped by `(channel_id, year, month)`. Each template has a `status` of `draft` or `active`. Only one template per `(channel_id, year, month)` SHALL be active at a time. The template SHALL store `cloned_from_id` when replicated from another template.

#### Scenario: Create template for a month
- **WHEN** an authorized user creates a template with `channel_id=C, year=2026, month=7, name="Programación Julio 2026"`
- **THEN** a `schedule_template` row SHALL be created with `status='draft'`

#### Scenario: Activate a template
- **WHEN** a user sets a template's status to `active`
- **THEN** any other template for the same `(channel_id, year, month)` SHALL be set to `draft`

#### Scenario: Clone template to another month
- **WHEN** a user clones template T (July 2026) to August 2026
- **THEN** a new template SHALL be created with `cloned_from_id=T.id`, `year=2026, month=8`, `status='draft'`, and all schedule_blocks and their playlist references SHALL be copied

### Requirement: Schedule blocks define time slots within a template
A `schedule_block` SHALL define a time slot within a template using `day_of_month` (for specific days) or `weekday_mask` (for recurring days), `start_time`, `end_time`, and an optional `playlist_id`. The `kind` field SHALL indicate the block type (`content` for playlists, `ad_break` for cuña-only blocks).

#### Scenario: Create a content block for a specific day
- **WHEN** a user creates a block with `day_of_month=15, start_time=06:00, end_time=12:00, playlist_id=P, kind='content'`
- **THEN** the block SHALL appear on the calendar view for day 15, 06:00–12:00, colored blue

#### Scenario: Create a recurring ad break block
- **WHEN** a user creates a block with `weekday_mask=0111110 (Mon-Fri), start_time=10:00, end_time=10:01, playlist_id=AdPlaylist, kind='ad_break'`
- **THEN** the block SHALL appear on every weekday at 10:00, colored red/orange

#### Scenario: Block without playlist shows as empty
- **WHEN** a block has `playlist_id=NULL`
- **THEN** the calendar SHALL show it as an empty/placeholder slot that can be filled later

### Requirement: Calendar view visualizes the weekly schedule
The scheduler SHALL render a 7-column weekly grid (Mon–Sun) with time slots on the vertical axis. Content blocks (videos) SHALL be colored blue. Ad break blocks SHALL be colored red/orange. Empty slots SHALL be visually distinct (light gray). Clicking a block SHALL open an edit modal.

#### Scenario: Videos appear in blue
- **WHEN** a content block with a playlist of video items renders on the calendar
- **THEN** the block SHALL have a blue background and show the playlist name

#### Scenario: Cuñas appear in red/orange
- **WHEN** an ad_break block renders on the calendar
- **THEN** the block SHALL have a red/orange background and show "Cuña" or the cuña name

#### Scenario: Empty slot is gray
- **WHEN** a time slot has no block assigned
- **THEN** the calendar SHALL render a light gray placeholder with a "+" button to create a block

### Requirement: Playlist editor mixes videos and cuñas
The playlist editor SHALL allow adding both `video` and `ad` kind media items to the same playlist. Items SHALL be ordered by `position` (sequential integer). The editor SHALL show videos in blue and cuñas in red/orange to match the calendar colors. Items SHALL be reorderable by drag-and-drop or up/down buttons.

#### Scenario: Add a video to a playlist
- **WHEN** a user selects a video media item and adds it to playlist P at position 3
- **THEN** `playlist_items` SHALL have a row with `playlist_id=P, media_item_id=<video>, position=3`

#### Scenario: Add a cuña between two videos
- **WHEN** a user adds a cuña (kind=ad) between position 2 and 3
- **THEN** the cuña SHALL be inserted at position 3 and existing items at position >= 3 SHALL shift to position + 1

#### Scenario: Reorder items
- **WHEN** a user drags item from position 1 to position 3
- **THEN** positions SHALL be renumbered sequentially (1,2,3,4...) with no gaps

#### Scenario: Remove item from playlist
- **WHEN** a user removes an item from a playlist
- **THEN** the `playlist_items` row SHALL be deleted and remaining positions SHALL be renumbered, and `total_duration_sec` SHALL be recalculated

### Requirement: Replicate blocks across days
The scheduler SHALL provide a "Replicar" action that copies all blocks from a source day (or week) to target days. The replication SHALL copy the block definitions (start_time, end_time, kind, playlist_id) with the new `day_of_month` or `weekday_mask`.

#### Scenario: Replicate one day to the whole week
- **WHEN** a user clicks "Replicar a toda la semana" on Monday's blocks
- **THEN** all blocks from Monday SHALL be copied to Tuesday through Sunday with the same start/end times and playlist references

#### Scenario: Replicate one week to the whole month
- **WHEN** a user clicks "Replicar a todo el mes" on week 1
- **THEN** all blocks from week 1 SHALL be copied to weeks 2, 3, 4, and 5 of the same month

#### Scenario: Replication overwrites existing blocks
- **WHEN** a user replicates blocks to a day that already has blocks
- **THEN** the existing blocks SHALL be deleted and replaced with the replicated ones

### Requirement: Authorization on scheduler
Admin users SHALL be able to manage all channels' schedules. Client users SHALL only be able to manage schedules for channels they own or are assigned to.

#### Scenario: Client accesses own channel scheduler
- **WHEN** a client who owns channel C navigates to the scheduler for C
- **THEN** the scheduler SHALL load with full edit capabilities

#### Scenario: Client tries to access another channel's scheduler
- **WHEN** a client who does NOT own channel C tries to access C's scheduler
- **THEN** the server SHALL respond 403

### Requirement: Schedule blocks define one playlist per day
A `schedule_block` SHALL contener `template_id`, `day_of_month`, `playlist_id`. A partir de este cambio, cada bloque SHALL generar `program_timeline_items` via `TimelineBuilder` que materializa el día 00:00-24:00. Timeline_items SHALL tener `starts_at_sec`, `ends_at_sec`, `kind`, `media_item_id`, `cue_in/out`, `parent_content_id`. Mismo `media_item_id` puede aparecer múltiples veces con cues distintas para soportar `película -> cuña -> reanudar`. Existe UNIQUE `(template_id, day_of_month)` para bloque, pero timeline_items permite duplicados del mismo media.

#### Scenario: Bloque genera timeline con duplicado para cuña
- **WHEN** Playlist tiene película 3600s y se inserta cuña 45s a mitad via UI programación
- **THEN** timeline SHALL tener 3 filas: película parte1 0-1800, cuña 1800-1845, película parte2 1845-3645 con `parent_content_id` apuntando a parte1

#### Scenario: Reasignar día regenera timeline
- **WHEN** se asigna playlist Q a día 15 que tenía P
- **THEN** SHALL borrarse timeline_items de día 15 y regenerarse desde Q

#### Scenario: Clear día
- **WHEN** clear día 15 (playlist_id null)
- **THEN** SHALL borrarse bloque y timeline_items del día, quedando fallback

### Requirement: Calendar view es mensual con una celda por día
Se mantiene, pero cada celda SHALL mostrar overflow si `timeline` excede 24h: badge "+Xm overflow" y `starts_at` de primer item fuera de rango en tooltip.

#### Scenario: Día con overflow
- **WHEN** timeline suma 25h por 3 cuñas insertadas
- **THEN** celda muestra badge overflow 60m

### Requirement: Per-day hover actions son usables desde la celda calendario
Se mantiene. Adición: acción Insertar Cuña SHALL aparecer en celdas con playlist y en timeline del día.

#### Scenario: Insertar cuña futura
- **WHEN** usuario elige Insertar Cuña en día actual live
- **THEN** SHALL abrir modal con lista cuñas disponibles (kind=ad) y hora sugerida > ahora; al confirmar se inserta en timeline futuro y recalcula posteriores

### Requirement: Side panel edits playlist with thumbnails and drag-and-drop
The right side panel of the scheduler SHALL display a playlist index (a list of all playlists for the current channel with an Edit button each), not the items/rename/assign UI of a single day's playlist. Per-day playlist editing and item manipulation SHALL happen exclusively in the playlist editor fullscreen modal, reachable from any playlist card's edit button.

#### Scenario: Rename playlist
- **WHEN** a user wants to rename a playlist
- **THEN** the user SHALL open the playlist editor fullscreen modal from the side panel card and edit the name there

### Requirement: Media library modal for adding items
When a user clicks "+ Agregar medio" in the side panel, a modal SHALL open showing all media items of the channel as a grid of thumbnails. Videos SHALL have a blue border, cuñas a red/orange border. Clicking an item SHALL add it to the end of the playlist and close the modal. The modal SHALL support search by filename and filter by kind (video/ad/all).

#### Scenario: Open library modal
- **WHEN** a user clicks "+ Agregar medio"
- **THEN** a modal SHALL open with a grid of channel media items showing thumbnails

#### Scenario: Add video to playlist
- **WHEN** a user clicks a video thumbnail in the library modal
- **THEN** the video SHALL be appended to the playlist, the modal SHALL close, and the side panel SHALL refresh showing the new item

#### Scenario: Search in library
- **WHEN** a user types "Dios" in the search box
- **THEN** only media items with "Dios" in the filename SHALL be shown

#### Scenario: Filter by kind
- **WHEN** a user selects "Solo cuñas" filter
- **THEN** only media items with kind=ad SHALL be shown

### Requirement: Replicate playlist across days
The scheduler SHALL provide replication actions from the side panel: "Replicar a toda la semana" copies the selected day's playlist assignment to the other days of the calendar week (Monday through Sunday) that contains the selected day, limited to days within the current month. "Replicar a todo el mes" copies to all remaining days of the current month. "Replicar a todo el año" copies to all remaining days of the current month (same effect as "Replicar a todo el mes" until cross-month replication is implemented). Replication SHALL overwrite existing assignments with a confirmation prompt. The set of days shown in the preview MUST match exactly the set of days sent to the backend.

#### Scenario: Replicate day to week
- **WHEN** a user clicks "Replicar a toda la semana" on day 16 (Thursday)
- **THEN** the calendar week's other days (Monday 13, Tuesday 14, Wednesday 15, Friday 17, Saturday 18, Sunday 19) SHALL be assigned the same playlist as day 16

#### Scenario: Replicate day to month
- **WHEN** a user clicks "Replicar a todo el mes" on day 15
- **THEN** all days 1-31 (except 15) SHALL be assigned the same playlist as day 15, with confirmation

#### Scenario: Replicate day to year
- **WHEN** a user clicks "Replicar a todo el año" on day 15
- **THEN** all days 1-31 (except 15) SHALL be assigned the same playlist as day 15, with confirmation

#### Scenario: Week starts on Monday at month boundary
- **WHEN** a user clicks "Replicar a toda la semana" on day 3 (Wednesday) of a month where day 1 is a Monday
- **THEN** only days 1 and 2 SHALL also receive the source playlist (days 7+ of the same ISO week fall in the next month and are excluded because the template is month-scoped)

#### Scenario: Week ends on Sunday at month boundary
- **WHEN** a user clicks "Replicar a toda la semana" on day 30 (Friday) of a 31-day month where day 31 is a Saturday
- **THEN** days 26, 27, 28, 29 and 31 SHALL also receive the source playlist (day 31 is included because it is in the current month)

#### Scenario: Selected day is Sunday — week includes the previous Monday
- **WHEN** a user clicks "Replicar a toda la semana" on day 19 (Sunday)
- **THEN** days 13 (Monday), 14, 15, 16, 17 and 18 SHALL be assigned the same playlist as day 19

#### Scenario: Selected day is Monday — week includes that Monday through Sunday
- **WHEN** a user clicks "Replicar a toda la semana" on day 13 (Monday)
- **THEN** days 14, 15, 16, 17, 18 and 19 SHALL be assigned the same playlist as day 13

#### Scenario: Preview matches the payload sent to the backend
- **WHEN** a user views the preview for any of the three buttons
- **THEN** the number shown in "Sobrescriben" plus "Llenos vacíos" SHALL equal exactly the number of `target_days` sent to the backend

#### Scenario: Modal state is reset between different source days
- **WHEN** a user opens the replicate modal for day 5, closes it, then opens it again for day 25
- **THEN** the modal SHALL display `selectedDay = 25` and the previews SHALL reflect the playlist of day 25

#### Scenario: Replication overwrites with confirmation
- **WHEN** a user replicates to days that already have playlists assigned
- **THEN** a confirmation dialog SHALL appear listing how many days will be overwritten

### Requirement: Replicate month to other months
The scheduler SHALL provide a "Replicar este mes a…" action accessible from the calendar header (outside day cells). The action SHALL open a modal that lets the user select one or more target months within the current year and SHALL clone the current month's full template (all 31 day→playlist blocks) to each selected target month by creating a new `schedule_template` with `is_replicated=true` and `cloned_from_id` pointing to the source template. A "Replicar al resto del año" shortcut SHALL auto-select all months of the current year that come after the source month.

#### Scenario: Clone month to a single target month
- **WHEN** a user clicks "Replicar este mes a…" on the July 2026 template and selects August 2026
- **THEN** a new template SHALL exist for `(channel_id, August, 2026)` with `is_replicated=true`, `cloned_from_id` pointing to the July 2026 template, and all 31 day→playlist blocks copied from July

#### Scenario: Clone month to multiple target months
- **WHEN** a user clicks "Replicar este mes a…" and selects August, September, and October 2026
- **THEN** three new templates SHALL exist (one per target month), each with all 31 day→playlist blocks copied from the source month

#### Scenario: Replicate to remaining year uses shortcut
- **WHEN** a user clicks "Replicar este mes a…" and then clicks the "Replicar al resto del año" shortcut
- **THEN** all months of the current year that come after the source month SHALL be auto-selected in the modal

#### Scenario: Target month already has a template
- **WHEN** a user attempts to clone to a month that already has a `schedule_template`
- **THEN** the operation SHALL abort with a clear error message identifying the conflicting month(s) and SHALL NOT modify the existing target template

#### Scenario: Source month has no blocks
- **WHEN** the current month template has zero `schedule_block` rows
- **THEN** the "Replicar este mes a…" button SHALL be disabled with a tooltip explaining that the source month has no programmed days

#### Scenario: Multiple clones complete in parallel
- **WHEN** a user clones to N target months
- **THEN** the system SHALL issue all N clone requests in parallel and SHALL display a single success toast with the count of cloned months after all complete

#### Scenario: Partial failure shows which months failed
- **WHEN** some clone requests fail (e.g. 8 succeed, 3 fail because the target months already had templates)
- **THEN** the system SHALL show a message listing the failed months and SHALL NOT reload the page (the user can decide to retry or abort)

### Requirement: Timeline 00-24 editable con preview shift
El sistema SHALL exponer `GET /api/schedule-templates/{id}/days/{day}/timeline` que devuelve timeline ordenado con `starts_at`, `ends_at`, `effective_duration`, `kind`, `media filename`. `POST .../timeline/insert-cue` SHALL insertar cuña como interrupción preservando contenido (split película en 2 partes si cae en medio). `DELETE .../timeline/{timelineItemId}` SHALL borrar solo si futuro y recalcular.

#### Scenario: Insertar cuña en medio de película via API
- **WHEN** `POST /days/15/timeline/insert-cue {at_sec:1800, media_item_id:cuña_45s}`
- **THEN** SHALL crearse split película parte1/cuña/parte2, parte1 `ends_at=1800`, cuña `starts=1800 ends=1845`, parte2 `starts=1845` con `resume_offset=1800`

#### Scenario: Borrar cuña futura re-encaja timeline
- **WHEN** se borra cuña futura de 60s
- **THEN** items posteriores SHALL desplazarse -60s y overflow reducirse

