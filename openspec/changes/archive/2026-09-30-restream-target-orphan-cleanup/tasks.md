## 1. Rollback de `store()` usa `forceDelete()` (dual-implementation)

- [x] 1.1 `php artisan db:backup` antes de tocar código que afecta la tabla `restream_targets` (protocolo AGENTS.md).
- [x] 1.2 Editar `app/Http/Controllers/Client/RestreamTargetController.php` línea 124: cambiar `$target->delete()` por `$target->forceDelete()` dentro del `catch (RestreamPlatformAccountException $e)`. Dejar el `if (isset($target))` y el `throw ValidationException` intactos.
- [x] 1.3 Editar `app/Http/Controllers/Admin/RestreamTargetController.php` línea 240: mismo cambio (mismo `catch`, mismo `forceDelete()`).
- [x] 1.4 Verificar que no hay otro `catch` con `$target->delete()` en estos dos controladores (grep de seguridad). Único `$target->delete()` que queda es en `Admin\RestreamTargetController::destroy` (línea 399) — intencional, es el soft-delete explícito del admin al archivar.

## 2. Validador distingue abortada vs archivada (dual-implementation)

- [x] 2.1 Editar `app/Http/Controllers/Client/RestreamTargetController.php` método `assertNoDuplicateTriple` (líneas 244-263):
  - Reemplazar el bloque `if ($existing)` por la lógica de dos pasos: (a) auto-purgar TODAS las huérfanas abortadas; (b) re-query y throw con archivado/activo según corresponda.
  - Actualizar la cadena del mensaje archivada: cambiar "eliminado" por "archivado".
  - Extraer predicado a `isAbortedOrphan(RestreamTarget)` para evitar duplicación.
- [x] 2.2 Replicar **idéntico** el método en `app/Http/Controllers/Admin/RestreamTargetController.php`. El admin controller **no tenía** `assertNoDuplicateTriple` — se agregó junto con la llamada desde `store()` (línea 204).
- [x] 2.3 Verificar que las cuatro columnas del predicado (`deleted_at`, `stream_key`, `platform_broadcast_id`, `status`) siguen siendo los atributos correctos del modelo en `app/Models/RestreamTarget.php` (no se renombraron en migraciones recientes).
- [x] 2.4 Decisión ajustada durante implementación: se **quitó** `enabled = false` del predicado abortada. El primer huérfano real encontrado en producción ("Youtobe", `a2dd6893-…`) tenía `enabled = true` porque `Client\RestreamTargetController::store` defaultea `$enabled = true` (línea 83). La señal decisiva es "nunca tuvo datos reales attached", no el flag enabled. Documentado en `design.md` Decisión 2.

## 3. Comando `restream:purge-aborted-targets`

- [x] 3.1 Crear `app/Console/Commands/PurgeAbortedRestreamTargetsCommand.php` con signature `restream:purge-aborted-targets`. Opciones: `--apply`, `--channel=UUID`, `--json`. Dry-run por defecto.
- [x] 3.2 El query replica el predicado del validador (4 columnas, sin `enabled`). Si `--channel=UUID` se pasa, agrega `where('channel_id', $uuid)`.
- [x] 3.3 En modo `--apply`: usa `DB::transaction` con `lockForUpdate()` sobre las filas candidatas. Itera con `get()` y `forceDelete()` una a una. Una sola entrada `audit_logs` con `action='purge_aborted.restream_target'`, `entity_id=null`, `after={purged_count, criteria, channel_id_or_null, purged_ids}`, `channel_id` del filtro si aplica.
- [x] 3.4 En modo dry-run (default): imprime tabla con id, user_id, channel_id, platform, created_at, deleted_at. Si `--json`, emite `{mode, criteria, channel_id, candidates_count, candidates[]}`.
- [x] 3.5 Confirmado: NO se registra como scheduled command — operator-driven. `php artisan list | grep restream:purge-aborted-targets` confirma visibilidad.

## 4. Verificación funcional

- [x] 4.1 Setup: en tinker, crear un `RestreamTarget` huérfano (uuid `a2dddea6-…`). Confirmar que el index con scope SoftDeletes NO la muestra pero `withTrashed()` sí.
- [x] 4.2 `php artisan restream:purge-aborted-targets --apply` la elimina junto con el huérfano real de producción ("Youtobe", `a2dd6893-…`).
- [x] 4.3 Archived target con `stream_key` → 422 con mensaje "Ya existe un destino archivado…".
- [x] 4.4 Aborted orphan sin `stream_key` → auto-purgado, no exception, estado limpio.
- [x] 4.5 (Cubierto por 4.3 + 4.4 + caso mixto): cualquier fila con `platform_broadcast_id` set pasa `isAbortedOrphan()` → false → tratada como archivada.
- [x] 4.6 Dual-implementation verificado vía tinker con `Admin\RestreamTargetController::assertNoDuplicateTriple` directo.

## 5. Verificación con datos reales

- [x] 5.1 Dry-run inicial: **2 candidatos** en producción (incluido el huérfano original que bloqueaba al usuario).
- [x] 5.2 Inspección del JSON antes de `--apply`: `a2dd6893-…` ("Youtobe", YouTube) y `a2dddea6-…` (test fixture).
- [x] 5.3 Después de `--apply`: 0 candidatos. Dry-run subsiguiente confirma.
- [x] 5.4 `audit_logs` contiene la entrada `purge_aborted.restream_target` con `purged_count: 2`, `purged_ids: [a2dd6893, a2dddea6]`, `channel_id: null`, `criteria` completos.

## 6. UI "Archivados" — POSPUESTO a un change separado

- [ ] 6.1–6.7 Marcados como pospuestos. La propuesta los marca como opcionales; el fix core (1–5) elimina el bug y los huérfanos existentes sin requerir UI nueva. La sección "Archivados" se aborda en un change dedicado con diseño visual específico (estilo del resto del panel).

## 7. Limpieza y rollback

- [x] 7.1 Documentado en `design.md` (no en este archivo). Sin migración; rollback = revertir los 2 controllers + borrar `PurgeAbortedRestreamTargetsCommand.php`. Filas huérfanas pre-existentes NO se restauran automáticamente al revertir (quedan purgadas).
- [x] 7.2 `php artisan check:destructive-migrations` → `OK — all destructive up() migrations declare WithDataSafetySnapshot + $criticalTables.`
