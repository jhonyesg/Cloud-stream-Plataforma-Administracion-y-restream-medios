# Spec Delta

## ADDED Requirements

### Requirement: El módulo de Programación ofrece las URLs de un canal
El módulo de Programación SHALL ofrecer, en admin y en client, un acceso a las URLs de publicación y de reproducción del canal seleccionado, mediante un botón que abre un modal con la matriz completa de URLs y un control de copia por fila.

#### Scenario: El botón existe en las dos superficies
- **WHEN** se renderiza la vista de Programación de admin y la de client
- **THEN** ambas muestran un botón que abre el modal de URLs del canal seleccionado

#### Scenario: El modal se monta donde se usa
- **WHEN** se busca en el HTML renderizado de cada vista de Programación
- **THEN** el componente del modal de URLs está presente en ambas

#### Scenario: El modal se abre con el canal seleccionado
- **WHEN** el usuario pulsa el botón de URLs con un canal seleccionado en el selector
- **THEN** el modal se abre y las URLs corresponden a ese canal, no a otro

### Requirement: Las URLs provienen de MediaServer Platform
El sistema SHALL obtener las URLs de un canal desde la API de MediaServer Platform y SHALL NOT reimplementar el cálculo de esas URLs en Cloudstream. La respuesta SHALL normalizarse en filas con grupo, etiqueta, URL y si la URL admite apertura en el navegador.

La superficie de cliente SHALL mostrar **solo el RTMP** en el grupo de publicación y **solo las variantes por dominio** (HTTPS, HTTP y SRT de lectura) en el grupo de reproducción. Las URLs directas al servidor por IP y puerto (SRS `:8080`, panel `:1985/mpegts`, SRT de lectura `:7386`) y el SRT de publicación SHALL NOT exponerse.

#### Scenario: Las filas cubren publicación y reproducción
- **WHEN** MediaServer Platform devuelve el bloque `urls` de un stream
- **THEN** la respuesta contiene una fila de publicación con la URL RTMP y filas de reproducción con las variantes por dominio (HTTPS, HTTP y SRT de lectura)

#### Scenario: Las URLs directas al servidor no se exponen
- **WHEN** la plataforma devuelve URLs directas al servidor por IP y puerto
- **THEN** la respuesta no las incluye, y tampoco incluye el SRT de publicación

#### Scenario: El mapeo canal → stream es el mismo que usa el dashboard
- **WHEN** se resuelve el nombre de stream de un canal para el modal de URLs
- **THEN** es el mismo nombre de stream que el dashboard de métricas usa para ese canal

#### Scenario: Un protocolo nuevo no rompe el modal
- **WHEN** MediaServer Platform añade un protocolo al bloque `urls`
- **THEN** el modal sigue funcionando y la fila aparece sin cambios en el frontend

### Requirement: Copia de cada URL
Cada fila del modal SHALL ofrecer un control de copia que deposite en el portapapeles exactamente la URL de esa fila, con un mecanismo de respaldo cuando la Clipboard API no está disponible, y las filas de reproducción SHALL ofrecer además la apertura en una pestaña nueva.

#### Scenario: Copiar una fila
- **WHEN** el usuario pulsa "Copiar" en una fila
- **THEN** el portapapeles recibe la URL exacta de esa fila y el control confirma la copia

#### Scenario: Abrir una fila de reproducción
- **WHEN** el usuario pulsa "Abrir" en una fila de reproducción
- **THEN** la URL se abre en una pestaña nueva

### Requirement: El modal degrada cuando el canal no tiene stream
Si MediaServer Platform no conoce el stream del canal porque no está emitiendo, el modal SHALL explicar el estado y SHALL ofrecer la URL pública que Cloudstream ya tiene configurada para ese canal, en lugar de mostrar un error genérico. Si la API de la plataforma no responde, el modal SHALL indicar que la plataforma no está disponible.

#### Scenario: Canal sin stream en la plataforma
- **WHEN** la plataforma responde que el stream no existe
- **THEN** el modal indica que el canal todavía no tiene stream en MediaServer Platform y ofrece la URL pública configurada en Cloudstream

#### Scenario: Plataforma no disponible
- **WHEN** la API de MediaServer Platform no responde
- **THEN** el modal indica que la plataforma no está disponible y no rompe el resto del módulo de Programación

### Requirement: El endpoint respeta el alcance de datos
El endpoint de URLs SHALL devolver las URLs de cualquier canal a un administrador y SHALL restringirse a los canales del `effectiveChannelIds()` del cliente, respondiendo 403 cuando el cliente no tiene acceso al canal solicitado.

#### Scenario: Cliente con acceso
- **WHEN** un client pide las URLs de uno de sus canales
- **THEN** la respuesta es 200 con las filas de URLs del canal

#### Scenario: Cliente sin acceso
- **WHEN** un client pide las URLs de un canal que no tiene asignado
- **THEN** la respuesta es 403 y no se devuelve ninguna URL
