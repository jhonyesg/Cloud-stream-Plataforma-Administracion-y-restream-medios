## ADDED Requirements

### Requirement: API JSON de ventanas y eventos

El sistema SHALL exponer, bajo el middleware `auth:sanctum` + middleware `restream.enabled` ya configurado, los endpoints:

- `GET /api/restream-targets/{target}/schedules?status=pending|running|ended|disabled` → lista ventanas del target (filtrable).
- `GET /api/restream-targets/{target}/schedules/{schedule}` → detalle.
- `POST /api/restream-targets/{target}/schedules` → crear. Body: `{name?, starts_at, ends_at?, timezone, notes?, enabled?}`.
- `PUT /api/restream-targets/{target}/schedules/{schedule}` → editar parcial (no permite cambiar `target_id`).
- `DELETE /api/restream-targets/{target}/schedules/{schedule}` → borrar.
- `POST /api/restream-targets/{target}/schedules/{schedule}/trigger-now` → iniciar manualmente sin esperar `starts_at`; equivalente a `RestreamOrchestrator::start()` directo.
- `POST /api/restream-targets/{target}/schedules/{schedule}/stop-now` → detener manualmente aunque la ventana siga vigente; el motor NO la vuelve a arrancar hasta que se cree una nueva ventana o se edite.
- `GET /api/restream-targets/{target}/events?kind=...&from=...&to=...&limit=50` → listado paginado del historial de eventos del target. Orden descendente por `occurred_at`.
- `GET /api/restream-targets/{target}/events/{event}` → detalle con metadata completa.

Las respuestas SHALL seguir el shape `{data: ...}` (single) o `{data: [...], meta: {total, page, per_page}}` (collection). Errores de validación SHALL seguir el formato Laravel estándar.

#### Scenario: Cliente lista ventanas de su target
- **WHEN** `GET /api/restream-targets/<uuid>/schedules` con token cliente dueño
- **THEN** responde 200 con `{data: [w1, w2], meta: {total: 2}}`

#### Scenario: Crear ventana con ends_at nulo
- **WHEN** POST con `ends_at` ausente
- **THEN** la fila persiste con `ends_at: null` y la respuesta lo refleja

#### Scenario: trigger-now en ventana pending
- **WHEN** cliente llama `POST /api/restream-targets/<uuid>/schedules/<id>/trigger-now`
- **THEN** el orquestador inicia el target; el evento `kind=manual_started` se inserta en `restream_target_events`

#### Scenario: stop-now no reabre por la misma ventana
- **WHEN** cliente llama stop-now mientras hay ventana applicable
- **THEN** el orquestador detiene el daemon y se inserta `kind=manual_stopped`; el motor `restream:run-target-schedules` en el siguiente tick NO vuelve a llamar `start()` porque la ventana está marcada como "user-stopped" (`stop-now` la desactiva automáticamente)

#### Scenario: Listado de eventos con filtro de kind
- **WHEN** cliente llama `GET /api/restream-targets/<uuid>/events?kind=youtube_revoked&limit=10`
- **THEN** responde solo los eventos cuyo kind coincide, ordenados por occurred_at desc

### Requirement: Web routes cliente y admin

El sistema SHALL registrar las siguientes rutas web (sesión autenticada):

Cliente:
- `GET /client/channels/{channel}/targets/{target}/schedules` → listado (incluye calendario visual).
- `GET /client/channels/{channel}/targets/{target}/schedules/create` → formulario.
- `GET /client/channels/{channel}/targets/{target}/schedules/{schedule}/edit` → formulario de edición.
- `POST /client/channels/{channel}/targets/{target}/schedules/{schedule}/trigger-now` → acción manual.
- `POST /client/channels/{channel}/targets/{target}/schedules/{schedule}/stop-now` → acción manual.
- `DELETE /client/channels/{channel}/targets/{target}/schedules/{schedule}` → borrado.

Admin (read-only + acciones privilegiadas):
- `GET /admin/channels/{channel}/targets/{target}/schedules` → listado (incluye historial completo).
- `POST /admin/channels/{channel}/targets/{target}/schedules/{schedule}/stop-now` → admin puede forzar stop.
- `GET /admin/channels/{channel}/targets/{target}/events` → vista cronológica del historial con kind legible.

Todas las rutas cliente SHALL pasar por `canAccessChannel` + `canAccessTarget`. Las rutas admin SHALL pasar por `auth.admin`.

#### Scenario: Cliente sin acceso al canal
- **WHEN** cliente llama `GET /client/channels/<uuid-ajeno>/targets/<uuid>/schedules`
- **THEN** responde 403

#### Scenario: Admin abre historial de un target
- **WHEN** admin llama `GET /admin/channels/<uuid>/targets/<uuid>/events`
- **THEN** responde 200 con vista cronológica, icono + tooltip por cada `kind` y paginación de 50

#### Scenario: Admin fuerza stop-now
- **WHEN** admin llama `POST .../stop-now` y el target está `live`
- **THEN** el orquestador detiene el daemon y se inserta `kind=admin_stopped`
