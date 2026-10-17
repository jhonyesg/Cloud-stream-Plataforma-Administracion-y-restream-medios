## Context

`ScheduleTemplate` tiene tres estados (`draft|active|archived`) y la migración base impone `UNIQUE INDEX uq_schedule_tmpl_active ON schedule_templates(channel_id, year, month, status)`, lo que garantiza un solo `active` por `(channel, year, month)`. La promoción `draft → active` ocurre hoy en un único lugar: `ScheduleTemplateController::autoActivateIfNeeded` (líneas 285-326), llamado tras `assignDay`, `replicate` y `replicateRange`.

Esa única ruta falla en dejar datos consistentes cuando los bloques se insertan por un camino distinto al endpoint (restauración de `database/snapshots/release/production.json.gz` con `ProductionDataSeeder`, tinker, fixtures de test, migraciones que tocan bloques). El resultado: plantillas con `status='draft'` y `blocks_count > 0`. El usuario ve la insignia "Draft" en `/client/scheduler`, pulsa "Iniciar transmisión", `ScheduledPlaylistResolver::resolveForNow()` (líneas 28-34) lanza `EmisionStartExceptionFactory::noActiveTemplate` con el mensaje "Activala desde el módulo Programación" — pero no existe tal botón. Dead-end de UX.

El codebase ya tiene tres patrones similares de auto-curación en producción: `Playlist::normalizeSplitOrdering` invocado al `cleanup-splits` y al `emission start`, `EmissionLogRotator` corre on-write, `TimelineBuilder::buildForDay` se llama tras `assignDay`. El heal encaja en esa cultura.

## Goals / Non-Goals

**Goals:**
- Eliminar el estado inconsistente `draft + blocks_count > 0` desde múltiples puntos de entrada (read, write, restore, manual).
- Mantener el comportamiento actual de `assignDay` / `replicate` / `replicateRange` intacto (la auto-activación por asignación sigue igual).
- Dejar rastro de auditoría claro de toda promoción vía heal.
- Proveer una herramienta de operación (`schedule-templates:heal-drafts`) para que soporte no dependa de esperar a que un cliente entre al scheduler.
- Respetar el contrato de doble capa: el heal NO toca `channels.status`. Canales suspendidos o archivados mantienen su plantilla en `draft` aunque tengan bloques.

**Non-Goals:**
- **No** modifica los endpoints existentes `assignDay`, `replicate`, `replicateRange` ni el contrato de `emission/start`.
- **No** cambia la autorización de `EmissionController::start` (queda como gap separado ver `AGENTS.md` — el endpoint no chequea `channels.status`).
- **No** añade UI nueva (la insignia "Draft" → "Active" se actualiza en el siguiente render sin acción del usuario).
- **No** introduce lock optimista, `Cache::lock` ni mutex — el `UNIQUE INDEX` y el guard `status != draft` ya son race-safe.

## Decisions

### Decision 1: Servicio explícito `TemplateActivator`, no observer de Eloquent

**Choice:** Nuevo servicio `App\Services\Scheduler\TemplateActivator` con un único método público `activateIfHealable(ScheduleTemplate $t): bool`.

**Alternativas consideradas:**

- *Eloquent observer* (`retrieved`/`saving`) → dispararía en cada query (writes implícitos en lecturas — mala higiene) y no permite condicionar por `channels.status` sin acoplar el modelo a su contexto.
- *Trait en ScheduleTemplate* → mete lógica de producto en el modelo y dificulta testing aislado.
- *Método estático en ScheduleTemplate* → mismo problema que el trait, peor para DI/mocking.

**Why:** El heal no es invariante del modelo — es una decisión de producto con tres inputs (`status`, `blocks_count`, `channels.status`). Un servicio explícito permite testing aislado, DI limpia, y deja claro al lector "esto es heal, no parte del modelo". Refactoriza `ScheduleTemplateController::autoActivateIfNeeded` extrayéndolo al servicio; los tres call sites del controller pasan a llamar al servicio (sin cambio de comportamiento).

### Decision 2: Heal silencioso en emission/start, no bloqueante

**Choice:** En `ScheduledPlaylistResolver::resolveActiveTemplate`, si la primera pasada con `where status='active'` no devuelve nada, hacer una segunda pasada que busca el template en `draft` para `(channel, year, month)`, intenta `TemplateActivator::activateIfHealable`, y si retorna `true` reusa ese template. Si retorna `false`, lanza `noActiveTemplate` como hoy.

**Alternativa descartada:** Bloquear la emisión con un error tipo "tu programación está en draft, actívala manualmente" — descartada porque (a) no hay UI para hacerlo, (b) empeora la DX sin agregar seguridad real (la auditoría queda), (c) el heal ya filtra por `channels.status` para no promover canales suspendidos.

**Why:** El heal silencioso es consistente con `Playlist::normalizeSplitOrdering` (también silencioso, también con audit). El usuario recibe éxito en el botón "Iniciar transmisión", y el operador puede revisar `audit_logs WHERE action='update.schedule_template.heal'` si necesita saber qué pasó.

### Decision 3: Heal en `ScheduleController::index` (admin + client), no en middleware

**Choice:** Llamar `TemplateActivator::activateIfHealable` justo después de cargar `$template` en ambos controllers, antes de pasar al view. Idempotente gracias al guard `status != draft`.

**Alternativa descartada:** Middleware global sobre rutas `/scheduler` — overkill para una sola operación, complica testing y mete lógica de negocio en la capa HTTP.

**Why:** El read site es el más barato y el más visible — el usuario refresca la página y ve "Active" sin hacer nada. Cubre los dos canales (admin, client) y satisface la regla "dual-implementation" sin duplicar lógica de UI (la insignia ya es una Blade helper compartida).

### Decision 4: Comando Artisan `schedule-templates:heal-drafts` con `--channel` y `--json`

**Choice:** `php artisan schedule-templates:heal-drafts [--channel=ID] [--json] [--dry-run]`. Por defecto recorre todos los canales con templates en `draft + blocks_count > 0` y los promueve. Dry-run por defecto para seguridad; `--apply` (o flag explícito) para aplicar. `--channel=ID` lo limita a un canal. `--json` emite `{healed: [...], skipped: [...], channel_id, template_id, before, after}` para piping a `jq` o a dashboards.

**Alternativa descartada:** Sub-comando de `db:check-data` — no encaja con el dominio (db:check-data es validación de integridad, no curación).

**Why:** Comando separado, dominio claro, idempotente. Lo va a usar soporte tras un `migrate:fresh --seed` para verificar que el seeder curó bien, o para curar datos pre-existentes que se quedaron en `draft` antes del deploy del heal automático.

### Decision 5: Heal en `ProductionDataSeeder` después de insertar `schedule_blocks`

**Choice:** En `ProductionDataSeeder::run()`, después del loop de inserción de `schedule_blocks`, escanear `schedule_templates` donde `status='draft' AND id IN (SELECT template_id FROM schedule_blocks)` y promoverlos dentro de la misma transacción. Si no hay ninguno, no-op.

**Alternativa descartada:** Trigger Postgres `AFTER INSERT ON schedule_blocks` que llame a la promoción — invasivo, rompe la portabilidad del seeder, complica testing local con SQLite.

**Why:** El seeder ya usa `DB::transaction` por tabla; añadir una pasada curativa al final es local y trivialmente testeable. Cura el origen conocido (snapshot restore) sin depender de que el usuario entre al scheduler.

### Decision 6: Audit action name = `update.schedule_template.heal`

**Choice:** Convencional con el patrón existente (`update.schedule_template.auto_activate`, `update.schedule_template.auto_archive_on_promote`, `create.schedule_template`). Permite filtrar fácil con `WHERE action LIKE 'update.schedule_template.%'`.

**Why:** Diferencia clara entre "auto-activate por asignación de día" (acción explícita del usuario) y "heal por estado inconsistente" (curación silenciosa). Los reportes de soporte pueden separar uno de otro.

## Risks / Trade-offs

- **[Heal masivo accidental]** — un comando Artisan sin flag `--dry-run` por defecto promovería todos los templates inconsistentes. → Mitigación: dry-run por defecto; flag `--apply` explícito para aplicar; `--json` siempre emite qué va a hacer antes de hacerlo.

- **[Heal sobre canal suspendido]** — si el guard `channels.status` falla (e.g. race condition entre la lectura del status y la promoción), podríamos promover una plantilla de un canal suspendido. → Mitigación: la guarda se evalúa dentro del servicio **antes** del `DB::transaction` y se vuelve a evaluar como `SELECT ... FOR UPDATE` dentro de la transacción en una segunda versión si se observa el problema. Por ahora, single read es suficiente porque el snapshot de producción se asume coherente.

- **[Audit log spam]** — si un cliente abre el scheduler muchas veces, ¿se generan muchos audit entries? → Mitigación: el guard `status != draft → return` garantiza que el heal sólo corre una vez por plantilla. Después es no-op silencioso sin escribir a audit_logs.

- **[Doble heal concurrente]** — dos requests paralelos al scheduler podrían intentar promover el mismo template. → Mitigación: el `UNIQUE INDEX uq_schedule_tmpl_active` rechaza el segundo INSERT/UPDATE con constraint violation; el servicio captura `UniqueConstraintViolationException` (o equivalente Postgres) y retorna `false` sin propagar. La transacción interna del servicio garantiza atomicidad.

- **[El heal no resuelve el gap de autorización en emission/start]** — un canal `suspended` puede emitir porque el endpoint no chequea `channels.status`. → Mitigación: queda fuera de scope (Non-Goals). Se documenta como follow-up sugerido en el proposal. El guard `channels.status` del heal sí cubre el camino del scheduler, que es donde el cliente ve la insignia.

- **[El operador no se entera del heal silencioso en emission/start]** — la insignia "Draft" puede pasar a "live" sin warning visible. → Mitigación: la insignia del scheduler se actualiza en el siguiente render (read-site heal). El log de emisión registra el inicio normal. La auditoría está disponible para consulta post-mortem.

## Migration Plan

Sin schema migration. Pasos de deploy:

1. `php artisan db:backup` (protocolo AGENTS.md).
2. Merge del PR con servicio + 4 call sites + comando + cambio en seeder.
3. `composer dump-autoload` para registrar el nuevo comando Artisan.
4. `php artisan schedule-templates:heal-drafts --dry-run --json | jq '.healed | length'` — ver cuántos templates van a ser curados en producción.
5. Si el número es razonable: `php artisan schedule-templates:heal-drafts --apply` — curación inmediata. (O dejar que se cure solo conforme los clientes entren al scheduler / arranquen emisiones.)
6. Verificar `audit_logs WHERE action='update.schedule_template.heal' ORDER BY created_at DESC LIMIT 20` — confirmar que las promociones tienen sentido.

Rollback: revert del PR. El servicio nunca borra — sólo promueve `draft → active`. Revertir devuelve el sistema al estado anterior con plantillas en `draft` y bloques, igual que antes del deploy. Si el operador quiere forzar el rollback explícito: `php artisan tinker` → `ScheduleTemplate::where('status','active')->whereIn('id', $ids)->update(['status'=>'draft'])` después de revertir.

## Open Questions

- **¿El comando Artisan debe aceptar `--apply` o `--commit`?** — Convención `db:backup` no usa flag, pero `media:hygiene` sí (`--apply`). Apunto a `--apply` por consistencia con `media:hygiene`, decisión final al implementar.
- **¿`schedule-templates:heal-drafts` debe correr automáticamente en `migrate:safe`?** — Por ahora no; el seeder cubre `migrate:fresh --seed`, y los datos en vivo se curan en read. Si tras deploy se observa que muchos canales están en draft sin que nadie haya entrado al scheduler, se puede añadir como paso opcional del comando. Decisión post-implementación.
