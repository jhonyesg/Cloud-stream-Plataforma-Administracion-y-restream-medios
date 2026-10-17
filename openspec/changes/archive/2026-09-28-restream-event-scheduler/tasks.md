## 1. Migración y modelos

- [ ] 1.1 `php artisan db:backup` antes de tocar migraciones (protocolo AGENTS.md).
- [ ] 1.2 Crear migración `database/migrations/<ts>_create_restream_events_table.php` con: `id uuid pk`, `user_id` FK users cascade, `channel_id` FK channels cascade, `name` string 120, `starts_at` timestamp tz, `ends_at` timestamp tz, `timezone` string 64, `enabled` bool default true, `notes` text nullable, `last_error` text nullable, `last_failed_at` timestamp nullable, `timestamps`. Índices: `(starts_at, ends_at)` y `(channel_id, starts_at)`.
- [ ] 1.3 Crear migración pivote `<ts>_create_restream_event_targets_table.php` con `(event_id, target_id)` PK compuesta, ambas FK con ON DELETE CASCADE.
- [ ] 1.4 Correr `php artisan migrate:safe` y validar que las tablas existen y los índices están creados.
- [ ] 1.5 Crear `app/Models/RestreamEvent.php` con fillable, casts (`starts_at`, `ends_at`, `last_failed_at` → datetime, `enabled` → bool), relaciones `user()`, `channel()`, `targets()` (belongsToMany via pivote), scopes `forChannel($channelId)`, `forUser($userId)`, accesor `status` que devuelve `pending|running|ended|disabled` derivable, accesor `local_starts_at` y `local_ends_at` usando `event->timezone`.
- [ ] 1.6 Crear `app/Models/RestreamEventTarget.php` (modelo pivote) si Laravel lo requiere para `belongsToMany` con timestamps explícitos.

## 2. Servicios y motor

- [ ] 2.1 Crear `app/Services/Restream/EventSchedulerService.php` con métodos `dueForStart(): Collection<RestreamEvent>`, `dueForStop(): Collection<RestreamEvent>`, `applyStart(RestreamEvent $e): void`, `applyStop(RestreamEvent $e): void`. Cada `apply*` itera `$e->targets`, llama `RestreamOrchestrator::start()`/`stop()`, registra auditoría con `audit_logs`.
- [ ] 2.2 Crear `app/Console/Commands/RestreamRunEventsCommand.php` con `signature='restream:run-events'`, `description` clara. En `handle()`: lock con `Cache::lock('restream:run-events', 60)`, llamar `dueForStart` y `dueForStop`, aplicar cada uno. Salida con `$this->info()` cuando hay eventos y `$this->line('No events due.')` cuando no.
- [ ] 2.3 Registrar el comando en `routes/console.php`: `Schedule::command('restream:run-events')->everyMinute()->withoutOverlapping()->name('restream-run-events');`. Verificar con `php artisan schedule:list` que aparece.
- [ ] 2.4 Test manual: `php artisan restream:run-events --once` y verificar log de auditoría.

## 3. Form requests y validación

- [ ] 3.1 Crear `app/Http/Requests/Restream/StoreRestreamEventRequest.php` con reglas: `name` required string max:120, `starts_at` required date, `ends_at` required date after:starts_at, `timezone` required string in `DateTimeZone::listIdentifiers()`, `channel_id` required uuid exists:channels,id, `target_ids` required array min:1, `target_ids.*` uuid, `notes` nullable string max:1000. Mensajes en español.
- [ ] 3.2 Crear `app/Http/Requests/Restream/UpdateRestreamEventRequest.php` con `sometimes` en los mismos campos (excepto `channel_id` que no se permite cambiar).
- [ ] 3.3 Validación custom `target_ids.*` debe pertenecer a `auth()->user()` y al `channel_id` enviado: agregar `withValidator()` que carga `RestreamTarget::whereIn('id', $targetIds)->where('user_id', auth()->id())->where('channel_id', $channelId)->count() === count($targetIds)` o lanza `ValidationException`.

## 4. Controladores (dual-implementation)

- [ ] 4.1 Crear `app/Http/Controllers/Api/RestreamEventController.php` con `index`, `show`, `store`, `update`, `destroy`, `runNow`. Inyectar `RestreamEventSchedulerService` solo en `runNow`. Todas las respuestas JSON con shape `{data: ...}` o `{data: [...], meta: {total: N}}`.
- [ ] 4.2 Registrar rutas API en `routes/api.php` bajo `Route::middleware(['auth:sanctum', 'restream.enabled'])->prefix('restream-events')->group(...)` con las 6 rutas.
- [ ] 4.3 Crear `app/Http/Controllers/Client/RestreamEventController.php` con `index`, `create`, `edit`, `destroy` (web). Pasar por `canAccessChannel`. Devolver vistas `client.restream.events.*`.
- [ ] 4.4 Crear `app/Http/Controllers/Admin/RestreamEventController.php` con `index` (read-only) y `show` (read-only). No métodos `create`/`edit`/`destroy`/`update`.
- [ ] 4.5 Registrar rutas web en `routes/web.php` dentro de los grupos `client.*` y `admin.*` ya existentes, con middleware `canAccessChannel` y `auth.admin` respectivamente.

## 5. Vistas Blade (dual-implementation)

- [ ] 5.1 Crear `resources/views/client/restream/events/index.blade.php` con tabla de eventos (badge de estado derivado, fecha local formateada con `$event->local_starts_at`, botones "Nuevo evento" y "Editar"). Estilo consistente con `client.restream.index` (Tailwind classes ya conocidas: `bg-white rounded-xl shadow-sm border border-gray-200`, `bg-foo-*`, etc.).
- [ ] 5.2 Crear `resources/views/client/restream/events/_form.blade.php` parcial con campos `name`, `starts_at` (datetime-local en local), `ends_at`, `timezone` (select con IANA list), `notes`, y `target_ids` (checkboxes por cada target del cliente en ese canal). CSRF token.
- [ ] 5.3 Crear `resources/views/client/restream/events/edit.blade.php` que extiende el layout cliente y renderiza `_form` con `@method('PUT')`.
- [ ] 5.4 Crear `resources/views/admin/restream/events/index.blade.php` (read-only): misma tabla pero sin botones de edición, con columna extra "Dueño" mostrando `event->user->email`.
- [ ] 5.5 Crear `resources/views/admin/restream/events/show.blade.php` con detalle completo + lista de destinos pivoteados + historial de auditoría del evento (`audit_logs` filtrado por `entity_id=event->id`).
- [ ] 5.6 Crear `resources/views/components/restream-event-modal.blade.php` reutilizado por cliente y admin para creación/edición rápida. Alpine.js con `x-data` siguiendo la convención del proyecto (escapar `"` como `&quot;` según AGENTS.md).
- [ ] 5.7 Integrar la sección de eventos en `client.restream.index` debajo de la tabla de destinos (la captura del incidente) con título "Eventos programados" y badge de estado, link "Ver todos" hacia `client.restream.events.index`.

## 6. Auditoría y cuota

- [ ] 6.1 En `RestreamEventSchedulerService::applyStart` y `applyStop`, insertar `AuditLog::record(...)` con `action='auto_start.restream_event'|'auto_stop.restream_event'`, `entity_type='restream_event'`, `entity_id=$event->id`, `after=['target_ids' => $ids]`, `channel_id=$event->channel_id`.
- [ ] 6.2 En los controladores cliente CRUD, envolver `store`/`update`/`destroy` con `AuditLog::record(...)` con `action='create.restream_event'|'update.restream_event'|'delete.restream_event'`, `before`/`after` snapshots.
- [ ] 6.3 En `applyStart`, antes de llamar `start()`, invocar `RestreamQuotaGuard::assertCanEnable($event->user, $event->channel)` por cada target; capturar `RestreamQuotaException` por target individual, escribir `last_error` en el target (`target->update(['last_error' => ..., 'last_failed_at' => now()])`) y continuar con los demás.

## 7. Verificación final

- [ ] 7.1 `npm run build:css` para asegurar que las nuevas clases Tailwind (si las hay) entran al bundle JIT (`tailwind.min.css` es estático según AGENTS.md).
- [ ] 7.2 `php artisan view:clear && php artisan config:clear && php artisan route:clear`.
- [ ] 7.3 `php artisan check:destructive-migrations` debe seguir limpio (no introdujimos migraciones destructivas).
- [ ] 7.4 Verificación manual con curl: `curl -H "Authorization: Bearer ..." /api/restream-events?channel_id=<uuid>` y validar shape JSON.
- [ ] 7.5 Verificación manual del motor: crear un evento que arranque en 2 minutos desde tinker, esperar al `restream:run-events`, confirmar `audit_logs` con la transición y los targets en `status=live`.
- [ ] 7.6 Verificación de coexistencia: confirmar que un target con `scheduled_start_at` inline sigue siendo procesado por `restream:launch-scheduled` sin cambios.
- [ ] 7.7 Verificación admin read-only: con sesión admin, intentar POST/PUT/DELETE → 403; GET → 200.
