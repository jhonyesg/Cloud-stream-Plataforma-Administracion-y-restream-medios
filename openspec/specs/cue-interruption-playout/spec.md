# cue-interruption-playout Specification

## Purpose

Inserción, segmentación, ordenamiento y reproducción de cuñas dentro de contenidos. Una cuña insertada en medio de una película produce la secuencia editorial `contenido anterior -> cuña -> contenido posterior`, con offsets de fuente que permiten reanudar el mismo archivo desde el punto exacto de corte.

## Requirements

### Requirement: Inserción de cuña como interrupción editorial
El sistema SHALL insertar una cuña futura dentro de un contenido creando una secuencia persistida `contenido anterior -> cuña -> contenido posterior`. El contenido anterior y posterior SHALL referenciar el mismo `media_item_id` y conservar offsets de fuente que representen una sola reproducción continua interrumpida.

#### Scenario: Insertar cuña en medio de una película
- **WHEN** una película de 3600 segundos recibe una cuña de 45 segundos en el segundo 1800
- **THEN** la playlist SHALL quedar ordenada como segmento `0-1800`, cuña `1800-1845`, segmento `1800-3600`

### Requirement: Segmentos sin solapamiento
Cada segmento materializado SHALL tener una duración efectiva positiva y los intervalos posteriores SHALL empezar exactamente al terminar el elemento anterior, salvo un fallback explícito.

#### Scenario: Validar secuencia de cuña
- **WHEN** se materializa una playlist con un split y una cuña
- **THEN** ningún intervalo de contenido SHALL solaparse con la cuña y la suma de duraciones SHALL producir el horario de reanudación correcto

### Requirement: Inserción futura atómica y auditable
La inserción de una cuña SHALL ejecutarse en una transacción, SHALL rechazar puntos pasados o activos, SHALL incrementar la versión de timeline y SHALL registrar el estado before/after.

#### Scenario: Insertar solo en el futuro
- **WHEN** el canal está al aire y el usuario solicita una cuña antes del límite editable
- **THEN** la operación SHALL responder 422, no SHALL modificar playlist/timeline y SHALL conservar el item activo

#### Scenario: Auditoría de inserción
- **WHEN** una cuña se inserta correctamente
- **THEN** `audit_logs` SHALL contener la cuña, el punto de inserción, los segmentos creados o modificados y la versión resultante
