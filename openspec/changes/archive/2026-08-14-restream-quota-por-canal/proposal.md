## Why

La habilitation de Restream (`restream_quotas`) está anclada al usuario (una fila global con `max_outputs`), pero el destino (`restream_targets`) vive a nivel canal. Esa esquizofrenia produce tres fallos: la UI cliente crea destinos sobre `channels[0]` en silencio (el usuario con 2 canales no sabe a cuál va), el guard de cupo cuenta destinos del usuario sin mirar el canal, y el admin habilita el módulo por usuario en vez de por canal. La intención de negocio es que el cupo **siga al canal asignado**: el admin habilita Restream en el canal del cliente, y ese canal tiene sus propios cupos.

## What Changes

- **BREAKING** `restream_quotas` pasa a ser por canal: se añade `channel_id` (unique compuesto con `user_id`). Una fila = un canal habilitado con su `max_outputs`.
- Migración de datos: las filas existentes (por usuario) se migran al primer canal asignado del usuario (el que tenga destinos, si los hay).
- `RestreamQuotaGuard` y los helpers del modelo `User` pasan a contar destinos **de ese canal** contra la quota **de ese canal**, con `lockForUpdate`.
- El middleware `restream.enabled` valida que el **canal de la ruta** tenga restream habilitado (ya no basta `hasRestreamEnabled()` global).
- El panel admin habilita Restream desde el **canal del cliente** (patrón del botón "Pantalla" con VirtualScreen), no desde `admin/users`.
- La vista cliente de Restream recibe un **selector de canal de contexto** (patrón scheduler, `?channel_id=`), el modal muestra/elige el canal destino y ya **nunca usa `channels[0]`**.
- Los conteos de `used_outputs` / `remaining_slots` en índice y modal se calculan por canal.

## Capabilities

### New Capabilities

- `restream-channel-context`: selector de canal de contexto en la vista cliente de Restream, URL `?channel_id=`, y modal de destino con selector de canal explícito (sin `channels[0]`).

### Modified Capabilities

- `restream-habilitation`: el requisito "One habilitation row per user" cambia a **una fila por (usuario, canal)**; la habilitación se otorga desde el canal del cliente y el middleware valida por canal.
- `restream-targets`: el conteo de cupo ("Active target count respects the user's quota") cambia a por canal; creación/activación validan contra la quota del canal del target.

## Impact

- **Base de datos**: migración de `restream_quotas` (añadir `channel_id`, unique `(user_id, channel_id)`, backfill de filas existentes). Usar `php artisan db:backup` + migración con `WithDataSafetySnapshot` (`$criticalTables = ['restream_quotas', 'restream_targets']`).
- **Modelos**: `RestreamQuota` (nueva relación `channel`), `RestreamTarget`, `User` (helpers por canal: `restreamMaxOutputs(channel)`, `restreamUsedOutputs(channel)`, `restreamRemainingSlots(channel)`).
- **Servicios**: `RestreamQuotaGuard` (firma `assertCanEnable(User $user, Channel $channel)`), `EnsureRestreamEnabled` middleware.
- **Controladores**: `Admin\RestreamQuotaController` (rutas ancladas a canal: `/admin/channels/{channel}/restream`), `Client\RestreamTargetController` (pasar canal al guard, conteos por canal), `Client\RestreamHomeController` (selector de contexto).
- **Vistas**: `client/restream/index.blade.php` (selector de canal), `components/restream-target-modal.blade.php` (selector de canal), `components/restream-modal.blade.php` (habilitación desde canal), `admin/users` (quitar la sección Restream global o redirigir al canal).
- **Rutas**: nuevas rutas admin por canal; el grupo `restream.enabled` cliente ya lleva `{channel}` en la URL.
- **Sin impacto en**: `RestreamLauncher`, `RestreamSupervisor`, motor FFmpeg, tabla `restream_targets` (esquema intacto).
