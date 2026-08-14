# live-viewer Specification

## Purpose

Modal `<x-live-viewer-modal>` que previsualiza la salida HLS de un canal desde el panel admin y client, con reproducción HLS con fallback, estado de emisión, URL pública copiable y abrible en pestaña nueva, y botones de acción con iconos circulares en la lista de canales.

## Requirements

### Requirement: Live viewer modal
The system SHALL provide a modal `<x-live-viewer-modal>` that previews the HLS output of a channel from inside the admin panel.

#### Scenario: Open from channels list
- **WHEN** an admin clicks the "Ver vivo" icon in the actions column of a channel in `admin/channels`
- **THEN** the live-viewer modal opens, fetches `/api/virtual-screens/{channelId}` and `/api/channels/{channelId}/emission/status`, resolves the public HLS URL, and starts playback if there is an active emission

#### Scenario: Open from scheduler
- **WHEN** an admin clicks the "Ver vivo" button in the emission control block of `admin/scheduler`
- **THEN** the live-viewer modal opens for the currently selected channel with the same fetch + playback flow as from the channels list

### Requirement: HLS playback with graceful fallback
The live-viewer modal SHALL play HLS in any modern browser.

#### Scenario: HLS.js available (Chrome/Firefox/Edge)
- **WHEN** the modal opens and `window.Hls` is loaded successfully from the CDN (jsdelivr, version pinned to `hls.js@1.5.13`)
- **THEN** the modal creates a `new Hls()` instance, attaches it to the internal `<video>` element, and starts playback muted

#### Scenario: HLS.js unavailable (Safari/iOS or CDN blocked)
- **WHEN** `window.Hls` cannot be loaded (CDN unreachable, CSP, or Safari/iOS native HLS)
- **THEN** the modal renders `<video src={hls_url} controls>` directly, relying on the browser's native HLS support, and shows a non-blocking hint "Si el video no carga, ábrelo en una pestaña nueva"

#### Scenario: Channel has no HLS output configured
- **WHEN** the VirtualScreen for the channel has `output_protocol !== 'hls'` or `output_url` is empty
- **THEN** the modal shows an empty state "El canal aún no tiene salida HLS configurada" with a button "Configurar pantalla virtual" that opens the existing `virtual-screen-editor` modal pre-filled with the channel id

### Requirement: Show emission status inside the viewer
The live-viewer modal SHALL indicate whether there is an active emission before and during playback.

#### Scenario: Status is live or starting
- **WHEN** the modal opens and `/api/channels/{id}/emission/status` returns `status === 'live'` or `'starting'`
- **THEN** the modal shows a green "Al aire" badge and attempts playback immediately

#### Scenario: Status is offline or error
- **WHEN** the modal opens and the emission status is `'offline'` or `'error'`
- **THEN** the modal shows an amber/red badge with the status label and a message "No hay emisión activa en este canal", but the URL panel and "Abrir en nueva pestaña" button remain visible

### Requirement: Public URL is visible and openable
The live-viewer modal SHALL display the resolved public HLS URL and offer a way to open it in a new browser tab.

#### Scenario: URL display and copy
- **WHEN** the modal is open
- **THEN** the modal shows the resolved URL in a read-only monospace field with a "Copiar" button that places the URL on the clipboard

#### Scenario: Open in new tab
- **WHEN** the admin clicks the "Abrir en nueva pestaña" button
- **THEN** the system opens the HLS URL in a new tab via `window.open(url, '_blank')`

### Requirement: Channels list actions use icon buttons
The `admin/channels` index table SHALL render its action column with circular icon buttons matching the visual language of the scheduler calendar cells.

#### Scenario: Action buttons are circular icons
- **WHEN** the channels index renders
- **THEN** each row in the "Acciones" column shows circular icon buttons (`w-9 h-9 rounded-lg bg-gradient-to-br`) for Edit, Pantalla, Ver vivo, and Archivar

### Requirement: Client surfaces expose the same viewer
The client channels list and client scheduler SHALL expose the same "Ver vivo" entry points as the admin, scoped to channels the user can access.

#### Scenario: Client channels list
- **WHEN** a client opens `/client/channels`
- **THEN** each accessible channel shows a "Ver vivo" icon that opens the live-viewer modal

#### Scenario: Client scheduler
- **WHEN** a client opens `/client/scheduler`
- **THEN** the emission control block shows a "Ver vivo" button bound to the same disabled state as the admin
