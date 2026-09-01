## Why

Cloudstream hoy emite hacia una sola salida por canal. Los clientes que quieren distribuir su señal en varias plataformas (Facebook, TikTok, YouTube, etc.) no tienen cómo hacerlo desde el panel. Necesitamos un módulo de **restream** que re-envíe la salida del canal a múltiples destinos simultáneos, con un esquema de **habilitación por niveles** controlado únicamente por el admin:

**Modelo de 4 niveles (1 base gratuito + 3 adiciones de pago):**

- **Nivel base (estándar, gratuito)**: 1 destino simultáneo.
- **Nivel 2 (1.ª adición de pago)**: hasta 2 destinos.
- **Nivel 3 (2.ª adición de pago)**: hasta 3 destinos.
- **Nivel 4 (3.ª adición de pago)**: hasta 4 destinos.

Es decir: `max_outputs ∈ {1, 2, 3, 4}` con default `1`. Cada nivel por encima del base requiere que el admin habilite la siguiente adición según el plan que el cliente haya contratado.

El pago, la facturación y el ciclo comercial quedan fuera del alcance de este cambio — el admin simplemente activa y ajusta el nivel del módulo por usuario desde el panel, según el plan que el cliente haya contratado.

## What Changes

- Nueva tabla `restream_quotas` (1 fila por usuario habilitado) con `user_id`, `max_outputs` (1/2/3/4, default 1), `enabled` y metadatos de auditoría (`granted_by`, `granted_at`, `notes`).
- Nueva tabla `restream_targets` con los destinos configurados por usuario: `user_id`, `channel_id`, `platform` (facebook, tiktok, youtube, custom), `name`, `destination_url`, `stream_key` (cifrado), `enabled`, `status` (idle/active/error).
- Nuevo módulo `Restream` visible en `admin/users/{user}` como sección "Restream" con: switch de habilitación, selector de nivel (1/2/3/4), y gestión de destinos (crear/editar/eliminar/activar).
- En `client/channels` y/o `client/dashboard`, los clientes con `restream_quotas.enabled = true` ven una pestaña "Restream" donde pueden configurar destinos hasta el límite de su nivel.
- Middleware `EnsureRestreamEnabled` que bloquea el acceso a rutas/UI del módulo si el usuario no tiene `restream_quotas.enabled = true`.
- Helper `User::restreamQuota()` y `User->restreamRemainingSlots()` que devuelven la cuota y los slots disponibles.
- Regla de validación: no se puede crear un `restream_target` activo si `count(activos) >= max_outputs` del usuario.
- **Restricción de canal**: un destino solo puede vincularse a un canal dentro del `effectiveChannelIds()` del usuario.
- Auditoría: cada cambio en `restream_quotas` y cada CRUD sobre `restream_targets` se registra en `audit_logs`.

## Capabilities

### New Capabilities

- `restream-habilitation`: define la tabla `restream_quotas`, las reglas de habilitación por admin, los niveles (1/2/3/4, default 1), el modelo de slots y la integración con el panel admin.
- `restream-targets`: define la tabla `restream_targets`, el modelo de configuración por destino (plataforma, URL, stream key, estado), las reglas de límite por cuota y la UI de gestión desde el panel client.

### Modified Capabilities

- `auth-and-users`: añade `User::restreamQuota()` y `User->restreamRemainingSlots()` como helpers derivados del módulo de restream; mantiene la separación de roles admin/client. (No cambian los requisitos de auth, solo se exponen dos helpers nuevos que viven en el modelo `User` por conveniencia.)

## Impact

- **Migraciones nuevas**: `restream_quotas`, `restream_targets`. Ambas usan `WithDataSafetySnapshot` si llegan a tener `down()` destructivo (no aplica aquí, son `up()` puros).
- **Modelos nuevos**: `App\Models\RestreamQuota`, `App\Models\RestreamTarget`.
- **Vistas nuevas (par admin/client)**:
  - `resources/views/admin/users/_restream_section.blade.php` (partial embebido en `admin/users/show`).
  - `resources/views/client/channels/_restream_panel.blade.php` (pestaña dentro de la vista de canal cliente).
- **Controladores nuevos (par)**:
  - `App\Http\Controllers\Admin\RestreamQuotaController` (CRUD de cuota).
  - `App\Http\Controllers\Client\RestreamTargetController` (CRUD de destinos limitado a su `effectiveChannelIds()`).
- **Rutas nuevas (par)**: rutas web bajo prefijo `admin/users/{user}/restream` y `client/channels/{channel}/restream-targets`, ambas con middleware de rol + `EnsureRestreamEnabled`.
- **Middleware nuevo**: `App\Http\Middleware\EnsureRestreamEnabled`.
- **Servicio auxiliar**: `App\Services\Restream\RestreamQuotaGuard` (centraliza la regla `count(activos) < max_outputs`).
- **Sin impacto** en emisión/playout actual — el módulo de restream define **qué destinos hay configurados**; la integración con el pipeline de emisión queda como hook futuro (la tabla `restream_targets` ya queda lista para que el daemon la consuma cuando el motor se reconstruya).
- **Sin cambios** en `EmissionState`, `VirtualScreen`, ni en `program_timeline_items`.
