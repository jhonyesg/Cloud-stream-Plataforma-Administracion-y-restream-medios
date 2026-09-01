## 1. Vista admin — botón Restream en la tabla

- [x] 1.1 En `resources/views/admin/channels/index.blade.php`, añadir el botón "Configurar Restream" en el bloque de acciones de la vista tabla (entre "Configurar pantalla virtual" y "Ver emisión en vivo"), copiando el `@click="$store.modals.open('restream', { channel_id: '{{ $ch->id }}', channel_name: '{{ addslashes($ch->display_name) }}' })"` de la vista tarjetas y ajustando el tamaño a `w-9 h-9`
- [x] 1.2 Verificar que `<x-restream-modal />` sigue montado en la vista (línea 203) y que el nombre del modal coincide con el `open('restream', ...)`

## 2. Verificación

- [x] 2.1 Smoke test admin: `GET /admin/channels` → cambiar a vista Tabla → clic en "Configurar Restream" de una fila → el modal abre y muestra la cuota (o el formulario de habilitación) del canal
- [x] 2.2 Smoke test admin: alternar entre Tarjetas y Tabla y confirmar que ambas vistas exponen el mismo conjunto de acciones por fila
- [x] 2.3 Confirmar que la vista cliente `GET /client/channels` no muestra botón de cuota Restream (la habilitación es admin-only)
