## ADDED Requirements

### Requirement: Programación de inicio y fin de un destino

El sistema SHALL permitir al usuario (cliente y admin) definir, de forma **opcional**, `scheduled_start_at` y `scheduled_stop_at` para un destino restream. Ambos campos son timestamps con zona horaria. Cuando ambos están presentes, `scheduled_stop_at` SHALL ser estrictamente posterior a `scheduled_start_at`.

#### Scenario: Se guardan inicio y fin juntos
- **WHEN** el usuario crea un destino con `scheduled_start_at = mañana 10:00` y `scheduled_stop_at = mañana 12:00`
- **THEN** ambos se persisten y el destino queda `enabled=false, status=idle`

#### Scenario: Fin programado sin inicio es válido
- **WHEN** el usuario envía solo `scheduled_stop_at`
- **THEN** el destino se guarda con ese valor y sin `scheduled_start_at`

#### Scenario: Fin anterior o igual al inicio es rechazado
- **WHEN** el usuario envía `scheduled_start_at = 10:00` y `scheduled_stop_at = 10:00` (o anterior)
- **THEN** el servidor responde 422 con error de validación en `scheduled_stop_at`

#### Scenario: Campos vacíos limpian la programación
- **WHEN** el usuario edita un destino programado y deja los campos de fecha vacíos
- **THEN** `scheduled_start_at` y/o `scheduled_stop_at` quedan en NULL

### Requirement: Arranque automático programado vía orquestador

El comando `restream:launch-scheduled` (cada minuto vía `routes/console.php`) SHALL arrancar (no solo habilitar) los destinos cuya hora programada llegó: `scheduled_start_at <= now() AND enabled = false AND (pipeline_pid IS NULL OR latido no fresco)`. El arranque SHALL ejecutar `RestreamQuotaGuard::assertCanEnable` y luego `RestreamOrchestrator::start()`. Un fallo de cuota SHALL registrar `last_error` en el target y no abortar el lote.

#### Scenario: Target arranca dentro del minuto de su hora
- **WHEN** un destino tiene `scheduled_start_at <= now`, `enabled = false`, `pipeline_pid = NULL`
- **AND** el comando corre
- **THEN** el orquestador arranca el daemon, el target queda `enabled=true, status=starting` y luego `live` por latido

#### Scenario: Cuota agotada no rompe el lote
- **WHEN** dos destinos programados llegan a su hora y el usuario solo tiene 1 slot libre
- **THEN** uno arranca y el otro queda con `last_error` explicando la cuota, `enabled=false`, sin afectar a los demás targets del lote

#### Scenario: Idempotencia del arranque
- **WHEN** el comando corre dos veces seguidas para un target ya arrancado
- **THEN** la segunda pasada no duplica procesos ni muta el target (no-op)

### Requirement: Parada automática programada

El comando `restream:launch-scheduled` SHALL detener los destinos cuya hora de fin llegó: `scheduled_stop_at <= now() AND (enabled = true OR pipeline_pid IS NOT NULL)`, ejecutando `RestreamOrchestrator::stop()`. Tras detener, el comando SHALL limpiar `scheduled_stop_at` para garantizar idempotencia. El target NO se deshabilita automáticamente (el slot se libera solo con `deactivate` explícito del usuario).

#### Scenario: Parada automática a la hora de fin
- **WHEN** un target `live` tiene `scheduled_stop_at <= now`
- **THEN** el orquestador detiene el daemon, el target queda `status=idle`, `pipeline_pid=NULL`, y `scheduled_stop_at` queda en NULL

#### Scenario: Target sin fin programado no se toca
- **WHEN** el comando corre y un target `live` tiene `scheduled_stop_at = NULL`
- **THEN** el target no es modificado

#### Scenario: Fallo de parada no rompe el lote
- **WHEN** `stop()` lanza excepción para un target (p. ej. proceso ya muerto)
- **THEN** el comando registra el error, limpia `scheduled_stop_at` igualmente y continúa con los demás

### Requirement: Cron del scheduler instalado en el sistema

El servidor SHALL tener un crontab (o timer systemd) que ejecute `php artisan schedule:run` **cada minuto** para esta instalación; sin él, ninguna tarea `everyMinute` de `routes/console.php` se ejecuta. La instalación SHALL verificarse con `php artisan schedule:list`.

#### Scenario: schedule:run corre cada minuto
- **WHEN** el operador inspecciona `crontab -l` / el timer del sistema
- **THEN** existe la entrada `* * * * * php artisan schedule:run` apuntando al proyecto

#### Scenario: Verificación de salud del scheduler
- **WHEN** el operador ejecuta `php artisan schedule:list`
- **THEN** `restream:launch-scheduled` aparece como programado y su última ejecución es reciente (menos de 60s en la siguiente verificación)

### Requirement: YouTube broadcast refleja el horario programado

Cuando el destino tiene cuenta vinculada de YouTube y `scheduled_stop_at` presente, `YoutubeBroadcastService` SHALL enviar `snippet.scheduledEndTime` al crear y al actualizar el broadcast. Si la API rechaza el campo, el servicio SHALL degradar a no enviarlo (log de warning) sin fallar la creación/actualización del broadcast.

#### Scenario: Creación con hora de fin
- **WHEN** se crea un broadcast para un target con `scheduled_stop_at`
- **THEN** la petición a `liveBroadcasts` incluye `scheduledEndTime` en formato ISO-8601

#### Scenario: API rechaza scheduledEndTime
- **WHEN** YouTube responde error atribuible al campo de horario
- **THEN** el servicio reintenta sin `scheduledEndTime`, registra warning y el broadcast se crea igualmente

### Requirement: UI de programación de fin (cliente y admin, paridad)

El formulario de destino (modal cliente `restream-target-modal` y modal admin `restream-modal`) SHALL incluir el campo opcional "Programar fin" (`datetime-local`, minutos), con mínimo dinámico = valor de inicio (si está). El resumen del destino SHALL mostrar la fecha de fin programada cuando exista, con el mismo patrón visual que la de inicio. Toda clase Tailwind nueva SHALL pasar por `npm run build:css`.

#### Scenario: Modal cliente muestra campo de fin
- **WHEN** el usuario abre el modal de nuevo destino y selecciona YouTube con cuenta vinculada
- **THEN** ve "Programar fin (opcional)" con `datetime-local` y puede guardarlo junto al inicio

#### Scenario: Mínimo del fin ligado al inicio
- **WHEN** el usuario elige inicio 10:00
- **THEN** el input de fin no acepta fechas anteriores a 10:00 (atributo `min`)

#### Scenario: Paridad admin
- **WHEN** un admin crea o edita un destino desde el panel admin
- **THEN** el formulario ofrece los mismos campos (título, descripción, miniatura, inicio, fin) que el cliente