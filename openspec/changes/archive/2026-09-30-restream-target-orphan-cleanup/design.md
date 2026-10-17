## Design

### Decisión 1 — `forceDelete()` en el rollback de `store()`

**Hoy:**
```php
// Client\RestreamTargetController.php:120-127
} catch (RestreamPlatformAccountException $e) {
    if (isset($target)) {
        $target->delete();            // soft-delete — deja huérfano
    }
    throw ValidationException::withMessages(['restream' => $e->getMessage()]);
}
```

**Después:**
```php
} catch (RestreamPlatformAccountException $e) {
    if (isset($target)) {
        $target->forceDelete();       // hard delete — la fila nunca estuvo viva
    }
    throw ValidationException::withMessages(['restream' => $e->getMessage()]);
}
```

**Por qué:** la fila es recién creada, nunca fue confirmada por el cliente, nunca consumió slot, nunca ejecutó el daemon, nunca tuvo `stream_key` ni `platform_broadcast_id`. Preservarla como soft-deleted es ruido — y rompe el flujo de retry. La trazabilidad del fallo vive en `audit_logs` (a través del `AuditLog::record` previo) y en el log de la plataforma externa.

**Mismo cambio en** `Admin\RestreamTargetController.php:238-243`.

### Decisión 2 — Distinguir "abortada" vs "archivada" en `assertNoDuplicateTriple`

**Hoy:**
```php
protected function assertNoDuplicateTriple(int|string $userId, int|string $channelId, ?string $platform, ?string $platformAccountId = null): void
{
    if (! $platform) return;

    $existing = RestreamTarget::withTrashed()
        ->where('user_id', $userId)
        ->where('channel_id', $channelId)
        ->where('platform', $platform)
        ->first();

    if ($existing) {
        $message = $existing->trashed()
            ? 'Ya existe un destino eliminado para esta plataforma en este canal. Restáuralo o elimínalo definitivamente para crear uno nuevo.'
            : 'Ya tienes un destino activo para esta plataforma en este canal.';
        throw ValidationException::withMessages(['restream' => $message]);
    }
}
```

**Después:**
```php
protected function assertNoDuplicateTriple(int|string $userId, int|string $channelId, ?string $platform, ?string $platformAccountId = null): void
{
    if (! $platform) return;

    // Step 1: auto-purge every aborted-create orphan matching the triplet.
    RestreamTarget::withTrashed()
        ->where('user_id', $userId)
        ->where('channel_id', $channelId)
        ->where('platform', $platform)
        ->get()
        ->filter(fn (RestreamTarget $t) => $this->isAbortedOrphan($t))
        ->each(fn (RestreamTarget $t) => $t->forceDelete());

    // Step 2: re-query; surface 422 for archived or active.
    $existing = RestreamTarget::withTrashed()
        ->where('user_id', $userId)
        ->where('channel_id', $channelId)
        ->where('platform', $platform)
        ->first();

    if (! $existing) return;

    if ($existing->trashed()) {
        throw ValidationException::withMessages([
            'restream' => 'Ya existe un destino archivado para esta plataforma en este canal. Restáuralo o elimínalo definitivamente para crear uno nuevo.',
        ]);
    }

    throw ValidationException::withMessages([
        'restream' => 'Ya tienes un destino activo para esta plataforma en este canal.',
    ]);
}

protected function isAbortedOrphan(RestreamTarget $target): bool
{
    return $target->trashed()
        && $target->stream_key === null
        && $target->platform_broadcast_id === null
        && $target->status === RestreamTarget::STATUS_IDLE;
}
```

**Por qué esos 4 predicados en AND** (NO incluimos `enabled`):

| Predicado | Justificación |
|---|---|
| `trashed()` | Solo aplica a soft-deleted. La fila activa sigue dando el mensaje "ya tienes un destino activo". |
| `stream_key IS NULL` | Un destino que recibió clave de stream fue, como mínimo, **intentado configurar** por el cliente. Eso es contenido que el cliente querría recuperar. |
| `platform_broadcast_id IS NULL` | Simétrico: si OAuth llegó a crear un broadcast remoto, hay un recurso externo ligado al target. |
| `status = 'idle'` | Un destino que llegó a `live` o `error` o `starting` fue operacional. |

**Por qué NO incluimos `enabled`** (corrección durante implementación): el flujo de `Client\RestreamTargetController::store` (línea 83) defaultea `$enabled = (bool) ($data['enabled'] ?? true)`. Es decir, un destino abortado en el rollback **típicamente lleva `enabled = true`**, no `false`. El primer huérfano real que descubrimos en producción ("Youtobe", `a2dd6893-…`) tenía `enabled=T` precisamente por eso. La señal decisiva no es el flag de enabled — es "nunca tuvo datos reales attached": `stream_key` + `platform_broadcast_id`.

**Por qué dos pasos en lugar de uno:** el primer `get()` purga TODOS los huérfanos que coincidan (puede haber varios intentos fallidos). El segundo `first()` evalúa si queda **alguna** fila bloqueante (archivada o activa). Si el cliente tenía un archivado legítimo y un huérfano abortado, purgamos el huérfano y seguimos viendo el archivado con su mensaje accionable — el cliente puede decidir restaurar o force-deletearlo. Si solo había huérfanos, los purga todos y la re-creación procede.

**Mensaje cambiado:** "eliminado" → "archivado". El verbo importa porque en la UI el flujo se llama "Archivar" (botón Inactivar preserva, botón Eliminar force-deletdea; no hay flujo de "Eliminar" en cliente que produzca soft-delete excepto el admin).

### Decisión 3 — Duplicar, no extraer (esta vez)

La regla dual-implementation del proyecto dice: si cambia `Client\RestreamTargetController`, también `Admin\RestreamTargetController`. Extraer un trait `AssertsNoDuplicateRestreamTriple` es factible pero **no vale la pena** para un helper de 15 líneas. El codebase ya duplica helpers entre los dos controladores (ver `Client\RestreamTargetController::createPlatformBroadcast` vs `Admin\RestreamTargetController::createPlatformBroadcast` — son casi idénticas). Mantener el patrón del proyecto: **duplicar y sincronizar**.

### Decisión 4 — Comando `restream:purge-aborted-targets`

Para limpiar las filas que **ya** están en producción hoy (este bug lleva meses, probablemente):

```
php artisan restream:purge-aborted-targets               # dry-run (default)
php artisan restream:purge-aborted-targets --apply        # mutar
php artisan restream:purge-aborted-targets --channel=UUID # scope a un canal
php artisan restream:purge-aborted-targets --json         # piping
```

Usa exactamente la misma firma de "abortada" que el validador. Dry-run muestra tabla: id, user_id, channel_id, platform, created_at, deleted_at.

**Auditoría:** una sola fila `audit_logs` con `action='purge_aborted.restream_target'`, `metadata={purged_count, criteria}` y `entity_id=null` (es bulk). Si se filtra por canal, `channel_id` se setea al del filtro.

### Decisión 5 — UI "Archivados" (admin + cliente)

Una sección colapsable al final de la tabla principal:

```
┌─ Archivados (N) ─────────────────────────────[+]┐
│ Plataforma │ Nombre    │ Slot  │ Archivado     │
│ YouTube    │ pruebas   │ —     │ hace 3 días [Restaurar] [Eliminar] │
│ Facebook   │ pruebas-2 │ —     │ hace 12 días [Restaurar] [Eliminar] │
└────────────────────────────────────────────────┘
```

- Default colapsado. Click en `+` expande.
- Botones llaman a los endpoints existentes (`POST .../restore`, `DELETE .../force`). Cero rutas nuevas.
- En cliente: solo ve **sus propios** archivados.
- En admin: ve todos (panel diagnóstico), pero los botones siguen siendo por-target con su `channel_id` para el endpoint.

Esto es **opcional** para el fix core pero alta-UX para el operador. Si el alcance crece, se puede mover a un change separado.

### Lo que NO hacemos

- **No** cambiamos el índice único parcial de Postgres. Sigue siendo `WHERE deleted_at IS NULL` y eso es correcto — los soft-deleted reales (archivados por el usuario) siguen siendo restaurables, y los huérfanos abortados se purgan por aplicación antes de evaluar el índice.
- **No** agregamos una columna `is_aborted` a la tabla. El predicado en el validador se evalúa contra los datos que ya están, lo cual es durable y no requiere migración.
- **No** modificamos `Admin\RestreamTargetController::destroy`. Su soft-delete explícito es **intencional** y forma parte del flujo admin. Lo que sí agregamos es que su fila resultante **sí** aparece en el validador como "archivada" — el comportamiento actual ya es correcto, solo le faltaba la UI.

### Compatibilidad hacia atrás

- Cliente que tenía un destino soft-deleted **antes** de este fix: el validador lo evalúa como "archivada" (porque tiene `stream_key` o `platform_broadcast_id` o no cumple la firma de abortada) → mismo 422 que antes, mismo flujo `restore` o `force-destroy`. **Cero regresión.**
- Cliente cuya fila soft-deleted **es** una abortada: hoy está pegado. Después del fix, se auto-purga al intentar crear → **fix**.
- Cliente que está creando un destino nuevo (sin historial): idéntico al flujo actual. **Cero regresión.**
- Admin archivando un destino real: el soft-delete explícito sigue intacto. Aparece en "Archivados" como antes (cuando esté la UI). **Cero regresión.**
