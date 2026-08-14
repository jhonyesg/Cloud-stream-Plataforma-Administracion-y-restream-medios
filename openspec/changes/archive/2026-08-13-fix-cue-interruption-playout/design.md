## Context

La playlist es la representación editorial que el usuario ordena, mientras que `program_timeline_items` es la hoja de ruta materializada para un día y el daemon es el consumidor de emisión. Actualmente esos niveles pueden divergir: el split puede quedar antes de la cuña, el builder ignora parte de los horarios explícitos y el daemon activo usa un pipeline FFmpeg que no aplica correctamente `cue_in_sec`/`cue_out_sec`.

El comportamiento requerido es una interrupción con reanudación: el contenido se reproduce hasta el punto de corte, se emite la cuña completa y se continúa el mismo archivo desde el offset original. La programación futura debe poder cambiarse sin alterar el elemento que ya está al aire.

## Goals / Non-Goals

**Goals:**

- Mantener una única secuencia editorial canónica: contenido, cuña, contenido.
- Generar una timeline diaria con intervalos consecutivos y sin solapamientos.
- Conservar offsets de fuente para cada segmento de contenido.
- Hacer que el daemon respete la duración efectiva de contenidos y cuñas.
- Recargar cambios futuros mediante `timeline_version` sin perder el punto actual.
- Validar el flujo de forma automatizada y con una prueba operativa controlada en Cine Dios.
- Mantener la funcionalidad simétrica en admin y client cuando esté expuesta en UI.

**Non-Goals:**

- No rediseñar el scheduler completo ni cambiar la semántica de `shift`.
- No permitir edición de elementos pasados o del elemento actualmente al aire.
- No introducir una nueva base de datos ni una nueva entidad de publicidad.
- No ejecutar una prueba destructiva o dejar una cuña de prueba permanente en Cine Dios.

## Decisions

### 1. La playlist es la fuente editorial de verdad

La inserción de una cuña debe persistir en `playlist_items` como tres elementos ordenados. La segunda parte se crea después de la cuña, no antes. `TimelineBuilder` materializa esa secuencia sin volver a inferir un orden incompatible desde `start_sec`.

Alternativa descartada: guardar la cuña únicamente en `program_timeline_items`. Esa opción se pierde cuando se reconstruye la timeline desde la playlist.

### 2. Cada split conserva el mismo media y sus offsets

El primer segmento conserva `cue_in_sec` y recibe `cue_out_sec` igual al punto de corte de fuente. El segmento posterior recibe `cue_in_sec` igual al punto de corte y conserva el `cue_out_sec` original si existía. La cuña queda entre ambos y sus horarios se calculan de forma secuencial.

Alternativa descartada: representar el split solo con `parent_content_id` en la timeline, porque la playlist debe conservar la intención editorial para futuras reconstrucciones.

### 3. La timeline se valida como intervalos consecutivos

Después de insertar, eliminar o reconstruir, se validará que cada fila posterior empiece exactamente donde termina la anterior, salvo gaps explícitamente representados como fallback. Ninguna cuña podrá compartir intervalo con un segmento de contenido.

### 4. El consumidor de emisión debe aplicar offsets y límites

El pipeline efectivo utilizado por el daemon debe iniciar el archivo en `cue_in_sec` y limitarlo a la duración efectiva del item. El avance al siguiente item debe suceder al finalizar ese segmento, no al finalizar necesariamente el archivo físico completo.

Alternativa descartada: confiar solo en un temporizador externo mientras FFmpeg reproduce el archivo completo, porque puede duplicar el contenido o emitirlo fuera de orden.

### 5. Las mutaciones futuras se versionan y se recargan

Una inserción futura incrementa `timeline_version`, registra auditoría before/after y notifica al daemon. El daemon conserva el item activo y su posición, compara la versión y aplica la nueva secuencia desde el siguiente punto seguro.

### 6. La prueba Cine Dios será reversible

Antes de probar se capturará la playlist relevante y se elegirá una cuña corta y conocida. Se insertará aproximadamente un minuto después de la hora de prueba, se observará el orden en preview y emisión, se detendrá y reiniciará el canal, y finalmente se retirará la cuña de prueba o se restaurará la playlist si el resultado no es válido.

## Risks / Trade-offs

- **[Cambios heredados inconsistentes]** → Añadir una operación de normalización que detecte splits y cuñas solapados antes de emitir; no corregir silenciosamente datos ambiguos.
- **[Recarga mientras se emite]** → Congelar el item activo y aplicar la nueva versión únicamente desde el próximo límite seguro.
- **[Corte RTMP al cambiar de proceso]** → Preferir una transición dentro de un pipeline persistente; si la implementación actual no lo permite, marcar explícitamente el riesgo y validarlo en Cine Dios.
- **[Cuña que excede las 24 horas]** → Mantener `overflow_sec`, mostrarlo en UI y desplazar los items posteriores sin truncar la cuña.
- **[Admin/client divergentes]** → Incluir tareas de smoke test para ambas superficies y aplicar las mismas reglas, variando solo el scope de canales.

## Migration Plan

1. Añadir pruebas de regresión sobre el estado actual y casos de split.
2. Corregir inserción, orden y materialización de timeline.
3. Corregir el consumidor de emisión y la recarga por versión.
4. Ejecutar validaciones unitarias, de API y de daemon.
5. Ejecutar prueba controlada en Cine Dios con cuña reversible.
6. Si falla la prueba, detener la emisión, restaurar playlist/timeline y revisar logs sin borrar datos históricos.

## Open Questions

- Confirmar si el pipeline persistente final será GStreamer o una implementación FFmpeg equivalente con límites y transición verificables.
- Confirmar qué endpoint de control se usará para solicitar reload al daemon después de una inserción futura.
- Confirmar el procedimiento operativo exacto para restaurar la playlist de Cine Dios después de la prueba.
