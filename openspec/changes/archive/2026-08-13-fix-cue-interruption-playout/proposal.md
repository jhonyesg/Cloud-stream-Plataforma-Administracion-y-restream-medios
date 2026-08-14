## Why

Las cuñas insertadas dentro de una película no están produciendo una hoja de ruta de emisión coherente. La playlist puede mostrar el split y la publicidad con el mismo horario, mientras que el motor real puede reproducir la segunda parte antes de la cuña o reiniciar el archivo desde el principio. Esto debe corregirse antes de usar Cine Dios como validación operativa, porque la publicidad es parte esencial de la programación diaria.

## What Changes

- Hacer que una cuña insertada en medio de un contenido produzca el orden `contenido anterior -> cuña -> contenido posterior`.
- Mantener intervalos consecutivos y sin solapamiento en la playlist y en `program_timeline_items`.
- Preservar `cue_in_sec` y `cue_out_sec` para que el segundo segmento reanude el mismo archivo desde el punto exacto de corte.
- Unificar la construcción de la timeline diaria con la representación que consume el motor de emisión.
- Hacer que el motor respete la duración efectiva de cada segmento y de cada cuña.
- Recargar de forma segura una nueva versión de timeline cuando una cuña futura sea insertada o modificada.
- Evitar que el arranque o reinicio de la emisión descarte cuñas válidas o reconstruya un orden incompatible.
- Añadir pruebas automatizadas y una validación operativa controlada con el canal Cine Dios.
- Verificar el flujo en las superficies admin y client cuando la funcionalidad sea visible para ambos roles.

## Capabilities

### New Capabilities

- `cue-interruption-playout`: Inserción, segmentación, ordenamiento y reproducción de cuñas dentro de contenidos.
- `emission-timeline-consumption`: Consumo de la timeline diaria por el motor de emisión, incluyendo splits, offsets, duración y recarga de versiones.

### Modified Capabilities

- `playlists`: La playlist debe ordenar y representar una cuña entre los dos segmentos del contenido interrumpido.
- `programming-timeline`: La timeline diaria debe generar intervalos consecutivos, sin solapamientos, y conservar los offsets de reanudación.

## Impact

- `app/Services/PlaylistCueInserter.php` y modelos de playlist.
- `app/Services/TimelineBuilder.php`, `TimelineMutator.php` y `ProgramTimelineItem`.
- Controladores y rutas de playlist y timeline.
- `app/Services/EmissionOrchestrator.php` y estado de emisión.
- `emisor_python/daemon/` y `emisor_python/pipeline/`.
- Vistas del editor de playlist y scheduler en admin y client, si requieren ajustes visuales o de interacción.
- Pruebas de servicios, API, timeline y emisión.
- Validación manual en Cine Dios: identificar la hora actual, insertar una cuña aproximadamente un minuto después, observar la emisión, detenerla y reiniciarla para comprobar la continuidad.
