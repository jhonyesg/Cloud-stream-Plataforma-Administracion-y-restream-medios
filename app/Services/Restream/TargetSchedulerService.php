<?php

namespace App\Services\Restream;

use App\Enums\RestreamTargetEventKind;
use App\Models\RestreamTarget;
use App\Models\RestreamTargetEvent;
use App\Models\RestreamTargetSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Coordinator between the engine (commands) and the rest of the system
 * (models, orchestrator, audit). Centralises three concerns:
 *
 * 1. Window resolution: given a target + moment, which schedule applies?
 * 2. Idempotent start/stop bridging to the existing `RestreamOrchestrator`.
 * 3. Append-only event recording through the closed `kind` whitelist.
 */
class TargetSchedulerService
{
    /**
     * Resolve the orchestrator lazily via the container. We don't inject it
     * through the constructor because that creates a circular dependency:
     * RestreamOrchestrator also accepts TargetSchedulerService (used by
     * stop() to record youtube_transition_* events), and the container's
     * auto-resolution would loop forever between the two.
     */
    private function orchestrator(): RestreamOrchestrator
    {
        return app(RestreamOrchestrator::class);
    }

    /**
     * Returns the most recently started applicable window for the target
     * at the given moment, or null when none is in range.
     */
    public function findApplicableWindow(RestreamTarget $target, ?CarbonInterface $moment = null): ?RestreamTargetSchedule
    {
        $moment ??= now();

        return RestreamTargetSchedule::query()
            ->forTarget($target->id)
            ->applicableAt($moment)
            ->where('stopped_by_user', false)
            ->orderByDesc('starts_at')
            ->first();
    }

    /**
     * Start the target because of (or on behalf of) a window. Idempotent:
     * `RestreamOrchestrator::start()` is already a no-op when the daemon is
     * alive, and `refreshDeadBroadcast()` handles dead YouTube broadcasts.
     */
    public function startTargetForWindow(RestreamTargetSchedule $window, string $causer): void
    {
        $target = $window->target;
        if (! $target) {
            return;
        }

        if (! $target->enabled) {
            // Disabled targets are intentionally ignored even if a window is
            // currently applicable — the operator has chosen to opt out.
            return;
        }

        // Resuelve los overrides de la ventana (miniatura, título, privacidad,
        // grabación) y los pasa al orquestador para que el broadcast de YouTube
        // los use en vez de los del destino.
        $context = [];
        if ($window->thumbnail_media_id) {
            $context['thumbnail_media_id'] = $window->thumbnail_media_id;
        } elseif ($window->thumbnail_path) {
            $context['thumbnail_path'] = $window->thumbnail_path;
        }
        if ($window->schedule_title) {
            $context['title'] = $window->schedule_title;
        }
        if ($window->schedule_description) {
            $context['description'] = $window->schedule_description;
        }
        if ($window->platform_privacy) {
            $context['platform_privacy'] = $window->platform_privacy;
        }
        if ($window->keep_recording !== null) {
            $context['keep_recording'] = (bool) $window->keep_recording;
        }

        try {
            $this->orchestrator()->start($target, false, $context);
        } catch (\App\Services\Restream\RestreamQuotaException $e) {
            $this->recordEvent($target, RestreamTargetEventKind::QUOTA_EXCEEDED, [
                'window_id' => $window->id,
                'error' => $e->getMessage(),
            ]);

            return;
        } catch (\Throwable $e) {
            $this->recordEvent($target, RestreamTargetEventKind::FFMPEG_DIED, [
                'window_id' => $window->id,
                'causer' => $causer,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $kind = match ($causer) {
            'admin' => RestreamTargetEventKind::ADMIN_STARTED,
            'manual' => RestreamTargetEventKind::MANUAL_STARTED,
            default => RestreamTargetEventKind::AUTO_STARTED,
        };

        $this->recordEvent($target, $kind, [
            'window_id' => $window->id,
            'broadcast_id' => $target->platform_broadcast_id,
        ]);
    }

    /**
     * Stop the target because the applicable window has ended (or the user
     * pressed "Detener ahora"). Idempotent — the orchestrator handles a
     * target that's already idle.
     *
     * If the user explicitly stopped a window that is still applicable,
     * we mark it `stopped_by_user` so the engine does not silently restart
     * the broadcast on the next tick.
     */
    public function stopTargetAfterWindow(RestreamTargetSchedule $window, string $causer, bool $userInitiated = false): void
    {
        $target = $window->target;
        if (! $target) {
            return;
        }

        $this->orchestrator()->stop($target);

        if ($userInitiated && $window->is_indefinite || $userInitiated) {
            $window->forceFill(['stopped_by_user' => true])->save();
        }

        $kind = match ($causer) {
            'admin' => RestreamTargetEventKind::ADMIN_STOPPED,
            'manual' => RestreamTargetEventKind::MANUAL_STOPPED,
            default => RestreamTargetEventKind::AUTO_STOPPED,
        };

        $this->recordEvent($target, $kind, [
            'window_id' => $window->id,
        ]);
    }

    /**
     * Append a row to the target's history with the closed-whitelist `kind`.
     * Throws on unknown kinds so a typo in caller code fails loud, not silent.
     */
    public function recordEvent(RestreamTarget $target, string $kind, array $metadata = []): RestreamTargetEvent
    {
        RestreamTargetEventKind::tryFrom($kind);

        $event = new RestreamTargetEvent([
            'target_id' => $target->id,
            'kind' => $kind,
            'message' => null,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
        $event->save();

        Log::info("[restream:event] {$kind}", array_merge([
            'target_id' => $target->id,
            'platform' => $target->platform,
        ], $metadata));

        return $event;
    }
}
