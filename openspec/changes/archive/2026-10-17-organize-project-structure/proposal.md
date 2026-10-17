# Proposal

## Why

La raíz del proyecto acumula archivos que no pertenecen a ningún sistema vivo: 10 POCs `test_redplanet_*.py` de la era GStreamer (rutas `/mnt/multimedia/...` hardcodeadas, cero referencias), un endpoint de debug expuesto (`public/api-test.php`), un venv roto (5 archivos rastreados por error), un test Python dentro del árbol PHPUnit (`tests/Unit/Emission/test_ffmpeg_pipeline.py`), placeholders de Laravel y basura de test en `database/snapshots/_test_*`. Este ruido ensucia cualquier análisis automatizado (IA o humano nuevo) y hace que el repositorio mienta sobre su propia forma.

El caso más grave no es basura de archivos sino documentación: `AGENTS.md` declara el módulo de emisión como **"DISMOUNTED — removed on 2026-07-20, do not look for it"**, cuando `emisor_python/` es el motor de emisión **activo en producción** (3 daemons emitiendo a RTMP ahora mismo, verificados el 2026-10-17). Una IA que lea ese documento concluiría que puede borrar el motor y tumbaría producción. Corregir esa mentira es tan urgente como limpiar los archivos.

## What Changes

- **Eliminar POCs muertos** `emisor_python/test_redplanet_*.py` (10 archivos, ~85KB): pruebas exploratorias GStreamer sin referencias, superadas por `ffmpeg_pipeline.py`.
- **Eliminar huérfano** `emisor_python/pipeline/gst_pipeline.py`: nadie lo importa (solo se autorreferencia); el pipeline vivo es `ffmpeg_pipeline.py`.
- **Eliminar endpoint de debug expuesto** `public/api-test.php`: refleja `$_SERVER` (URI, script, remote_addr) públicamente. Riesgo de información.
- **Eliminar test mal ubicado** `tests/Unit/Emission/test_ffmpeg_pipeline.py`: Python que PHPUnit ignora, nunca corre.
- **Eliminar placeholders** `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`.
- **Eliminar basura de test** `database/snapshots/_test_{capture,list,roundtrip,tx}/` (ya gitignored).
- **Eliminar stub de venv roto** `emisor_python/venv/` (28K, solo symlinks a `/usr/bin/python3` + `pyvenv.cfg`). Los daemons corren con Python del sistema, no con este venv.
- **Corregir la unit systemd** `emisor_python/systemd/cloudstream-emission@.service`: hoy su `ExecStart` apunta a `emisor_python/venv/bin/python3`, que desaparecerá. Cambiar a `/usr/bin/python3` (el intérprete real de los daemons vivos). No está instalada, así que no rompe nada hoy, pero dejaría una bomba de tiempo.
- **Arreglar `.gitignore`**: el patrón `/venv/` con ancla raíz no cubría `emisor_python/venv/`; añadir `venv/` sin ancla y una regla para `.env.bak*`.
- **Documentar** `scripts/`: nuevo `scripts/README.md` que explica qué es cada script standalone, por qué existe (p. ej. `reap_youtube_zombies.php` existe porque artisan OOMea en este entorno) y cuándo usarlo.
- **Corregir `AGENTS.md`**: reescribir la sección "Emission Module — Status: DISMOUNTED" para reflejar que el motor está **ACTIVO** (emisor_python/ + EmissionOrchestrator + ffmpeg_pipeline), y anotar que `gst_pipeline.py` fue el motor original ya superado. Este es el cambio más importante del alcance.

**BREAKING:** Ninguno en runtime. No se toca ningún archivo que un proceso vivo lea o ejecute (`emisor_python/{main,daemon,api,models,pipeline,utils}`, `restream_daemon/`, `app/`, `public/index.php` y estáticos, `routes/`). Los daemons en producción no se ven afectados. La única arista es la unit systemd (no instalada), que se corrige en el mismo cambio.

## Capabilities

### New Capabilities

Ninguna. Este cambio no introduce ni modifica comportamiento del sistema — es limpieza de archivos, `.gitignore`, una unit systemd no instalada y documentación. Es un refactor puro.

### Modified Capabilities

Ninguna. `AGENTS.md` es documentación operativa, no una spec de comportamiento del sistema; los specs existentes (`emission-daemon`, `emission-pipeline`, etc.) describen comportamiento que **no cambia** con este trabajo.

> `skip_specs: true` se declara en `.openspec.yaml` para reflejar que no hay delta de comportamiento.

## Impact

- **Archivos eliminados**: 10 POCs Python, 1 pipeline huérfano, 1 endpoint PHP público, 1 test Python mal ubicado, 2 placeholders PHPUnit, 4 directorios de snapshot de test, 1 venv roto.
- **Archivos modificados**: `.gitignore`, `AGENTS.md`, `emisor_python/systemd/cloudstream-emission@.service`.
- **Archivos creados**: `scripts/README.md`.
- **Runtime**: cero impacto. Los daemons `emisor_python/main.py` (3 canales) y `restream_daemon/main.py` (N targets) siguen corriendo sin interrupción.
- **Riesgo**: bajo. Las únicas verificaciones necesarias son que los daemons siguen vivos tras el `git rm` y que el `.gitignore` corregido no deje de rastrear nada relevante.
