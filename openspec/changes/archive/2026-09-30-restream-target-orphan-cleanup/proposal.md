## Why

Cuando un cliente intenta crear un destino de restream **con cuenta OAuth conectada** (YouTube o Facebook) y la llamada `createPlatformBroadcast()` falla — por red, por quota de YouTube, por broadcast binding que devuelve error, etc. — el catch de `store()` hace `$target->delete()` sobre la fila recién insertada. Eso deja una fila con `deleted_at` poblado, sin `stream_key`, sin `platform_broadcast_id`, sin eventos y nunca vista por el cliente (porque el index filtra con el scope de `SoftDeletes`).

A partir de ese momento el cliente **no puede volver a crear** un destino con el mismo `(user_id, channel_id, platform)` porque `assertNoDuplicateTriple()` (en `Client\RestreamTargetController.php:244-263`) usa `withTrashed()` y devuelve:

> "Ya existe un destino eliminado para esta plataforma en este canal. Restáuralo o elimínalo definitivamente para crear uno nuevo."

El problema es que el cliente **no puede ni restaurar ni force-eliminar** esa fila desde la UI: no aparece en la tabla y los endpoints `restore` / `force-destroy` no están expuestos en los botones del panel de cliente para destinos soft-deleted. Solo un admin que conozca las rutas puede sacarlo del medio. En la práctica el usuario queda bloqueado y termina llamando a soporte.

El índice único de Postgres (`restream_targets_user_channel_platform_unique … WHERE deleted_at IS NULL`) **sí permite** el re-crear después de un soft-delete; el bloqueo es enteramente del validador de Laravel, y existe como hint UX ("oye, ¿no querrías restaurar el que ya tenías?"). Ese hint tiene sentido para un destino **que el cliente realmente usó y archivó** (con `stream_key`, con `platform_broadcast_id`, con eventos en `restream_target_events`), pero **no tiene sentido** para una fila que nació muerta en un `store()` fallido: ahí no hay nada que restaurar.

Hay un segundo productor del mismo síntoma, más raro pero existente: `Admin\RestreamTargetController::destroy` (`Admin/RestreamTargetController.php:386-411`) hace soft-delete explícito cuando un admin archiva un destino. Esa fila sí puede aparecer listada con un badge de "archivado" en el panel admin, pero el panel cliente no la muestra, así que un cliente que intenta re-crear después de un archive-admin choca con el mismo 422.

## What Changes

1. **Rollback de `store()` usa `forceDelete()`, no `delete()`** (client y admin, ambos `createPlatformBroadcast` catch blocks). La fila nunca estuvo viva, no tiene valor de auditoría que justifique preservarla, y la excepción original ya quedó registrada en `audit_logs` (a través del flujo de `RestreamPlatformAccountException`). Esto elimina el 90% de las filas huérfanas futuras.

2. **`assertNoDuplicateTriple` distingue "abortada" vs "archivada por el usuario"**:
   - **Abortada**: `deleted_at IS NOT NULL AND stream_key IS NULL AND platform_broadcast_id IS NULL AND enabled = false AND status = 'idle'`. Estas filas se `forceDelete()` automáticamente antes del INSERT, sin error, sin mensaje al usuario.
   - **Archivada**: cualquier otro soft-deleted (tiene `stream_key`, tiene `platform_broadcast_id`, fue un admin-archive, etc.). Se conserva el comportamiento actual: 422 con el mensaje accionable "Restáuralo o elimínalo definitivamente para crear uno nuevo."

3. **Symmetry con la regla dual-implementation**: el mismo cambio de comportamiento debe caer en `app/Http/Controllers/Client/RestreamTargetController.php` y `app/Http/Controllers/Admin/RestreamTargetController.php`. La validación `assertNoDuplicateTriple` se extrae a un trait `App\Http\Controllers\Concerns\AssertsNoDuplicateRestreamTriple` (o se mantiene duplicada — el codebase ya duplica varios helpers entre client y admin).

4. **Comando de limpieza one-shot** (`php artisan restream:purge-aborted-targets`) para purgar las filas huérfanas que ya existen en producción hoy. Borra con `whereNull('stream_key')->whereNull('platform_broadcast_id')->where('enabled', false)->where('status', 'idle')->whereNotNull('deleted_at')`. Dry-run por defecto, `--apply` para mutar, `--json` para piping. Una sola fila de `audit_logs` con `action='purge_aborted.restream_target'` y `before`/`after`.

5. **Refactor mínimo** del index admin para mostrar una sección colapsable "Archivados" con los targets soft-deleted que el admin sí puede restaurar (`POST .../restore`) o force-eliminar (`DELETE .../force`). El admin ya tenía `restore` y `forceDestroy` (rutas en `routes/web.php:96-97`); solo faltaba la UI. La regla dual-implementation aplica: la sección existe en `/admin/restream-targets` (panel admin) **y** en `/client/restream` (panel cliente) — el cliente necesita ver sus propios archivados para entender el 422 si alguna vez lo ve por otra vía.

## Capabilities

### Modified Capabilities

- `restream-targets`: el requisito "Target CRUD endpoints" gana un escenario explícito que cubre el `store()` rollback (sin huérfanos) y un escenario que cubre el caso del validador distinguiendo abortada vs archivada. No se agregan columnas ni tablas nuevas; es un cambio de comportamiento en los controladores y la lógica del validador.

## Impact

- **DB:** ningún cambio de schema. Solo una mutación one-shot de filas existentes vía el comando artisan nuevo (defense-in-depth).
- **Modelos:** sin cambios. `RestreamTarget` sigue con `SoftDeletes`, sigue siendo la fuente de verdad.
- **Controladores (dual-implementation):** `Client\RestreamTargetController::store`, `Client\RestreamTargetController::assertNoDuplicateTriple`, `Admin\RestreamTargetController::store`, `Admin\RestreamTargetController::assertNoDuplicateTriple`.
- **Comandos nuevos:** `app/Console/Commands/PurgeAbortedRestreamTargetsCommand.php`.
- **Vistas (dual-implementation):** sección colapsable "Archivados" en `resources/views/client/restream/index.blade.php` y `resources/views/admin/restream/index.blade.php`. Sin nuevos componentes blade.
- **Backward compat:** el caso "destino archivado por el usuario" sigue funcionando igual — el cliente sigue viendo el 422 accionable. El único cambio visible para el usuario final es que **ya no queda pegado** después de un fallo de OAuth en el alta.
- **Sin nuevas dependencias.**
- **Snapshot release:** no se ve afectado.
- **Auditoría:** el comando nuevo escribe una entrada consolidada `action='purge_aborted.restream_target'` con `metadata={purged_count, criteria}`.
