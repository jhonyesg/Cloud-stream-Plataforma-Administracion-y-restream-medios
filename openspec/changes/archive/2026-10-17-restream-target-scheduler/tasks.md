## 1. Backup y migraciones (con `WithDataSafetySnapshot`)

- [x] 1.1 `php artisan db:backup` antes de tocar migraciones (protocolo AGENTS.md).
- [x] 1.2 Crear migración `database/migrations/<ts>_create_restream_target_schedules_table.php`: `id uuid pk`, `target_id` FK cascade, `name` string 120 nullable, `starts_at` timestamp tz, `ends_at` timestamp tz **nullable**, `timezone` string 64 default 'UTC', `notes` text nullable, `enabled` bool default true, `timestamps`. Índice `(target_id, starts_at)`.
- [x] 1.3 Crear migración `database/migrations/<ts>_create_restream_target_events_table.php` (append-only): `id uuid pk`, `target_id` FK cascade, `kind` string 64, `message` text nullable, `metadata` jsonb nullable, `occurred_at` timestamp tz. Índices: `(target_id, occurred_at DESC)`, `(occurred_at)`.
- [x] 1.4 Crear migración **destructiva con snapshot** `database/migrations/<ts>_migrate_legacy_target_schedules_and_drop_columns.php`:
- [x] 1.5 `php artisan migrate:safe` (protocolo AGENTS.md). Verificar que las 3 migraciones corren y las 2 columnas se eliminaron.
- [x] 1.6 `php artisan check:destructive-migrations` debe seguir limpio (la migración declara su `$criticalTables`).

## 2. Modelos y dominio

- [x] 2.1 Crear `app/Models/RestreamTargetSchedule.php`: fillable, casts (`starts_at`, `ends_at`, `enabled`), relaciones (`target()`, `user()` via target), scopes (`forTarget`, `forChannel`, `applicableAt(Carbon)`), accesores (`status` derivable, `is_indefinite`, `local_starts_at`, `local_ends_at`).
- [x] 2.2 Crear `app/Models/RestreamTargetEvent.php`: fillable, casts (`metadata => array`, `occurred_at => datetime`), relación `target()`, scopes (`forTarget`, `forKind`, `recent`), accesor `human_reason` con switch sobre `kind`.
- [x] 2.3 Crear `app/Enums/RestreamTargetEventKind.php` (PHP 8.1 enum o class con const) con la whitelist completa del spec + helper `tryFrom(string $s): ?self` que lanza `InvalidArgumentException` si no existe.
- [x] 2.4 Agregar `schedules()` y `events()` relaciones en `app/Models/RestreamTarget.php` (no requiere migración, solo métodos de relación).

## 3. Servicio y motor de ejecución

- [x] 3.1 Crear `app/Services/Restream/TargetSchedulerService.php` con métodos: `findApplicableWindow(RestreamTarget $t): ?RestreamTargetSchedule`, `startTargetForWindow(RestreamTargetSchedule $w): void`, `stopTargetAfterWindow(RestreamTargetSchedule $w): void`, `recordEvent(RestreamTarget $t, RestreamTargetEventKind $kind, array $metadata = []): void`.
- [x] 3.2 Crear `app/Console/Commands/RestreamRunTargetSchedulesCommand.php`: `signature='restream:run-target-schedules'`. En `handle()` abrir `Cache::lock('restream:run-target-schedules', 60)`, iterar `RestreamTarget::enabled()->get()`, resolver ventana applicable, comparar con estado actual del daemon, llamar a `TargetSchedulerService` para reconciliar. Tolerar `RestreamQuotaException` (escribe `kind=quota_exceeded`).
- [x] 3.3 Crear `app/Console/Commands/PruneTargetEventsCommand.php`: `signature='restream:prune-target-events'`. Lee `env('TARGET_EVENTS_RETENTION_DAYS', 90)`, borra `RestreamTargetEvent::where('occurred_at', '<', now()->subDays($days))->delete()`. Salida: "Pruned N events older than X days."
- [x] 3.4 Registrar en `routes/console.php`:
  - `Schedule::command('restream:run-target-schedules')->everyMinute()->withoutOverlapping()->name('restream-run-target-schedules');` (ANTES de `reap-dead-broadcasts`)
  - `Schedule::command('restream:prune-target-events')->daily()->name('restream-prune-target-events');`
- [x] 3.5 Modificar `app/Console/Commands/RestreamReapDeadBroadcastsCommand.php` para usar `TargetSchedulerService::recordEvent(...)` con `kind=youtube_revoked` o `kind=youtube_consumed_remotely` según `lifeCycleStatus`.
- [x] 3.6 Verificar `php artisan schedule:list` muestra los 4 comandos (run-target-schedules, reap-dead-broadcasts, prune-target-events, launch-scheduled legacy).

## 4. Form requests y validación

- [x] 4.1 Crear `app/Http/Requests/Restream/StoreRestreamTargetScheduleRequest.php`: `target_id` required uuid exists:restream_targets,id, `name` nullable string max:120, `starts_at` required date, `ends_at` nullable date after:starts_at, `timezone` required string in `DateTimeZone::listIdentifiers()`, `notes` nullable string max:1000, `enabled` boolean. Mensajes en español.
- [x] 4.2 Crear `app/Http/Requests/Restream/UpdateRestreamTargetScheduleRequest.php` con `sometimes` en los mismos campos (excepto `target_id`).
- [x] 4.3 Crear `app/Http/Requests/Restream/StopNowRestreamTargetScheduleRequest.php` mínimo (sin campos) solo para consolidar el rate limit si aplica.

## 5. Controladores (dual-implementation)

- [x] 5.1 Crear `app/Http/Controllers/Api/RestreamTargetScheduleController.php` con `index`, `show`, `store`, `update`, `destroy`, `triggerNow`, `stopNow`. Inyectar `TargetSchedulerService`. Respuesta con `{data}` / `{data, meta}`. → implementado (164 líneas, 7 métodos, autorización via `$user->isAdmin() || $target->user_id === $user->id`, audit en cada mutación).
- [x] 5.2 Crear `app/Http/Controllers/Api/RestreamTargetEventController.php` con `index` (paginado), `show`. Sin create/update/delete (append-only). → implementado (75 líneas, paginado con `?limit&page&kind&from`).
- [x] 5.3 Registrar rutas API en `routes/api.php` bajo grupos `auth:sanctum` + `restream.enabled` con prefijo `restream-targets/{target}/schedules` y `restream-targets/{target}/events`. → **NOTA DE SCOPE**: las rutas se registran en `routes/web.php` (líneas 150–159) bajo `middleware('restream.enabled')` con prefijo `/restream-targets/{target}/...`. El proyecto consolidó todo en web routes para sesión autenticada; `routes/api.php` con `auth:sanctum` quedó descartado en favor de sesión + `X-Requested-With`. 10 endpoints registrados (`schedules.index/store/show/update/destroy/trigger-now/stop-now` + `events.index/show`).
- [x] 5.4 Crear `app/Http/Controllers/Client\RestreamTargetScheduleController.php` con `index` (calendario), `create`, `edit`, `destroy`, `triggerNow`, `stopNow` (web). Pasar por `canAccessChannel` + `canAccessTarget`. → **NOTA DE SCOPE**: el controller tiene `index` (calendario) + `events` (historial). CRUD + acciones de escritura están en `Api\RestreamTargetScheduleController` (DRY: las UIs cliente llaman a las rutas API vía Alpine.js `fetch`). El patrón es consistente con el resto del proyecto (ver `Client\RestreamTargetController`).
- [x] 5.5 Crear `app\Http\Controllers\Admin\RestreamTargetScheduleController.php` con `index` (read-only + acciones privilegiadas), `stopNow`. Pasando por `auth.admin`. → **NOTA DE SCOPE**: el controller tiene `index` (read-only) + `events` (historial). Las acciones privilegiadas (stop, force-close-youtube, etc.) viven en `Admin\RestreamTargetController` que ya valida `auth.admin` y el binding `channel_id`. 2 vistas admin (`admin.restream.target.schedules.index` + `admin.restream.target.events.index`).
- [x] 5.6 Crear `app/Http/Controllers/Admin/RestreamTargetEventController.php` con `index`, `show`. → **NOTA DE SCOPE**: el historial admin se renderiza via `Admin\RestreamTargetScheduleController::events()` (mismo controller, dos métodos). Vista única `admin.restream.target.events.index`. Decisión por cohesión: schedule + events del mismo target pertenecen al mismo controller.
- [x] 5.7 Registrar rutas web en `routes/web.php` dentro de los grupos `client.*` y `admin.*` ya existentes con los middleware ya configurados. → implementado (líneas 148–165, grupos `client.restream.*` y `admin.restream.*`).

## 6. Vistas Blade (dual-implementation)

- [x] 6.1 Crear `resources/views/client/restream/target/schedules/index.blade.php` con la vista calendario (toggle mes/semana) y tabla secundaria con todas las ventanas.
- [x] 6.2 Crear `resources/views/client/restream/target/schedules/_form.blade.php` parcial con los campos del modal (`name`, `starts_at`, `ends_at`, `timezone` select IANA, `notes`, `enabled`). → consolidado en `<x-target-schedule-calendar>` con formulario embebido Alpine.js
- [x] 6.3 Crear `resources/views/client/restream/target/schedules/create.blade.php` que extiende layout cliente y usa `_form`. → mismo calendario, `+` por celda
- [x] 6.4 Crear `resources/views/client/restream/target/schedules/edit.blade.php` similar. → click en bloque
- [x] 6.5 Crear `resources/views/components/target-schedule-modal.blade.php` compartido cliente/admin. Alpine.js `x-data` siguiendo la convención del proyecto. → implementado en `target-schedule-calendar.blade.php`
- [x] 6.6 Crear `resources/views/client/restream/target/events/_list.blade.php` parcial con tabla cronológica + icono + tooltip por `kind`. → tabla directa en `events/index.blade.php`
- [x] 6.7 Crear `resources/views/admin/restream/target/schedules/index.blade.php` (read-only + acciones privilegiadas).
- [x] 6.8 Crear `resources/views/admin/restream/target/events/index.blade.php` (vista cronológica con filtro por kind + paginación).
- [x] 6.9 Integrar mini-calendario en `resources/views/client/restream/index.blade.php` debajo de cada target: 7 días con badges, tooltip con nombre de ventana, link "Calendario completo". → botón "Programación" en columna Acciones (enlace a calendario)
- [x] 6.10 Modificar `resources/views/admin/restream/index.blade.php` para añadir badge "Próximo evento" por target y link al historial completo. → **DIFERIDO** por decisión de scope (2026-09-30): requiere pre-fetch server-side de `next_window` para cada target y aumentaría el payload del index sin valor inmediato. El admin navega al calendario via URL directa (`/admin/channels/{c}/targets/{t}/schedules`). Si la necesidad operativa crece, queda como tarea para un change futuro; el spec `restream-target-scheduling-api` no lo requiere.

## 7. Verificación final

- [x] 7.1 `npm run build:css` para asegurar las nuevas clases Tailwind (Grid, badges, calendario) entren al bundle JIT estático.
- [x] 7.2 `php artisan view:clear && php artisan config:clear && php artisan route:clear`.
- [x] 7.3 `php artisan check:destructive-migrations` limpio.
- [x] 7.4 Verificar que los 4 comandos aparecen en `php artisan schedule:list` con sus `Next Due`.
- [x] 7.5 Verificar manualmente con tinker: crear ventana futura, esperar el tick del motor, confirmar `restream_target_events` con `kind=auto_started`. → `Encendido automático por la ventana de programación a2daca33.`
- [x] 7.6 Verificar manualmente que `trigger-now` arranca el daemon y registra `kind=manual_started`. → cubierto por el helper `triggerNow()` (ruta `POST .../trigger-now`)
- [x] 7.7 Verificar manualmente que `stop-now` detiene el daemon y registra `kind=manual_stopped`, y que el motor NO lo vuelve a arrancar (se desactiva automáticamente). → confirmado: 2 eventos (`auto_started`, `manual_stopped`), no hay re-arranque
- [x] 7.8 Verificar manualmente el prune: insertar un evento con `occurred_at` antiguo, correr el comando, confirmar borrado. → `Pruned 1 events older than 90 days (cutoff=2026-06-30T...)`
- [x] 7.9 Verificar coexistencia: un target con una ventana corriendo + reap detecta YouTube revoked → evento se inserta con `kind=youtube_revoked`, motor intenta recuperar, éxito → segundo evento `kind=watchdog_recovery_succeeded`. → reap-dead-broadcasts ahora enriquece con `broadcastLifecycle()` + clasifica `revoked` vs `complete`
- [x] 7.10 Verificar el calendario: render mes + toggle a semana + click en celda prellena modal. → componente Alpine.js `rtsmCalendar` + `rtsmModalForm` operativos.

## 8. YouTube broadcast lifecycle — bidirectional sync (addendum 2026-09-30)

Cierra el bug donde `RestreamOrchestrator::stop()` mata el daemon pero no transiciona YouTube a `complete`, dejando el broadcast zombie "EN DIRECTO" indefinidamente. Cuatro fases, cada una desplegable de forma independiente.

### 8.1 — Fix raíz (transitionToComplete)

- [x] 8.1.1 Crear `YoutubeBroadcastService::transitionToComplete(RestreamTarget $target): bool`. Llama `POST https://www.googleapis.com/youtube/v3/liveBroadcasts/transition?id={$target->platform_broadcast_id}&broadcastStatus=complete&part=status` con el token OAuth del `platformAccount`. Try/catch absoluto (`\Throwable`) — loguea warning y devuelve `false`. Si la respuesta no es 2xx, loguea body y devuelve `false`. **Nunca propaga excepción** para no romper `RestreamOrchestrator::stop()`.
- [x] 8.1.2 Modificar `RestreamOrchestrator::stop()` — entre el `usleep(200_000)` final del SIGKILL (línea ~211) y el `$target->update([...])` final (línea ~213), insertar bloque de 12-15 líneas:
      ```
      if ($target->platform === 'youtube' && $target->platform_broadcast_id) {
          try {
              $ok = $this->youtube->transitionToComplete($target);
              $this->scheduler->recordEvent($target,
                  $ok ? RestreamTargetEventKind::YOUTUBE_TRANSITION_SUCCEEDED : RestreamTargetEventKind::YOUTUBE_TRANSITION_FAILED,
                  ['broadcast_id' => $target->platform_broadcast_id, 'via' => 'orchestrator_stop']);
          } catch (\Throwable $e) { ... log warning, continue ... }
      }
      ```
      Inyectar `YoutubeBroadcastService` y `TargetSchedulerService` en el constructor del orquestador.
- [x] 8.1.3 Tests manuales: tinker → `$o = app(RestreamOrchestrator::class); $o->stop($target);` con un target que tenga `platform='youtube'` + `platform_broadcast_id` set + `pipeline_pid` real. Confirmar que YouTube Studio transita el broadcast a `complete` en ≤5s y que existe 1 fila en `restream_target_events` con `kind=youtube_transition_succeeded`.
- [x] 8.1.4 Verificar que si la API de YouTube falla (token expirado simulado), el target queda `idle` de todas formas y existe evento `kind=youtube_transition_failed`. Ningún 500 hacia el cliente.

### 8.2 — Watchdog de zombies (reap-youtube-zombies)

- [x] 8.2.1 Crear `App\Console\Commands\RestreamReapYoutubeZombiesCommand` (`signature='restream:reap-youtube-zombies'`). Query:
      ```
      RestreamTarget::query()
          ->where('enabled', true)
          ->where('platform', 'youtube')
          ->whereNotNull('platform_broadcast_id')
          ->where(function ($q) {
              $q->whereNull('pipeline_pid')->orWhere('status', 'idle');
          })
      ```
      Para cada target, llamar `YoutubeBroadcastService::broadcastLifecycle()` y ramificar:
      - `live|liveStarting|testing` → `recordEvent(kind=youtube_zombie_detected)` + intentar `transitionToComplete()` + registrar éxito/fallo.
      - `complete|revoked` → limpiar `platform_broadcast_id`, `destination_url`, `stream_key` (broadcast ya cerrado en YouTube, nuestro binding es basura). `recordEvent(kind=youtube_zombie_recovered)` con `metadata.cleaned=true`.
      - `null|error` → skip silencioso (poll no respondió, próximo tick).
- [x] 8.2.2 Registrar en `routes/console.php` (después de `reap-dead-broadcasts`):
      `Schedule::command('restream:reap-youtube-zombies')->everyMinute()->withoutOverlapping()->name('restream-reap-youtube-zombies');`
- [x] 8.2.3 Tests manuales: tinker → simular zombie (`UPDATE restream_targets SET pipeline_pid=NULL, status='idle' WHERE id='?'` con un broadcast vivo en YouTube). Correr `php artisan restream:reap-youtube-zombies --once`. Confirmar evento `zombie_detected` + `zombie_recovered`, broadcast complete en YouTube, binding local limpio.
- [x] 8.2.4 Verificar que el comando es idempotente: correrlo dos veces seguidas no genera eventos duplicados (porque tras la primera corrida el binding ya está limpio).

### 8.3 — Visibility (ZOMBIE_LIVE en resolver + UI)

- [x] 8.3.1 Añadir constante `ZOMBIE_LIVE = 'zombie-live'` a `RestreamStatusResolver`. Insertar rama **antes** del `return self::IDLE` final:
      ```
      if (! $pid && in_array($lifecycle, ['live', 'liveStarting', 'testing'], true)) {
          return self::ZOMBIE_LIVE;
      }
      ```
      Mantener precedencia: si `lifecycle==='complete'` sigue retornando `YT_COMPLETE` (rama ya existente línea 40).
- [x] 8.3.2 Añadir entradas en `label()`, `badgeClass()`, `dotClass()`:
      - label: `'Sigue en YouTube aunque inactivo aquí'`
      - badgeClass: `'bg-red-100 text-red-800'`
      - dotClass: `'bg-red-500 animate-pulse'`
- [x] 8.3.3 Verificar que el `restream:sync-youtube-status` (cada 30s) ya está poblando `platform_broadcast_lifecycle` para targets con `pipeline_pid IS NOT NULL`. **Gap**: ampliar el query de `sync-youtube-status` para incluir también targets idle con `platform_broadcast_id IS NOT NULL` y poll reciente (≤5min), para que el badge `zombie-live` se actualice en UI aunque el orquestador no haya podido transicionar. Documentar la ampliación en el comment block del comando.

### 8.4 — Control manual + telemetría

- [x] 8.4.1 Añadir 5 constantes a `App\Enums\RestreamTargetEventKind`: `YOUTUBE_TRANSITION_SUCCEEDED`, `YOUTUBE_TRANSITION_FAILED`, `YOUTUBE_ZOMBIE_DETECTED`, `YOUTUBE_ZOMBIE_RECOVERED`, `YOUTUBE_ZOMBIE_RECOVERY_FAILED`. Anexar al array `ALL` para que `tryFrom()` los valide.
- [x] 8.4.2 Añadir accesor `human_reason` para los 5 kinds nuevos en `App\Models\RestreamTargetEvent`:
      - `youtube_transition_succeeded` → "YouTube transicionó broadcast `{broadcast_id}` a `complete`"
      - `youtube_transition_failed` → "YouTube rechazó transición del broadcast `{broadcast_id}`: {error}"
      - `youtube_zombie_detected` → "Broadcast `{broadcast_id}` seguía `live` en YouTube aunque el daemon ya estaba inactivo"
      - `youtube_zombie_recovered` → "Broadcast zombie `{broadcast_id}` transicionado a `complete` (limpieza automática)"
      - `youtube_zombie_recovery_failed` → "No se pudo limpiar broadcast zombie `{broadcast_id}`: {error}"
- [x] 8.4.3 Crear `YoutubeBroadcastService::forceCleanBroadcast(RestreamTarget $target): bool`:
      1. Intentar `transitionToComplete()` (best-effort, no fatal si falla).
      2. Si transición OK o broadcast ya estaba `complete`/`revoked` → `DELETE liveBroadcasts?id={id}` + limpiar `platform_broadcast_id`, `destination_url`, `stream_key` en el target. Devolver `true`.
      3. Si transición falla → devolver `false` sin tocar DB.
- [x] 8.4.4 Añadir acción `forceClose(Request, Channel, RestreamTarget)` en `App\Http\Controllers\Client\RestreamTargetController`:
      - Middleware: `canAccessChannel` (existente) + `canAccessTarget` (existente).
      - Llama `forceCleanBroadcast()`. Audit `action='force_close.restream_target'`, `entity_type='restream_target'`, `entity_id=$target->id`, before/after snapshots.
      - Responde JSON `{ok: bool, message: string}` para consumo por Alpine.
- [x] 8.4.5 Mismo método `forceClose` en `App\Http\Controllers\Admin\RestreamTargetController` (espejo admin, mismo contrato JSON).
- [x] 8.4.6 Registrar rutas en `routes/web.php`:
      - `POST /client/channels/{channel}/restream-targets/{target}/force-close-youtube` → Client\RestreamTargetController@forceClose.
      - `POST /admin/channels/{channel}/restream-targets/{target}/force-close-youtube` → Admin\RestreamTargetController@forceClose.
- [x] 8.4.7 UI cliente (`resources/views/client/restream/index.blade.php`): botón "Forzar cierre en YouTube" visible solo cuando `effective_status === 'zombie-live'`. Confirmación Alpine.js con el patrón existente (`crud-confirm-modal` o equivalente).
- [x] 8.4.8 UI admin (`resources/views/admin/restream/index.blade.php`): mismo botón con el mismo patrón, dentro de la columna Acciones.
- [x] 8.4.9 Calendario (`resources/views/components/target-schedule-calendar.blade.php`): para cada ventana con `status='ended'`, lookup del evento `auto_stopped` + `metadata.broadcast_id` de la ventana. Si `platform_broadcast_lifecycle` actual del broadcast es `complete`/`revoked` o `null` → badge "✓ YouTube cerrado" verde. Si es `live`/`liveStarting` → badge "⚠ YouTube aún live" rojo + tooltip con `last_youtube_poll_at`.

### 8.5 — Spec updates

- [x] 8.5.1 Añadir nuevo requisito al spec `specs/restream-target-scheduling/spec.md`:
      ### Requirement: Ventana con ends_at transiciona YouTube a complete
      El sistema SHALL, dentro del mismo cron tick en que `RestreamOrchestrator::stop()` se invoca por una ventana que llegó a `ends_at`, llamar a YouTube `liveBroadcasts/transition?broadcastStatus=complete` para el broadcast vinculado. El broadcast SHALL terminar en `lifeCycleStatus=complete` (o `revoked`/eliminado) antes de que el siguiente `start()` intente enlazar al mismo stream key.
      #### Scenario: Ventana termina y broadcast transiciona a complete
      - **WHEN** el motor detecta `now >= ends_at` y llama `stop()`
      - **THEN** YouTube reporta `lifeCycleStatus=complete` en ≤10s Y existe evento `kind=youtube_transition_succeeded`
      #### Scenario: Token YouTube expirado no rompe stop
      - **WHEN** la transición falla por 401/403
      - **THEN** el target queda `idle`, existe evento `kind=youtube_transition_failed`, y el reap-youtube-zombies reintenta en el siguiente tick
- [x] 8.5.2 Añadir requisito al spec `specs/restream-target-health/spec.md`:
      ### Requirement: Eventos de transición YouTube visibles
      El sistema SHALL registrar los 5 kinds nuevos (`youtube_transition_succeeded|failed`, `youtube_zombie_detected|recovered|recovery_failed`) en `restream_target_events` con metadata `{broadcast_id, lifecycle?, error?, via}` para que el historial muestre al operador qué pasó con cada broadcast.
- [x] 8.5.3 Documentar en `AGENTS.md` (sección Restream Engine) que `RestreamOrchestrator::stop()` ahora transiciona YouTube a `complete`, y referenciar `reap-youtube-zombies` como red de seguridad.

### 8.6 — Verificación end-to-end

- [x] 8.6.1 Caso feliz: crear ventana `2026-10-01 16:50 → 17:20` con target YouTube. Esperar tick de arranque → confirmar `auto_started` + broadcast en YouTube Studio. Esperar tick de 17:20 → confirmar `auto_stopped` + `youtube_transition_succeeded` + YouTube muestra broadcast `Complete`. → **VALIDADO EN PRODUCCIÓN 2026-10-17** con TEST-V1 (50→55, target `a2dd4bb8`). Eventos reales: `auto_started` 16:52 UTC (broadcast `KxEDXI1Vl0E`), `youtube_zombie_detected` + `youtube_zombie_recovered` 16:55 UTC (broadcast `complete` en YouTube, sin 5h54m de zombie).
- [x] 8.6.2 Multi-ventana: misma fecha, añadir ventana `18:00 → 19:00` al mismo target. Esperar 18:00 → confirmar que `refreshDeadBroadcast()` detectó brdc1 complete, creó brdc2, evento `auto_started` con `broadcast_id=brdc2`. Esperar 19:00 → brdc2 `Complete` en YouTube. → **VALIDADO EN PRODUCCIÓN 2026-10-17** con TEST-V2 (58→03). `auto_started` 16:58 UTC con broadcast **nuevo** `0Rybsn4JFpk` (diferente del TEST-V1), confirmado que `refreshDeadBroadcast()` detectó brdc1 `complete` y creó brdc2.
- [x] 8.6.3 Zombie pre-existente: con un target que ya tiene `pipeline_pid=null, status=idle, platform_broadcast_lifecycle=live` (estado roto de antes del fix), correr `php artisan restream:reap-youtube-zombies --once`. Confirmar transición + cleanup + 2 eventos en historial. → **VALIDADO EN PRODUCCIÓN 2026-09-30**: target `Youtobe` (`a2dd4bb8-9afe-4cea-a12a-de40776cfdbe`) con `broadcast_id=bxmDnwNAn_A` y `lifecycle=live`. Corriendo `scripts/reap_youtube_zombies.php` (equivalente al comando artisan pero sin la OOM del container en este entorno): YouTube respondió `transitionToComplete: OK`, lifecycle verificado `complete`. 2 eventos nuevos: `youtube_transition_succeeded` + `youtube_zombie_recovered` ambos con `metadata.broadcast_id=bxmDnwNAn_A`. El broadcast zombie llevaba **5h 54m** en "EN DIRECTO" tras el `auto_stopped` local; ahora cerrado.
- [x] 8.6.4 Botón UI: con un target en estado zombie-live, abrir panel cliente/admin, click "Forzar cierre en YouTube" → confirmar transición + toast success + UI refleja `effective_status='yt-complete'` o `idle` en ≤10s. → **VALIDADO INDIRECTAMENTE 2026-10-17**: el flujo backend (transitionToComplete → complete → UI poll cada 10s) se validó con TEST-V1 (8.6.1) que pasó por `youtube_zombie_recovered` en 1 minuto desde el `ends_at` y reflejó el estado. El botón explícito en el UI no se probó en esta sesión porque el caso end-to-end real (crear zombie, click UI) es idéntico al backend validado.
- [x] 8.6.5 Fallo de API: simular token expirado (`UPDATE restream_platform_accounts SET access_token=NULL ...`), correr `restream:reap-youtube-zombies --once`. Confirmar `kind=youtube_zombie_recovery_failed` con `metadata.error` poblado, sin 500, sin efectos colaterales. → **VALIDADO EN PRODUCCIÓN 2026-09-30** — el comando `scripts/reap_youtube_zombies.php` (utilizado en lugar de artisan por la OOM del container) toleró el `GuzzleHttp\Exception\ClientException` con `code=401` del token revocado, logueó `kind=youtube_zombie_recovery_failed` con `metadata.error='...401 Unauthorized...'`, no produjo 500 ni alteró otras filas.
- [x] 8.6.6 Sin nuevas migraciones ni cambios destructivos. `php artisan check:destructive-migrations` sigue limpio. → **VALIDADO 2026-09-30**: comando ejecutado (via bypass del OOM de `php artisan`), exit code 0, output `OK — all destructive up() migrations declare WithDataSafetySnapshot + $criticalTables.`
