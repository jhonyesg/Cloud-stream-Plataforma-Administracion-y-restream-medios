# Proposal

## Why

El botón **"Detener"** del panel de Restream no detenía la emisión. El
operador lo pulsaba una y otra vez y el destino seguía "En vivo" en YouTube,
con el daemon empujando vídeo y acumulando penalización por emisión
prolongada sin audiencia.

Había **dos fallos encadenados** en `App\Services\Restream\RestreamOrchestrator::stop()`:

1. **`pipeline_pid` en NULL → no pasaba nada.** El método retornaba
   enseguida y solo marcaba la fila como `idle`. Como el propio stop vacía
   `pipeline_pid`, **el segundo intento ya no hacía absolutamente nada**.
   El daemon seguía vivo, su heartbeat devolvía el destino a `live` y la
   emisión continuaba.
2. **El daemon corría como root y el panel como `www`.** El `posix_kill()`
   de PHP fallaba con **EPERM** (un usuario no puede señalar procesos de
   otro), y el chequeo de vida `posix_kill($pid, 0)` devolvía `false` con
   EPERM — indistinguible de "el proceso ya no existe". El orquestador
   concluía "detenido", escribía `stopped` en el log y respondía
   **"Destino detenido." sin haber parado nada**.

El agravante: el daemon huérfano (destino borrado desde el panel) seguía
emitiendo **invisible**: nadie lo veía en la UI y nadie lo podía detener.

## What Changes

- **`RestreamOrchestrator::stop()` deja de ser un no-op y deja de mentir.**
  - Resuelve el PID del daemon **escaneando `/proc`** (el `pipeline_pid`
    guardado puede estar en NULL o apuntar al hijo ffmpeg de un heartbeat
    viejo), no confiando solo en la columna.
  - Comprueba la vida con `/proc/<pid>/stat` (distingue zombie de proceso
    vivo y no depende de los permisos del usuario).
  - Si la señal es rechazada (EPERM) **escribe un flag de parada** que el
    daemon sondea cada 5 s → el daemon se detiene solo aunque pertenezca a
    otro usuario.
  - Mata los **ffmpeg huérfanos** que sigan empujando al destino de este
    destino (daemon muerto, hijo vivo).
  - **Verifica** que el proceso murió. Solo entonces marca el destino como
    `idle`; si sigue vivo devuelve `stopped=false` con el motivo.
  - Devuelve el resultado (`stopped`, `pid`, `method`, `reason`) en vez de `void`.
- **El daemon aprende a detenerse sin señal**: `target_daemon.py` sondea
  `storage/app/restream-daemons/<target_id>.stop` y se apaga de forma
  ordenada (mata al hijo ffmpeg, avisa el heartbeat `offline`, borra el
  flag).
- **Los controllers (client y admin) dejan de responder "Destino detenido."
  cuando no detuvieron nada**: devuelven **409** con el motivo, y el panel
  lo muestra en rojo.
- **Nuevo comando `restream:reap-orphan-daemons`** (cada minuto): encuentra
  los daemons cuyo destino ya no existe en la base y los detiene. Además
  adopta el PID real de los daemons vivos cuyo `pipeline_pid` se perdió, para
  que "Detener" los alcance.
- **El scheduler pasa a correr como `www`**: el cron raíz lanzaba
  `artisan schedule:run` como root, y por eso los daemons que arrancaba el
 scheduler quedaban como root (insignificables para el panel). Ahora el
  scheduler corre con el mismo usuario que el panel y las señales funcionan
  de forma nativa.

**Non-goals**: no se cambia el motor de ventanas (`restream_target_schedules`); no se toca la lógica de transición a `complete` del broadcast de YouTube; no se cambia el contrato de la UI más allá de mostrar el error real.

## Capabilities

### New Capabilities
- `restream-stop`: el botón "Detener" detiene de verdad la emisión, el daemon puede detenerse sin señal, y los daemons huérfanos se reapean.

## Impact

- **Servicios**: `app/Services/Restream/RestreamOrchestrator.php` (`stop()` reescrito + helpers de /proc, señal, flag y huérfanos).
- **Daemon**: `restream_daemon/daemon/target_daemon.py` (sondeo del flag de parada).
- **Controllers**: `app/Http/Controllers/Client/RestreamTargetController.php` y `app/Http/Controllers/Admin/RestreamTargetController.php` (409 con el motivo, `stopped_by_user` en la ventana activa).
- **Comando**: `app/Console/Commands/ReapOrphanRestreamDaemonsCommand.php` (nuevo) + `routes/console.php` (cada minuto).
- **Operación**: el cron `artisan schedule:run` pasa del usuario `root` al usuario `www`; `storage/app/restream-daemons/` pasa a `www:www` (el flag de parada lo escribe el panel).
- **Tests**: `tests/Feature/Services/RestreamOrchestratorStopTest.php` (4 tests).
- Sin cambios de esquema ni de migraciones.
