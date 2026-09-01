## ADDED Requirements

### Requirement: Admin channels table view exposes Restream action

The admin channels index **table view** SHALL include a "Configurar Restream" action per row, alongside the existing Editar / Pantalla Virtual / Ver vivo / Archivar actions. The action SHALL open the `restream` modal (mounted in the same view) with the channel's `id` and `display_name`, matching the behavior of the cards view.

#### Scenario: Restream action present in table rows
- **WHEN** an admin loads `GET /admin/channels` with `viewMode = 'table'`
- **THEN** each channel row SHALL render a "Configurar Restream" button that opens the `restream` modal for that channel

#### Scenario: Restream action absent from client channels view
- **WHEN** a client loads `GET /client/channels`
- **THEN** the channels view SHALL NOT render a Restream quota button (quota habilitation is admin-only; clients manage targets from `/client/restream`)
