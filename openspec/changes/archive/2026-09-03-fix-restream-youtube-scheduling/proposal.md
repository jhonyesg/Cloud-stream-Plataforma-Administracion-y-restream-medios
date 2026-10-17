# Proposal: fix-restream-youtube-scheduling

## Why

Crear un destino YouTube con cuenta vinculada hoy falla con **error 500** (violación de la constraint UNIQUE `restream_targets_user_channel_platform_unique` por filas soft-deleted que siguen ocupando la llave). Además, la programación de inicio (`scheduled_start_at`) existe en el formulario y en la BD pero **nunca se ejecuta**: no hay cron que corra `schedule:run` en este servidor, y el comando `restream:launch-scheduled` solo marca `enabled=true` sin arrancar el daemon. No existe forma de programar el **fin** de la emisión.

## What Changes

- **Fix 500**: migrar la constraint `restream_targets_user_channel_platform_unique` a una constraint **parcial** `WHERE deleted_at IS NULL` para que los destinos soft-deleted liberen la tripleta `(user_id, channel_id, platform)`.
- **Nueva columna** `scheduled_stop_at` (timestampTz nullable) en `restream_targets` + cast en el modelo + validación en los controladores.
- **Validación temporal**: `scheduled_stop_at > scheduled_start_at` cuando ambos están presentes.
- **Scheduler real**: ampliar `restream:launch-scheduled` para (a) arrancar destinos cuyo `scheduled_start_at` llegó (llamando a `RestreamOrchestrator::start()` con `RestreamQuotaGuard::assertCanEnable`, no solo `enabled=true`), y (b) detener destinos cuyo `scheduled_stop_at` llegó (llamando a `RestreamOrchestrator::stop()`).
- **Cron de sistema**: documentar e instalar el crontab `* * * * * php artisan schedule:run` (hoy NO existe en este servidor; `restream:launch-scheduled` está registrado en `routes/console.php` pero nada lo dispara).
- **Formulario cliente** (`restream-target-modal`): campo opcional "Programar fin" (`scheduled_stop_at`, `datetime-local`, min = inicio) + envío en store/update.
- **Paridad admin** (`Admin/RestreamTargetController` + `restream-modal`): soporte para `title`, `description`, `thumbnail_media_id`, `scheduled_start_at` y `scheduled_stop_at` (hoy el admin no los maneja).
- **YouTube API**: enviar `scheduledEndTime` en el snippet del broadcast cuando exista `scheduled_stop_at` (create y update).
- **Normalización y validación de BD**: auditoría y corrección de las tablas restream — limpiar huerfanos/referencias inconsistentes, verificar FKs (`platform_account_id`, `thumbnail_media_id`, `user_id`, `channel_id`, `created_by`), alinear los valores de `status` con el enum del modelo (`idle|starting|live|error`; hoy existe una fila legacy con `status='active'` que el modelo no reconoce como constante), e índices sobre las columnas usadas por el scheduler (`enabled`, `scheduled_start_at`, `scheduled_stop_at`).
- Snapshots de seguridad (`WithDataSafetySnapshot`) en las migraciones destructivas, conforme al protocolo del proyecto.

### Añadidos durante la implementación (validados en E2E con la cuenta real de YouTube)

- **Programación para todas las plataformas** (no solo OAuth): el panel "Programación" es global (custom/RTMP incluido); el modal ya no borra `scheduled_start_at`/`scheduled_stop_at` del payload cuando el destino no usa cuenta vinculada.
- **Columna "Programación" en las tablas** (cliente + admin): hora de inicio y fin visibles de un vistazo, reemplaza la columna PID.
- **Historial de ejecución**: el scheduler registra `auto_start.restream_target` / `auto_stop.restream_target` en `audit_logs`; las tablas muestran "✓ auto-inicio / ✓ auto-fin" con fechas — el usuario puede verificar si la emisión se ejecutó.
- **Precaliente**: el scheduler arranca el FFmpeg 45 s antes de la hora programada (`PREHEAT_SECONDS`) para que YouTube ya tenga señal al inicio exacto.
- **Link compartible de YouTube**: botón "Compartir" por destino (copia `https://www.youtube.com/watch?v=<platform_broadcast_id>` al portapapeles con confirmación).
- **Privacidad configurable**: select Público/Oculto/Privado en el formulario; por defecto **Público** para YouTube (antes todo era `unlisted` y no aparecía en el canal). Nueva columna `platform_privacy`; el update sincroniza la privacidad al broadcast.
- **Re-creación automática del broadcast**: los broadcasts de YouTube son de un solo uso; al editar/guardar o al arrancar una emisión programada con un broadcast `complete`/inexistente, se crea uno nuevo automáticamente (`isBroadcastConsumed()`). También se crea cuando el destino no tiene broadcast vinculado.
- **"Conservar la grabación"** (checkbox, por defecto activo): al desmarcarse, el scheduler borra el video de YouTube justo después de la parada automática. Nueva columna `keep_recording`. (YouTube rechaza `enableDvr=false` en esta cuenta, por lo que se implementa como post-borrado).
- **Precarga del modal al editar**: programación, título, descripción, miniatura, privacidad y ahora "Conservar grabación" se rellenan al abrir (evento `restream-target-modal-opened`).
- **Fix UI**: select de privacidad colapsado (había options duplicados fuera del select), texto informativo "las miniaturas se toman del módulo Multimedia", y validación `platform_privacy`/`keep_recording` en ambos controladores.
- **Landing comercial**: nueva sección "Emite a YouTube en 4 pasos" con screenshots reales del módulo (`restream-panel.jpg`, `restream-schedule.jpg`).

## Capabilities

### New Capabilities

- `restream-scheduled-lifecycle`: programación de inicio y fin de un destino restream (inicio automático vía cron + orquestador con precaliente de 45 s, parada automática programada, validaciones temporales, cron de sistema, auditoría de ejecuciones, re-creación automática de broadcasts de YouTube consumidos, conservación opcional de grabación, link compartible).

### Modified Capabilities

- `restream-targets`: la constraint única pasa a ser parcial (respeta soft-deletes); nuevas columnas `scheduled_stop_at`, `platform_privacy` y `keep_recording`; valores de `status` normalizados; paridad admin en metadatos y programación.
- `data-model`: `restream_targets` gana `scheduled_stop_at`, `platform_privacy`, `keep_recording`, constraint única parcial e índices del scheduler; corrección de estados legacy.
- `restream-launcher`: el launcher programado ahora arranca/detiene vía orquestador (no solo habilita), arranca con 45 s de precaliente, audita sus acciones, re-crea broadcasts de YouTube consumidos y borra grabaciones no conservadas; idempotente y seguro ante cuota agotada.

## Impact

- **Migraciones**: 4 nuevas (constraint parcial + `scheduled_stop_at` con índices; `platform_privacy`; `keep_recording`), con snapshot la primera.
- **Modelos**: `App\Models\RestreamTarget` (fillables, casts, constantes de estado).
- **Controladores**: `Client\RestreamTargetController`, `Admin\RestreamTargetController` (validación + lógica + payload).
- **Servicios**: `App\Services\Restream\Platform\YoutubeBroadcastService` (`scheduledEndTime`, `scheduledEndTime`, privacidad, `isBroadcastConsumed`), `App\Console\Commands\RestreamLaunchScheduledCommand`.
- **Vistas**: `restream-target-modal` (compartido), `restream-modal` (admin), tablas de `client/restream/index` y `admin/restream/index`, `landing.blade.php`.
- **Infra**: crontab de sistema (`schedule:run` cada minuto, PHP 8.4) — instalado y verificado.
- **Assets**: `public/images/demo/restream-panel.jpg`, `restream-schedule.jpg` (screenshots reales para la landing).
- **Comando CI**: `php artisan check:destructive-migrations` pasa con las nuevas migraciones.
- **Riesgo de despliegue**: la migración de la constraint requiere borrar/reciclar la fila fantasma soft-deleted duplicada (el snapshot permite rollback).