# restream-target-calendar-ui Specification

## Purpose
TBD - created by archiving change restream-target-scheduler. Update Purpose after archive.

## Requirements

### Requirement: Vista calendario por target

El sistema SHALL renderizar una vista calendario para un target en `client.restream.target.schedules.index` y `admin.restream.target.schedules.index`. La vista SHALL ofrecer un toggle entre **vista mensual** (7 columnas × N filas de días) y **vista semanal** (7 columnas × H filas de horas). Cada ventana SHALL visualizarse como un bloque coloreado dentro de la celda correspondiente, posicionado por su `starts_at` y altura proporcional a `duration = ends_at - starts_at`.

#### Scenario: Vista mensual por defecto
- **WHEN** el cliente abre `/client/channels/<c>/targets/<t>/schedules`
- **THEN** la vista arranca en modo mensual mostrando el mes actual con todas las ventanas del target como bloques

#### Scenario: Toggle a vista semanal
- **WHEN** el usuario hace click en "Semana"
- **THEN** la vista cambia a semanal mostrando los próximos 7 días con bloques por hora; las ventanas se renderizan con su posición y duración proporcionales

#### Scenario: Ventana sin fin se visualiza con icono "sin fin"
- **WHEN** la ventana tiene `ends_at = null`
- **THEN** el bloque se extiende hasta el final del día visible con un icono ∞ en el borde derecho

#### Scenario: Estados visuales diferenciados
- **WHEN** el calendario muestra ventanas
- **THEN** los bloques usan colores según estado: pendiente=gris, en curso=verde, terminado=azul, deshabilitado=rojo, falló=amarillo

### Requirement: Modal de creación/edición de ventana

El sistema SHALL ofrecer un modal único para crear o editar ventanas, accesible por (a) click en celda vacía del calendario, (b) botón "Nueva ventana" en el listado, (c) botón "Editar" sobre un bloque existente. El modal SHALL contener los campos:

- `name` (text, opcional, max 120)
- `starts_at` (`<input type="datetime-local">`, requerido)
- `ends_at` (`<input type="datetime-local">`, opcional; checkbox "Sin fin programado" lo deja en null)
- `timezone` (`<select>` poblado con `DateTimeZone::listIdentifiers()`, default = timezone del usuario o `UTC`)
- `notes` (textarea, opcional)
- `enabled` (checkbox, default true)

CSRF token SHALL incluirse.

#### Scenario: Modal prellenado al click en celda
- **WHEN** el usuario hace click en una celda vacía del día Y hora H
- **THEN** el modal abre con `starts_at` prellenado con la fecha de la celda a hora H:00

#### Scenario: Modal en modo edición
- **WHEN** el usuario hace click en un bloque existente
- **THEN** el modal abre con todos los campos prellenados con los valores de la ventana

#### Scenario: Submit con ends_at vacío
- **WHEN** el usuario deja `ends_at` vacío o marca "Sin fin programado"
- **THEN** la ventana se guarda con `ends_at = NULL`

#### Scenario: Submit con ends_at < starts_at
- **WHEN** el usuario envía `ends_at` anterior o igual a `starts_at`
- **THEN** el formulario bloquea el envío y muestra error bajo el campo `ends_at`

### Requirement: Mini-calendario en la lista de targets del cliente

El sistema SHALL renderizar en `client.restream.index` (la captura inicial del incidente) una sección colapsable por cada target que muestre un mini-calendario de los próximos 7 días con badges por ventana, más un enlace "Calendario completo" hacia la vista detallada. Cada target con al menos una ventana en los próximos 7 días SHALL mostrar un punto de color junto a su nombre.

#### Scenario: Mini-calendario muestra próximas 7 días
- **WHEN** el cliente abre `client.restream.index`
- **THEN** cada target muestra los próximos 7 días con badges por ventana próxima (hoy + 6 días)

#### Scenario: Click en badge abre el calendario completo
- **WHEN** el usuario hace click en un badge de un mini-calendario
- **THEN** navega a `client.restream.target.schedules.index` con la vista ya en el día clickeado

#### Scenario: Target sin ventanas próximas no muestra mini-calendario
- **WHEN** un target no tiene ventanas en los próximos 7 días
- **THEN** la sección del target en `client.restream.index` NO muestra el mini-calendario, solo el estado actual (live/idle/error)
