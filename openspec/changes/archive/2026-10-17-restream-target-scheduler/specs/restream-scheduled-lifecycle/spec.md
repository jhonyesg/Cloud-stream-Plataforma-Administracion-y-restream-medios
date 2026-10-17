## MODIFIED Requirements

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