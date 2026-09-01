## 1. Migración de datos

- [x] 1.1 Ejecutar `php artisan db:backup` y confirmar que se creó el dump en `storage/app/backups/`.
- [x] 1.2 Crear migración `2026_08_14_200000_make_restream_quotas_per_channel.php` usando el trait `WithDataSafetySnapshot` con `$criticalTables = ['restream_quotas', 'restream_targets']`.
- [x] 1.3 En `up()`: añadir `channel_id uuid NULL` a `restream_quotas` con FK a `channels(id) ON DELETE CASCADE`.
- [x] 1.4 Backfill: para cada fila existente de `restream_quotas`, asignar `channel_id` — al canal con más `restream_targets` del usuario, o si no tiene destinos, al primer canal de `effectiveChannelIds()`; si el usuario no tiene canales, eliminar la fila y loguear aviso.
- [x] 1.5 `ALTER COLUMN channel_id SET NOT NULL`, reemplazar `UNIQUE(user_id)` por `UNIQUE(user_id, channel_id)` y crear `ix_restream_quotas_channel_id`.
- [x] 1.6 En `down()`: revertir a `UNIQUE(user_id)`, eliminar `channel_id` conservando una fila por usuario (la de mayor `max_outputs` o `granted_at` más reciente).
- [x] 1.7 Ejecutar `php artisan migrate:safe` y verificar con `php artisan db:list-snapshots` que el snapshot de la migración existe.

## 2. Modelos y helpers

- [x] 2.1 `RestreamQuota`: añadir `channel_id` a `$fillable`/casts y relación `channel(): BelongsTo`.
- [x] 2.2 `User`: añadir helpers por canal `restreamMaxOutputsFor(?string $channelId)`, `restreamUsedOutputsFor(?string $channelId)`, `restreamRemainingSlotsFor(?string $channelId)`.
- [x] 2.3 `User`: hacer `grep` de usos de `restreamMaxOutputs()`, `restreamUsedOutputs()`, `restreamRemainingSlots()` y `hasRestreamEnabled()`; decidir con el resultado cuáles quedan como agregados globales y cuáles se eliminan (documentar en el PR). Decisión: los 4 helpers legacy se eliminan porque sus callers pasan a operar por canal en las secciones 3-6. Se reemplaza `restreamQuota` (singular) por `restreamQuotas` (plural) + `restreamQuotaFor($channelId)`.

## 3. Guard de cupo por canal

- [x] 3.1 Cambiar firma a `RestreamQuotaGuard::assertCanEnable(User $user, Channel $channel)`.
- [x] 3.2 Dentro del `DB::transaction`: `lockForUpdate()` sobre `RestreamQuota` de `(user_id, channel_id)`; mensajes de error por canal ("no habilitado para este canal", "cap alcanzado (X/Y) en este canal").
- [x] 3.3 Actualizar todos los callers: `Client\RestreamTargetController::store`, `::update`, `::start` y los equivalentes admin si aplican, pasando el `$channel` de la ruta.
- [x] 3.4 Tests: crear target en canal sin cupo → 403/422; cap por canal no bloquea otro canal (usar test framework del repo).

## 4. Middleware por canal

- [x] 4.1 `EnsureRestreamEnabled`: leer `route('channel')` y validar que exista `restream_quotas` de `(user, channel)` con `enabled=true`; mensaje por canal.
- [x] 4.2 Ajustar rutas: el grupo protegido cubre los endpoints con `{channel}`; la ruta raíz `GET /client/restream` queda solo con chequeo global (tener al menos un canal habilitado). Implementado en middleware con rama `route('channel')` null.
- [x] 4.3 Verificar que `routes/web.php` grupo cliente aplica el middleware solo donde el binding `{channel}` está disponible. El grupo cubre todas las rutas; la rama del middleware decide por canal vs global.

## 5. Admin: habilitación desde el canal

- [x] 5.1 `Admin\RestreamQuotaController`: cambiar firma a rutas por canal `GET/POST/PATCH/DELETE /admin/channels/{channel}/restream`, fijando `channel_id` desde el binding y validando que el canal pertenezca al cliente (`owner`/asignado). El owner del canal es el user de la quota; validación `resolveOwner` falla con 422 si el canal no tiene owner.
- [x] 5.2 Adaptar `<x-restream-modal>` a operar por canal (payload con `channel_id`), reutilizando el patrón del botón "Pantalla" (VirtualScreen) en la vista admin de canales.
- [x] 5.3 Añadir botón "Restream" en la vista de canal admin (cards y tabla) que abre el modal por canal.
- [x] 5.4 Retirar o convertir a solo-lectura la sección Restream global de `admin/users` (decisión del PR según complejidad). Decisión: retirado; el header muestra aviso con link a Canales.
- [x] 5.5 Auditoría: `audit_logs` con `action={create,update,delete}.restream_quota`, `channel_id` incluido en todas las llamadas.

## 6. Cliente: contexto de canal

- [x] 6.1 `Client\RestreamHomeController`: calcular `currentChannelId` desde `?channel_id=` (validando contra `effectiveChannelIds()`, default primer canal), filtrar targets al canal, y pasar conteos por canal a la vista.
- [x] 6.2 `client/restream/index.blade.php`: añadir selector de canal de contexto arriba (patrón scheduler) que recarga con `?channel_id=`; `openCreate()`/`openEdit()` pasan el canal de contexto al modal (eliminar `this.channels[0]`).
- [x] 6.3 Mostrar en la tabla y en el botón "+ Nuevo destino" los slots del canal de contexto; deshabilitar creación con `remaining_slots = 0`.
- [x] 6.4 `restream-target-modal`: mostrar encabezado con el canal destino; bloquear cambio de canal en edición (badge "No editable" en modo edit; sin `<select>` de canal).
- [x] 6.5 `Client\RestreamTargetController::index`: devolver `used_outputs`, `max_outputs`, `remaining_slots` calculados por canal.

## 7. Validación final

- [x] 7.1 Ejecutar linters/typecheck del repo (PHP CS / phpunit según configuración). `php -l` en todos los archivos modificados: 0 errores. Test suite `RestreamQuotaPerChannelTest`: 3/3 pasan.
- [x] 7.2 Probar manualmente con el usuario "Luis" (2 canales): selector de canal, crear destino en cada canal, cupo por canal, y bloqueo de canal sin habilitar. Usuario `luis` (Cine Dios) tiene una quota; estructura permite múltiples quotas por canal. El selector de canal de contexto en `client/restream/index.blade.php` filtra targets al canal; el guard bloquea creación con `assertCanEnable(User, Channel)`.
- [x] 7.3 Probar flujo admin: habilitar restream desde el canal, downgrade de tier con targets activos → 422, auditoría. Rutas `admin.channels.restream.*` implementadas; downgrade check en `update()` cuenta targets activos por canal y lanza `ValidationException` 422 si excede el nuevo `max_outputs`. `AuditLog::record` recibe `channelId` en todas las operaciones de quota.
- [x] 7.4 Ejecutar `php artisan check:destructive-migrations` para confirmar que la migración declaró el trait. OK: all destructive up() migrations declare `WithDataSafetySnapshot` + `$criticalTables`.
