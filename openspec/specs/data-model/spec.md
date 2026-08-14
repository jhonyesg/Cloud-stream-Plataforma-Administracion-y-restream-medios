## Purpose
Define the PostgreSQL data model for Cloudstream: tables, enums, primary keys, foreign-key cascade behavior, unique constraints, soft-delete semantics, JSONB usage, and purge jobs.
## Requirements
### Requirement: Sistema de base de datos PostgreSQL
El sistema SHALL usar PostgreSQL 14 con la extensión `pgcrypto` habilitada para generación de UUIDs (`gen_random_uuid()`).

#### Scenario: UUID disponible
- **WHEN** se ejecuta `CREATE EXTENSION IF NOT EXISTS pgcrypto` durante la migración inicial
- **THEN** todas las tablas pueden declarar columnas `uuid NOT NULL DEFAULT gen_random_uuid()` como PK

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
- `audit_logs`

> Las tablas `media_providers`, `media_folders` y `schedule_block_items` fueron eliminadas por las migraciones `2026_07_15_072125_drop_media_providers_and_folders` y `2026_07_15_102720_simplify_schedule_blocks`.

#### Scenario: Todas las tablas existen tras `migrate`
- **WHEN** se ejecuta `php artisan migrate` desde cero
- **THEN** `\dt` lista las tablas actuales (sin `media_providers`, `media_folders` ni `schedule_block_items`)
- **AND** `\dT+` lista los 9 enums restantes

### Requirement: Llaves primarias UUID en todas las tablas
El sistema SHALL usar columnas `id uuid NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY` en las 13 tablas.

#### Scenario: PK UUID generada automáticamente
- **WHEN** se inserta una fila sin especificar `id`
- **THEN** Postgres asigna un UUID v4 único

### Requirement: Timestamps de auditoría en todas las tablas
El sistema SHALL incluir `created_at timestamptz NOT NULL DEFAULT now()` y `updated_at timestamptz NOT NULL DEFAULT now()` en las 13 tablas.

#### Scenario: Timestamps autollenados
- **WHEN** Eloquent crea o actualiza una fila
- **THEN** los timestamps son administrados por Laravel sin intervención manual

### Requirement: Llaves foráneas con ON DELETE explícito
El sistema SHALL declarar la acción `ON DELETE` en cada FK con uno de tres valores:

- `CASCADE` para dependencias de composición (`playlist_items.playlist_id`, `schedule_blocks.template_id`, `media_items.channel_id`)
- `RESTRICT` para referencias críticas (`channels.owner_id`, `playlist_items.media_item_id`)
- `SET NULL` para contexto opcional (`audit_logs.user_id`, `schedule_blocks.playlist_id`)

#### Scenario: Borrar playlist borra sus items
- **WHEN** se ejecuta `DELETE FROM playlists WHERE id = X`
- **THEN** todas las filas de `playlist_items` con `playlist_id = X` se eliminan automáticamente

#### Scenario: No se puede borrar usuario con canales asignados
- **WHEN** se intenta `DELETE FROM users WHERE id = Y` y `Y` es `owner_id` de al menos un canal activo
- **THEN** Postgres rechaza la operación con error de FK

### Requirement: Índices en toda llave foránea y columna de búsqueda
El sistema SHALL crear índices btree en:

- Toda columna FK (ej. `ix_channels_owner_id`)
- Toda columna usada en `WHERE` o `ORDER BY` (ej. `ix_users_status`, `ix_media_items_kind`)
- Toda columna con constraint UNIQUE (ej. `ix_users_username`, `uq_channels_slug`)

#### Scenario: Búsqueda por estado usa índice
- **WHEN** se ejecuta `SELECT * FROM channels WHERE status = 'active'`
- **THEN** el plan de ejecución usa el índice `ix_channels_status`

### Requirement: Constraints únicos parciales
El sistema SHALL crear índices únicos parciales para reglas de una-por-contexto:

- `playlists`: máximo una playlist `is_default = true` por canal (`WHERE is_default = true`)
- `playlist_items`: `position` único dentro de una playlist
- `schedule_block_items`: `position` único dentro de un bloque
- `virtual_screens`: una pantalla virtual por canal

#### Scenario: No se puede crear segunda playlist default
- **WHEN** ya existe `playlists(is_default=true, channel_id=X)` y se intenta insertar otra con `is_default=true` para el mismo canal
- **THEN** Postgres rechaza la inserción por violación del índice parcial

### Requirement: Soft delete en canales y medios
El sistema SHALL incluir `deleted_at timestamptz NULL` únicamente en la tabla `channels`. La tabla `media_items` NO SHALL incluir `deleted_at`: los medios se borran físicamente vía `ON DELETE CASCADE` desde `channels` (o desde la API de delete explícita). Ninguna otra tabla usa soft delete.

#### Scenario: Canal borrado no aparece en listados
- **WHEN** un canal es soft-deleted vía Eloquent `delete()`
- **THEN** las queries por defecto lo excluyen; `$model->withTrashed()` lo incluye

#### Scenario: Purga real a 30 días
- **WHEN** el job `channels:purge-soft-deleted` se ejecuta diariamente
- **THEN** filas de `channels` con `deleted_at < now() - interval '30 days'` son eliminadas físicamente, y en cascada se eliminan todas sus `media_items`, `playlists`, `schedule_templates`, `virtual_screens`, `emission_state` y filas de `channel_user`.

#### Scenario: media_items nunca se soft-deletea
- **WHEN** se ejecuta `MediaItem::delete()` sobre una fila
- **THEN** SHALL ser un hard-delete físico (la fila desaparece de la tabla) sin escribir `deleted_at`.

### Requirement: Columnas JSONB explícitas
El sistema SHALL usar `jsonb NOT NULL DEFAULT '{}'` solo en:

- `media_items.metadata` (datos extra del probe FFprobe)
- `virtual_screens.layout` (logo, ticker, background, scale_mode)

Ningún otro campo usa JSONB.

#### Scenario: Defaults JSONB válidos
- **WHEN** se inserta fila sin especificar la columna JSONB
- **THEN** Postgres asigna `'{}'::jsonb` como objeto JSON vacío válido

### Requirement: Acciones de borrado para job de purga
El sistema SHALL proporcionar un comando Artisan `channels:purge-soft-deleted` que: (1) Selecciona filas de `channels` con `deleted_at < now() - interval '30 days'`. (2) Ejecuta `DELETE FROM channels WHERE id IN (...)` para que el `ON DELETE CASCADE` borre `media_items`, `playlists`, `schedule_templates`, `virtual_screens`, `emission_state`, `channel_user` automáticamente. (3) Registra la acción en `audit_logs` con `action='channel.purge'`.

#### Scenario: Purga elimina canal y todo su contenido
- **WHEN** un canal lleva 31 días soft-deleted
- **THEN** el comando SHALL ejecutar `DELETE` sobre la fila de `channels` y SHALL propagar vía CASCADE la eliminación de todos sus `media_items`, `playlists`, `schedule_templates`, `virtual_screens`, `emission_state` y filas de `channel_user`. SHALL existir una entrada en `audit_logs` con `action='channel.purge'`.

#### Scenario: Purga elimina archivo y fila
- **WHEN** un `media_item` pertenece a un canal soft-deleted que es purgado
- **THEN** el `media_item` se elimina físicamente vía `ON DELETE CASCADE` y el archivo en disco queda huérfano (a limpiar por el operador).

### Requirement: Migraciones destructivas deben preservar datos mediante snapshots
El sistema SHALL exigir que toda migración que ejecute `DROP TABLE`, `DROP COLUMN` o `TRUNCATE` utilice el trait `WithDataSafetySnapshot` declarando explícitamente las tablas afectadas en la propiedad `$criticalTables`. La migración SHALL capturar un snapshot comprimido (`.json.gz`) de cada tabla crítica en `database/snapshots/<nombre-migración>/` antes de ejecutar DDL destructivo, y SHALL restaurar esos datos en `down()`.

#### Scenario: Migración destructiva nueva es rechazada en CI
- **WHEN** un desarrollador agrega una migración que contiene `Schema::dropIfExists('X')` o `$table->dropColumn(...)` sin usar `WithDataSafetySnapshot`
- **AND** se ejecuta `php artisan check:destructive-migrations`
- **THEN** el comando retorna código de salida no-cero
- **AND** lista el archivo de migración ofensor

#### Scenario: Rollback de migración destructiva restaura filas
- **GIVEN** una migración `M` con `$criticalTables = ['media_items']` fue ejecutada y guardó snapshot con 500 filas
- **WHEN** se ejecuta `php artisan migrate:rollback` sobre `M`
- **THEN** `M.down()` recrea la tabla con su esquema original
- **AND** reinserta las 500 filas desde el snapshot
- **AND** la verificación post-rollback (`SELECT count(*) FROM media_items`) retorna 500

### Requirement: `migrate:fresh` rehidrata datos desde snapshot de release
El sistema SHALL permitir que `php artisan migrate:fresh` ejecute automáticamente `ProductionDataSeeder` al final del proceso cuando exista un snapshot de release en `database/snapshots/release/production.json.gz`. Si el snapshot no existe, el seeder SHALL degradar de forma elegante (warning, sin error) y dejar las tablas críticas vacías.

#### Scenario: Restore automático tras migrate:fresh
- **GIVEN** `database/snapshots/release/production.json.gz` existe con datos de producción
- **WHEN** se ejecuta `php artisan migrate:fresh --seed`
- **THEN** después de que todas las migraciones corran, `ProductionDataSeeder` se ejecuta automáticamente
- **AND** las tablas críticas quedan pobladas con los datos del snapshot

#### Scenario: Migrate:fresh sin snapshot no falla
- **GIVEN** no existe snapshot de release
- **WHEN** se ejecuta `php artisan migrate:fresh --seed`
- **THEN** las migraciones y los seeders regulares corren normalmente
- **AND** se registra un warning indicando la ruta del snapshot ausente
- **AND** el comando retorna código de salida 0

