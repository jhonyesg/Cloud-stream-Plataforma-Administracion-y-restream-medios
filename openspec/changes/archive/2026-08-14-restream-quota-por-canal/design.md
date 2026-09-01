## Context

El módulo Restream (AGENTS.md) se construyó con una decisión que hoy resulta incoherente:

- `restream_quotas` — la habilitación — es **por usuario**: una fila con `user_id` único, `enabled`, `max_outputs ∈ {1,2,3,4}`. El admin la otorga desde `admin/users` (modal `<x-restream-modal>`).
- `restream_targets` — el destino — es **por usuario + canal**: cada destino tiene `user_id` y `channel_id`.

Consecuencias observadas en producción (usuario "Luis" con 2 canales asignados):

1. La vista cliente `GET /client/restream` carga sin contexto de canal: el `<select>` de la línea 105 es solo un filtro de tabla, no un selector de contexto. `openCreate()` usa `this.channels[0]` (línea 18) — crea el destino en el primer canal asignado, en silencio.
2. El modal `restream-target-modal` no muestra ni permite cambiar el canal.
3. `RestreamQuotaGuard::assertCanEnable($user)` (app/Services/Restream/RestreamQuotaGuard.php:30) cuenta `RestreamTarget::where('user_id')->where('enabled', true)` sin mirar el canal — el cupo "pertenece" al usuario pero el destino "pertenece" al canal.
4. El middleware `EnsureRestreamEnabled` valida solo `hasRestreamEnabled()` global, nunca el canal de la ruta.

Decisión de negocio confirmada con el usuario: **el cupo sigue al canal asignado**. El admin habilita Restream en el canal del cliente; ese canal tiene sus propios cupos.

Estado actual de las migraciones:
- `2026_08_14_000001_create_restrean_module_tables.php` — crea `restream_quotas` (con `UNIQUE(user_id)`) y `restream_targets`.
- `2026_08_14_100000_add_engine_columns_to_restream_targets.php` — añade `source_url`, `pipeline_pid`, `last_failed_at` a `restream_targets`.

## Goals / Non-Goals

**Goals:**
- Re-anclar la habilitación de Restream del usuario al canal: una fila de `restream_quotas` por par `(user_id, channel_id)`.
- Hacer que el canal participe en todas las decisiones: guard de cupo, middleware, conteos de UI, creación/activación de destinos.
- Dar contexto de canal a la vista cliente (selector tipo scheduler, `?channel_id=`) y eliminar el `channels[0]` silencioso del modal.
- Migrar sin pérdida las filas de quota existentes.

**Non-Goals:**
- No se toca el motor FFmpeg (`RestreamLauncher`, `RestreamSupervisor`, comandos `restream:*`).
- No se toca la tabla `restream_targets` (su esquema ya es correcto).
- No se cambia el sistema de tiers (1–4) ni el billing (fuera de alcance del módulo).
- No se reconstruye el motor de emisión (hilo separado; la relación emisión↔restream se decide en otro cambio).

## Decisions

### D1. `restream_quotas` pasa a ser por canal

Añadir `channel_id uuid NOT NULL REFERENCES channels(id) ON DELETE CASCADE` y sustituir `CONSTRAINT restream_quotas_user_unique UNIQUE (user_id)` por `UNIQUE (user_id, channel_id)`. Índice nuevo `ix_restream_quotas_channel_id`.

**Alternativa considerada**: mantener la quota por usuario y validar acceso por canal en el guard. Se descarta porque no expresa la intención de negocio ("habilito el canal"), no permite dar tiers distintos por canal, y deja el middleware ambiguo.

**Migración de datos**: las filas existentes (una por usuario) se migran al canal "más probable":
1. Si el usuario tiene destinos, al canal con más destinos del usuario.
2. Si no, al primer canal de `effectiveChannelIds()`.
3. Si no tiene canales, la fila se deshabilita (`enabled=false`) y queda sin `channel_id` de forma transitoria... **no**: `channel_id` es NOT NULL. En ese caso la fila se elimina (un usuario sin canales no puede usar restream de todos modos). Este caso extremo se documenta en el output de la migración.

### D2. El guard pasa a ser por canal

`RestreamQuotaGuard::assertCanEnable(User $user, Channel $channel)`:

1. Abre `DB::transaction`.
2. `RestreamQuota::where('user_id', $user->id)->where('channel_id', $channel->id)->lockForUpdate()->first()`.
3. Si no existe o `enabled=false` → excepción `restream.quota_not_enabled` ("El módulo Restream no está habilitado para este canal.").
4. Cuenta `RestreamTarget::where('user_id')->where('channel_id', $channel->id)->where('enabled', true)`.
5. Si `used >= max_outputs` → excepción `restream.cap_reached` con el mensaje por canal.

Los helpers de `User` se extienden con variantes por canal:
- `restreamMaxOutputsFor(?string $channelId): int`
- `restreamUsedOutputsFor(?string $channelId): int`
- `restreamRemainingSlotsFor(?string $channelId): int`

Se mantienen los helpers sin canal para compatibilidad interna (p. ej. `User::restreamUsedOutputs` usado en el admin), pero el guard y los índices cliente pasan a las variantes por canal. **Decisión**: los helpers legacy se eliminan si no quedan usos; se hace `grep` en tareas para decidir.

**Alternativa considerada**: mantener el guard global y filtrar por canal solo en el query. Se descarta: permite que un usuario con cupo en el canal A cree destinos en el canal B sin cupo (fuga de cupo).

### D3. Middleware valida por canal

`EnsureRestreamEnabled::handle($request, $next)` lee `$request->route('channel')` (el route-model binding ya resuelve el `Channel`). Valida:

- `$user` existe (login).
- Existe `RestreamQuota` para `(user, channel)` con `enabled=true`.
- Además, el middleware conserva el chequeo global (`hasRestreamEnabled()`) solo como atajo de rendimiento; la decisión de autorización la toma la fila del canal.

**Impacto en rutas**: el grupo cliente ya tiene `{channel}` en la URL (`/client/channels/{channel}/restream-targets`), por lo que `route('channel')` está disponible. La ruta raíz `GET /client/restream` (índice sin canal) se mantiene **fuera** del middleware por-canal (el middleware actual cubre todo el grupo; se ajusta para que el índice solo requiera `hasRestreamEnabled()` global y el selector decida el canal).

### D4. Selector de canal de contexto en la vista cliente

Patrón scheduler (`client/scheduler/index.blade.php:49`): un `<select>` prominente arriba que recarga `GET /client/restream?channel_id=...`. El controlador `RestreamHomeController`:

- Calcula `currentChannelId = $request->query('channel_id')`, validando que esté en `effectiveChannelIds()`; si no, usa el primero.
- Filtra `targets` al canal actual.
- Pasa `channelsJson`, `currentChannelId`, `channels`, `targets`, y los conteos por canal (`usedOutputsFor`, `maxOutputsFor`, `remainingSlotsFor`).

`openCreate()` y `openEdit()` pasan el **canal actual** al modal (nunca `channels[0]`). El modal `restream-target-modal` muestra el canal en un encabezado informativo; si se desea cambiar de canal al editar, el campo se habilita solo en modo crear con la lista de canales del usuario (decisión UX: en edición el canal no cambia — requiere apagar/encender destinos y podría cruzar cupos; se deja bloqueado).

### D5. Admin habilita desde el canal

Nuevas rutas admin por canal (patrón VirtualScreen/Pantalla):

```
GET   /admin/channels/{channel}/restream           → estado + targets del canal
POST  /admin/channels/{channel}/restream           → crear quota (channel_id fijo)
PATCH /admin/channels/{channel}/restream           → actualizar enabled/max_outputs/notes
DELETE /admin/channels/{channel}/restream          → eliminar quota (deshabilita todo)
```

`RestreamQuotaController` recibe `Channel $channel` en lugar de `User $user`. La UI de habilitación vive en un modal hermano de `virtual-screen-editor-modal` (botón "Restream" en la vista de canales admin), reutilizando `<x-restream-modal>` adaptado a `channel_id`.

La sección global de `admin/users` se mantiene solo como **lectura** (ver qué canales tienen restream) o se retira; decisión de tarea según complejidad. Preferencia: retirar el modal de `admin/users` y dejar el acceso desde `admin/channels`.

### D6. Migración con safety snapshot

Nueva migración `2026_08_14_200000_make_restream_quotas_per_channel.php` que usa el trait `WithDataSafetySnapshot` con `$criticalTables = ['restream_quotas', 'restream_targets']`, según la política de AGENTS.md. `up()`:

1. Añade `channel_id` (nullable primero).
2. Backfill: por cada fila, canal del usuario (regla D1).
3. `ALTER COLUMN channel_id SET NOT NULL`.
4. Drop `restream_quotas_user_unique`, add `restream_quotas_user_channel_unique UNIQUE(user_id, channel_id)`.
5. Índice `ix_restream_quotas_channel_id`.

`down()` revierte: reconstruye `UNIQUE(user_id)`, elimina `channel_id` (la fila sobrante de un mismo usuario se conserva con el mayor `max_outputs` o `granted_at` más reciente).

## Risks / Trade-offs

- **Fuga de cupo si el guard no recibe canal** → El guard firma con `Channel $channel` (type-hinted) y todos los callers (create/update/start client y admin) pasan el canal de la ruta; los tests de regresión cubren "crear en canal sin cupo → 422".
- **Migración: usuarios con quota pero sin canales asignados** → La fila se elimina (caso sin sentido de negocio); se loguea con aviso y se documenta. Backup previo `php artisan db:backup`.
- **Downgrade de tier por canal** → La validación existente de `RestreamQuotaController::update` (no bajar `max_outputs` por debajo de los activos) se replica contando por canal.
- **UX: cambiar de canal en edición** → Se bloquea el cambio de canal en el modal de edición (solo se muestra informativo). Crear con el selector de contexto elimina la necesidad de re-asignar.
- **Middleware aplicado al índice sin canal** → La ruta raíz se excluye del chequeo por canal; el selector de la vista impide navegar a canales sin cupo (filtra las opciones a canales habilitados).

## Migration Plan

1. `php artisan db:backup` (obligatorio, AGENTS.md).
2. `php artisan migrate:safe` — ejecuta la nueva migración con snapshot previo de `restream_quotas` y `restream_targets`.
3. Verificar con `php artisan db:list-snapshots` que el snapshot de la migración existe.
4. Smoke: `php artisan restream:status` y revisar la vista cliente con el usuario de 2 canales (Luis).
5. Rollback si algo falla: `php artisan db:restore-snapshot 2026_08_14_200000_make_restream_quotas_per_channel` (o `migrate:rollback` de esa migración).

## Open Questions

- ¿El selector de la vista cliente debe ocultar canales sin restream habilitado o mostrarlos con aviso "pide al admin habilitarlo"? (Pref: mostrarlos con aviso, para que el cliente sepa que existe la opción.)
- ¿Se elimina del todo la sección Restream global de `admin/users` o se mantiene como vista de solo lectura?
- ¿Los helpers legacy de `User` (sin canal) se eliminan o se conservan como agregados? Se resuelve con `grep` de usos en tareas.
