## Why

El módulo de Programación en el panel de clientes no calcula correctamente las duraciones efectivas (`effective_duration`) ni los horarios (`_scheduleStart`) al abrir el editor de playlist fullscreen. Esto provoca que, tras insertar una cuña con split, el tail del contenido partido y los items posteriores aparezcan desplazados, con huecos enormes o superpuestos en el timeline visual. El panel de administrador sí realiza estos cálculos correctamente. La única diferencia legítima entre admin y cliente es el filtro de canales (admin ve todos, cliente solo los asignados); el resto del comportamiento del scheduler debe ser idéntico.

## What Changes

- **Sincronizar** la lógica de `openEditor` en `client/scheduler/index.blade.php` con la versión de `admin/scheduler/index.blade.php` para que:
  - Se compute la `duration_sec` efectiva respetando `cue_in_sec` / `cue_out_sec` (items partidos por splits).
  - Se inicialice `_scheduleStart` mediante un cursor que honre los `start_sec` explícitos de la BD.
  - Se reordene el array final por `_scheduleStart` para mantener coherencia visual con la base de datos.
- **Extraer** (opcional, recomendado) la lógica de mapeo/schedule a un helper JS compartido para prevenir futuras divergencias entre admin y cliente.
- **Validar** que el playlist editor fullscreen, el timeline diario, el preview player y la inserción de cuñas funcionan idénticamente en ambos paneles tras el ajuste.

## Capabilities

### New Capabilities
<!-- No new capabilities introduced; this is a parity fix -->
- (none)

### Modified Capabilities
- `playlist-editor`: Actualizar el requerimiento de que el cliente y el admin deben cargar los items del playlist con la misma semántica de duración efectiva y cursor de schedule. El spec actual solo describe la funcionalidad del editor; se añade explícito que la inicialización de datos debe ser idéntica en ambos roles.
- `scheduler`: Reflejar que la vista de programación mensual y el editor de playlist deben comportarse exactamente igual para admin y cliente, salvo el filtro de canales visible.

## Impact

- `resources/views/client/scheduler/index.blade.php` — JavaScript de `openEditor` (~líneas 600–645).
- `resources/views/admin/scheduler/index.blade.php` — Referencia de la lógica correcta ya existente (~líneas 758–840).
- `resources/views/components/playlist-editor-modal-js.blade.php` — Métodos `loadPlaylist()`, `recalcTotals()`, `cumulativeStart()`; no requieren cambios si los datos de entrada están bien formados, pero se validará que consuman `_scheduleStart` correctamente.
- Usuarios finales (cliente): visualización correcta de splits, cuñas insertadas y timeline diario.
