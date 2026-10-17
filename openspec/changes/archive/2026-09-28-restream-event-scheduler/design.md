## Context

Hoy la programación restream se reduce a dos campos opcionales en `restream_targets` (`scheduled_start_at`, `scheduled_stop_at`) procesados por `restream:launch-scheduled` cada minuto. Cubre el caso mínimo de un cliente que programa un único par inicio/fin en un único destino. El cliente pide poder definir **varios eventos** (transmisión del noticiero matutino, cobertura especial del miércoles, evento del domingo) sin tener que volver a editar el target cada vez, y poder aplicar un mismo evento a varios destinos a la vez (por ejemplo, "sábado 8pm: arrancar YouTube y Facebook juntos durante 2h").

El cliente ya cuenta con:
- `restream_targets` (1 a N por usuario, con cuota `restream_quotas.max_outputs`).
- `RestreamOrchestrator` que abstrae `start()` / `stop()` y maneja la recuperación de broadcast de YouTube (recién parchado: ahora recrea broadcasts consumidos).
- `RestreamQuotaGuard` que valida slots antes de habilitar.
- `routes/console.php` con `restream:launch-scheduled` cada minuto.
- UI dual cliente/admin con `client.restream.index` (la captura que dispara este cambio) y `admin.restream.index`.

Lo que falta es la **entidad evento** desacoplada del target, con CRUD dual, motor de ejecución por minuto y respeto de cuota.

## Goals / Non-Goals

**Goals:**
- Entidad `restream_events` (tabla propia) con CRUD admin/cliente, ejecución automática y auditoría.
- Modelo one-shot puro: el evento tiene `starts_at` y `ends_at`; no hay recurrencia.
- Un evento puede aplicar a N destinos del cliente (relación pivote).
- Motor idempotente que detecta eventos candidatos cada minuto, llama `RestreamOrchestrator::start()` / `stop()` por destino y registra auditoría.
- Coexistencia limpia con el scheduler existente (`restream:launch-scheduled`); ningún cambio rompe su contrato actual.

**Non-Goals:**
- Recurrencia (semanal, diaria, "cada lunes"). El usuario lo descartó en la sesión de explore.
- Crear destinos nuevos desde la pantalla de eventos; los destinos ya existen y el evento los referencia.
- Modificar `restream_targets` (los campos `scheduled_start_at` / `scheduled_stop_at` se mantienen).
- Notificaciones al cliente (email/push/webhook) cuando un evento arranca/pierde. Eso queda para otro change.
- Permisos granulares admin sobre eventos del cliente (mute, override) — esta entrega admin es read-only.

## Decisions

**D1. Tabla propia + pivote vs. JSON column.**
- Decisión: tabla `restream_events` + pivote `restream_event_targets(event_id, target_id)`.
- Por qué: el cliente necesita consultar, listar y filtrar por destino, y la quota guard necesita `lockForUpdate` por target. JSON column complica esos queries. Pivote también permite ON DELETE CASCADE limpio cuando se borra el evento.
- Alternativa considerada: `target_ids jsonb` con índice GIN. Descartada por la auditoría (`audit_logs` espera `entity_type=restream_event, entity_id=<event-id>`, no del target) y por la atomicidad del pivote bajo `lockForUpdate`.

**D2. Timezone por evento, no global.**
- Decisión: columna `timezone` (string IANA, e.g. `America/Bogota`) en el evento, además de `starts_at`/`ends_at` en UTC.
- Por qué: el cliente programa "sábado 8pm hora Colombia" pero el motor corre en UTC y debe comparar contra `now()`. Guardar la TZ original permite mostrar la hora local en UI y mantener trazabilidad.
- Alternativa: solo UTC en BD y TZ global por usuario. Descartada porque distintos clientes del mismo tenant pueden estar en husos distintos.

**D3. Motor nuevo (`restream:run-events`) vs. extender `restream:launch-scheduled`.**
- Decisión: comando nuevo `restream:run-events`, registrado en `routes/console.php` con `everyMinute`.
- Por qué: responsabilidades distintas. El existente procesa campos inline del target; el nuevo procesa entidad propia con pivote y fases (pre-start, start, post-end, stop). Mantenerlos separados facilita rollback (borrar la línea del schedule y el comando deja de correr) y testing.
- Alternativa: extender el existente. Descartada por cohesión: la lógica de eventos no aplica a targets sin `scheduled_start_at`, y mezclarla oscurece ambos lados.

**D4. Detección de fase por consulta, no por flags.**
- Decisión: el motor consulta `where('starts_at', '<=', now())->where('ends_at', '>', now())` para fase "running" y `where('ends_at', '<=', now())` para fase "ended". Sin columnas `started_running_at` / `stopped_running_at`.
- Por qué: cero estado derivado = cero riesgo de inconsistencia. La auditoría (insert en `audit_logs` con `entity_type=restream_event`) ya cuenta quién vio qué. Si una ejecución falla y se reintenta, la próxima llamada a `start()` del target es idempotente por la lógica ya existente en `RestreamOrchestrator`.
- Riesgo aceptado: si el comando se salta un minuto (clock skew, cron atrasado) un evento de 1 minuto podría no ejecutarse. Mitigación documentada (R1).

**D5. Cuota se valida por target dentro del evento.**
- Decisión: el motor itera `event->targets`, llama `RestreamQuotaGuard::assertCanEnable` por cada uno y aplica los que pueda. Si un target falla por cuota, se registra `last_error` en el target y se continúa.
- Por qué: consistente con el comportamiento existente de `restream:launch-scheduled` (escenario "Cuota agotada no rompe el lote").

**D6. UI dual cliente/admin.**
- Decisión: implementar ambas vistas. Cliente crea/editar/borra sus eventos; admin lista y puede ver detalle pero no editar (read-only). Sigue la regla de dual-implementation del proyecto (ver `.kilocode/skills/cloudstream-dual-implementation/SKILL.md`).
- Modal compartido `<x-restream-event-modal>` consumido por ambas vistas.

**D7. Validación `ends_at > starts_at` en servidor, no en cliente.**
- Decisión: enforced en el form request del backend.
- Por qué: ya existe el patrón en `RestreamTargetController` (`scheduled_stop_at` debe ser posterior a `scheduled_start_at`).

## Risks / Trade-offs

- **R1. Comando se salta un minuto → evento corto no se ejecuta** → Documentar en `tasks.md` que eventos con duración < 60s son mejor-effort. Para eventos críticos el cliente debe mantener `duration >= 5min`. Loggear warning si se detecta un evento con duración < 60s al crearlo.
- **R2. Pivote sin lock global → dos ejecuciones simultáneas podrían intentar el mismo start** → `restream:run-events` se registra con `withoutOverlapping()` (Laravel scheduler nativo) para evitar solapamiento. Además, `RestreamOrchestrator::start()` ya es idempotente (línea 109-129: chequea `pipeline_pid` contra `/proc` cmdline).
- **R3. Timezones con DST pueden generar eventos 1h fuera** → Almacenar TZ IANA y normalizar a UTC al guardar. La UI muestra la hora local del evento usando `Carbon::setTimezone($event->timezone)` para evitar sorpresas.
- **R4. Quota del cliente cambia entre create y execute** → Si el cliente reduce su tier, un evento ya programado puede exceder la nueva quota al ejecutarse. Mitigación: el motor usa la quota al momento de ejecutar, igual que el scheduler existente.
- **R5. Migración nueva = ventana de bloqueo mínima** → La migración es aditiva (2 tablas, FKs, índices), sin tocar tablas existentes. Estimado < 1s en producción con snapshot pre-migración (`php artisan db:backup` antes, según protocolo).

## Migration Plan

1. Pre-deploy: `php artisan db:backup` (protocolo AGENTS.md).
2. Deploy del código + `php artisan migrate:safe` (que también hace backup + verify).
3. La migración crea `restream_events` y `restream_event_targets` con sus FKs e índices.
4. Post-deploy: ejecutar `php artisan restream:run-events --once` para validar el comando manualmente antes de que el cron lo dispare al siguiente minuto.
5. Verificar `php artisan schedule:list` muestra `restream-run-events` con `Next Due < 60s`.
6. Rollback: si la migración falla, `migrate:rollback` elimina las 2 tablas (no había datos). Si el comando tiene bug, basta con comentar la línea en `routes/console.php` y `php artisan schedule:clear`.

## Open Questions

- **Q1.** ¿La UI admin debe permitir **cancelar** un evento en `running` (forzar stop de todos sus targets)? Esta entrega lo deja como follow-up; admin solo ve detalle. Decisión en sesión aparte si el cliente lo pide.
- **Q2.** ¿Exponer endpoint público (no JSON API, sino webhook saliente) al arrancar/detener un evento para integraciones externas del cliente? Out of scope explícito por ahora; lo recogemos si surge.
- **Q3.** ¿Límite de duración máxima del evento? Tentativo: 24h (alineado con YouTube Live Stream max). Si el cliente pide más, se sube.
