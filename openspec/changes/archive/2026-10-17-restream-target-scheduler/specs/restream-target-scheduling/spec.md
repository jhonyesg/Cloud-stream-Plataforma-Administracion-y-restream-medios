## ADDED Requirements

### Requirement: Entidad RestreamTargetSchedule

El sistema SHALL persistir ventanas de programación como filas en `restream_target_schedules` con los campos: `id` (UUID), `target_id` (FK a `restream_targets`, cascade on delete), `name` (string nullable, max 120), `starts_at` (timestamp UTC), `ends_at` (timestamp UTC, **nullable** para fin abierto), `timezone` (string IANA, default `UTC`), `notes` (text nullable), `enabled` (bool default true), `created_at`, `updated_at`. SHALL existir índice en `(target_id, starts_at)`.

#### Scenario: Crear ventana con fin concreto
- **WHEN** el cliente envía POST con `{target_id, starts_at: "2026-10-01T13:00:00Z", ends_at: "2026-10-01T15:00:00Z", timezone: "America/Bogota", name: "Noticiero mañana"}`
- **THEN** la fila se persiste y la respuesta 201 muestra el recurso

#### Scenario: Crear ventana sin fin (broadcast indefinido)
- **WHEN** el cliente envía POST con `ends_at: null` (o lo omite)
- **THEN** la fila se persiste con `ends_at: NULL` y se marca como "indefinida" en la respuesta

#### Scenario: ends_at anterior o igual a starts_at es rechazado
- **WHEN** el cliente envía `ends_at <= starts_at`
- **THEN** el servidor responde 422 con error en `ends_at`

#### Scenario: timezone inválida es rechazada
- **WHEN** el cliente envía `timezone: "Bogota/Colombia"` (no IANA)
- **THEN** el servidor responde 422 con error en `timezone`

#### Scenario: Borrar target borra sus ventanas (CASCADE)
- **WHEN** el operador borra un target con N ventanas pivoteadas
- **THEN** `restream_target_schedules` queda sin filas para ese `target_id`

### Requirement: Estado derivable de la ventana

El sistema SHALL calcular el estado operativo de cada ventana en cada render a partir de `now()`, `starts_at`, `ends_at` y `enabled`, sin columnas calculadas persistidas. Estados: `pending` (`starts_at > now`), `running` (`starts_at <= now AND (ends_at IS NULL OR ends_at > now)`), `ended` (`ends_at IS NOT NULL AND ends_at <= now`), `disabled` (`enabled = false`).

#### Scenario: Ventana futura en estado pending
- **WHEN** `starts_at` está 1h en el futuro
- **THEN** la UI muestra "Pendiente" + cuenta regresiva + hora local

#### Scenario: Ventana en curso sin fin en estado running
- **WHEN** `starts_at <= now AND ends_at IS NULL`
- **THEN** la UI muestra "En vivo (sin fin programado)" + tiempo transcurrido

#### Scenario: Ventana en curso con fin en estado running
- **WHEN** `starts_at <= now < ends_at`
- **THEN** la UI muestra "En vivo" + cuenta regresiva a `ends_at`

#### Scenario: Ventana con fin vencido en estado ended
- **WHEN** `ends_at IS NOT NULL AND ends_at <= now`
- **THEN** la UI muestra "Finalizado" + duración real (ends_at - starts_at)

### Requirement: Motor de ejecución por minuto (restream:run-target-schedules)

El sistema SHALL ejecutar `restream:run-target-schedules` cada minuto (registrado en `routes/console.php` con `everyMinute()->withoutOverlapping()`). Cada tick SHALL: (a) encontrar todos los targets `enabled=true`; (b) para cada target, identificar la ventana aplicable (la única cuyo `started_at <= now AND (ends_at IS NULL OR ends_at > now)`, o ninguna); (c) si no hay ventana aplicable y el daemon está corriendo por una razón atribuible a una ventana que ya terminó, llamar `RestreamOrchestrator::stop()`; (d) si hay ventana aplicable y el daemon NO está corriendo para esa ventana, llamar `RestreamOrchestrator::start()` respetando quota; (e) escribir el resultado en `restream_target_events` con `kind` apropiado. Targets con `enabled=false` SHALL ser ignorados (no se auto-arranca).

#### Scenario: Ventana arranca al cruzar starts_at
- **WHEN** `now >= starts_at`, `enabled=true`, daemon no corriendo
- **AND** `RestreamQuotaGuard::assertCanEnable` pasa
- **THEN** el motor llama `start()`, el target queda `status=starting`→`live`, y se inserta evento `kind=auto_started` con metadata `{window_id, scheduled_starts_at}`

#### Scenario: Cuota agotada no rompe el bucle
- **WHEN** la ventana applicable require arrancar pero el usuario no tiene slot libre
- **THEN** se inserta evento `kind=quota_exceeded` y el target queda sin arrancar; el motor continúa con los siguientes targets

#### Scenario: Target deshabilitado no arranca por ventana
- **WHEN** `enabled=false` aunque haya ventana applicable en este momento
- **THEN** el motor ignora el target sin escribir evento (no arrancar es el estado esperado)

#### Scenario: Ventana con ends_at detiene el daemon
- **WHEN** `now >= ends_at` y la ventana era la que mantenía el daemon corriendo
- **AND** no hay otra ventana abierta
- **THEN** el motor llama `stop()`, target queda `status=idle`, y se inserta evento `kind=auto_stopped`

#### Scenario: Ventana sin fin NO detiene el daemon
- **WHEN** `now` supera `starts_at` y `ends_at IS NULL`
- **THEN** el motor enciende el daemon (si no estaba) pero no llama `stop()` por sí solo; solo se detendrá cuando el usuario haga click manual o el watchdog reaparezca tras una falla externa

#### Scenario: Motor no se solapa consigo mismo
- **WHEN** una corrida tarda > 60s y el siguiente tick dispara
- **THEN** el segundo tick es skip por `withoutOverlapping()`

#### Scenario: Idempotencia con daemon ya corriendo
- **WHEN** la ventana applicable ya está activa y el daemon está vivo
- **THEN** el motor no llama `start()` de nuevo (el orquestador detectaría el daemon y haría no-op de todas formas)

#### Scenario: Ventanas solapadas no duplican daemon
- **WHEN** dos ventanas del mismo target cubren el mismo intervalo
- **THEN** el motor enciende UNA sola vez; mantiene el daemon encendido mientras CUALQUIER ventana applicable; apaga cuando NINGUNA ventana aplicable

### Requirement: Acceso por canal y por target (scope)

El sistema SHALL permitir a un cliente listar/CRUD ventanas solo de sus propios targets (validación por `auth()->id()`). El admin SHALL poder listar/CRUD ventanas de cualquier target (todas las acciones requieren `auth.admin`). El motor SHALL ejecutarse a nivel global (no por usuario) ya que solo necesita target_id y el lock global.

#### Scenario: Cliente intenta ver ventana de target ajeno
- **WHEN** cliente autenticado llama `GET /api/restream-targets/<otro-target>/schedules`
- **THEN** responde 403

#### Scenario: Admin lista todas las ventanas de cualquier target
- **WHEN** admin llama `GET /api/restream-targets/<target-uuid>/schedules`
- **THEN** responde 200 con la lista completa

### Requirement: Coexistencia con el reap-dead-broadcasts

El sistema SHALL ejecutar tanto `restream:run-target-schedules` como `restream:reap-dead-broadcasts` cada minuto, en ese orden. El reap SHALL detectar broadcasts de YouTube marcados `complete` o `revoked` en `restream_target_events` con la `kind` apropiada (`youtube_consumed_remotely` o `youtube_revoked`) y SHALL delegar la recuperación a `RestreamOrchestrator::start()` (que ya invoca `refreshDeadBroadcast()`).

#### Scenario: YouTube marca broadcast como revoked
- **WHEN** el reap detecta `lifeCycleStatus=revoked` en el broadcast de un target
- **THEN** se inserta evento `kind=youtube_revoked` con metadata `{broadcast_id, lifecycle, recovered: true/false}` y la respuesta del orchestrator determina `recovered`

#### Scenario: YouTube marca broadcast como complete (auto-stop natural)
- **WHEN** el reap detecta `lifeCycleStatus=complete` siendo `enableAutoStop=false`
- **THEN** se inserta evento `kind=youtube_consumed_remotely` con metadata `{broadcast_id, lifecycle}` y el motor de ventanas (siguiente tick) NO intenta reabrir el target (porque el target sigue alive o porque la ventana no es aplicable)

### Requirement: Auditoría de cambios en ventanas (CRUD)

El sistema SHALL escribir `audit_logs` en cada POST/PUT/DELETE de ventana con `action='create.restream_target_schedule' | 'update.restream_target_schedule' | 'delete.restream_target_schedule'`, `entity_type='restream_target_schedule'`, `entity_id=<schedule-uuid>`, before/after snapshots.

#### Scenario: Crear ventana desde UI cliente escribe audit
- **WHEN** POST exitoso para crear ventana
- **THEN** existe 1 fila en `audit_logs` con la acción correspondiente

### Requirement: Ventana con ends_at transiciona YouTube a complete

El sistema SHALL, dentro del mismo cron tick en que `RestreamOrchestrator::stop()` se invoca por una ventana que llegó a `ends_at`, llamar a YouTube `liveBroadcasts/transition?broadcastStatus=complete` para el broadcast vinculado. El broadcast SHALL terminar en `lifeCycleStatus=complete` (o `revoked`/eliminado) antes de que el siguiente `start()` intente enlazar al mismo stream key. Si la transición falla (token expirado, red, broadcast ya consumido), el target SHALL quedar `idle` de todas formas y SHALL emitirse un evento `kind=youtube_transition_failed`; el watchdog `restream:reap-youtube-zombies` SHALL reintentar la transición en el siguiente tick.

#### Scenario: Ventana termina y broadcast transiciona a complete
- **WHEN** el motor detecta `now >= ends_at` y llama `RestreamOrchestrator::stop()`
- **THEN** YouTube reporta `lifeCycleStatus=complete` en ≤10s Y existe evento `kind=youtube_transition_succeeded` con `metadata.broadcast_id` igual al broadcast previamente vinculado

#### Scenario: Token YouTube expirado no rompe el stop
- **WHEN** la transición a `complete` falla por 401/403 (token expirado) o error de red
- **THEN** el target queda `pipeline_pid=null, status=idle` Y existe evento `kind=youtube_transition_failed` con `metadata.error` poblado Y el watchdog `restream:reap-youtube-zombies` reintenta en el siguiente tick del cron

#### Scenario: Multi-ventana usa broadcast fresco en cada start
- **WHEN** ventana 1 termina (transición OK, broadcast consumido) y ventana 2 arranca después
- **THEN** `RestreamOrchestrator::start()` invoca `refreshDeadBroadcast()` que detecta `isBroadcastConsumed()=true` y crea un broadcast NUEVO con su propio `platform_broadcast_id`, `destination_url` y `stream_key`. El `metadata.broadcast_id` del evento `auto_started` de ventana 2 es distinto del de ventana 1.

#### Scenario: Ventana sin fin NO transiciona (broadcast indefinido)
- **WHEN** la ventana aplicable tiene `ends_at IS NULL`
- **THEN** el motor enciende el daemon pero nunca invoca la transición YouTube; el broadcast queda vivo hasta intervención manual del operador o falla externa.
