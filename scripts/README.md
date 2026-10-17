# `scripts/` — Herramientas operativas standalone

Estos scripts no forman parte del runtime de Laravel ni de los daemons de
emisión/restream. Son herramientas de operador que se ejecutan a mano y, en
algunos casos, existen precisamente **porque** el kernel completo de Artisan
no es viable en este entorno (OOM).

| Script | Qué es | Cuándo usarlo |
| --- | --- | --- |
| `reap_youtube_zombies.php` | Reaper standalone de broadcasts zombie de YouTube. Recorre los `restream_targets` con `platform_broadcast_id` y sin `pipeline_pid`, consulta el `lifeCycleStatus` en YouTube y, si sigue `live`/`liveStarting`/`testing`, transiciona a `complete` o limpia el binding. Escribe `restream_target_events` con `via='standalone_reap'`. | Cuando `php artisan restream:reap-youtube-zombies` no puede correr porque el kernel de Artisan agota memoria (razón documentada en la cabecera del propio script). Soporta `--dry-run`. |
| `restream-smoke.php` | Smoke test del módulo Restream: crea quota → target → toggle `enabled` → verifica tope de slots → rechazo de downgrade → deshabilita quota → cuenta `audit_logs`. Limpia sus propios datos al final. | Verificación manual tras un cambio en `RestreamQuotaGuard` o en el CRUD de targets. Requiere seed data (admin, cliente titular y al menos un canal). **No** está pensado para CI. |

## Notas

- Ambos scripts hacen `require __DIR__ . '/../bootstrap/app.php'` para arrancar
  un kernel mínimo — funcionan desde la raíz del proyecto.
- `restream-smoke.php` crea y borra filas en `restream_quotas` y
  `restream_targets`. No ejecutarlo contra datos de producción que importen
  (usa un cliente de prueba).
- El reaper estándar y soportado es el comando Artisan
  `restream:reap-youtube-zombies`, que corre cada minuto vía
  `routes/console.php`. Este script es la vía de escape cuando el kernel
  OOMea.
- Antes de correr cualquiera de los dos, `php artisan db:backup` (protocolo
  AGENTS.md).
