## Context

`admin/channels/index.blade.php` tiene dos modos de vista (tarjetas y tabla) conmutables por `viewMode` (persistido en `localStorage`). La vista tarjetas incluye un botón "Configurar Restream" (línea 77) que abre `<x-restream-modal>` con payload `{ channel_id, channel_name }`. La vista tabla (líneas 128-192) renderiza las mismas acciones por fila (editar, pantalla virtual, ver vivo, archivar) pero **omite** el botón de Restream. El modal ya está montado en la vista (línea 203), y las rutas admin por canal (`/admin/channels/{channel}/restream`) ya existen desde el cambio `restream-quota-por-canal`.

## Goals / Non-Goals

**Goals:**
- Que el botón "Configurar Restream" esté disponible en la vista tabla, con el mismo comportamiento y payload que en la vista tarjetas.
- Mantener la paridad visual entre ambos modos de la misma vista.

**Non-Goals:**
- No se toca el backend (rutas, controladores, guard, middleware) — todo ya existe.
- No se añade una columna de estado de Restream en la tabla (fuera de alcance; el modal ya muestra estado, tier y destinos).
- No se toca la vista cliente de canales: el cliente gestiona destinos desde `/client/restream` con su selector de canal de contexto; la habilitación de cuota es exclusiva del admin (decisión D5 del cambio `restream-quota-por-canal`).

## Decisions

### D1. Replicar el botón de tarjetas en la fila de la tabla

Copiar el `<button>` de la línea 77 (icono rose, `title="Configurar Restream"`, `@click="$store.modals.open('restream', { channel_id: '{{ $ch->id }}', channel_name: '{{ addslashes($ch->display_name) }}' })"`) al bloque de acciones de la tabla, ajustando el tamaño de `w-8 h-8` a `w-9 h-9` para igualar el resto de botones de la fila de tabla.

**Alternativa considerada**: añadir una columna "Restream" con badge de estado (habilitado/deshabilitado + slots). Se descarta: requiere cargar datos de quota en el controlador de índice de canales y amplía el alcance; el modal ya provee esa información bajo demanda.

### D2. Posición del botón en la fila

Insertar el botón entre "Configurar pantalla virtual" y "Ver emisión en vivo", replicando el orden de la vista tarjetas (editar → pantalla → restream → live → archivar). Esto mantiene la coherencia visual entre ambos modos.

### D3. Sin cambios de montaje ni de rutas

`<x-restream-modal />` ya está montado (línea 203) y las rutas admin por canal ya existen. El cambio es puramente de Blade.

## Risks / Trade-offs

- **Divergencia entre modos** → El botón se copia verbatim del modo tarjetas; cualquier cambio futuro de payload debe aplicarse en ambos lugares (mismo patrón que el resto de botones compartidos de la vista).
- **Ningún riesgo de datos** → No hay migración, ni escritura, ni cambio de contrato de API.
