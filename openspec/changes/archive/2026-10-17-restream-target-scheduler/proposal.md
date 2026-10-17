## Why

La programación de un destino restream vive hoy como **dos campos opcionales** (`scheduled_start_at`, `scheduled_stop_at`) en `restream_targets`, procesados por `restream:launch-scheduled` cada minuto. Cubre el caso trivial — un cliente programa un único par inicio/fin en un único destino — pero **no escala a los casos reales del cliente**:

1. Un cliente que quiere emitir todos los días hábiles 7–9am (noticiero matutino) **no puede** definir esa rutina porque el modelo es un único par por target.
2. El cliente que quiere dejar un stream corriendo "hasta que yo lo pare" (caso real de transmisiones 24/7 o de una cobertura que se extiende) **no puede** dejar `scheduled_stop_at` vacío: sin fin el motor no enciende nada (porque el chequeo actual es `scheduled_stop_at <= now()` y con `NULL` nunca se cumple la condición de arranque).
3. Si una transmisión que lleva 3 meses emitiendo cae de madrugada, el operador abre Cloudstream al día siguiente y solo encuentra `last_error` (un string sobreescrito) + trazas dispersas en `audit_logs`. **No sabe si fue la red, YouTube que bloqueó, o el daemon local.**
4. No existe un visualizador tipo calendario: las programaciones son dos inputs de formulario, no una agenda navegable.

Se necesita un módulo de **ventanas de programación N por destino** (no entidades separadas tipo "evento"): arranca cuando corresponde, se detiene cuando corresponde, soporta fin abierto (broadcast indefinido), persiste historial causal de fallos, y se visualiza como calendario.

## What Changes

- **Tabla nueva** `restream_target_schedules` (id, target_id, starts_at, ends_at nullable, timezone, name opcional, notes, enabled, created/updated) — N ventanas por destino.
- **Tabla nueva** `restream_target_events` (id, target_id, kind, message, metadata jsonb, occurred_at) — historial de transiciones y fallos con causa clasificada (auto_started, auto_stopped, manual_started, manual_stopped, youtube_revoked, youtube_completed_by_remote, ffmpeg_died, network_unreachable, oauth_expired, daemon_crashed, watchdog_recovery_succeeded, watchdog_recovery_exhausted, …).
- **Migración destructiva** que convierte cada par `scheduled_start_at`/`scheduled_stop_at` existente en una fila de `restream_target_schedules` y luego elimina ambas columnas. Usa el trait `WithDataSafetySnapshot` del proyecto (protocolo AGENTS.md).
- **Nuevo modelo** `RestreamTargetSchedule` con relaciones, scopes (por target, por canal, ventanas activas, próximas a vencer) y accesores `is_running`, `is_indefinite`, `local_starts_at`, `local_ends_at`.
- **Nuevo modelo** `RestreamTargetEvent` (readonly append-only) con scopes por target y por kind, y accesores para "reason" legible en español.
- **Nuevo motor** `restream:run-target-schedules` (cada minuto, `withoutOverlapping`): para cada target habilitado, encuentra la ventana aplicable, reconcilia con el estado actual del daemon (idempotente con la lógica ya existente en `RestreamOrchestrator::start()`/`stop()`), escribe al historial con la causa observada.
- **API JSON** `restream-target-scheduler-api`: CRUD para `restream_target_schedules`, listar `restream_target_events` por target, acciones `pause`/`resume`/`stop-now`/`trigger-now`.
- **Web CRUD (dual-implementation)** cliente/admin para las ventanas del target.
- **UI calendario** por target con toggle mes/semana, bloques coloreados por estado, modal para crear/editar con `starts_at` + `ends_at` (ambos `datetime-local`, `ends_at` opcional) + `timezone` (select IANA).
- **Sección de historial** dentro del target que muestra los últimos N eventos con su `kind` legible (icono + tooltip). El operador ve explícito "YouTube revocó el broadcast por community guidelines" en vez de un `last_error` genérico.
- **Coexistencia con el watchdog**: el `restream:reap-dead-broadcasts` que acabamos de agregar sigue corriendo y enriquece `restream_target_events` cuando detecta un broadcast consumido por YouTube (con `kind=youtube_revoked` o `kind=youtube_consumed` según el `lifeCycleStatus`).
- **Cuota y permisos**: el motor respeta `RestreamQuotaGuard::assertCanEnable` al intentar arrancar; si no hay slot, escribe `kind=quota_exceeded` al historial y no arranca.
- **Targets deshabilitados**: si `enabled=false`, las ventanas se ignoran (no se auto-arranca); documentado en la UI.

**BREAKING:**
- Se eliminan las columnas `restream_targets.scheduled_start_at` y `restream_targets.scheduled_stop_at` (migradas). El controlador admin (`Admin\RestreamTargetController`) que las exponía en su form request debe actualizarse. El controlador cliente (`Client\RestreamTargetController`) ya no las muestra.
- Las claves de auditoría `auto_start.restream_target` / `auto_stop.restream_target` se complementan con nuevas claves `auto_start.restream_target_schedule` / `auto_stop.restream_target_schedule` (sin eliminar las primeras; siguen usándose para los arranques manuales desde la UI).

## Capabilities

### New Capabilities

- `restream-target-scheduling`: entidad ventana de programación (`RestreamTargetSchedule`), motor de ejecución por minuto con fin opcional, idempotente, respeto de quota, targets deshabilitados se ignoran.
- `restream-target-scheduling-api`: contratos JSON para CRUD de ventanas, listado de historial de eventos, acciones manuales `trigger-now` / `stop-now` / `pause` / `resume`.
- `restream-target-health`: tabla append-only `restream_target_events` con `kind` clasificado, accesores legibles, retention de 90 días vía `restream:prune-target-events` diario.
- `restream-target-calendar-ui`: vista calendario por target con toggle mes/semana, bloques coloreados por estado, modal de creación/edición con `datetime-local` y selector IANA de timezone.
- `restream-schedule-legacy-migration`: migrar `restream_targets.scheduled_start_at`/`scheduled_stop_at` a filas de `restream_target_schedules` y eliminar las columnas (cambio destructivo con snapshot de seguridad).

### Modified Capabilities

- `restream-scheduled-lifecycle`: su requisito `Programación de inicio y fin de un destino` se **elimina** y se reemplaza por referencia a la nueva capability `restream-target-scheduling`. El campo `restream_targets.scheduled_start_at` deja de existir. El comando `restream:launch-scheduled` deja de procesar esos campos (la lógica equivalente queda en `restream:run-target-schedules`). El requisito de "Cron del scheduler instalado" se mantiene; agregamos nota en `Purpose` de que coexisten dos comandos: `restream:launch-scheduled` (legacy, sin targets una vez migrados) y `restream:run-target-schedules` (motor nuevo). Eventualmente se podría borrar `restream:launch-scheduled` cuando no haya targets con ventanas gestionadas por el sistema legacy (dejar para Open Question).

## Impact

- **DB:** 2 tablas nuevas (`restream_target_schedules`, `restream_target_events`), 1 columna eliminada de `restream_targets.scheduled_start_at`, 1 columna eliminada de `restream_targets.scheduled_stop_at`, índices en `(target_id, starts_at)` y `(occurred_at)`. Migración nueva usa `WithDataSafetySnapshot` (obligatorio por protocolo AGENTS.md para migraciones destructivas).
- **Modelos:** `App\Models\RestreamTargetSchedule` (nuevo), `App\Models\RestreamTargetEvent` (nuevo). `RestreamTarget` gana relación `schedules()` y `events()`; pierde las 2 columnas.
- **Servicios:** `App\Services\Restream\TargetSchedulerService` (nuevo, encapsula resolver-ventanas, reconciliar-estado, escribir-evento).
- **Comandos nuevos:** `App\Console\Commands\RestreamRunTargetSchedulesCommand`, `App\Console\Commands\PruneTargetEventsCommand`. `routes/console.php` registra ambos.
- **Enriquecimiento del `RestreamReapDeadBroadcastsCommand`:** ahora escribe el resultado al `restream_target_events` del target con `kind=youtube_revoked` o `kind=youtube_completed_by_remote` según `lifeCycleStatus` (no consume nada nuevo; solo añade escritura).
- **Controladores (dual-implementation):** `Client\RestreamTargetScheduleController`, `Admin\RestreamTargetScheduleController`. `Api\RestreamTargetScheduleController`. APIs CRUD + acciones manuales.
- **Vistas (dual-implementation):** `client.restream.target.schedules.*` (incluye calendario), `admin.restream.target.schedules.*` (read-only + acciones admin). Componente compartido `<x-target-schedule-modal>`.
- **Sin nuevas dependencias Composer.** El calendario se implementa con Alpine.js + Tailwind (grid CSS); no usamos FullCalendar ni libs externas.
- **El snapshot release** no se ve afectado (no toca tablas de PlataformaAccount, Quota, Channel, User).
- **Cambios al UI cliente:** la captura inicial (`client.restream.index`) gana una sección colapsable "Programación" por cada target con un mini-calendario de los próximos 7 días y un botón "Calendario completo".
- **Cambios al UI admin:** `admin.restream.index` muestra el próximo evento de cada target (badge) + acceso al historial completo.

---

## Addendum (2026-09-30): YouTube bidirectional broadcast lifecycle

### Why

El change original implementó el lado **START** del ciclo de vida (`start` crea un broadcast nuevo en YouTube vía `YoutubeBroadcastService::create()`) pero dejó **incompleto el lado END**. `RestreamOrchestrator::stop()` mata el daemon Python y limpia `pipeline_pid`/`status`, **pero nunca llama al endpoint `liveBroadcasts/transition?broadcastStatus=complete`** de YouTube. Como los broadcasts se crean con `enableAutoStop=false` (decisión correcta — evita que un micro-disconnect queme el stream key), YouTube nunca los transita a `complete` por sí solo. Resultado: cuando una ventana programada llega a `ends_at`, el calendario marca "Finalizado", nuestro status queda `idle`, pero YouTube Studio sigue mostrando el stream como "EN DIRECTO" indefinidamente.

Esto fue confirmado contra `YoutubeBroadcastService.php:54-60` (el comentario describe una transición manual que nunca se implementó) y por una búsqueda global en `app/` que no encuentra ninguna llamada a `liveBroadcasts/transition` o `broadcastStatus=complete`.

Adicionalmente, ambos comandos que consultan lifecycle de YouTube (`restream:reap-dead-broadcasts` y `restream:sync-youtube-status`) filtran `WHERE pipeline_pid IS NOT NULL`, por lo que **ni siquiera detectan el zombie**: cuando nuestro target ya está `idle`, YouTube sigue siendo `live`, y nadie mira.

### What Changes (addendum)

- **Nuevo método** `YoutubeBroadcastService::transitionToComplete(RestreamTarget): bool` — llama `POST liveBroadcasts/transition?id={id}&broadcastStatus=complete&part=status`. Try/catch absoluto, nunca propaga. Devuelve `true` solo cuando YouTube confirma la transición.
- **Nuevo método** `YoutubeBroadcastService::forceCleanBroadcast(RestreamTarget): bool` — usado por el botón "Forzar cierre" de la UI; llama `transitionToComplete()` y luego limpia `platform_broadcast_id`/`destination_url`/`stream_key` del target para que el próximo `start()` cree un broadcast fresco.
- **Modificación** `RestreamOrchestrator::stop()` — entre el wait offline y el UPDATE final, llama `transitionToComplete()` cuando `platform='youtube' && platform_broadcast_id IS NOT NULL`. Falla tolerante: si la API responde error (token expirado, broadcast ya complete, red), log warning + escribe `restream_target_events.kind=youtube_transition_failed` y continúa con el UPDATE a `idle` de todas formas.
- **Nuevo comando** `restream:reap-youtube-zombies` (cada minuto, `withoutOverlapping`) — escanea targets con `enabled=true, platform='youtube', pipeline_pid IS NULL, status IN ('idle','stopping'), platform_broadcast_id IS NOT NULL` y consulta YouTube. Si `lifeCycleStatus ∈ {live, liveStarting, testing}` → intenta `transitionToComplete()` → registra `kind=youtube_zombie_detected` y `kind=youtube_zombie_recovered` o `kind=youtube_zombie_recovery_failed`. Si `lifeCycleStatus ∈ {complete, revoked}` → limpia `platform_broadcast_id` (broadcast ya cerrado en YouTube; nuestro binding es basura).
- **Nuevo estado `zombie-live`** en `RestreamStatusResolver` — `pipeline_pid=null AND lifecycle ∈ {live, liveStarting, testing}`. Label: "Sigue en YouTube aunque inactivo aquí". Badge `bg-red-100 text-red-800` + dot `bg-red-500 animate-pulse`. Alimentado por el sync cada 30s existente (`platform_broadcast_lifecycle` ya se persiste).
- **Nuevas constantes** en `RestreamTargetEventKind`: `YOUTUBE_TRANSITION_SUCCEEDED`, `YOUTUBE_TRANSITION_FAILED`, `YOUTUBE_ZOMBIE_DETECTED`, `YOUTUBE_ZOMBIE_RECOVERED`, `YOUTUBE_ZOMBIE_RECOVERY_FAILED`.
- **Nuevo endpoint** `POST /client/channels/{c}/restream-targets/{t}/force-close-youtube` y su espejo admin — llama `forceCleanBroadcast()`. Permisos: owner del channel (cliente) o admin (admin). Audit `action='force_close.restream_target'`.
- **Vista calendario** — junto al badge "Finalizado" de cada ventana, renderiza "✓ YouTube cerrado" o "⚠ YouTube aún live" leyendo el lifecycle persistido del broadcast vinculado a esa ventana (evento `auto_stopped` + `metadata.broadcast_id` → poll lifecycle actual).
- **Botón "Forzar cierre en YouTube"** en el panel de detalle del target (admin + cliente) cuando `effective_status='zombie-live'`. Confirmación Alpine.js con el patrón del proyecto.

### Modified Capabilities

- `restream-target-scheduling`: nuevo requisito "When a scheduled window reaches `ends_at`, the system MUST transition the bound YouTube broadcast to `complete` within the same cron tick, and the broadcast MUST end up in `lifeCycleStatus=complete` (or `revoked`/deleted) before the next `start()` is allowed to bind to the same stream key."
- `restream-target-health`: cinco `kind` nuevos anexados al enum cerrado (ver lista arriba).
- `restream-target-scheduling-api`: nuevo endpoint `force-close-youtube` documentado en el spec.
- `restream-target-calendar-ui`: badge "✓ YouTube cerrado" / "⚠ YouTube aún live" post-finalización de cada ventana.

### Impact (incremental, sobre lo ya implementado)

- **Servicios:** 2 métodos nuevos en `YoutubeBroadcastService`. `RestreamOrchestrator::stop()` gana un bloque de 15 líneas (transition + recordEvent). `RestreamStatusResolver` gana 1 constante + 1 rama + 3 métodos de presentación.
- **Comandos:** 1 nuevo (`RestreamReapYoutubeZombiesCommand`). `routes/console.php` registra 1 línea adicional.
- **Modelos:** `RestreamTargetEventKind` gana 5 constantes + 5 entries en `ALL`.
- **Controladores (dual-implementation):** 2 nuevos métodos `forceClose` (Client + Admin) + 2 rutas web.
- **Vistas (dual-implementation):** 1 badge nuevo en calendario + 1 botón "Forzar cierre" en panel de detalle (admin + cliente). Sin cambios estructurales; solo condicionales sobre `effective_status='zombie-live'`.
- **Sin nuevas dependencias Composer. Sin nuevas migraciones** (el addendum opera sobre la tabla `restream_targets` ya existente).
- **Coste de API:** `reap-youtube-zombies` consume `liveBroadcasts.list` solo para targets idle con broadcast pendiente (típicamente cero después del rollout). `transitionToComplete` consume 1 unidad por `stop()` YouTube (cada ventana termina = 1 call). Por debajo de las 10k/día del quota de YouTube Data API v3.

### Coexistencia con `refreshDeadBroadcast()`

El `refreshDeadBroadcast()` ya existente detecta correctamente broadcasts en `lifeCycleStatus=complete` al `start()` y los recrea — por lo que el flujo de múltiples ventanas consecutivas (ventana 1 termina, ventana 2 arranca) queda cubierto en cuanto la transición se hace: ventana 2 detecta `isBroadcastConsumed()=true` y crea un broadcast nuevo. No requiere cambios en `refreshDeadBroadcast()`.

### Plan de rollout incremental (4 fases, cada una desplegable independiente)

1. **`transitionToComplete()` + llamada en `stop()`** — cierra el bug raíz. Falla tolerante → no rompe nada.
2. **`restream:reap-youtube-zombies`** — limpia los zombies históricos ya en producción.
3. **`ZOMBIE_LIVE` en resolver + badge UI** — visibilidad para el operador.
4. **Botón "Forzar cierre" + 5 `kind` nuevos en historial** — control manual + trazabilidad completa.
