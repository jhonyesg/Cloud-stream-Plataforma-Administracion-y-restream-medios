# restream-channel-context Specification

## Purpose
TBD - created by archiving change restream-quota-por-canal. Update Purpose after archive.
## Requirements
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

### Requirement: Vista renderiza payload Alpine sin errores de parseo

La página `GET /client/restream` y `GET /admin/restream` SHALL emitir el atributo `x-data="…"` del wrapper de Alpine de forma tal que el navegador lo parsee como un único atributo HTML y el payload resultante sea JS sintácticamente válido. En particular, la inyección de JSON dentro del atributo SHALL pasar por `htmlspecialchars` (vía `{{ … }}` con una cadena pre-codificada por el controlador), NUNCA por `@json(...)` crudo.

#### Scenario: El atributo x-data contiene el cuerpo completo

- **WHEN** la vista se renderiza con un canal de contexto válido y al menos un destino configurado
- **THEN** el HTML servido contiene el atributo `x-data` cuyo valor decodificado por el navegador incluye TODOS los métodos declarados (`csrf`, `accountFor`, `connectAccount`, `disconnectAccount`, `openCreate`, `openEdit`, `startOrStop`, `start`, `stop`, `remove`, `inactivate`, `forceDestroy`, `init`, `refresh`, `openLog`, `fetchLog`, `revealCreds`, `copyText`) y termina con la `}` que cierra el literal objeto
- **AND** el navegador no interpreta partes del valor como atributos HTML adicionales (ningún `id`, `display_name`, `csrf-token`, etc. aparece como atributo del `<div>` wrapper)

#### Scenario: Console limpia al cargar la vista

- **WHEN** un cliente o admin carga la vista de Restream
- **THEN** la consola del navegador no contiene ningún error de tipo "Alpine Expression Error: Unexpected token ';'" ni ninguna advertencia "is not defined" para `accountFor`, `flash`, `targets`, `currentChannel`, `csrf` u otros miembros del payload

#### Scenario: Binding `currentChannel` resuelve correctamente

- **WHEN** el usuario tiene un canal de contexto seleccionado y la vista se monta
- **THEN** el binding `currentChannel` en Alpine es un objeto `{ id, display_name }` y el binding de fallback `null` cuando no hay canal
- **AND** los bindings descendientes que dependen del scope (`accountFor(platform)`, `flash`, `targets.length`, `targets` en `x-for`) resuelven sin error

#### Scenario: Cambio de canal sigue funcionando

- **WHEN** el usuario cambia la selección de canal y la página recarga con `?channel_id=<otro>`
- **THEN** el nuevo `currentChannel` se inyecta en el payload Alpine sin reintroducir el truncamiento

