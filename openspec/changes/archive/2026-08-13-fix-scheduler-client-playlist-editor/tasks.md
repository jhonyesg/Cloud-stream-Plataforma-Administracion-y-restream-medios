## 1. Sincronizar openEditor en cliente con admin

- [x] 1.1 Copiar la lógica de cálculo de `effective` duration y `_scheduleStart` desde `admin/scheduler/index.blade.php` hacia `client/scheduler/index.blade.php` en la función `openEditor` del componente `playlistsList`
- [x] 1.2 Asegurar que el cliente incluya `start_sec`, `split_from_id`, `cue_in_sec`, `cue_out_sec` en el mapeo de items (actualmente faltan)
- [x] 1.3 Verificar que el sort final por `_scheduleStart` esté presente en el cliente
- [x] 1.4 Agregar comentario de sincronización en ambas vistas (admin y cliente) indicando que la lógica debe mantenerse idéntica

## 2. Validar paridad visual y funcional

- [x] 2.1 Probar inserción de cuña con split en admin: verificar que timeline, items y preview se ven correctos
- [x] 2.2 Probar inserción de cuña con split en cliente con el mismo canal: confirmar resultado idéntico al admin
- [x] 2.3 Verificar que drag-and-drop, reorder y eliminación de items funcionan igual en ambas vistas
- [x] 2.4 Confirmar que el total, loops/día y hora de terminación coinciden entre admin y cliente

## 3. Limpieza y caché

- [x] 3.1 Ejecutar `php artisan view:clear` para limpiar vistas compiladas
- [x] 3.2 Revisar que no haya archivos de caché de Blade obsoletos que puedan servir la versión antigua
