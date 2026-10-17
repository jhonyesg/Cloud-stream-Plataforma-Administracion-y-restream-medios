## MODIFIED Requirements

### Requirement: Constraints únicos parciales
El sistema SHALL crear índices únicos parciales para reglas de una-por-contexto:

- `playlists`: máximo una playlist `is_default = true` por canal (`WHERE is_default = true`)
- `playlist_items`: `position` único dentro de una playlist
- `schedule_block_items`: `position` único dentro de un bloque
- `virtual_screens`: una pantalla virtual por canal
- `restream_targets`: la tripleta `(user_id, channel_id, platform)` SHALL ser única **solo entre filas no eliminadas** (`WHERE deleted_at IS NULL`), porque la tabla usa soft deletes y una constraint rígida bloquearía re-crear un destino tras borrarlo

#### Scenario: No se puede crear segunda playlist default
- **WHEN** ya existe `playlists(is_default=true, channel_id=X)` y se intenta insertar otra con `is_default=true` para el mismo canal
- **THEN** Postgres rechaza la inserción por violación del índice parcial

#### Scenario: Destino restream soft-deleted libera su tripleta
- **WHEN** existe `restream_targets(user_id=U, channel_id=C, platform='youtube', deleted_at IS NOT NULL)` y se inserta otra fila viva con la misma tripleta
- **THEN** Postgres la acepta (el índice parcial ignora las filas eliminadas)

#### Scenario: Dos destinos vivos con la misma tripleta siguen prohibidos
- **WHEN** existe un destino vivo `(user_id=U, channel_id=C, platform='youtube')` y se inserta otro con la misma tripleta
- **THEN** Postgres rechaza la inserción por el índice único parcial

### Requirement: Tablas principales y enums
El sistema SHALL definir las siguientes tablas y enums en su forma actual (post-refactor):

- `users` con enums `user_role (admin|client)` y `user_status (active|suspended)`
- `channels` con enum `channel_status (draft|active|suspended|archived)`
- `media_items` con enums `media_kind (video|ad|image|audio|slate|other)` y `media_status (pending|probing|ready|failed)`
- `playlists`
- `playlist_items` con enum `transition_in (cut|fade|slide_left|slide_right|dissolve)`
- `schedule_templates` con enum `template_status (draft|active|archived)`
- `schedule_blocks` con enum `block_kind (program|ad_break)`
- `virtual_screens`
- `emission_state` con enum `emission_status (offline|starting|live|paused|error)`
- `restream_targets` con estados aplicativos normalizados `idle|starting|live|error` (sin valores legacy fuera del enum del modelo)
- `audit_logs`

> Las tablas `media_providers`, `media_folders` y `schedule_block_items` fueron eliminadas por las migraciones `2026_07_15_072125_drop_media_providers_and_folders` y `2026_07_15_102720_simplify_schedule_blocks`.

#### Scenario: Todas las tablas existen tras `migrate`
- **WHEN** se ejecuta `php artisan migrate` desde cero
- **THEN** `\dt` lista las tablas actuales (sin `media_providers`, `media_folders` ni `schedule_block_items`)
- **AND** `\dT+` lista los 9 enums restantes

#### Scenario: Estados de restream_targets normalizados
- **WHEN** se ejecuta la auditoría de normalización sobre `restream_targets`
- **THEN** ninguna fila tiene `status` fuera de `idle|starting|live|error` (los valores legacy como `active` se reescriben a su equivalente del modelo)