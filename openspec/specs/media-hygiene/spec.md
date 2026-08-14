# media-hygiene Specification

## Purpose
TBD - created by archiving change db-hygiene-orphan-cleanup. Update Purpose after archive.
## Requirements
### Requirement: Comando `media:hygiene` con modo dry-run por defecto
El sistema SHALL proporcionar el comando Artisan `php artisan media:hygiene` que analiza el estado de `media_items` frente al filesystem y la BD. En su modo por defecto (sin flag `--apply`) el comando SHALL únicamente imprimir un reporte sin modificar la base de datos.

#### Scenario: Ejecución dry-run no muta la BD
- **WHEN** el operador ejecuta `php artisan media:hygiene` sin flag `--apply`
- **THEN** el comando SHALL imprimir el reporte en pantalla y SHALL NO ejecutar ningún UPDATE sobre `media_items`. El conteo de filas con `status='ready'` SHALL ser idéntico antes y después.

#### Scenario: Flag `--apply` ejecuta mutaciones
- **WHEN** el operador ejecuta `php artisan media:hygiene --apply`
- **THEN** el comando SHALL ejecutar las reclasificaciones descritas en los requisitos siguientes y SHALL imprimir el resumen "Reclasificados: N, Omitidos: M".

### Requirement: Detección de `media_items` con archivo físico faltante
El comando SHALL detectar, para cada canal con `root_path` no nulo, los `media_items` cuyo archivo combinado `<root_path>/<filename>` no exista en disco. Cada fila faltante SHALL listarse en el reporte bajo la sección `file_missing[]` con `id`, `filename`, `channel_id`, `channel_name`, `kind` y `size_bytes`.

#### Scenario: Reporte lista media_items huérfanos
- **WHEN** un `media_item` tiene `status='ready'` pero su archivo físico no existe en `channel.root_path + '/' + filename`
- **THEN** SHALL aparecer en la sección `file_missing` del reporte.

### Requirement: Detección de `media_items` mal clasificados
El comando SHALL detectar `media_items` con extensiones no audiovisuales (`.py`, `.log`, `.lock`, `.tmp`, `.upload.tmp`) y `status='ready'`. SHALL listarlos en la sección `misclassified[]` del reporte.

#### Scenario: Reporte lista archivos no audiovisuales marcados ready
- **WHEN** existe un `media_item` con `filename='archivo.py'` y `status='ready'`
- **THEN** SHALL aparecer en `misclassified[]` con `current_kind='other'` (o cualquier kind distinto de no-aplicable).

### Requirement: Detección de archivos en disco no indexados
El comando SHALL listar, por canal, los archivos presentes en `root_path` que no tengan fila correspondiente en `media_items` para ese canal. SHALL separarlos en dos sub-secciones:
- `unindexed_uploads[]`: archivos cuyo nombre termina en `.upload.tmp`.
- `unindexed_disk_files[]`: archivos reales (videos, imágenes, audios, playlist.txt) candidatos a importarse vía `media:scan`.

#### Scenario: Listado de archivos sin BD
- **WHEN** existe `/mnt/multimedia/Cine Dios/caracol.mp4` y no hay `media_item` con `channel_id=C` y `filename='caracol.mp4'`
- **THEN** SHALL aparecer en `unindexed_disk_files[]` con tamaño y extensión.

#### Scenario: Listado de uploads truncados
- **WHEN** existe `/mnt/multimedia/Cine Dios/video.mp4.123.upload.tmp`
- **THEN** SHALL aparecer en `unindexed_uploads[]`.

### Requirement: Reclasificación de huérfanos bajo `--apply`
Cuando se invoca con `--apply`, el comando SHALL ejecutar, en una sola transacción:
1. `UPDATE media_items SET status='failed', status_reason='file_missing', updated_at=NOW() WHERE id IN (SELECT id FROM file_missing);`
2. `UPDATE media_items SET status='failed', status_reason='non_media_file', kind='other', updated_at=NOW() WHERE id IN (SELECT id FROM misclassified);`

El comando SHALL imprimir el conteo de filas afectadas y SHALL registrar cada UPDATE en `audit_logs` con `action='media.hygiene.reclassify'`, `entity_type='MediaItem'` y `entity_id=<id>`.

#### Scenario: Aplicación marca huérfanos como failed
- **WHEN** el operador ejecuta `php artisan media:hygiene --apply` con N items en `file_missing`
- **THEN** exactamente N filas de `media_items` SHALL pasar a `status='failed', status_reason='file_missing'` y SHALL existir N entradas nuevas en `audit_logs` con `action='media.hygiene.reclassify'`.

#### Scenario: Aplicación marca no-aviso como failed
- **WHEN** el operador ejecuta `php artisan media:hygiene --apply` con M items en `misclassified`
- **THEN** exactamente M filas de `media_items` SHALL pasar a `status='failed', status_reason='non_media_file', kind='other'` y SHALL existir M entradas en `audit_logs`.

### Requirement: Rollback de reclasificaciones
El comando SHALL poder revertir una reclasificación ejecutada previamente. Con el flag `--rollback-since=<ISO8601>` SHALL restaurar `status='ready'`, `status_reason=NULL` y `kind=<kind_original>` para todos los `media_items` cuyo `audit_logs` con `action='media.hygiene.reclassify'` tenga `at >= <ISO8601>`. El `kind_original` SHALL recuperarse del snapshot JSON guardado en `audit_logs.before.kind`.

#### Scenario: Rollback restaura estado original
- **WHEN** el operador ejecuta `php artisan media:hygiene --rollback-since=2026-07-15T16:00:00Z`
- **THEN** todos los `media_items` modificados por reclasificaciones posteriores SHALL volver a `status='ready'` con su `kind` original, y SHALL existir una entrada en `audit_logs` con `action='media.hygiene.rollback'` por cada uno.

### Requirement: Salida JSON para consumo programático
El comando SHALL aceptar el flag `--json`. Cuando se especifica, la salida SHALL ser un único objeto JSON con esta estructura exacta, imprimible en stdout y parseable por `jq`:

```json
{
  "channels_scanned": 2,
  "file_missing": [{ "id": "uuid", "channel_id": "uuid", "channel_name": "Cine Dios", "filename": "video.mp4", "kind": "video", "size_bytes": 123456 }],
  "misclassified": [{ "id": "uuid", "filename": "archivo.py", "current_kind": "other", "extension": "py" }],
  "unindexed_uploads": [{ "channel_id": "uuid", "filename": "video.mp4.123.upload.tmp", "size_bytes": 999 }],
  "unindexed_disk_files": [{ "channel_id": "uuid", "filename": "caracol.mp4", "extension": "mp4", "size_bytes": 999 }]
}
```

#### Scenario: Output JSON válido
- **WHEN** el operador ejecuta `php artisan media:hygiene --json | jq .channels_scanned`
- **THEN** SHALL devolver un entero con la cantidad de canales escaneados sin error de parseo.

### Requirement: Filtrado por canal
El comando SHALL aceptar el flag `--channel=<slug-or-uuid>`. Cuando se especifica, SHALL procesar únicamente ese canal. Sin el flag SHALL procesar todos los canales con `root_path` no nulo.

#### Scenario: Filtrado por slug
- **WHEN** el operador ejecuta `php artisan media:hygiene --channel=cine-dios`
- **THEN** SHALL procesar únicamente el canal cuyo `slug='cine-dios'` y SHALL omitir los demás.

### Requirement: Comando seguro bajo `db:backup`
El sistema SHALL documentar (en `AGENTS.md` y en el banner `--help` del comando) que el operador DEBE ejecutar `php artisan db:backup` antes de cualquier invocación con `--apply`. El comando SHALL comprobar como pre-condición la existencia de al menos un archivo en `storage/app/backups/` con `mtime` dentro de las últimas 24 horas; si no existe, SHALL abortar con mensaje claro.

#### Scenario: Bloqueo si no hay backup reciente
- **WHEN** el operador ejecuta `php artisan media:hygiene --apply` y el backup más reciente en `storage/app/backups/` tiene más de 24 horas
- **THEN** SHALL abortar con `error: No recent db:backup found (last: 2026-07-13T...). Run \`php artisan db:backup\` first.` y código de salida 2.

