## ADDED Requirements

### Requirement: Entidad RestreamEvent

El sistema SHALL persistir eventos de programación one-shot como filas en `restream_events` con, al menos, los campos: `id` (UUID), `user_id` (FK a `users`), `channel_id` (FK a `channels`), `name` (string 1-120), `starts_at` (timestamp UTC), `ends_at` (timestamp UTC, estrictamente posterior a `starts_at`), `timezone` (string IANA, e.g. `America/Bogota`), `enabled` (bool default true), `notes` (text nullable), `last_error` (text nullable), `last_failed_at` (timestamp nullable), y campos de auditoría (`created_at`, `updated_at`). SHALL existir una tabla pivote `restream_event_targets` con `(event_id, target_id)` y FKs ON DELETE CASCADE a ambas tablas. SHALL existir un índice compuesto en `(starts_at, ends_at)` y un índice en `(channel_id, starts_at)`.

#### Scenario: Crear evento mínimo
- **WHEN** el cliente envía POST con `{name: "Noticiero", starts_at: "2026-10-01T13:00:00Z", ends_at: "2026-10-01T15:00:00Z", timezone: "America/Bogota", target_ids: ["..."]}`
- **THEN** el servidor persiste la fila en `restream_events`, inserta N filas en `restream_event_targets`, responde 201 con el recurso hidratado

#### Scenario: ends_at <= starts_at es rechazado
- **WHEN** el cliente envía `ends_at` igual o anterior a `starts_at`
- **THEN** el servidor responde 422 con error de validación en `ends_at`

#### Scenario: timezone inválida es rechazada
- **WHEN** el cliente envía `timezone = "Bogota/Colombia"` (no IANA)
- **THEN** el servidor responde 422 con error de validación en `timezone`

#### Scenario: target de otro usuario es rechazado
- **WHEN** el cliente envía `target_ids` que pertenecen a otro `user_id`
- **THEN** el servidor responde 422 con error de validación en `target_ids`

#### Scenario: Borrar evento borra sus pivotes
- **WHEN** el operador borra un evento con N destinos pivoteados
- **THEN** `restream_event_targets` queda sin filas para ese `event_id` (CASCADE)

### Requirement: Visibilidad cliente vs admin (dual-implementation)

El sistema SHALL exponer las operaciones de creación, edición, listado y borrado en el panel del cliente para los eventos cuyo `user_id` coincide con el usuario autenticado, y SHALL exponer **listado y detalle** (read-only) en el panel admin sobre los eventos de todos los usuarios. El admin SHALL **NO** editar ni borrar eventos del cliente en esta entrega (acción futura explícita en `tasks.md`).

#### Scenario: Cliente lista solo sus eventos
- **WHEN** el cliente autenticado abre `client.restream.index`
- **THEN** la UI muestra solo los eventos con `user_id = auth()->id()`

#### Scenario: Admin lista todos los eventos
- **WHEN** el admin abre `admin.restream.index`
- **THEN** la UI muestra todos los eventos del sistema con badge del dueño

#### Scenario: Admin intenta editar evento del cliente
- **WHEN** el admin llama a PUT `/admin/restream-events/{id}`
- **THEN** el servidor responde 403 (admin es read-only en esta entrega)

### Requirement: Estado del evento derivable del reloj

El estado operativo de un evento SHALL poder calcularse a partir de `now()`, `starts_at` y `ends_at`, sin columnas extra. Los estados son: `pending` (`starts_at > now`), `running` (`starts_at <= now < ends_at`), `ended` (`ends_at <= now`), `disabled` (`enabled = false`). La UI SHALL mostrar el estado calculado en cada render (sin persistir).

#### Scenario: Evento futuro se ve como pending
- **WHEN** un evento tiene `starts_at` 1 hora en el futuro
- **THEN** la UI muestra badge "Pendiente" con la hora local formateada usando `event->timezone`

#### Scenario: Evento en curso se ve como running
- **WHEN** un evento tiene `starts_at` ayer y `ends_at` en 30 minutos
- **THEN** la UI muestra badge "En vivo" con cuenta regresiva a `ends_at`

#### Scenario: Evento terminado se ve como ended
- **WHEN** un evento tiene `ends_at` hace 5 minutos
- **THEN** la UI muestra badge "Finalizado" con la hora de fin en local

#### Scenario: Evento disabled se ve como disabled
- **WHEN** un evento tiene `enabled = false`
- **THEN** la UI muestra badge "Deshabilitado" independiente del reloj

### Requirement: Motor de ejecución por minuto

El sistema SHALL ejecutar el comando `restream:run-events` cada minuto (registrado en `routes/console.php` con `everyMinute()->withoutOverlapping()`). En cada tick el motor SHALL: (a) encontrar eventos en fase `running` que aún no han sido aplicados al menos una vez desde su `starts_at`, llamar `RestreamOrchestrator::start()` por cada destino pivoteado del evento; (b) encontrar eventos en fase `ended` que aún no han sido detenidos, llamar `RestreamOrchestrator::stop()` por cada destino pivoteado. Cada transición SHALL escribir `audit_logs` con `action='auto_start.restream_event'` o `action='auto_stop.restream_event'`, `entity_type='restream_event'`, `entity_id=<event-id>`, y `after` con la lista de `target_id`s afectados. La transición SHALL respetar `RestreamQuotaGuard::assertCanEnable` por destino; si un destino falla por cuota, se registra `last_error` en el destino y se continúa con el resto.

#### Scenario: Evento arranca sus destinos al cruzar starts_at
- **WHEN** `now() >= starts_at` y la última corrida no marcó el evento como aplicado
- **THEN** por cada `target_id` del evento, el motor llama `start()`, los targets quedan `enabled=true, status=starting` y luego `live` por heartbeat, y se inserta 1 audit log `auto_start.restream_event` con la lista de targets

#### Scenario: Evento detiene sus destinos al cruzar ends_at
- **WHEN** `now() >= ends_at` y los destinos siguen activos
- **THEN** por cada `target_id` del evento, el motor llama `stop()`, los targets quedan `status=idle, pipeline_pid=null`, y se inserta 1 audit log `auto_stop.restream_event`

#### Scenario: Comando no se solapa consigo mismo
- **WHEN** una corrida del comando tarda > 60s y el siguiente tick dispara
- **THEN** el segundo tick es skip por `withoutOverlapping()`; no se duplican llamadas a `start()`/`stop()`

#### Scenario: Cuota agotada en uno de los destinos
- **WHEN** un evento tiene 2 destinos y el usuario solo tiene 1 slot libre
- **THEN** un destino arranca y el otro queda con `last_error` describiendo la cuota, sin abortar el lote

#### Scenario: Sin eventos candidatos, comando es no-op
- **WHEN** no hay eventos cuyo `starts_at <= now < ends_at` ni `ends_at <= now` pendientes
- **THEN** el comando termina en < 1s sin escribir auditoría ni tocar targets

### Requirement: Coexistencia con el scheduler existente

El comando `restream:launch-scheduled` SHALL seguir procesando los campos `scheduled_start_at` y `scheduled_stop_at` de `restream_targets` sin cambios. Un mismo target puede tener tanto un horario inline (target) como estar pivoteado a N eventos. El motor de eventos SHALL NO interferir con un target cuyo `pipeline_pid` ya esté corriendo bajo el scheduler inline; SHALL llamar `stop()` solo si el evento explícitamente pide finalizar.

#### Scenario: Target con horario inline + en un evento
- **WHEN** un target tiene `scheduled_start_at = hoy 10:00` (inline) y está pivoteado a un evento que empieza hoy 9:00
- **THEN** el scheduler inline arranca el target a las 10:00 y el evento a las 9:00 también intenta `start()`; el segundo `start()` es idempotente (no duplica daemon) por la lógica ya existente en `RestreamOrchestrator`

#### Scenario: Evento no pisa un target ya corriendo por otra razón
- **WHEN** un evento llega a `ends_at` pero el target no está `live` (lo arrancó el usuario manualmente y aún no termina)
- **THEN** el motor igual llama `stop()` para mantener coherencia con la programación del evento

### Requirement: Auditoría de eventos

Cada creación, edición y borrado de un evento desde las UIs cliente/admin SHALL escribir `audit_logs` con `action='create.restream_event' / 'update.restream_event' / 'delete.restream_event'`, `entity_type='restream_event'`, `entity_id=<event-id>`, e incluir el snapshot before/after del recurso. Las transiciones automáticas del motor SHALL escribir `auto_start.restream_event` / `auto_stop.restream_event` con la lista de `target_id`s en `after`.

#### Scenario: Crear evento desde UI escribe audit log
- **WHEN** el cliente envía POST exitoso para crear un evento
- **THEN** existe 1 fila en `audit_logs` con `action='create.restream_event'` y `after` conteniendo `name`, `starts_at`, `ends_at`, `target_ids`

#### Scenario: Auto-start escribe audit log con targets afectados
- **WHEN** el motor arranca 2 destinos de un evento
- **THEN** existe 1 fila en `audit_logs` con `action='auto_start.restream_event'`, `entity_id=<event-id>`, `after.target_ids = [t1, t2]`
