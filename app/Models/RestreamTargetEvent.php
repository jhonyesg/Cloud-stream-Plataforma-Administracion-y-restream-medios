<?php

namespace App\Models;

use App\Enums\RestreamTargetEventKind;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit row for a restream target. One row per state transition
 * or external failure, classified by `kind`. Use the engine / orchestrator /
 * watchdog to write here — never insert manually without going through
 * `TargetSchedulerService::recordEvent()` so the whitelist is enforced.
 */
class RestreamTargetEvent extends Model
{
    use HasUuids;

    protected $table = 'restream_target_events';

    /**
     * Append-only: the table has neither created_at nor updated_at. We
     * record the wall-clock moment on `occurred_at` (set explicitly on save)
     * and never let Eloquent touch a timestamp column.
     */
    public $timestamps = false;

    protected $fillable = [
        'target_id',
        'kind',
        'message',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(RestreamTarget::class, 'target_id');
    }

    public function scopeForTarget(Builder $query, string $targetId): Builder
    {
        return $query->where('target_id', $targetId);
    }

    public function scopeForKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function scopeRecent(Builder $query, int $limit = 50): Builder
    {
        return $query->orderByDesc('occurred_at')->limit($limit);
    }

    public function scopeSince(Builder $query, CarbonInterface $since): Builder
    {
        return $query->where('occurred_at', '>=', $since);
    }

    /**
     * Returns a Spanish-readable label for the operator UI. Includes the
     * broadcast id when present in metadata so the operator can identify
     * the specific YouTube artifact involved.
     */
    public function getHumanReasonAttribute(): string
    {
        $meta = (array) ($this->metadata ?? []);
        $broadcast = isset($meta['broadcast_id']) ? (string) $meta['broadcast_id'] : null;
        $window = isset($meta['window_id']) ? substr((string) $meta['window_id'], 0, 8) : null;
        $error = isset($meta['error']) ? (string) $meta['error'] : null;

        return match ($this->kind) {
            RestreamTargetEventKind::MANUAL_STARTED => 'Encendido manualmente desde la UI.',
            RestreamTargetEventKind::MANUAL_STOPPED => 'Detenido manualmente desde la UI.',
            RestreamTargetEventKind::ADMIN_STARTED => 'Encendido por un administrador.',
            RestreamTargetEventKind::ADMIN_STOPPED => 'Detenido por un administrador.',
            RestreamTargetEventKind::AUTO_STARTED => $window
                ? "Encendido automático por la ventana de programación {$window}."
                : 'Encendido automático por la ventana de programación.',
            RestreamTargetEventKind::AUTO_STOPPED => $window
                ? "Detenido automático al cerrar la ventana {$window}."
                : 'Detenido automático al cerrar la ventana de programación.',
            RestreamTargetEventKind::YOUTUBE_REVOKED => $broadcast
                ? "YouTube revocó el broadcast {$broadcast}. Revisa YouTube Studio."
                : 'YouTube revocó el broadcast. Revisa YouTube Studio.',
            RestreamTargetEventKind::YOUTUBE_CONSUMED_REMOTELY => $broadcast
                ? "El broadcast {$broadcast} se cerró por un corte de RTMP en YouTube."
                : 'El broadcast se cerró por un corte de RTMP en YouTube.',
            RestreamTargetEventKind::YOUTUBE_HEALTH_LOST => 'YouTube dejó de recibir datos del stream (healthStatus=noData).',
            RestreamTargetEventKind::FFMPEG_DIED => 'El proceso FFmpeg del daemon murió sin razón clara. Revisa el log.',
            RestreamTargetEventKind::NETWORK_UNREACHABLE => 'No se pudo alcanzar el origen o el destino de la transmisión.',
            RestreamTargetEventKind::OAUTH_EXPIRED => 'El token OAuth de la plataforma expiró. Reconecta la cuenta.',
            RestreamTargetEventKind::QUOTA_EXCEEDED => 'Sin slots disponibles en la cuota. Libera un destino o sube el tier.',
            RestreamTargetEventKind::WATCHDOG_RECOVERY_SUCCEEDED => 'El watchdog recreó el broadcast y el daemon volvió a emitir.',
            RestreamTargetEventKind::WATCHDOG_RECOVERY_EXHAUSTED => 'El watchdog intentó recrear el broadcast N veces sin éxito. Emisión caída.',
            RestreamTargetEventKind::LEGACY_FAILURE_ARCHIVED => 'Error migrado del campo legacy `last_error`.',
            RestreamTargetEventKind::YOUTUBE_TRANSITION_SUCCEEDED => $broadcast
                ? "YouTube transicionó el broadcast {$broadcast} a `complete`."
                : 'YouTube transicionó el broadcast a `complete`.',
            RestreamTargetEventKind::YOUTUBE_TRANSITION_FAILED => $broadcast
                ? "YouTube rechazó la transición del broadcast {$broadcast} a `complete`."
                    . ($error ? " Causa: {$error}" : '')
                : 'YouTube rechazó la transición del broadcast a `complete`.'
                    . ($error ? " Causa: {$error}" : ''),
            RestreamTargetEventKind::YOUTUBE_ZOMBIE_DETECTED => $broadcast
                ? "Broadcast {$broadcast} seguía `live` en YouTube aunque el daemon ya estaba inactivo aquí."
                : 'Broadcast zombie detectado en YouTube: seguía `live` aunque el daemon estaba inactivo.',
            RestreamTargetEventKind::YOUTUBE_ZOMBIE_RECOVERED => $broadcast
                ? "Broadcast zombie {$broadcast} transicionado a `complete` y binding local limpiado."
                : 'Broadcast zombie transicionado a `complete` y binding local limpiado.',
            RestreamTargetEventKind::YOUTUBE_ZOMBIE_RECOVERY_FAILED => $broadcast
                ? "No se pudo limpiar el broadcast zombie {$broadcast}."
                    . ($error ? " Causa: {$error}" : '')
                : 'No se pudo limpiar un broadcast zombie.'
                    . ($error ? " Causa: {$error}" : ''),
            default => "Evento del sistema: {$this->kind}.",
        };
    }

    /**
     * Color + icon hint for the UI. Pure presentation logic that lives next
     * to the model so it stays in sync with the whitelist above.
     *
     * @return array{color: string, icon: string}
     */
    public function getPresentationAttribute(): array
    {
        return match ($this->kind) {
            RestreamTargetEventKind::AUTO_STARTED,
            RestreamTargetEventKind::MANUAL_STARTED,
            RestreamTargetEventKind::ADMIN_STARTED,
            RestreamTargetEventKind::WATCHDOG_RECOVERY_SUCCEEDED,
            RestreamTargetEventKind::YOUTUBE_TRANSITION_SUCCEEDED,
            RestreamTargetEventKind::YOUTUBE_ZOMBIE_RECOVERED => ['color' => 'green', 'icon' => 'check'],

            RestreamTargetEventKind::AUTO_STOPPED,
            RestreamTargetEventKind::MANUAL_STOPPED,
            RestreamTargetEventKind::ADMIN_STOPPED => ['color' => 'blue', 'icon' => 'stop'],

            RestreamTargetEventKind::YOUTUBE_REVOKED,
            RestreamTargetEventKind::WATCHDOG_RECOVERY_EXHAUSTED,
            RestreamTargetEventKind::OAUTH_EXPIRED,
            RestreamTargetEventKind::YOUTUBE_ZOMBIE_DETECTED,
            RestreamTargetEventKind::YOUTUBE_TRANSITION_FAILED,
            RestreamTargetEventKind::YOUTUBE_ZOMBIE_RECOVERY_FAILED => ['color' => 'red', 'icon' => 'ban'],

            RestreamTargetEventKind::YOUTUBE_CONSUMED_REMOTELY,
            RestreamTargetEventKind::YOUTUBE_HEALTH_LOST,
            RestreamTargetEventKind::FFMPEG_DIED,
            RestreamTargetEventKind::NETWORK_UNREACHABLE,
            RestreamTargetEventKind::QUOTA_EXCEEDED => ['color' => 'amber', 'icon' => 'alert-triangle'],

            default => ['color' => 'gray', 'icon' => 'circle'],
        };
    }
}
