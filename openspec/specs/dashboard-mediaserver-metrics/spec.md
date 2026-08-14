# Dashboard Mediaserver Metrics Specification

## Purpose

Sección de métricas en vivo de MediaServer Platform integrada en el dashboard de admin y client, con mapeo stream↔canal, selector de stream para admin, scoping por `effectiveChannelIds()` para client, detalle de viewers/agentes/reglas, y degradación elegante cuando la API no responde.

## Requirements

### Requirement: Dashboard muestra métricas en vivo de MediaServer
El dashboard de admin y de client SHALL mostrar una sección de métricas en vivo de MediaServer Platform, alimentada por la API REST de MediaServer (`config('mediaserver.api_url')`), con datos por stream: estado de emisión (alive/health), viewers conectados, agentes/players usados, bitrate recibido y enviado (kbps), resolución y codecs, uptime, puerto SRT, y las reglas de bloqueo que aplican a ese stream (blacklist, geoblock, reglas por IP/país).

#### Scenario: API configurada y respondiendo
- **WHEN** el dashboard se renderiza y `MediaserverApiService::isReachable()` es true
- **THEN** la sección de métricas en vivo se muestra con los datos reales de la API para cada stream mapeado a un canal

#### Scenario: API no configurada o no responde
- **WHEN** `MediaserverApiService::isReachable()` es false (API no configurada, timeout o error de red)
- **THEN** el dashboard muestra un estado "MediaServer no disponible" en la sección de métricas y el resto del panel se renderiza sin errores

### Requirement: Mapeo stream a canal por stream name
El sistema SHALL mapear cada stream de MediaServer a un canal de Cloudstream usando el stream name extraído de `public_hls_url` (basename del path sin `.m3u8`) o de `virtualScreen.output_url` (patrón `live/<stream>`), con fallback al `slug` del canal. Los streams sin canal correspondiente SHALL mostrarse igualmente en el dashboard de admin (como streams sin canal asociado) pero NO en el dashboard de client.

#### Scenario: Stream coincide con stream name de canal
- **WHEN** la API devuelve un stream cuyo `name` coincide con el stream name extraído de un canal existente
- **THEN** el dashboard asocia las métricas de ese stream al canal y muestra el `display_name` del canal

#### Scenario: Stream sin canal asociado
- **WHEN** la API devuelve un stream cuyo `name` no coincide con ningún canal
- **THEN** el dashboard de admin lo muestra con su nombre de stream y la etiqueta "sin canal asociado", y el dashboard de client no lo muestra

### Requirement: Admin ve todos los streams con selector
El dashboard de admin SHALL mostrar métricas de TODOS los streams de MediaServer y SHALL ofrecer un selector (dropdown) para elegir un stream puntual, siguiendo el patrón de selector usado en otros módulos del panel.

#### Scenario: Admin selecciona un stream del dropdown
- **WHEN** el admin elige un stream en el selector del dashboard
- **THEN** la sección de métricas muestra los detalles de ese stream puntual (viewers, agentes, bitrate, resolución, reglas de bloqueo)

#### Scenario: Admin no selecciona ningún stream
- **WHEN** el dashboard de admin se carga sin selección previa
- **THEN** se muestra un resumen agregado de todos los streams (total de streams activos, total de viewers, streams con health degradado) y el selector permite elegir uno

### Requirement: Client ve solo sus canales asignados
El dashboard de client SHALL mostrar métricas en vivo SOLO de los streams cuyo stream name corresponde a canales dentro de `effectiveChannelIds()` del usuario autenticado. El client NO SHALL ver streams de canales ajenos ni streams sin canal asociado.

#### Scenario: Client con canales asignados
- **WHEN** un client carga su dashboard y la API responde
- **THEN** la sección de métricas muestra únicamente los streams de sus canales asignados, sin selector global

#### Scenario: Client sin canales asignados
- **WHEN** un client carga su dashboard y `effectiveChannelIds()` está vacío
- **THEN** la sección de métricas muestra un estado vacío "No tienes canales con emisión activa" sin consultar datos ajenos

### Requirement: Detalle de viewers y agentes por stream
El dashboard SHALL mostrar, para el stream seleccionado (admin) o para cada canal del client, el detalle de viewers conectados: cantidad total, IPs únicas, y los agentes/players usados (user_agent) con su conteo.

#### Scenario: Stream con viewers conectados
- **WHEN** el stream tiene viewers conectados según `/api/streams/{name}/clients`
- **THEN** el dashboard muestra el total de viewers, las IPs únicas y la lista de agentes/players (user_agent) con su conteo

#### Scenario: Stream sin viewers
- **WHEN** el stream no tiene viewers conectados
- **THEN** el dashboard muestra "0 viewers" sin lista de agentes

### Requirement: Lista individual de clientes conectados
El dashboard SHALL mostrar la lista individual de clientes conectados por stream (IP, país, tipo de player, agente, tiempo conectado y bitrate), enriquecida cruzando `/api/streams/{name}/clients` con `/api/admin/srs/clients` por IP. La lista SHALL ser una tabla expandible con búsqueda en vivo, ordenamiento por columna (asc → desc → normal) y bandera del país.

#### Scenario: Stream con clientes conectados
- **WHEN** el stream tiene viewers conectados
- **THEN** la tabla muestra cada cliente con IP, bandera y código de país, tipo de player con badge de color, agente, tiempo conectado y bitrate

#### Scenario: Búsqueda y ordenamiento
- **WHEN** el usuario escribe en el cuadro de búsqueda o hace clic en un encabezado
- **THEN** la tabla filtra en vivo por IP/país/agente y ordena asc → desc → normal según la columna

### Requirement: Reglas de bloqueo por stream
El dashboard SHALL mostrar las reglas de bloqueo que aplican a cada stream: si el stream está en la blacklist, si tiene geoblock (países permitidos), y las reglas por cliente (IP/país) cuyo `stream_filter` incluya el stream o esté vacío (aplica a todos).

#### Scenario: Stream con reglas aplicables
- **WHEN** el stream está en la blacklist, tiene geoblock, o existen reglas por cliente que le aplican
- **THEN** el dashboard muestra un resumen de esas reglas (stream bloqueado, países permitidos, reglas IP/país activas)

#### Scenario: Stream sin reglas
- **WHEN** el stream no tiene blacklist, geoblock ni reglas por cliente aplicables
- **THEN** el dashboard muestra "Sin reglas de bloqueo" para ese stream

### Requirement: Métricas de bitrate, resolución y salud
El dashboard SHALL mostrar por stream: bitrate recibido y enviado (kbps), resolución y codecs de video/audio, health state, y estado del puerto SRT.

#### Scenario: Stream activo con datos completos
- **WHEN** la API devuelve un stream activo con `kbps_recv`, `kbps_send`, `video`, `audio`, `health_state` y `srt_port`
- **THEN** el dashboard muestra esos valores formateados (kbps, resolución, codecs, badge de health, puerto SRT)

#### Scenario: Stream sin puerto SRT
- **WHEN** la API devuelve un stream con `srt_port: null`
- **THEN** el dashboard muestra "SRT no asignado" en lugar de un puerto
