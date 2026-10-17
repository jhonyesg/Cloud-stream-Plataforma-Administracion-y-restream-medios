## Why

Una `ScheduleTemplate` puede quedar en `status='draft'` con bloques asignados cuando los datos se restauran desde el snapshot de producción (`database/snapshots/release/production.json.gz` vía `ProductionDataSeeder`), o cuando bloques se insertan fuera del endpoint `assignDay` (tinker, migraciones, fixtures). En ese estado inconsistente, `ScheduledPlaylistResolver::resolveForNow()` falla con `no hay programación activa`, el cliente ve la insignia `Draft` y no encuentra forma de activar la plantilla desde la UI — el botón no existe. El error promete una acción ("activala desde el módulo Programación") que la UI no provee. El cliente queda bloqueado.

## What Changes

- **Nuevo servicio** `App\Services\Scheduler\TemplateActivator` con un único método público `activateIfHealable(ScheduleTemplate $t): bool`. Extrae y generaliza la lógica actual de `ScheduleTemplateController::autoActivateIfNeeded` para que pueda invocarse desde múltiples puntos de entrada.
- **Heal en carga de scheduler** (`Admin\ScheduleController::index` y `Client\ScheduleController::index`): tras cargar el template y verificar que su canal está habilitado comercialmente, si está en `draft` con `blocks_count > 0`, se promueve a `active`. La insignia pasa a "Active" en el siguiente render sin acción del usuario.
- **Heal en arranque de emisión** (`ScheduledPlaylistResolver::resolveActiveTemplate`): si el resolver no encuentra un template activo para `(channel, year, month)`, antes de lanzar `noActiveTemplate`, intenta una segunda pasada promoviendo el template en `draft` con bloques del mismo mes. Si la promoción tiene éxito, continúa con el flujo normal; si falla, lanza la excepción original.
- **Heal en restauración de snapshot** (`ProductionDataSeeder`): tras insertar `schedule_templates` y `schedule_blocks`, escanea los pares y promueve los templates en `draft` con bloques antes de cerrar el seeder. Cura el origen conocido del problema.
- **Nuevo comando Artisan** `php artisan schedule-templates:heal-drafts [--channel=ID]` para soporte y operaciones puntuales. Por defecto es no-op si no hay templates inconsistentes. Acepta `--json` para piping.
- **Auditoría**: cada promoción via heal registra en `audit_logs` con `action='update.schedule_template.heal'`, `before={status:'draft'}`, `after={status:'active'}`, `channel_id=t.channel_id`.

El heal es **no-op** cuando:
- `status != 'draft'` (la plantilla ya está `active` o `archived`),
- `blocks_count == 0` (es un draft genuino sin contenido),
- `channels.status IN ('suspended','archived')` (no se promueve programación de un canal comercialmente deshabilitado).

Idempotente: dos llamadas concurrentes son race-safe gracias al `UNIQUE INDEX uq_schedule_tmpl_active` y al guard `if status != draft → return` al inicio del método.

## Capabilities

### New Capabilities
- `scheduler-template-heal`: Define el contrato del heal auto-promotor de `ScheduleTemplate` de `draft` a `active`, sus guards (blocks_count, channels.status), su auditoría y sus puntos de invocación (scheduler load, emission start, snapshot restore, artisan command).

### Modified Capabilities
- (ninguno) — el requerimiento existente en `scheduler` ("Asignar playlist a un día promueve el template a activo si está en draft") se mantiene intacto; este cambio introduce un camino de promoción adicional e independiente para estados inconsistentes pre-existentes. La API pública de `assignDay`, `replicate` y `replicateRange` no cambia.

## Impact

- **Servicios nuevos**:
  - `app/Services/Scheduler/TemplateActivator.php`
- **Servicios modificados**:
  - `app/Services/ScheduledPlaylistResolver.php` (añadir intento de heal antes de `noActiveTemplate`)
- **Controladores modificados**:
  - `app/Http/Controllers/Admin/ScheduleController.php`
  - `app/Http/Controllers/Client/ScheduleController.php`
- **Comandos nuevos**:
  - `app/Console/Commands/Scheduler/HealDraftTemplates.php`
- **Seeders modificados**:
  - `database/seeders/ProductionDataSeeder.php`
- **Rutas**: ninguna nueva (comando Artisan + endpoint emission/start existente).
- **Vistas Blade**: ninguna (la insignia "Draft" → "Active" se actualiza naturalmente en el siguiente render porque el heal ocurre antes de pasar `$template` al view).
- **Migraciones**: ninguna.
- **Dual-implementation**: el cambio vive en controladores y servicios (capa PHP), no en vistas. La regla "dual" del proyecto se cumple tocando ambos `ScheduleController` (admin + client). La insignia es la misma Blade helper ya existente.
- **Riesgo operacional bajo**: el guard `channels.status NOT IN ('suspended','archived')` previene promociones sobre canales suspendidos por impago o archivados. El guard `blocks_count > 0` previene promover templates vacíos recién creados.
