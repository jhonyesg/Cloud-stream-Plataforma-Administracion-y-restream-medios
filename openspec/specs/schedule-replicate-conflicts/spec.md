## ADDED Requirements

### Requirement: Vista previa de conflictos antes de replicar
El sistema SHALL exponer `POST /api/schedule-templates/{id}/clone-preview` que reciba `{year: int, months: int[]}` y devuelva `{conflicts: [{month: int, status: string, blocks_count: int, template_id: string}]}`. SHALL listar como conflicto cualquier `schedule_template` existente para `(channel_id, year, month)` independientemente de su `status`. SHALL respetar el scope por canal del usuario autenticado.

#### Scenario: Vista previa sin conflictos
- **WHEN** admin solicita preview de plantilla T hacia meses [9, 10] del año 2026 y no existen plantillas en esos meses
- **THEN** la respuesta es `{conflicts: []}`

#### Scenario: Vista previa con conflicto en un mes
- **WHEN** admin solicita preview hacia mes 8 y existe un template `draft` con 7 bloques en (canal, 2026, 8)
- **THEN** la respuesta incluye `{month: 8, status: "draft", blocks_count: 7, template_id: "..."}`

#### Scenario: Vista previa detecta active como conflicto
- **WHEN** admin solicita preview hacia mes 8 y existe un template con `status="active"`
- **THEN** la respuesta lo incluye en conflicts con `status: "active"` (el sistema no excluye activos)

#### Scenario: Preview fuera de scope del canal
- **WHEN** cliente solicita preview hacia meses del año 2026 sobre plantilla de un canal que no le pertenece
- **THEN** el sistema responde HTTP 403 o 404 (mismo criterio que `clone` actual)

### Requirement: Reemplazo transparente del destino al clonar
Cuando `POST /api/schedule-templates/{id}/clone` se ejecuta y ya existe un `schedule_template` para `(channel_id, year, month)`, el sistema SHALL reemplazar el existente: borrar la fila existente (con cascade de `schedule_blocks` y `schedule_block_items`) y crear la nueva plantilla clonada con `status='draft'`, `is_replicated=true`, `cloned_from_id` apuntando al origen. La operación SHALL ejecutarse dentro de una `DB::transaction`.

#### Scenario: Clonar sobre destino existente en draft
- **WHEN** admin clona plantilla T(Jul 2026) hacia mes 8 y existe T_existing(Ago 2026, status='draft', 7 bloques)
- **THEN** el sistema borra T_existing y sus 7 bloques, crea T_new(Ago 2026, status='draft', cloned_from_id=T.id) con los bloques del origen, y responde HTTP 201

#### Scenario: Clonar sobre destino existente en active
- **WHEN** admin clona plantilla T hacia mes 8 y existe T_existing con `status='active'`
- **THEN** el sistema borra T_existing (incluyendo sus bloques), crea la nueva plantilla clonada como `draft`, y responde HTTP 201

#### Scenario: Clonar sobre mes sin plantilla existente
- **WHEN** admin clona plantilla T hacia mes 9 y no existe plantilla para ese mes
- **THEN** el sistema crea la nueva plantilla clonada (sin borrado previo) y responde HTTP 201 (comportamiento previo intacto)

### Requirement: Auditoría de reemplazos al clonar
Cuando el reemplazo elimina una plantilla existente, el sistema SHALL registrar en `audit_logs` una entrada con `action='clone.schedule_template.replace'`, `entityType='ScheduleTemplate'`, `entityId=existing.id`, `before` conteniendo los atributos de la plantilla existente más `blocks` con el array de bloques (incluyendo `day_of_month` y `playlist_id`), `after=null`, `channel_id=existing.channel_id`. El registro SHALL ocurrir dentro de la misma transacción que el borrado.

#### Scenario: Snapshot registrado en reemplazo
- **WHEN** se reemplaza T_existing(7 bloques) al clonar
- **THEN** existe una fila en `audit_logs` con `action='clone.schedule_template.replace'`, `entity_id=T_existing.id`, y `before.blocks` con un array de longitud 7

#### Scenario: Sin snapshot cuando no hay reemplazo
- **WHEN** se clona a un mes sin plantilla existente
- **THEN** no se crea entrada en `audit_logs` con `action='clone.schedule_template.replace'` (solo la entrada `clone.schedule_template` ya existente)

### Requirement: Consentimiento explícito antes de reemplazar
Cuando la respuesta del preview contiene al menos un conflicto, el modal SHALL mostrar un checkbox "Sí, reemplazar las plantillas existentes en los meses destino" SHALL mantener deshabilitado el botón "Replicar" hasta que el checkbox esté tildado. El label del botón SHALL pasar a "Sí, reemplazar y replicar en N meses" cuando hay conflictos confirmados. Si no hay conflictos, el checkbox SHALL no mostrarse y el botón SHALL estar habilitado solo con la selección de meses (label "Replicar a N meses").

#### Scenario: Botón deshabilitado con conflictos sin confirmar
- **WHEN** el preview devuelve al menos un conflicto y el checkbox "Sí, reemplazar..." no está tildado
- **THEN** el botón "Replicar" permanece deshabilitado y muestra "Reemplazar y replicar en N meses" como label tentativo

#### Scenario: Botón habilitado al confirmar conflictos
- **WHEN** el preview devuelve conflictos y el usuario tilda "Sí, reemplazar las plantillas existentes"
- **THEN** el botón se habilita con label "Sí, reemplazar y replicar en N meses"

#### Scenario: Botón habilitado sin conflictos
- **WHEN** el preview devuelve `conflicts: []` y hay meses seleccionados
- **THEN** el botón está habilitado con label "Replicar a N meses" y el checkbox de consentimiento no se muestra

#### Scenario: Cancelación del consentimiento
- **WHEN** el usuario destilda el checkbox "Sí, reemplazar..." después de haberlo tildado
- **THEN** el botón vuelve a estar deshabilitado