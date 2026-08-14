## 1. Baseline y contrato de datos

- [x] 1.1 Añadir pruebas de regresión que reproduzcan el estado actual: segmento posterior antes de la cuña y ambos con el mismo `start_sec`.
- [x] 1.2 Definir y validar una función común para calcular intervalos editoriales consecutivos a partir de `position`, `cue_in_sec`, `cue_out_sec` y duración efectiva.
- [x] 1.3 Añadir validación que rechace cues inválidos, segmentos de duración cero y solapamientos no explicados por fallback.

## 2. Inserción y orden de playlist

- [x] 2.1 Corregir `PlaylistCueInserter` para persistir la cuña antes del segmento posterior y renumerar posiciones de forma atómica.
- [x] 2.2 Corregir el cálculo de `start_sec` del segmento posterior para que empiece después de la duración completa de la cuña.
- [x] 2.3 Preservar y validar `cue_in_sec`/`cue_out_sec` del contenido dividido, incluyendo múltiples splits del mismo medio.
- [x] 2.4 Ajustar limpieza, eliminación y reordenamiento para no crear splits huérfanos ni reintroducir solapamientos.
- [x] 2.5 Añadir pruebas de servicio y API para insertar una cuña al inicio, en medio, al final y en una playlist con otra cuña.

## 3. Timeline diaria

- [x] 3.1 Corregir `TimelineBuilder` para materializar la secuencia exacta de la playlist y no descartar cuñas persistidas.
- [x] 3.2 Hacer que la timeline copie offsets, `effective_duration_sec`, `parent_content_id` y tipo de cada elemento.
- [x] 3.3 Garantizar que `starts_at_sec`/`ends_at_sec` sean consecutivos y que los items posteriores se desplacen según la política `shift`.
- [x] 3.4 Hacer transaccional la mutación y registrar snapshots before/after en auditoría.
- [x] 3.5 Añadir pruebas de reconstrucción idempotente, overflow de 24 horas y edición futura mientras otro item está al aire.

## 4. Consumidor de emisión

- [x] 4.1 Confirmar el pipeline efectivo de producción y retirar la divergencia entre el manager importado por el daemon y el manager especificado.
- [x] 4.2 Implementar reproducción de cada segmento usando `cue_in_sec`, `cue_out_sec` y `effective_duration_sec` como autoridad.
- [x] 4.3 Garantizar que el cambio entre contenido, cuña y continuación no reinicie la continuación desde el segundo cero.
- [x] 4.4 Implementar o corregir la transición de items manteniendo la salida RTMP según la capacidad real del pipeline elegido.
- [x] 4.5 Actualizar heartbeat y estado para reportar correctamente contenido, cuña, offset y `timeline_version`.
- [x] 4.6 Añadir pruebas de daemon para película dividida, cuña completa, reinicio durante el segundo segmento y archivo ausente.

## 5. Recarga y recuperación

- [x] 5.1 Conectar la inserción futura con la recarga versionada del daemon.
- [x] 5.2 Mantener congelado el item activo y aplicar la nueva timeline desde el siguiente límite seguro.
- [x] 5.3 Corregir recuperación por reloj de emisión para resolver correctamente una cuña o un segmento posterior.
- [x] 5.4 Añadir pruebas de recarga durante emisión y reinicio con `timeline_version` anterior y nueva.

## 6. Superficies admin y client

- [x] 6.1 Revisar en paralelo las vistas, modales, controladores y rutas admin/client relacionados con insertar cuñas y mostrar splits; ambas superficies ya consumen los endpoints corregidos.
- [x] 6.2 Verificar que el scope client solo permita operar sobre canales propios o asignados y que admin conserve acceso global.
- [x] 6.3 Smoke test admin: abrir el editor de programación/playlist, insertar una cuña futura y comprobar visualmente `contenido -> cuña -> continuación`.
- [x] 6.4 Smoke test client: repetir el mismo flujo con el scope del canal asignado y comprobar la misma secuencia.

## 7. Validación controlada en Cine Dios

- [x] 7.1 Identificar la playlist activa de Cine Dios, una cuña corta `kind=ad` y registrar la hora local y el estado inicial de emisión.
- [x] 7.2 Capturar un snapshot reversible de la playlist y confirmar que la cuña de prueba está lista y que el archivo existe.
- [x] 7.3 Con Cine Dios detenido o en una ventana segura, calcular una inserción aproximadamente un minuto después de la hora actual y verificar la timeline resultante.
- [x] 7.4 Iniciar la emisión y observar que se reproduce el contenido hasta el corte, luego la cuña completa y después la continuación desde el offset correcto.
- [x] 7.5 Detener y volver a iniciar la emisión para validar que la recuperación no repite la primera parte ni omite la cuña cuando corresponda.
- [x] 7.6 Revisar logs, heartbeat, item actual, modo y `timeline_version`; registrar resultado y cualquier corte RTMP.
- [x] 7.7 Retirar la cuña de prueba o restaurar la playlist/timeline de Cine Dios y verificar que el canal queda en el estado operativo esperado.

## 8. Cierre y documentación

- [x] 8.1 Ejecutar la suite de pruebas relevante y validar que no quedan solapamientos en playlists/timelines existentes de prueba.
- [x] 8.2 Actualizar documentación operativa con el procedimiento de inserción, recarga, detención, reinicio y rollback de una cuña.
- [x] 8.3 Revisar el cambio completo contra las especificaciones y confirmar que todos los escenarios de `cue-interruption-playout` y `emission-timeline-consumption` están cubiertos.
