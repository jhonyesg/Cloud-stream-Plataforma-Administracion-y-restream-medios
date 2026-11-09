# Spec Delta

## ADDED Requirements

### Requirement: MediaserverApiService expone la lectura de un stream y su bloque de URLs
`MediaserverApiService` SHALL exponer `getStream(string $name)` y `getStreamUrls(string $name)` que consulten el endpoint `GET /api/streams/{name}` de MediaServer Platform. `getStream()` SHALL devolver el payload JSON decodificado del stream y `getStreamUrls()` SHALL devolver el bloque `urls` de ese payload (array vacío si el payload no lo trae). Ambos SHALL lanzar `MediaserverApiException` cuando la API no está configurada, no responde o devuelve un error HTTP.

#### Scenario: Lectura del bloque de URLs
- **WHEN** se llama `getStreamUrls('cinedios')` y la plataforma responde con el detalle del stream
- **THEN** SHALL devolver el bloque `urls` con los grupos `publish` y `play` (incluido `play.via_proxy`)

#### Scenario: El stream no existe en la plataforma
- **WHEN** se llama `getStream('cinedios')` y la plataforma responde HTTP 404
- **THEN** SHALL lanzar `MediaserverApiException` con `statusCode` 404, de modo que el consumidor pueda distinguir "el canal no está emitiendo" de "la plataforma está caída"

### Requirement: El mapeo canal → stream de MediaServer es único
El sistema SHALL resolver el nombre de stream de MediaServer Platform para un canal de Cloudstream en un único lugar (`App\Services\MediaserverStreamResolver`), de modo que el dashboard de métricas y el modal de URLs de Programación apunten al mismo stream. La regla SHALL tomar el basename de `public_hls_url` sin la extensión `.m3u8`, y en su defecto el segmento `live/<stream>` de `virtualScreen.output_url`, y en último caso el slug del canal.

#### Scenario: Canal con URL pública HLS
- **WHEN** se resuelve el stream de un canal cuyo `public_hls_url` es `https://canal.mediaserver.com.co/live/cinedios.m3u8`
- **THEN** SHALL devolver `cinedios`

#### Scenario: Dashboard y modal coinciden
- **WHEN** el dashboard de métricas y el modal de URLs resuelven el mismo canal
- **THEN** ambos usan el resolver compartido y obtienen el mismo nombre de stream