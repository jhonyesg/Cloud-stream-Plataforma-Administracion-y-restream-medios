# restream-channel-context Specification

## Purpose
Selector de canal de contexto en la vista cliente de Restream y modal de destino con canal explícito. Elimina el uso silencioso de `channels[0]` al crear destinos.

## ADDED Requirements

### Requirement: Vista cliente con selector de canal de contexto

La página `GET /client/restream` SHALL mostrar un selector de canal prominente (patrón scheduler) que recarga la vista con `?channel_id=<id>`. La vista SHALL operar sobre un único canal de contexto: lista de destinos, conteos de cupo (`used_outputs`, `max_outputs`, `remaining_slots`) y creación de destinos se calculan sobre ese canal.

#### Scenario: Usuario con varios canales ve selector y contexto definido
- **WHEN** un cliente con 2 canales asignados carga `GET /client/restream` sin `channel_id`
- **THEN** la vista muestra un selector de canal, el primer canal asignado queda seleccionado por defecto, y la tabla muestra solo los destinos de ese canal

#### Scenario: Selector cambia el contexto
- **WHEN** el cliente elige otro canal en el selector
- **THEN** la URL se actualiza a `?channel_id=<otro>` y la vista recarga mostrando los destinos y conteos de ese canal

#### Scenario: channel_id inválido se ignora
- **WHEN** el cliente carga `?channel_id=<id>` donde `<id>` no está en `effectiveChannelIds()`
- **THEN** la vista ignora el valor y usa el primer canal asignado sin error

#### Scenario: Usuario sin canales no puede crear
- **WHEN** un cliente sin canales asignados intenta abrir el creador de destinos
- **THEN** la vista muestra un aviso indicando que pida un canal al administrador y no abre el modal

### Requirement: Modal de destino con canal explícito

El modal de creación de destino (`restream-target-modal`) SHALL recibir el canal de contexto y mostrarlo de forma visible. La creación de un destino SHALL usar siempre el canal de contexto del selector, nunca el primer canal de la lista en silencio.

#### Scenario: Crear destino usa el canal de contexto
- **WHEN** un cliente con canal de contexto `C` abre el creador de destinos y guarda
- **THEN** el destino se crea con `channel_id = C` y la respuesta incluye el nombre del canal para confirmación visual

#### Scenario: Modal muestra el canal destino
- **WHEN** el modal de creación/edición se abre
- **THEN** el modal muestra el nombre del canal al que pertenece el destino en un encabezado visible

#### Scenario: Edición no permite cambiar de canal
- **WHEN** un cliente edita un destino existente
- **THEN** el campo de canal se muestra como información (no editable); cambiar de canal se logra creando un destino nuevo en el otro canal

### Requirement: Conteos de cupo por canal en la UI

La vista cliente SHALL mostrar `used_outputs`, `max_outputs` y `remaining_slots` calculados sobre el canal de contexto actual, y SHALL deshabilitar el botón de nuevo destino cuando `remaining_slots = 0` para ese canal.

#### Scenario: Slots mostrados corresponden al canal
- **WHEN** el canal de contexto tiene 1 destino activo de un cupo de 2
- **THEN** la vista muestra "Slots: 1/2" y el botón de nuevo destino está habilitado

#### Scenario: Cap alcanzado en el canal bloquea creación
- **WHEN** el canal de contexto tiene 2 destinos activos de un cupo de 2
- **THEN** la vista muestra "Slots: 2/2" y el botón de nuevo destino está deshabilitado
