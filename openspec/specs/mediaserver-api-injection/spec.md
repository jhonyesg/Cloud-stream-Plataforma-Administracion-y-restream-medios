# Mediaserver API Injection Specification

## Purpose

`App\Services\MediaserverApiService` es resoluble por el container de Laravel y expone métodos de lectura de la API de MediaServer Platform (streams, clients, diagnostics, reglas, SRT, health) que el dashboard consume, con tolerancia a fallos para no romper la página.

## Requirements

### Requirement: MediaserverApiService es resoluble por el container de Laravel
El service container de Laravel SHALL poder resolver `App\Services\MediaserverApiService` cuando cualquier consumer lo solicite (controllers, otros services, `app()`, etc.) sin lanzar `Illuminate\Contracts\Container\BindingResolutionException`. La resolución SHALL usar el factory estático `MediaserverApiService::fromConfig()` para proveer los parámetros escalares del constructor (`$apiUrl`, `$apiUser`, `$apiPassword`, `$httpTimeout`) desde `config('mediaserver.*')`. El binding SHALL estar registrado en `AppServiceProvider::register()` (no en `boot()`).

#### Scenario: Controller de Emisión recibe el servicio por type-hint
- **WHEN** un controller con firma `function index(Request $request, MediaserverApiService $api)` es instanciado por Laravel durante el dispatch de una request
- **THEN** el container SHALL resolver `MediaserverApiService` exitosamente usando el factory `fromConfig()` registrado en el service provider, y el método SHALL ejecutarse sin `BindingResolutionException`

#### Scenario: El container devuelve una instancia configurada
- **WHEN** se llama `app(MediaserverApiService::class)`
- **THEN** SHALL devolver una instancia con `isConfigured()` coherente con los valores de `config('mediaserver.api_url')`, `config('mediaserver.api_user')` y `config('mediaserver.api_password')` (true si los tres están no vacíos)

#### Scenario: Mock en tests
- **WHEN** un test llama `$this->app->instance(MediaserverApiService::class, $fake)`
- **THEN** todas las inyecciones posteriores en ese test SHALL recibir `$fake` en vez de la instancia real, confirmando que el binding es reemplazable vía el container estándar de Laravel

#### Scenario: Cambio futuro en la fuente de credenciales
- **WHEN** se modifique la implementación interna de `MediaserverApiService::fromConfig()` (por ejemplo, para leer de un secret manager en vez de `config()`)
- **THEN** SHALL poder hacerse sin tocar el binding del service provider, porque la resolución delega 100% al factory

### Requirement: MediaserverApiService expone métodos de lectura de métricas
`MediaserverApiService` SHALL exponer métodos de lectura para los endpoints de métricas de MediaServer Platform que el dashboard consume: `getStreams()`, `getStreamClients(string $name)`, `getDiagnostics()`, `getBlacklist()`, `getGeoblock()`, `getClientRules()`, `getSrtStatus()`, y `getHealth()`. Cada método SHALL devolver el payload JSON decodificado del endpoint correspondiente y SHALL lanzar `MediaserverApiException` si la API no está configurada, no responde, o devuelve un error HTTP.

#### Scenario: Dashboard consume getStreams
- **WHEN** el dashboard llama `MediaserverApiService::getStreams()`
- **THEN** SHALL devolver el array de streams del endpoint `GET /api/streams/` (o `[]` si el payload no trae `streams`)

#### Scenario: Dashboard consume getStreamClients
- **WHEN** el dashboard llama `MediaserverApiService::getStreamClients('cinedios')`
- **THEN** SHALL devolver el array de clientes del endpoint `GET /api/streams/{name}/clients` (o `[]` si el payload no trae `clients`)

#### Scenario: Dashboard consume reglas y diagnóstico
- **WHEN** el dashboard llama `getBlacklist()`, `getGeoblock()`, `getClientRules()`, `getDiagnostics()` o `getSrtStatus()`
- **THEN** SHALL devolver el payload JSON decodificado de los endpoints `/api/rules/blacklist`, `/api/rules/geoblock`, `/api/rules/clients`, `/api/diagnostics/streams` y `/api/srt/status` respectivamente

#### Scenario: API no configurada
- **WHEN** se llama a cualquiera de los métodos de lectura y `isConfigured()` es false
- **THEN** SHALL lanzar `MediaserverApiException` con `detail` indicando que la API no está configurada

### Requirement: El dashboard tolera fallos de la API sin romper la página
Los controllers de dashboard (admin y client) SHALL capturar `MediaserverApiException` y `Throwable` al consultar las métricas y SHALL pasar al dashboard un estado de disponibilidad (`mediaserver_available: false`) en lugar de propagar el error, de modo que la página se renderice completa con la sección de métricas degradada.

#### Scenario: La API lanza excepción durante el render
- **WHEN** `MediaserverApiService` lanza `MediaserverApiException` (o cualquier `Throwable`) al consultar métricas en el controller del dashboard
- **THEN** el controller captura la excepción, registra el fallo en el log, y pasa `mediaserver_available: false` con datos vacíos a la vista, que se renderiza sin error 500

#### Scenario: La API responde correctamente
- **WHEN** todas las llamadas a la API devuelven payloads válidos
- **THEN** el controller pasa `mediaserver_available: true` con los datos agregados a la vista
