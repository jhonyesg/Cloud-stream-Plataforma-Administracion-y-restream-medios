# restream-scheduled-lifecycle Specification

## Purpose
TBD - created by archiving change fix-restream-youtube-scheduling. Update Purpose after archive.

## Requirements

### Requirement: Programación de inicio y fin de un destino

**Reason**: Los campos inline `restream_targets.scheduled_start_at` y `restream_targets.scheduled_stop_at` se eliminan en favor de la entidad `restream_target_schedules` definida en la capability `restream-target-scheduling`. Toda la programación por destino vive ahora en filas de esa tabla, con N ventanas, fin opcional, timezone y calendar UI. Esta capability queda como referencia histórica.

**Migration**: El código que aún lea/escriba `scheduled_start_at`/`scheduled_stop_at` debe migrarse a usar `target->schedules()`. El comando legacy `restream:launch-scheduled` se mantiene durante 1 sprint como no-op silencioso, después se borra (ver Open Question Q1 en `design.md`). El sistema SHALL reflejar que las columnas legacy ya no existen en producción tras la migración.

#### Scenario: Se guardan inicio y fin juntos
- **WHEN** el operador guarda `starts_at=2026-10-01 16:00` y `ends_at=2026-10-01 18:00` en una sola operación
- **THEN** se persiste una fila en `restream_target_schedules` con ambos timestamps en UTC, `timezone='America/Bogota'`, `enabled=true`

#### Scenario: Fin programado sin inicio es válido
- **WHEN** el operador guarda `starts_at=NULL` y `ends_at=2026-10-01 18:00` (fin sin inicio explícito)
- **THEN** la fila se persiste con `starts_at=NULL`; el motor del scheduler la trata como "vigente desde siempre hasta ese fin"

#### Scenario: Fin anterior o igual al inicio es rechazado
- **WHEN** el operador guarda `ends_at <= starts_at`
- **THEN** la validación del request devuelve `422 Unprocessable Entity` con `errors.ends_at = ['fin debe ser posterior al inicio']` y la fila no se persiste

#### Scenario: Campos vacíos limpian la programación
- **WHEN** el operador edita un target y deja `starts_at` y `ends_at` vacíos
- **THEN** la fila se persiste con ambos timestamps `NULL`; el motor no la considera aplicable (`applicableAt` no la incluye en la lista de ventanas activas)

#### Scenario: Código legacy accede a columnas eliminadas
- **WHEN** alguna parte del código (e.g. un comando, un controlador legacy que se olvidó migrar) lee `target->scheduled_start_at`
- **THEN** PostgreSQL rechaza con `column does not exist` y la app falla ruidosamente; en `error_log` queda la traza apuntando al path que necesita migración (defensa a favor de descubrir código legacy olvidado, no falla silenciosa)

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

### Requirement: Schedule view shows a live countdown to `ends_at` for running and recently-ended schedules

The client and admin schedule index (`resources/views/{client,admin}/restream/target/schedules/index.blade.php`) and the calendar chip component (`resources/views/components/target-schedule-calendar.blade.php`) MUST display, alongside the existing status badge, a per-row countdown label that updates every second on the client. The countdown MUST be the same on both surfaces.

#### Scenario: Running schedule shows "Termina en HH:MM:SS"

- **GIVEN** a schedule with `starts_at < now() < ends_at` and `status='running'`
- **WHEN** the operator opens the schedule page
- **THEN** the row shows `Termina en HH:MM:SS` where the value decreases once per second without server polling
- **WHEN** the countdown reaches zero
- **THEN** the label flips to `Finalizó hace Xm` and stays that way

#### Scenario: Indefinite schedule shows "Sin fin"

- **GIVEN** a schedule with `is_indefinite=true` and `status='running'`
- **WHEN** the operator opens the schedule page
- **THEN** the countdown cell shows `Sin fin` (no numeric counter)

#### Scenario: Pending schedule shows "Inicia en HH:MM:SS"

- **GIVEN** a schedule with `starts_at > now()` and `status='pending'`
- **WHEN** the operator opens the schedule page
- **THEN** the countdown cell shows `Inicia en HH:MM:SS` decreasing once per second

#### Scenario: Ended schedule shows "Finalizó hace Xm"

- **GIVEN** a schedule with `now() > ends_at` and `status='ended'`
- **WHEN** the operator opens the schedule page
- **THEN** the countdown cell shows `Finalizó hace 5m` (or `2h 13m`, whichever is the smallest unit that fits)

#### Scenario: Calendar grid chip includes the countdown

- **WHEN** the operator looks at the calendar grid for a day with a `running` schedule
- **THEN** the chip on that day cell renders the countdown text in addition to the schedule name and start time

#### Scenario: Parity client/admin

- **WHEN** the operator opens `/client/restream/restream-targets/{t}/schedules` and `/admin/channels/{c}/restream-targets/{t}/schedules` for the same target
- **THEN** both pages show the same countdown text for the same schedule
