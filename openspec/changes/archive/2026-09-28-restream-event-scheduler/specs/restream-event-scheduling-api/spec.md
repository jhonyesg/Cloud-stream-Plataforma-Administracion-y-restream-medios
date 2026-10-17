## ADDED Requirements

### Requirement: API JSON de eventos para las UIs

El sistema SHALL exponer, bajo el middleware `restream.enabled` ya configurado, los endpoints JSON:

- `GET /api/restream-events?channel_id=<uuid>` → lista eventos del canal (cliente: solo los suyos; admin: todos los del canal). Acepta `?status=pending|running|ended|disabled` como filtro derivado, sin persistir.
- `GET /api/restream-events/{id}` → detalle del evento con sus `target_ids` hidratados.
- `POST /api/restream-events` → crear. Body: `{name, starts_at, ends_at, timezone, channel_id, target_ids[], notes?}`.
- `PUT /api/restream-events/{id}` → editar. Body parcial. No permite cambiar `channel_id` ni `user_id`.
- `DELETE /api/restream-events/{id}` → borrar (cascade pivote).
- `POST /api/restream-events/{id}/run-now` → ejecutar `start()` manualmente sobre los destinos (acción admin/debug; cliente solo si su quota lo permite).

Las respuestas SHALL ser JSON con la forma `{data: ...}` para single y `{data: [...], meta: {total: N}}` para colecciones. Errores de validación SHALL seguir el formato Laravel estándar `{message, errors: {field: [...]}}`.

#### Scenario: Cliente lista eventos de su canal
- **WHEN** `GET /api/restream-events?channel_id=<uuid>` con token de cliente dueño del canal
- **THEN** responde 200 con `{data: [event1, event2], meta: {total: 2}}` solo con eventos del cliente

#### Scenario: Cliente lista eventos de canal ajeno
- **WHEN** `GET /api/restream-events?channel_id=<uuid-de-otro-cliente>` con token de cliente que no es dueño
- **THEN** responde 403

#### Scenario: Crear evento con validación fallida
- **WHEN** POST sin `starts_at`
- **THEN** responde 422 con `{message: ..., errors: {starts_at: ["required"]}}`

#### Scenario: Editar evento actualiza solo campos enviados
- **WHEN** PUT con `{notes: "nueva nota"}`
- **THEN** los demás campos quedan intactos en BD y la respuesta refleja el recurso actualizado

#### Scenario: Borrar evento responde 204
- **WHEN** DELETE sobre evento existente del cliente
- **THEN** responde 204 y el evento (y sus pivotes) ya no aparece en GET

#### Scenario: Admin usa run-now para arrancar manualmente
- **WHEN** admin llama `POST /api/restream-events/{id}/run-now`
- **THEN** el orquestador llama `start()` por cada destino, los targets quedan `enabled=true, status=starting`, y se inserta audit log `manual_start.restream_event`

### Requirement: Web routes cliente y admin

El sistema SHALL registrar las siguientes rutas web (sesión autenticada, no API JSON):

- Cliente: `GET /client/channels/{channel}/restream-events` (index), `GET /client/channels/{channel}/restream-events/create`, `GET /client/channels/{channel}/restream-events/{event}/edit`, todas renderizando vistas Blade dentro del layout cliente existente.
- Admin: `GET /admin/channels/{channel}/restream-events` (index read-only), `GET /admin/channels/{channel}/restream-events/{event}` (show read-only).

Todas las rutas cliente SHALL pasar por el middleware `canAccessChannel` ya usado en `Client\RestreamTargetController`. Las rutas admin SHALL pasar por el middleware `auth.admin`.

#### Scenario: Cliente sin acceso al canal
- **WHEN** cliente autenticado llama `GET /client/channels/<uuid-ajeno>/restream-events`
- **THEN** responde 403 vía `canAccessChannel`

#### Scenario: Admin abre index de eventos
- **WHEN** admin llama `GET /admin/channels/<uuid>/restream-events`
- **THEN** responde 200 con la vista `admin.restream.events.index` y la lista de todos los eventos del canal

#### Scenario: Cliente abre index de su canal
- **WHEN** cliente dueño del canal llama `GET /client/channels/<uuid>/restream-events`
- **THEN** responde 200 con la vista `client.restream.events.index` y la lista de sus eventos
