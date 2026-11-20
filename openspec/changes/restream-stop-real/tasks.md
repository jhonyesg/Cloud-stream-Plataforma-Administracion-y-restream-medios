# Tasks

## 1. Parada real en el orquestador

- [x] 1.1 Reescribir `RestreamOrchestrator::stop()`: resolver el PID del daemon por `/proc`, comprobar la vida con `/proc/<pid>/stat` (zombie = muerto), y devolver `['stopped','pid','method','reason']` en vez de `void`
- [x] 1.2 Señal: `SIGTERM` → espera → `SIGKILL`; si `posix_kill` falla (EPERM), escribir el flag de parada `storage/app/restream-daemons/<id>.stop` y seguir esperando
- [x] 1.3 Matar los ffmpeg huérfanos que sigan empujando al `destination_url/stream_key` de este destino
- [x] 1.4 No marcar el destino como `idle` si el daemon sigue vivo: devolver `stopped=false`, dejar `status=error` y `last_error` con el motivo
- [x] 1.5 Conservar la transición del broadcast de YouTube a `complete` y el registro del evento en `restream_target_events`

## 2. El daemon se puede detener sin señal

- [x] 2.1 `target_daemon.py` sondea `storage/app/restream-daemons/<target_id>.stop` en los loops de heartbeat y de stats
- [x] 2.2 Al encontrarlo: borrarlo, registrar en el log y disparar el apagado ordenado (mata el grupo de procesos del hijo ffmpeg, heartbeat `offline`, exit)
- [x] 2.3 `storage/app/restream-daemons/` en `www:www` y añadido al cron de `chown` de `storage/`

## 3. El panel dice la verdad

- [x] 3.1 `Client\RestreamTargetController::stop()`: responder 409 con el motivo cuando el daemon sigue vivo; marcar la ventana activa con `stopped_by_user` para que el motor no la reencienda
- [x] 3.2 `Admin\RestreamTargetController::stop()`: mismo contrato
- [x] 3.3 Verificar que las vistas de client y admin muestran el mensaje de error en rojo (`flashKind = r.ok ? 'ok' : 'err'`)

## 4. Reaper de daemons huérfanos

- [x] 4.1 `restream:reap-orphan-daemons` (nuevo): recorre `/proc/*/cmdline`, detiene los daemons cuyo destino no existe y adopta el PID real de los que sí existen
- [x] 4.2 Programarlo cada minuto en `routes/console.php`
- [x] 4.3 Verificar en el servidor que no quedan daemons de restream vivos ni ffmpeg empujando a YouTube/restream.io

## 5. Causa de fondo: el usuario del scheduler

- [x] 5.1 Mover el cron `artisan schedule:run` del usuario `root` al usuario `www` (mismo usuario que PHP-FPM) para que los daemons sean señalizables de forma nativa
- [x] 5.2 Verificar que el tick del scheduler corre como `www` y que `storage/app/restream-daemons/` es escribible por `www`

## 6. Pruebas

- [x] 6.1 `tests/Feature/Services/RestreamOrchestratorStopTest.php`: stop con `pipeline_pid` perdido, stop con el PID del hijo ffmpeg, stop sin daemon, y stop que deja ffmpeg huérfano
- [x] 6.2 Suite completa sin regresiones

## 7. Documentación

- [x] 7.1 AGENTS.md: documentar la semántica de `stop()`, el flag de parada, el reaper de huérfanos y el usuario del scheduler
- [ ] 7.2 Smoke test manual: iniciar un destino, pulsar "Detener" y comprobar que el destino queda en "idle" y que el log del daemon registra la parada

## Workflow follow-up

- Archivar el change cuando la implementación esté revisada y los tests en verde.
