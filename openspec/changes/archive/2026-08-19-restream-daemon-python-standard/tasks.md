## 1. Refactor compartido en emisor_python

- [x] 1.1 Refactorizar `emisor_python/pipeline/ffmpeg_pipeline.py` para que la construcción de args y la gestión del subproceso (Popen, kill por grupo, stats, watchdog hooks) sean reutilizables por el restream daemon sin cambiar el comportamiento del emisor
- [x] 1.2 Extender `emisor_python/utils/laravel_client.py` con `post_restream_heartbeat(target_id, payload)` y `write_restream_registration(target_id, data)` apuntando a los endpoints internos de restream
- [x] 1.3 Verificar que el emisor sigue pasando su smoke test (`emisor_python/test_redplanet_production.py` o equivalente) tras el refactor

## 2. Daemon Python de restream

- [x] 2.1 Crear `restream_daemon/main.py` con el supervisor loop del estándar (restart con backoff exponencial, máx 5 en 60s, cooldown 30s, heartbeat `error` al agotar el presupuesto) — espejo de `emisor_python/main.py`
- [x] 2.2 Crear `restream_daemon/daemon/target_daemon.py`: lee el config JSON, construye el comando ffmpeg con `-rtmp_live live` (corregido), spawnea el hijo, escribe el registration file en `storage/app/restream-daemons/{target_id}.json`
- [x] 2.3 Implementar el heartbeat loop (cada 5s: `status`, `pipeline_pid` del hijo ffmpeg, `error_message`) y el stats loop (línea `[STATS]` cada 5s con uptime/bitrate/fps/frames_sent/state)
- [x] 2.4 Implementar el watchdog (15s sin progreso → reiniciar el hijo ffmpeg con log `[WATCHDOG]`)
- [x] 2.5 Implementar shutdown limpio: matar el grupo de procesos del ffmpeg hijo, heartbeat final `offline`, borrar el config JSON
- [x] 2.6 Garantizar que el `stream_key` nunca aparece en el log ni en el config persistido tras el arranque (permisos 0600, borrado post-lectura)
- [x] 2.7 Crear `systemd/cloudstream-restream@.service` (mismo estilo que `cloudstream-emission@.service`, usuario www, `Restart=on-failure`)
- [x] 2.8 Smoke test manual: arrancar el daemon con un target real y verificar que emite al destino RTMP y escribe `[STATS]` en el log

## 3. Migración de base de datos

- [x] 3.1 Crear migración con `WithDataSafetySnapshot` y `$criticalTables = ['restream_targets']` que añada `last_heartbeat_at` (timestamp nullable) y `loops_completed` (integer default 0) a `restream_targets`
- [x] 3.2 En la misma migración, reasignar `status` existente: `active` → `live` (y preparar el enum para `starting`)
- [x] 3.3 Ejecutar `php artisan db:backup` y `php artisan migrate:safe`; verificar con `php artisan check:destructive-migrations`

## 4. Orquestador PHP y endpoints internos

- [x] 4.1 Crear `App\Services\Restream\RestreamOrchestrator` con `buildConfig(RestreamTarget): array` (resuelve source_url/fallback, descifra stream_key, resuelve ffmpeg_bin) y `start(RestreamTarget, dryRun=false): array` (escribe config, `status='starting'`, spawn vía proc_open, health-check 500ms, 422 con causa real si muere)
- [x] 4.2 Implementar `stop(RestreamTarget): void` (SIGTERM al daemon, SIGKILL tras 5s, espera heartbeat `offline` hasta 10s, limpieza forzada si no llega)
- [x] 4.3 Crear `App\Http\Controllers\Api\RestreamHeartbeatController` con `POST /api/internal/restream/{target}/heartbeat` (localhost-only, valida status/pipeline_pid/error_message, actualiza la fila, `offline` limpia PID y pone `idle`)
- [x] 4.4 Registrar la ruta interna en `routes/api.php` con el mismo guard de localhost que el heartbeat del emisor
- [x] 4.5 Añadir endpoint de log: `GET /api/internal/restream/{target}/log?lines=N` (tail del archivo con parseo `[STATS]`/`[DAEMON]`/`[WATCHDOG]`, estilo `EmissionController::log`)

## 5. Controladores y UI (admin + cliente — dual implementation)

- [x] 5.1 Actualizar `RestreamTargetController` (client) y `AdminRestreamTargetController` para delegar start/stop en `RestreamOrchestrator` (422 con causa real al fallar, audit_logs igual)
- [x] 5.2 Enriquecer el index de targets con `last_heartbeat_at` y `status` real (stale > 15s → error) en `RestreamHomeController` y el admin equivalente
- [x] 5.3 Añadir polling de estado cada 10s en `client/restream/index.blade.php` y `admin/restream/index.blade.php` (patrón `emissionControl` del scheduler, pausa en `visibilitychange`)
- [x] 5.4 Añadir panel "Log de restream" por target (acordeón en la fila) con polling cada 5s mientras esté abierto, reutilizando el estilo del panel de log del scheduler
- [x] 5.5 Actualizar `restream-urls.blade.php` con las URLs de log/status necesarias
- [x] 5.6 Verificar la dualidad: los cambios de UI/controladores aplican simétricamente a admin y cliente (skill cloudstream-dual-implementation)

## 6. Eliminación de la implementación PHP antigua

- [x] 6.1 Grep de usos de `RestreamLauncher`, `RestreamSupervisor`, `RestreamSuperviseCommand`, `RestreamStartCommand`, `RestreamStopCommand` y eliminar los que queden huérfanos
- [x] 6.2 Eliminar `App\Services\Restream\RestreamLauncher.php` y `RestreamSupervisor.php` (y `RestreamQuotaException`/`RestreamQuotaGuard` se conservan — son del módulo de cupos)
- [x] 6.3 Eliminar `RestreamSuperviseCommand`, `RestreamStartCommand`, `RestreamStopCommand` (conservar `RestreamStatusCommand` como diagnóstico read-only si se decide mantenerlo)
- [x] 6.4 Actualizar `AGENTS.md` (sección Restream Engine) para reflejar el daemon Python como el motor y la eliminación del supervisor PHP
- [x] 6.5 Verificar que no quedan referencias rotas: `php artisan route:list`, `php artisan command:list`, grep de `restream:supervise` en docs/scripts

## 7. Verificación final

- [x] 7.1 Prueba end-to-end: crear target, iniciar desde la UI, verificar heartbeat `live`, log con `[STATS]`, y que el destino RTMP recibe el flujo
- [x] 7.2 Prueba de aislamiento: con 2+ targets activos, matar el ffmpeg de uno y verificar que el otro sigue emitiendo sin interrupción
- [x] 7.3 Prueba de fallo visible: target con destino inválido → la UI muestra el error real (p. ej. `Connection refused`) en el tooltip y en el panel de log
- [x] 7.4 Prueba de stop: detener desde la UI → daemon y ffmpeg hijo muertos, `status='idle'`, `pipeline_pid` limpio
- [x] 7.5 Ejecutar `php artisan migrate:safe`, `php artisan check:destructive-migrations` y los tests existentes del módulo restream
