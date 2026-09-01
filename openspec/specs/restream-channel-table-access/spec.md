# restream-channel-table-access Specification

## Purpose

Acceso a la configuración de Restream desde la vista tabla del módulo de canales del admin. El botón "Configurar Restream" abre el modal `<x-restream-modal>` con el canal como payload, permitiendo ver/otorgar/actualizar/eliminar la cuota de Restream del canal sin salir del módulo de canales.

## Requirements

### Requirement: Restream access button in admin channels table view

The admin channels index view SHALL render a "Configurar Restream" button in the per-row actions of the **table view**, identical in behavior to the button already present in the cards view. Clicking it SHALL open the `<x-restream-modal>` with the channel's `id` and `display_name` as payload, so the admin can view/grant/update/delete the channel's Restream quota without leaving the channels module.

#### Scenario: Table row shows Restream button
- **WHEN** an admin loads `GET /admin/channels` with `viewMode = 'table'`
- **THEN** every channel row SHALL contain a "Configurar Restream" button whose click handler opens the `restream` modal with `{ channel_id, channel_name }` for that channel

#### Scenario: Restream modal opens from table view
- **WHEN** the admin clicks the "Configurar Restream" button on a table row
- **THEN** the `<x-restream-modal />` SHALL open showing the quota state (or the grant form) for that channel, and the admin SHALL be able to grant, update, or delete the quota

#### Scenario: Cards and table views stay in parity
- **WHEN** an admin toggles between cards and table views on `GET /admin/channels`
- **THEN** both views SHALL expose the same set of per-row actions, including "Configurar Restream"
