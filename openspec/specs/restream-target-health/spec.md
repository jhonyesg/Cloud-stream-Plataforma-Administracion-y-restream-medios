# restream-target-health Specification

## Purpose
TBD - created by archiving change restream-target-scheduler. Update Purpose after archive.

## Requirements

### Requirement: Tabla append-only restream_target_events

El sistema SHALL persistir eventos del ciclo de vida de un target restream en `restream_target_events` (append-only, sin `updated_at`). Campos: `id` (UUID), `target_id` (FK a `restream_targets`, cascade on delete), `kind` (string contra whitelist cerrada), `message` (string legible en español o i18n key), `metadata` (jsonb nullable), `occurred_at` (timestamp con zona). Índice en `(target_id, occurred_at)` descendente y `occurred_at` simple.

#### Scenario: Evento de arranque automático se persiste
- **WHEN** el motor arranca el daemon del target
- **THEN** se inserta 1 fila `kind=auto_started`, `occurred_at=now()`, `metadata={window_id, broadcast_id?}`

#### Scenario: Evento de revocación YouTube se persiste con metadata causal
- **WHEN** el reap detecta `lifeCycleStatus=revoked`
- **THEN** se inserta 1 fila `kind=youtube_revoked`, `metadata={broadcast_id, lifecycle: "revoked", recovered: bool}`

#### Scenario: kind inválido es rechazado
- **WHEN** el código intenta insertar un `kind` fuera de la whitelist
- **THEN** la inserción lanza `InvalidArgumentException` y no se persiste

#### Scenario: Borrar target borra sus eventos (CASCADE)
- **WHEN** el operador borra un target con eventos asociados
- **THEN** los eventos se eliminan en cascada

### Requirement: Whitelist cerrada de kinds

El sistema SHALL aceptar solo los siguientes `kind` values:

- `manual_started`, `manual_stopped` (UI cliente/admin)
- `admin_started`, `admin_stopped` (acciones admin privilegiadas)
- `auto_started`, `auto_stopped` (motor de ventanas `restream:run-target-schedules`)
- `youtube_revoked` (broadcast cancelado por YouTube, lifeCycleStatus=revoked)
- `youtube_consumed_remotely` (broadcast terminado por stream disconnect, lifeCycleStatus=complete)
- `youtube_health_lost` (streamStatus=inactive o healthStatus=noData mientras daemon vivo)
- `ffmpeg_died` (FFmpeg child exited sin razón clara)
- `network_unreachable` (no se pudo conectar al source o destination)
- `oauth_expired` (refresh token rejected)
- `quota_exceeded` (slot lleno al intentar start)
- `watchdog_recovery_succeeded` (reenlace automático funcionó)
- `watchdog_recovery_exhausted` (N intentos fallaron; emisión queda DOWN con causa)
- `legacy_failure_archived` (migración: evento sintético del viejo `last_error`)
- `youtube_transition_succeeded` (orchestrator stop o reap cerró YouTube `complete`)
- `youtube_transition_failed` (YouTube rechazó el `transition?broadcastStatus=complete`)
- `youtube_zombie_detected` (target idle pero YouTube sigue `live`; el reap lo detectó)
- `youtube_zombie_recovered` (reap transicionó o limpió un broadcast zombie)
- `youtube_zombie_recovery_failed` (reap no pudo limpiar el zombie; ver `metadata.error`)

#### Scenario: Whitelist rechaza kinds no listados
- **WHEN** el código intenta insertar `kind="bug_detected"` o cualquier string no en la whitelist
- **THEN** se lanza `InvalidArgumentException` y no se persiste

### Requirement: Retención de 90 días

El sistema SHALL ejecutar `restream:prune-target-events` diariamente (registrado en `routes/console.php` con `daily()`). El comando SHALL borrar filas con `occurred_at < now()->subDays(90)`. La constante `TARGET_EVENTS_RETENTION_DAYS` SHALL ser configurable vía env (default 90).

#### Scenario: Prune borra solo eventos antiguos
- **WHEN** hay 100 eventos con `occurred_at` > 90 días y 50 con `occurred_at` <= 90 días
- **THEN** el prune borra los 100 y deja los 50

#### Scenario: Prune con retention custom desde env
- **WHEN** `TARGET_EVENTS_RETENTION_DAYS=30` en `.env`
- **THEN** el prune borra eventos > 30 días

### Requirement: Accesor legible en español

El sistema SHALL exponer un accesor `human_reason` en cada evento que devuelve un mensaje legible para mostrar al operador, derivado de `kind` + `metadata`. Esto permite que la UI renderice "YouTube revocó el broadcast `5LmorayGCWU` por community guidelines" en lugar de `kind=youtube_revoked`.

#### Scenario: youtube_revoked muestra mensaje con broadcast_id
- **WHEN** evento tiene `kind=youtube_revoked` y `metadata.broadcast_id="5LmorayGCWU"`
- **THEN** el accesor `human_reason` devuelve `"YouTube revocó el broadcast 5LmorayGCWU. El operador debe revisar el estado del canal en YouTube Studio."`

#### Scenario: auto_started muestra ventana
- **WHEN** evento tiene `kind=auto_started` y `metadata.window_id="<uuid>"`
- **THEN** el accesor `human_reason` devuelve `"Arrancado automáticamente por la ventana <nombre (o UUID corto)>"`

#### Scenario: kind desconocido (futuro) muestra mensaje genérico
- **WHEN** evento tiene `kind=future_kind` no contemplado en el accesor
- **THEN** el accesor `human_reason` devuelve `"Evento del sistema: future_kind"` (no rompe)

### Requirement: Eventos de transición YouTube visibles en historial

El sistema SHALL registrar los 5 kinds relacionados con la transición / reaparición del broadcast de YouTube (`youtube_transition_succeeded`, `youtube_transition_failed`, `youtube_zombie_detected`, `youtube_zombie_recovered`, `youtube_zombie_recovery_failed`) en `restream_target_events` con metadata `{broadcast_id, lifecycle?, error?, via?, cleaned?}` para que el historial muestre al operador qué pasó con cada broadcast. El accesor `human_reason` SHALL producir mensajes en español que incluyan `broadcast_id` y, cuando aplique, `error`.

#### Scenario: youtube_transition_succeeded muestra broadcast_id
- **WHEN** `RestreamOrchestrator::stop()` cierra un target YouTube exitosamente
- **THEN** existe 1 fila en `restream_target_events` con `kind=youtube_transition_succeeded`, `metadata.broadcast_id=<id>`, `metadata.via=orchestrator_stop`, y `human_reason` dice `"YouTube transicionó el broadcast <id> a `complete`."`

#### Scenario: youtube_transition_failed conserva el target en idle
- **WHEN** la API de YouTube devuelve error (token expirado, broadcast ya complete, etc.) durante `stop()` o el reap
- **THEN** el target queda `pipeline_pid=null, status=idle` Y existe evento `kind=youtube_transition_failed` con `metadata.error` poblado; el reap del siguiente tick vuelve a intentarlo

#### Scenario: zombie_detected + zombie_recovered cierran el ciclo
- **WHEN** el reap detecta un target idle con broadcast `live` en YouTube
- **THEN** inserta evento `kind=youtube_zombie_detected` y, tras transicionar a `complete` exitosamente, inserta evento `kind=youtube_zombie_recovered` con `metadata.broadcast_id=<id>`
