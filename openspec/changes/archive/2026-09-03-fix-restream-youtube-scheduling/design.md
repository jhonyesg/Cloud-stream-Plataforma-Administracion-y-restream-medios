# Design: fix-restream-youtube-scheduling

## Context

El módulo Restream permite crear destinos (YouTube/Facebook/TikTok/custom). Con **cuenta vinculada** (`restream_platform_accounts`), `YoutubeBroadcastService` ya crea broadcast + liveStream + bind vía API y devuelve la URL/clave de ingesta — no hace falta RTMP manual. El broadcast se crea con `enableAutoStart/enableAutoStop=true`, de modo que **la programación de inicio/fin equivale a decidir cuándo arranca y cuándo se detiene el proceso FFmpeg local**.

Estado actual verificado:

- `restream_targets` tiene `scheduled_start_at`, `title`, `description`, `thumbnail_media_id`, `thumbnail_path`, `platform_broadcast_id`.
- `RestreamTarget` usa `SoftDeletes`; existe la constraint rígida `UNIQUE (user_id, channel_id, platform)` creada en `2026_08_14_000001_create_restrean_module_tables.php`.
- Log del 2026-09-02 11:36/11:43: `SQLSTATE[23505]` al crear destino YouTube para el canal de pruebas — existe una fila soft-deleted (`a2a52a02…`) que bloquea la tripleta.
- `routes/console.php:14` registra `restream:launch-scheduled` everyMinute, pero **ningún crontab ni systemd timer ejecuta `php artisan schedule:run`** en este servidor (verificado en `crontab -l`, `/var/spool/cron/crontabs/root`, `/etc/cron.d`, `systemctl list-timers`).
- `RestreamLaunchScheduledCommand` solo hace `$target->update(['enabled' => true])`; el supervisor PHP antiguo fue eliminado (restream-engine), así que `enabled=true` **no arranca nada**. El arranque real lo hace `RestreamOrchestrator::start()` (invocado por HTTP en los botones start/stop).
- Una fila legacy tiene `status='active'` mientras el modelo define `STATUS_ACTIVE = 'live'`.
- Timezone de la app: `America/Bogota` (coincide con el requisito "horario Colombia").
- El modal cliente ya envía `scheduled_start_at` (datetime-local) y lo limpia del FormData si va vacío.

## Goals / Non-Goals

**Goals:**

- Eliminar el 500 al crear un destino YouTube tras un borrado previo.
- Programación completa inicio **y** fin (ambos opcionales) que realmente ejecuta el orquestador.
- Paridad admin/cliente en metadatos + programación.
- Normalización y validación de las tablas restream (estados, FKs, índices, huérfanos).

**Non-Goals:**

- No se construye UI de pagos/comercial de habilitaciones (fuera de alcance del módulo).
- No se cambia el daemon Python (`restream_daemon`) ni su modelo de latidos.
- No se implementa parada de Facebook/TikTok programada más allá del FFmpeg (mismo mecanismo genérico aplica).
- No se refactoriza `RestreamOrchestrator` más allá de lo necesario.

## Decisions

### D1 — Constraint única parcial (PostgreSQL) en vez de columna discriminatoria

```sql
ALTER TABLE restream_targets DROP CONSTRAINT restream_targets_user_channel_platform_unique;
ALTER TABLE restream_targets
  ADD CONSTRAINT restream_targets_user_channel_platform_unique
  UNIQUE (user_id, channel_id, platform)
  WHERE (deleted_at IS NULL);
```

- *Por qué*: `SoftDeletes` + unique rígido es la causa directa del 500. La única parcial es idiomática en PG y no toca código de aplicación. Alternativa descartada: incluir `deleted_at` en la llave (frágil con microsegundos y múltiples borrados del mismo destino).

### D2 — Detección de duplicados en el controlador (defensa en profundidad)

Antes de crear, `RestreamTargetController::store` consulta (incluyendo soft-deleted) si la tripleta existe; si existe soft-deleted, **la recicla** (restaura y actualiza) o responde 422 con mensaje claro — se elige 422 explícito para que el usuario decida restaurar desde la UI ya existente. El 23505 no debe volver a manifestarse como 500.

### D3 — Fin programado como columna nueva `scheduled_stop_at`, no como duración

- `scheduled_stop_at` timestampTz nullable, cast datetime, validación `after_or_equal` contra `scheduled_start_at` cuando ambos están.
- *Por qué*: más simple y simétrico con `scheduled_start_at`; una duración derivable se calcula en UI si se quiere. Alternativa (campo `duration_min`) descartada por ambigüedad al editar.

### D4 — Scheduler único: `restream:launch-scheduled` hace start/stop reales

El comando pasa a:

1. `start`: targets con `scheduled_start_at <= now`, `enabled=false`, sin `pipeline_pid` fresco → `RestreamQuotaGuard::assertCanEnable` (si falla cuota: marca `last_error` y continúa con los demás) → `RestreamOrchestrator::start($target)`.
2. `stop`: targets con `scheduled_stop_at <= now` y (`enabled=true` o `pipeline_pid` presente) → `RestreamOrchestrator::stop($target)`.
3. Idempotente: cada query se limita a los que aún no transicionaron (start solo toca `enabled=false`; stop limpia `scheduled_stop_at` tras ejecutar para no repetir).
4. `try/catch` por target: un fallo no aborta el lote; registra `last_error` y continúa.

*Alternativa descartada*: command aparte `restream:stop-scheduled` — dos crons para un ciclo de vida único complica la instalación; un comando con ambos pasadas es más fácil de auditar con `restream:status`.

### D5 — Cron de sistema: instalación documentada + verificación de salud

Se instala (o documenta en tasks para el operador) el crontab:

```
* * * * * www-data cd /www/wwwroot/cloudstream.mediaserver.com.co && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

- *Por qué*: sin esto, `everyMinute` en `routes/console.php` es código muerto. La instalación es una tarea de deploy (requiere acceso root), no código; el change la documenta y añade verificación (`php artisan schedule:list`).
- Nota de horario: el cron corre en timezone del servidor (America/Bogota ya confirmado en `date`); `scheduled_start_at` se almacena con offset `-05` en Postgres (`timestampTz`), por lo que las comparaciones `<= now()` son seguras independientemente del TZ del proceso.

### D6 — Paridad admin: mismos campos en `Admin\RestreamTargetController` y `restream-modal`

Validaciones espejo de las del cliente: `title`, `description`, `thumbnail_media_id`, `thumbnail`, `scheduled_start_at`, `scheduled_stop_at`, `platform_account_id`. Regla del proyecto (dual-implementation): solo cambia el alcance de datos (admin = todos, cliente = propios).

### D7 — YouTube: `scheduledEndTime` en snippet

En `create()` y `update()`, cuando `scheduled_stop_at` esté presente se añade `snippet.scheduledEndTime` (formato Atom/ISO-8601). Si la API lo rechaza por versión de API, se degrada a no enviarlo con log de warning (no rompe la creación). `enableAutoStart/enableAutoStop` permanecen: el corte de FFmpeg detiene la emisión aunque YT ignore el endTime.

### D8 — Normalización de BD restream (auditoría + corrección)

Auditoría y correcciones incluidas en la migración/limpieza:

1. **Estados**: normalizar `status='active'` → `'live'` (constante del modelo). Se añade `CHECK` lógico vía validación (no constraint dura, para no bloquear valores futuros) y un comando/heurística de reparación en la migración.
2. **Huérfanos**: `user_id`/`channel_id`/`platform_account_id`/`thumbnail_media_id` ya tienen FK verificadas en BD; se verifica que no haya `platform_account_id` apuntando a cuenta de otra plataforma (inconsistencia lógica) y se nulifica si existe.
3. **Fila fantasma**: la fila soft-deleted duplicada se conserva (histórico) — la constraint parcial de D1 la libera sin borrar nada.
4. **Índices para el scheduler**: `CREATE INDEX ... ON restream_targets (scheduled_start_at) WHERE enabled = false` y `ON restream_targets (scheduled_stop_at) WHERE scheduled_stop_at IS NOT NULL` (parciales, baratos, exactamente los predicados del comando).
5. **Verificación**: reporte impreso por la migración (números de filas normalizadas) + `php artisan db:list-snapshots` antes/después.

*Toda* migración destructiva usa `WithDataSafetySnapshot` con `$criticalTables = ['restream_targets']`, y `php artisan db:backup` precede al despliegue (protocolo AGENTS.md).

## Risks / Trade-offs

- [La constraint parcial requiere PostgreSQL ≥ 9.0] → Servidor está en PG14/16; verificado. Riesgo nulo.
- [Cron de sistema puede no instalarse en el despliegue] → Tarea explícita en tasks.md con comando exacto + verificación `schedule:list`; sin ella la programación no funciona (se marca como prerequisito operacional).
- [Cuota agotada al llegar la hora programada] → el comando captura `RestreamQuotaException`, escribe `last_error`, deja el target habilitado=false y no bloquea a otros targets. El usuario ve el error en `restream:status`.
- [`scheduledEndTime` puede no aceptarse por la API de YT] → envío condicional con catch de degradación; el corte real lo hace la parada programada local.
- [Doble arranque (cron + usuario pulsa start a la vez)] → `start()` del orquestador ya es guardado (`isHeartbeatFresh`/`pipeline_pid`); la ventana es mínima y el guard lo cubre.
- [Migración de constraint sobre tabla con datos] → `db:backup` previo + snapshot por migración; rollback verificado en down().

## Migration Plan

1. `php artisan db:backup` (protocolo obligatorio).
2. `php artisan migrate:safe` — aplica: constraint parcial (con verificación previa de no-duplicados entre filas vivas), `scheduled_stop_at`, índices, normalización de estados.
3. Instalar cron `schedule:run` (operador) y verificar con `php artisan schedule:list`.
4. Desplegar código (controladores, servicio, comando, vistas). `npm run build:css` si se añaden clases Tailwind nuevas (sí: badges de programación en el modal).
5. Prueba E2E (ver tasks): crear destino YouTube con inicio +5 min y fin +15 min, cron en marcha, observar estados `idle → starting → live → idle`.
6. Rollback: `php artisan migrate:rollback` restaura snapshot de `restream_targets`; el código es retrocompatible (la columna nueva es nullable).

## Open Questions

- ¿Se quiere notificación (email/UI) cuando el arranque programado falla por cuota? Hoy solo queda en `last_error` + `restream:status`. (Se propone postergar.)
- ¿El fin programado debe deshabilitar el target (`enabled=false`) además de detenerlo, para liberar el slot de cuota? (Se propone: solo detener; el usuario decide si deshabilita — coincide con `deactivate` explícito.)