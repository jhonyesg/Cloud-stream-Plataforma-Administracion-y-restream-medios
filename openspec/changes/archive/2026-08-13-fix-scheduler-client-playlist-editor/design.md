## Context

El panel de Programación tiene dos vistas: `admin/scheduler/index.blade.php` (todos los canales) y `client/scheduler/index.blade.php` (solo canales asignados al usuario). Ambas incluyen el mismo componente `playlist-editor-fullscreen-modal` y su Alpine.js store (`playlist-editor-modal-js.blade.php`).

El bug descubierto: cuando el **cliente** abre el editor de playlist fullscreen, la función `openEditor` en su `playlistsList` no calcula la `duration_sec` efectiva de cada item (respetando `cue_in_sec` / `cue_out_sec` que definen los cortes de split). Tampoco inicializa `_scheduleStart` con el cursor de schedule que honra los `start_sec` explícitos. En cambio, el **admin** sí realiza ambos cálculos correctamente.

Esto provoca que, tras insertar una cuña que parte un contenido, el tail del split y los items siguientes aparezcan desfasados en el timeline visual, con huecos enormes o superposiciones. La BD está correcta (`PlaylistCueInserter` y `PlaylistController` funcionan bien); es puramente un error de inicialización de datos en el frontend del cliente.

## Goals / Non-Goals

**Goals:**
- Garantizar que el playlist editor fullscreen calcule `duration_sec` efectiva e inicialice `_scheduleStart` idénticamente en admin y cliente.
- Mantener la única diferencia legítima: filtro de canales (cliente solo ve asignados).
- Prevenir futuras divergencias entre ambas vistas del scheduler.

**Non-Goals:**
- No se modifica la lógica de backend (`PlaylistCueInserter`, `TimelineMutator`, `PlaylistController`).
- No se cambia la apariencia visual del editor ni se agregan nuevas funcionalidades.
- No se modifica el comportamiento del admin (ya es correcto).

## Decisions

### 1. Sincronizar código cliente con admin (no al revés)
**Rationale:** El admin tiene la lógica correcta y completa. Copiarla al cliente es el cambio mínimo y seguro. El admin calcula `effective` duration y `_scheduleStart` de forma explícita al abrir el editor.

### 2. No extraer a helper JS compartido en este change
**Rationale:** Aunque ideal, un helper compartido requeriría reorganizar ambas vistas y probar regresiones. El fix inmediato es la sincronización directa. Se deja como deuda técnica documentada para un refactor posterior.

### 3. Mantener `loadPlaylist()` del componente sin cambios
**Rationale:** `loadPlaylist()` en `playlist-editor-modal-js.blade.php` ya hace el cálculo de `_scheduleStart` correctamente. El problema es que el cliente no lo invoca al abrir; en su lugar monta los items directamente. Tras sincronizar `openEditor`, `loadPlaylist()` se invocará o el cálculo se hará inline igual que en admin.

## Risks / Trade-offs

- **[Risk] Código duplicado persiste** → Si en el futuro se modifica el admin sin tocar el cliente, la divergencia reaparece. **Mitigación:** Agregar comentario explícito en ambas vistas indicando que la lógica debe mantenerse sincronizada.
- **[Risk] Regresión en vista cliente** → Al copiar la lógica más compleja del admin, podría romperse algo que antes "funcionaba" (aunque mal). **Mitigación:** Probar inserción de cuña con split, drag-and-drop, y guardado en ambas vistas.

## Migration Plan

No aplica migración de datos ni cambios de API. El fix es puramente frontend. Despliegue:
1. Aplicar cambio en `client/scheduler/index.blade.php`.
2. Limpiar caché de vistas: `php artisan view:clear`.
3. Probar en ambos roles (admin + cliente) con el mismo canal: abrir playlist, insertar cuña con split, verificar timeline y preview.

## Open Questions

- (none)
