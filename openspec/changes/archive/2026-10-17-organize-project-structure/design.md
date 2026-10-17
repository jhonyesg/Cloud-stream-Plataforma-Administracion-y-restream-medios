# Design

## Context

Ver `proposal.md` — Why. Los hechos que condicionan el enfoque:

- El docroot de nginx para `cloudstream.mediaserver.com.co` es la **raíz del repo**, no `public/` (vía `extension/cloudstream.mediaserver.com.co/laravel.conf`, que reescribe `/index.php` a `public/index.php`). Mover `public/` o los archivos de primer nivel rompería el sitio.
- `emisor_python/main.py` y `restream_daemon/main.py` son lanzados por `EmissionOrchestrator`/`RestreamOrchestrator` vía `proc_open` desde php-fpm. Rutas duras en `EmissionOrchestrator.php:288` y `RestreamOrchestrator.php:424`.
- Las units systemd (`emisor_python/systemd/`, `restream_daemon/systemd/`) **no están instaladas** en `/etc/systemd/system/`. Los daemons vienen de php-fpm.
- `emisor_python/main.py` usa `sys.path.insert(0, dirname(__file__))` y `restream_daemon/main.py` inyecta `emisor_python` en el path para reutilizar módulos. Ninguno de los archivos a eliminar está en ese path de import.
- El `.gitignore` actual tiene `/venv/` (anclado a la raíz) por lo que `emisor_python/venv/` quedó rastreado.

## Goals / Non-Goals

**Goals:**
- Que la primera capa del repo refleje solo sistemas vivos + configuración esperada de un proyecto Laravel.
- Eliminar basura sin tocar runtime.
- Corregir la documentación que hoy describe el motor de emisión como muerto.
- Dejar `scripts/` autoexplicado.

**Non-Goals:**
- Reorganizar `app/Services/` (coherencia interna Laravel) — fuera de alcance, es fase 2.
- Mover `resources/views/` o renombrar `landing.blade.php` en la raíz de views.
- Unificar los tres árboles de config de agente (`.claude/`, `.kilo/`, `.kilocode/`).
- Tocar `AGENTS.md` más allá de la sección de emisión.

## Decisions

### D1: `git rm` para archivos rastreados, borrado directo para los no rastreados

Los POCs `test_redplanet_*` están rastreados (`git ls-files` los lista) → `git rm`. `database/snapshots/_test_*` y `.env.bak_seo` no están rastreados (gitignored / untracked) → `rm -rf` directo. Evita entradas de borrado colgantes en el working tree.

**Alternativa considerada:** mover todo a `openspec/changes/archive/...`. Descartada — el usuario decidió que no aportan valor; archivarlos reproduce el problema del ruido.

### D2: Corregir la unit systemd en el mismo cambio, no eliminarla

`cloudstream-emission@.service` apunta a `emisor_python/venv/bin/python3`, que se elimina. Como la unit **no está instalada**, no hay urgencia operativa; pero dejarla rota es una bomba de tiempo. Se cambia `ExecStart` a `/usr/bin/python3` (el intérprete real que usan los procesos vivos, verificado con `ps`).

**Alternativa considerada:** borrar la unit. Descartada — es material de despliegue válido; el daemon de restream sí tiene una equivalente correcta.

### D3: `.gitignore` con patrón sin ancla

`/venv/` → `venv/` (sin ancla inicial) para cubrir cualquier venv anidado, más `*.bak`, `.env.bak*`. El `.phpunit.result.cache` ya está cubierto.

### D4: `AGENTS.md` — reescritura quirúrgica, no del archivo completo

Solo se reescribe la sección `## Emission Module — Status: DISMOUNTED (2026-07-20)`. El resto de `AGENTS.md` (protocolo de base de datos, restream, etc.) es correcto y se conserva. La sección pasa de "dismontado" a describir el motor activo.

**Alternativa considerada:** archivar el histórico de la decisión de desmontaje. Descartada — la decisión se revirtió de facto desde septiembre; lo correcto es documentar el estado actual, con una nota breve de que `gst_pipeline.py` fue el motor original.

### D5: `scripts/README.md` que documenta, no reubica

Los dos scripts (`reap_youtube_zombies.php`, `restream-smoke.php`) se quedan donde están. `reap_youtube_zombies.php` existe a propósito porque "el kernel completo de artisan OOMea en este entorno" (comentario en el propio archivo). Sólo se documenta su razón de ser.

## Risks / Trade-offs

- **[Borrar un archivo que un proceso vivo importa]** → Antes de cada `git rm` de Python, verificar con `grep` que no hay imports (`from X import`/`import X`) ni referencias en `sys.path`. Los 10 POCs, `gst_pipeline.py` y `test_ffmpeg_pipeline.py` ya fueron verificados: cero referencias no-simismo.
- **[El daemon muere al borrar el venv]** → Verificado: los procesos corren con `python3` del sistema (`/proc/<pid>/cmdline`). El venv es un stub de 5 archivos. Mitigación extra: no reiniciar daemons durante el cambio, y comprobar `ps` al final.
- **[El `.gitignore` corregido deje de rastrear algo]** → `venv/` sin ancla sólo afecta directorios llamados venv; `git status` tras el cambio lo confirma.
- **[La reescritura de AGENTS.md rompa convenciones de otros agentes]** → Sólo se cambia una sección autocontenida; el resto del documento intacto.
- **[docroot = raíz del repo]** → Ningún cambio mueve `public/` ni archivos de primer nivel. Este riesgo se evita por diseño.

## Migration Plan

1. `php artisan db:backup` (protocolo AGENTS.md) antes de tocar nada.
2. `git rm` de los archivos rastreados; `rm -rf` de los no rastreados.
3. Editar `.gitignore`, `emisor_python/systemd/cloudstream-emission@.service`, crear `scripts/README.md`.
4. Reescribir la sección de emisión de `AGENTS.md`.
5. Verificar: `ps aux | grep main.py` (daemons vivos), `git status` (venv/lib64 no reaparece), `php artisan schedule:list` (sin cambios).

**Rollback:** `git revert` del commit de limpieza restaura todo salvo los directorios gitignored (`database/snapshots/_test_*`, ya borrados — irrelevantes). El venv roto no se pierde (era inservible).

## Open Questions

Ninguna que bloquee la implementación.
