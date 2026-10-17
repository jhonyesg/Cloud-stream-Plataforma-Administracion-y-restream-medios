# Tasks

## 1. Backup y verificación previa

- [x] 1.1 `php artisan db:backup` (protocolo AGENTS.md) — verificar que se crea un dump en `storage/app/backups/`.
- [x] 1.2 Confirmar el estado vivo antes de tocar nada: `ps aux | grep -E 'emisor_python/main.py|restream_daemon/main.py' | grep -v grep` debe listar los 3 daemons de emisión + N de restream.

## 2. Eliminar POCs e imports muertos de `emisor_python/`

- [x] 2.1 `git rm` de los 10 POCs: `emisor_python/test_redplanet_4min.py`, `test_redplanet_copy.py`, `test_redplanet_copy_v2.py`, `test_redplanet_gst.py`, `test_redplanet_gst_v2.py`, `test_redplanet_gst_v3.py`, `test_redplanet_gst_v4.py`, `test_redplanet_gst_v5.py`, `test_redplanet_gst_v6.py`, `test_redplanet_production.py`. Verificar: `git status` los muestra borrados y `ls emisor_python/test_redplanet_*` no existe.
- [x] 2.2 `git rm emisor_python/pipeline/gst_pipeline.py` (huérfano). Verificar: `grep -rn "gst_pipeline\|GstPipeline" --include='*.py' emisor_python/ restream_daemon/ | grep -v __pycache__` no devuelve nada.
- [x] 2.3 Eliminar el venv roto no rastreado: `rm -rf emisor_python/venv/`. Verificar: `ls emisor_python/venv` no existe.

## 3. Eliminar artefactos de test y debug

- [x] 3.1 `git rm public/api-test.php`. Verificar: `ls public/api-test.php` no existe; `curl -sI https://cloudstream.mediaserver.com.co/api-test.php` ya no responde 200 con JSON.
- [x] 3.2 `git rm tests/Unit/Emission/test_ffmpeg_pipeline.py` y eliminar el directorio vacío `tests/Unit/Emission/`. Verificar: `ls tests/Unit/` no contiene `Emission/`.
- [x] 3.3 `git rm tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php`. Verificar: `php artisan test --filter=ExampleTest` reporta "No tests found" (o equivalente) sin error.
- [x] 3.4 `rm -rf database/snapshots/_test_capture/_test_list/_test_roundtrip/_test_tx` (no rastreados). Verificar: `ls database/snapshots/` no lista ningún directorio `_test_*`.

## 4. Arreglar `.gitignore` y la unit systemd

- [x] 4.1 En `.gitignore`, cambiar `/venv/` y `.venv/` por patrones sin ancla (`venv/`) y añadir `*.bak` + `.env.bak*`. Verificar: `git check-ignore emisor_python/venv .env.bak_seo` los marca como ignorados.
- [x] 4.2 Editar `emisor_python/systemd/cloudstream-emission@.service`: cambiar `ExecStart` de `.../emisor_python/venv/bin/python3 ...` a `/usr/bin/python3 ...`. Verificar: `grep ExecStart emisor_python/systemd/cloudstream-emission@.service` no menciona `venv`.
- [x] 4.3 Confirmar que `git status` no muestra `emisor_python/venv/lib64` ni otros residuos del venv como untracked.

## 5. Documentar `scripts/`

- [x] 5.1 Crear `scripts/README.md` explicando qué es cada script (`reap_youtube_zombies.php` — standalone porque artisan OOMea en este entorno; `restream-smoke.php` — dev tool de humo) y cuándo usarlo. Verificar: el archivo existe y menciona ambos scripts por nombre.
- [x] 5.2 Verificar que ningún comando del README quedó desactualizado: `php -l scripts/restream-smoke.php` y `php -l scripts/reap_youtube_zombies.php` no reportan errores de sintaxis.

## 6. Corregir `AGENTS.md` (sección Emission)

- [x] 6.1 Reescribir la sección `## Emission Module — Status: DISMOUNTED (2026-07-20)` para reflejar el motor **ACTIVO**: `emisor_python/` como daemon en producción lanzado por `EmissionOrchestrator`, `ffmpeg_pipeline.py` como pipeline vivo, y una nota de que `gst_pipeline.py` (eliminado) fue el motor original superado. Verificar: `grep -n "DISMOUNTED\|Do not look for it" AGENTS.md` no devuelve nada, y `grep -n "emisor_python" AGENTS.md` sigue mostrando la referencia viva.

## 7. Verificación de integración

- [x] 7.1 Daemons intactos: `ps aux | grep -E 'emisor_python/main.py|restream_daemon/main.py' | grep -v grep` lista los mismos procesos que en la tarea 1.2 (o más nuevos, si se reiniciaron solos), y `tail storage/logs/emission-*.log` sigue escribiendo `[STATS] ... state=playing`.
- [x] 7.2 Laravel sano: `php artisan view:cache && php artisan view:clear` sin errores; `php artisan schedule:list` lista los comandos esperados sin cambios.
- [x] 7.3 `git status --short` limpio de residuos: no aparece `emisor_python/venv/`, ni `_test_*`, ni `.env.bak_seo`, ni `public/api-test.php`.
- [x] 7.4 Suite PHPUnit sigue verde: `php artisan test` pasa con el mismo conjunto (o subconjunto) de tests que antes, sin fallos nuevos por archivos ausentes.

## Workflow follow-up

- Archivar el cambio cuando el usuario valide el resultado en el repo.
- Confirmar tras el archivo que `openspec list` ya no muestra `organize-project-structure` como in-progress.
