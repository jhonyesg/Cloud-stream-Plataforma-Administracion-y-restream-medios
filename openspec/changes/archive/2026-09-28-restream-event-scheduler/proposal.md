## Why

Hoy la programación de un destino restream vive como dos campos opcionales (`scheduled_start_at`, `scheduled_stop_at`) en el propio `restream_targets`, ejecutados por `restream:launch-scheduled`. Esto cubre el caso trivial — un cliente programa un único inicio y un único fin para un destino — pero el cliente no puede definir varios eventos sobre el mismo destino en fechas distintas, ni crear una sola programación que abarque varios destinos a la vez. El módulo actual es un campo del target, no una entidad programable. Se necesita una entidad propia — `restream_event` — con CRUD, UI, motor de ejecución y vistas admin/cliente, para que un cliente con una sola transmisión pueda agendar varios eventos (noticiero matutino, transmisión especial del miércoles, cobertura del domingo) sin tener que volver a editar el target cada vez.

## What Changes

- Nueva tabla `restream_events` (id, user_id, channel_id, name, starts_at, ends_at, timezone, enabled, last_error, audit) y tabla pivote `restream_event_targets` (event_id, target_id) para que un evento abarque N destinos del cliente.
- Modelo `App\Models\RestreamEvent` con scopes por canal, validaciones (ends_at > starts_at, timezone válida, targets del cliente) y accessors consistentes con `RestreamTarget`.
- CRUD en cliente y admin (rutas, controladores, vistas, modal) con implementación dual siguiendo la regla del proyecto: el cliente ve solo sus eventos; el admin ve todos (read-only en esta entrega salvo acciones de moderación documentadas en `tasks.md`).
- Endpoint JSON `GET /api/restream-events?channel_id=...` para alimentar la UI cliente/admin sin sobre-fetch.
- Nuevo comando `restream:run-events` (registrado en `routes/console.php`, `everyMinute`) que detecta eventos cuyo `starts_at <= now() < ends_at` y aún no están aplicados, llama a `RestreamOrchestrator::start()` por cada destino del evento, y al pasar `ends_at` llama a `RestreamOrchestrator::stop()`. Idempotente: aplica cada transición una sola vez.
- Auditoría: cada arranque/parada automático escribe `audit_logs` con `action='auto_start.restream_event' / 'auto_stop.restream_event'` enlazando al evento y al target afectado.
- Quota guard: el motor respeta `RestreamQuotaGuard::assertCanEnable` por cada target; si un target no se puede activar por cuota, el evento se aplica parcialmente con `last_error` en el target y el resto de destinos del evento sigue intentando.

**No breaking:** los campos `scheduled_start_at` / `scheduled_stop_at` del target se conservan. El comando `restream:launch-scheduled` existente sigue procesándolos. Los eventos son una capa adicional, no un reemplazo.

## Capabilities

### New Capabilities

- `restream-event-scheduling`: entidad programable de uno o varios destinos restream con fecha/hora de inicio y fin, CRUD admin+cliente, motor de ejecución por minuto, auditoría y respeto de cuota.
- `restream-event-scheduling-api`: contratos JSON consumidos por las UIs cliente y admin (`index`, `show`, `store`, `update`, `destroy`) más el endpoint interno consumido por el motor para reportar resultado de una transición aplicada.

### Modified Capabilities

- (ninguno). `restream-scheduled-lifecycle` se mantiene intacto: la nota de coexistencia con el nuevo motor `restream:run-events` se documenta en `design.md` como decisión arquitectónica, no como cambio de requisito (no agrega ni modifica ningún `### Requirement:` del spec existente).

## Impact

- **DB:** 2 tablas nuevas (`restream_events`, `restream_event_targets`), 1 FK nueva a `channels` y `users`, índice en `(starts_at, ends_at)` y `(channel_id, starts_at)`. Migración nueva; usar `php artisan migrate:safe` por el protocolo del proyecto.
- **Modelos:** `App\Models\RestreamEvent` (nuevo) y pivote `RestreamEventTarget` (nuevo). `RestreamTarget` gana relación `events()` sin tocar columnas.
- **Servicios:** `App\Services\Restream\EventSchedulerService` (nuevo) encapsula la lógica del motor (resolver eventos candidatos, aplicar transiciones, escribir auditoría). Reutiliza `RestreamOrchestrator` y `RestreamQuotaGuard` ya existentes.
- **Comandos:** `App\Console\Commands\RestreamRunEventsCommand` (nuevo). `routes/console.php` gana una línea `Schedule::command('restream:run-events')->everyMinute()->name('restream-run-events')`.
- **Rutas/controladores/vistas (dual-implementation):** `app/Http/Controllers/Client/RestreamEventController.php`, `app/Http/Controllers/Admin/RestreamEventController.php`, `resources/views/client/restream/events/*`, `resources/views/admin/restream/events/*`, componente `<x-restream-event-modal>` reutilizado por ambas vistas. Rutas en `routes/web.php` dentro de los grupos `client.*` y `admin.*` ya existentes.
- **API JSON:** `app/Http/Controllers/Api/RestreamEventController.php` (nuevo) detrás del middleware `restream.enabled` ya configurado.
- **UI cliente:** los eventos se listan en `client.restream.index` (la vista del screenshot) como una sección debajo de la tabla de destinos, con badges para estados `pending` / `running` / `completed` / `failed`.
- **Sin nuevas dependencias Composer.**
- **Sin migraciones destructivas** (no toca `restream_targets`, `restream_quotas` ni tablas existentes). El snapshot release no se ve afectado; se puede regenerar con `db:export-production-data` cuando los datos reales lo justifiquen.
