# restream-channel-context delta

## ADDED Requirements

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
