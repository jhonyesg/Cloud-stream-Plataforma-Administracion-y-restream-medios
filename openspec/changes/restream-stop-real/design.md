# Design

## Por qué fallaba el `stop()`

| Escenario | Qué pasaba |
|---|---|
| `pipeline_pid` NULL | `stop()` hacía `return` después de marcar `idle`. Cero señales, cero espera. Como el stop también vacía `pipeline_pid`, **el segundo clic ya no hacía nada**. |
| Daemon de otro usuario | `posix_kill()` → EPERM (www no puede señalizar root). El chequeo `posix_kill($pid, 0)` devolvía `false` → el código lo leía como "el proceso ya no existe" → se saltaba el SIGKILL → logueaba `stopped` y respondía "Destino detenido.". |
| `pipeline_pid` del hijo ffmpeg | Un heartbeat viejo guardó el PID del hijo; el stop mataba al ffmpeg y **el daemon lo respawnaba** (`[DAEMON] FFmpeg child exited (state=stopped). Restarting...`). |

## Resolución del PID del daemon

`pipeline_pid` no es una fuente fiable (se vacía, o guarda el hijo). La
fuente fiable es **la línea de órdenes**: todo daemon se lanza como

```
python3 /ruta/restream_daemon/main.py --target-id=<uuid>
```

`RestreamOrchestrator::start()` ya usaba `/proc/<pid>/cmdline` para evitar
falsos positivos de reutilización de PID; el stop usa el mismo patrón, pero
**barriendo `/proc/*/cmdline`** cuando el PID guardado no encaja. El
fragmento `restream_daemon/main.py --target-id=<uuid>` identifica al daemon
de forma unívoca.

## Comprobación de vida sin permisos

`posix_kill($pid, 0)` devuelve `false` tanto si el proceso no existe (ESRCH)
como si no tenemos permiso (EPERM). La comprobación correcta es
`/proc/<pid>/stat`: existe para cualquier proceso, sea nuestro o no, y el
campo de estado distingue `Z` (zombie: ya no ejecuta nada, solo espera a ser
recolectado) de los estados vivos.

## Detener sin poder señalar: el flag de parada

Cuando el daemon pertenece a otro usuario, la señal no llega. Se escribe un
archivo `storage/app/restream-daemons/<target_id>.stop` que el daemon sondea
en su loop de heartbeat (cada 5 s). Al encontrarlo, lo borra y dispara su
apagado ordenado: mata al hijo ffmpeg (por grupo de procesos), avisa el
heartbeat `offline` (que limpia `pipeline_pid` y pone el destino en `idle`) y
sale.

El directorio debe ser escribible por el panel: por eso `storage/app/restream-daemons/`
pasa a `www:www` y se añade al cron de `chown` que ya normaliza `storage/`.

## El scheduler como root, causa de fondo

El cron raíz lanzaba `artisan schedule:run` como root. Los daemons que
arranca el scheduler heredan ese usuario, y el panel (PHP-FPM) corre como
`www` → `posix_kill` siempre EPERM. Mover el cron a `www` alinea los
usuarios: los daemons quedan señalizables de forma nativa y el flag de
parada queda como red de seguridad (daemons que ya estaban como root).

## Verificación: no basta con "dejó de empujar"

El `stop()` termina y **comprueba** que el daemon murió. Si sigue vivo
(signal y SIGKILL rechazados), devuelve `stopped=false` con un motivo
legible, el destino queda en `error` con el `last_error` puesto y el
controller responde **409** en lugar de un "Destino detenido." que sería
falso. Además se matan los **ffmpeg huérfanos** que sigan empujando al
destino de este destino: se localizan por la cadena exacta
`destination_url/stream_key` que el daemon pasa a ffmpeg como un solo
argumento.

## Daemons huérfanos (emisiones invisibles)

Un destino borrado desde el panel dejaba su daemon emitiendo sin que nadie
lo viera. `restream:reap-orphan-daemons` (cada minuto) recorre
`/proc/*/cmdline`, y para cada daemon cuyo `target_id` no existe en
`restream_targets` lo detiene (señal o flag). Para los daemons vivos cuyo
destino **sí** existe pero con el PID perdido, adopta el PID real en la fila
para que el botón "Detener" los alcance.
