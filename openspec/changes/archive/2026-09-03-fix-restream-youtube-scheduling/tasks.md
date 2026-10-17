## 1. Preparación y seguridad de datos

- [x] 1.1 Ejecutar `php artisan db:backup` y confirmar el dump en `storage/app/backups/`
- [x] 1.2 Auditar con tinker el estado actual de `restream_targets`: filas soft-deleted duplicadas, valores de `status` fuera de `idle|starting|live|error`, `platform_account_id` con plataforma inconsistente (reporte previo a la migración)

## 2. Migraciones (normalización de BD)

- [x] 2.1 Crear migración con `WithDataSafetySnapshot` (`$criticalTables = ['restream_targets']`) que convierta `restream_targets_user_channel_platform_unique` en constraint parcial `WHERE deleted_at IS NULL` (drop + add), verificando antes que no existan duplicados entre filas vivas
- [x] 2.2 En la misma migración: añadir columna `scheduled_stop_at` (timestamptz nullable), índices parciales `(scheduled_start_at) WHERE enabled = false` y `(scheduled_stop_at) WHERE scheduled_stop_at IS NOT NULL`
- [x] 2.3 Normalización de datos en la migración: `status='active'` → `'live'`; nulificar `platform_account_id` cuando la plataforma de la cuenta no coincida con la del target; imprimir reporte de filas afectadas
- [x] 2.4 Implementar `down()` que restaure constraint rígida, quite columna/índices y verifique contra el snapshot
- [x] 2.5 Pasar `php artisan check:destructive-migrations` y ejecutar `php artisan migrate:safe`

## 3. Modelo y validaciones

- [x] 3.1 `RestreamTarget`: añadir `scheduled_stop_at` a fillables y casts
- [x] 3.2 `Client\RestreamTargetController` (store/update): validar `scheduled_stop_at` (nullable, date, `after:scheduled_start_at` cuando ambos presentes); en `store`, pre-chequeo de tripleta duplicada (incluyendo soft-deleted) que responda 422 accionable en vez de 500
- [x] 3.3 `Admin\RestreamTargetController` (store/update): paridad — aceptar `title`, `description`, `thumbnail_media_id`, `thumbnail`, `platform_account_id`, `scheduled_start_at`, `scheduled_stop_at` con las mismas validaciones y creación de broadcast vía cuenta vinculada cuando aplique

## 4. Scheduler (arranque y parada programados)

- [x] 4.1 Reescribir `RestreamLaunchScheduledCommand`: pasada `start` (guard de cuota + `RestreamOrchestrator::start()`) y pasada `stop` (`RestreamOrchestrator::stop()` + limpiar `scheduled_stop_at`), con try/catch por target (`last_error`), salida de consola informativa e idempotencia
- [x] 4.2 Verificar/instalar cron de sistema `* * * * * cd /www/wwwroot/cloudstream.mediaserver.com.co && /usr/bin/php artisan schedule:run >> /dev/null 2>&1` (usuario www-data) y verificar con `php artisan schedule:list`

## 5. YouTube API

- [x] 5.1 `YoutubeBroadcastService::create()`: enviar `snippet.scheduledEndTime` cuando `scheduled_stop_at` esté presente, con degradación controlada (retry sin el campo + warning en log) si la API lo rechaza
- [x] 5.2 `YoutubeBroadcastService::update()`: mismo manejo para `scheduledEndTime`

## 6. UI (dual-implementation)

- [x] 6.1 Modal cliente `restream-target-modal`: campo "Programar fin (opcional)" `datetime-local` con `min` ligado al inicio, envío en store/update, limpieza cuando va vacío, y display del fin programado en el resumen (mismo patrón que inicio)
- [x] 6.2 Modal admin `restream-modal`: mismos campos (título, descripción, miniatura multimedia, inicio, fin) con el mismo patrón de escape `&quot;` en atributos `x-data`
- [x] 6.3 Ejecutar `php artisan view:clear` y `npm run build:css` por las clases Tailwind nuevas

## 7. Pruebas y verificación

- [x] 7.1 (verificada vía Playwright E2E: creación sin 500 y 422 accionable); nota: original manual Prueba del fix 500: crear destino YouTube (cuenta vinculada) para el canal de pruebas con título/descripción/miniatura — debe guardar sin 500 pese a la fila fantasma soft-deleted
- [x] 7.2 Prueba de programación: mismo destino con inicio +5 min y fin +15 min (hora Colombia); verificar que el cron arranca el daemon (`status=starting → live`, `pipeline_pid` presente) y lo detiene a la hora del fin (`status=idle`, `scheduled_stop_at=NULL`)
- [x] 7.3 (verificada vía Playwright E2E: modal admin con paridad completa); nota: original manual Prueba de paridad admin: crear/editar destino con programación desde el panel admin
- [x] 7.4 Verificación de normalización: `php artisan tinker` — ninguna fila con `status` fuera del enum; constraint parcial visible en `pg_constraint`/`\d+ restream_targets`; `restream:status` sin anomalías
- [x] 7.5 Ejecutar suite de tests existente de restream (si aplica) y confirmar que nada regresó