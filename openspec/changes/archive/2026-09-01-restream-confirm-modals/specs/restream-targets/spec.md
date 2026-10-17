# restream-targets delta

## ADDED Requirements

### Requirement: Acciones destructivas usan el modal de confirmación

Las acciones destructivas de un destino (`remove`, `inactivate`, `forceDestroy`) SHALL abrir el `<x-confirm-modal>` con un payload específico por acción, en lugar de llamar `window.confirm()`. El payload SHALL incluir al menos `title`, `message`, `tone`, `confirmLabel` y (para `forceDestroy`) `requireText`. Las llamadas nativas `window.confirm()` SHALL eliminarse del módulo.

#### Scenario: Archivar abre el modal en tono warning
- **WHEN** un cliente o admin hace clic en el botón "Archivar" sobre un destino
- **THEN** se abre el `<x-confirm-modal>` con `title="Archivar destino"`, `message` mencionando que el slot queda libre y se puede restaurar, `tone: 'warning'`, `confirmLabel: 'Sí, archivar'`, `action` apuntando a `destroy` (DELETE) y la fila no se borra hasta que el usuario confirma

#### Scenario: Inactivar abre el modal en tono warning
- **WHEN** un cliente o admin hace clic en "Inactivar" sobre un destino activo
- **THEN** se abre el modal con `title="Inactivar destino"`, mensaje explicando que se detiene y libera el slot, `tone: 'warning'`, `confirmLabel: 'Sí, inactivar'`, y el `action` apuntando a `deactivate` (POST)

#### Scenario: Eliminar definitivo exige tipear ELIMINAR
- **WHEN** un cliente o admin hace clic en "Eliminar" sobre un destino
- **THEN** se abre el modal en `tone: 'danger'` con `requireText: 'ELIMINAR'`; el botón de acción está deshabilitado hasta que el usuario tipea exactamente `ELIMINAR`. Si el usuario cancela o tipea mal, ninguna request se dispara. Si confirma, se hace DELETE a `forceDestroy` y luego aparece el toast de éxito

#### Scenario: Confirm dispara la acción HTTP y refresca la tabla
- **WHEN** el usuario confirma cualquiera de los tres modales
- **THEN** se hace el `fetch` correspondiente, se cierra el modal, aparece el toast global (`crud-success`/`crud-error`), se actualiza `flash`/`flashKind` en el `<x-data>` raíz y se dispara `restream-targets-changed` para refrescar la tabla

#### Scenario: Cancelar / Escape cierra el modal sin acción
- **WHEN** el usuario hace clic en "Cancelar" o presiona Escape estando uno de los tres modales abiertos
- **THEN** el modal se cierra y NO se ejecuta ninguna request
