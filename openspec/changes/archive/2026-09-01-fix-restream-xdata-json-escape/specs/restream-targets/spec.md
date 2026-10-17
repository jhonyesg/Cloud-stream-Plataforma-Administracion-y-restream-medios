# restream-targets delta

## ADDED Requirements

### Requirement: UI de Restream se monta sin errores de Alpine

Las vistas `/client/restream` y `/admin/restream` SHALL montarse en el navegador sin producir errores ni advertencias de Alpine en la consola del navegador relacionados con el payload inicial (`x-data`). Esto requiere que cualquier objeto JSON anidado dentro del atributo `x-data` sea inyectado mediante una cadena pre-codificada en el controlador y renderizada con `{{ … }}` (que aplica `htmlspecialchars`), no con `@json(...)` crudo.

#### Scenario: Acciones de la tabla responden al estado

- **WHEN** un usuario carga la vista con uno o más destinos configurados en el canal de contexto
- **THEN** los badges de estado (`live`, `starting`, `error`, `idle`) reflejan el valor real de cada destino y los botones "Iniciar", "Detener", "Inactivar", "Archivar", "Eliminar" muestran/ocultan correctamente según `t.status`, `t.enabled` y `t.pipeline_pid`

#### Scenario: Modal de destino se abre con datos correctos

- **WHEN** el usuario hace clic en "Editar" sobre un destino o en "+ Nuevo destino"
- **THEN** el modal se abre con `channel`, `mode`, `target`, `connectedAccounts` propagados desde el payload inicial de Alpine, y los bindings `x-text="channel ? channel.display_name : '—'"` muestran el nombre del canal

#### Scenario: Conexión de cuenta OAuth renderiza el badge correcto

- **WHEN** el cliente tiene una cuenta de YouTube o Facebook conectada (con o sin `needs_reconnect`)
- **THEN** la fila correspondiente en "Cuentas conectadas" muestra el badge verde (`border-emerald-300 bg-emerald-50`) o ámbar (`border-amber-300 bg-amber-50`) según `accountFor(platform).needs_reconnect`, y el botón "Conectar"/"Desconectar"/"Reconectar" es el correcto
