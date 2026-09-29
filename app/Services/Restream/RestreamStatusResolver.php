<?php

namespace App\Services\Restream;

use App\Models\RestreamTarget;

class RestreamStatusResolver
{
    public const HEARTBEAT_FRESH_SECONDS = 15;

    public const LIVE = 'live';

    public const STARTING = 'starting';

    public const IDLE = 'idle';

    public const ERROR = 'error';

    public const STALE = 'stale';

    public const YT_COMPLETE = 'yt-complete';

    public const YT_NO_DATA = 'yt-no-data';

    public const YT_REVOKED = 'yt-revoked';

    public const YT_TEST_STARTING = 'yt-test-starting';

    public const UNKNOWN = 'unknown';

    /**
     * Derive a single effective_status string for a restream target.
     */
    public function resolve(RestreamTarget $target): string
    {
        $hbFresh = $target->isHeartbeatFresh(self::HEARTBEAT_FRESH_SECONDS);
        $pid = (int) ($target->pipeline_pid ?? 0) > 0;
        $lifecycle = $target->platform_broadcast_lifecycle;

        if ($lifecycle === 'complete') {
            return self::YT_COMPLETE;
        }
        if ($lifecycle === 'revoked') {
            return self::YT_REVOKED;
        }

        if ($target->daemon_stalled_at && $target->daemon_stalled_at->gt(now()->subSeconds(60))) {
            return self::YT_NO_DATA;
        }

        if (! $hbFresh) {
            return self::STALE;
        }

        if (in_array($lifecycle, ['live'], true)) {
            return self::LIVE;
        }
        if (in_array($lifecycle, ['testStarting'], true)) {
            return self::YT_TEST_STARTING;
        }
        if (in_array($lifecycle, ['noData'], true)) {
            return self::YT_NO_DATA;
        }

        if ($target->last_error && ! $pid) {
            return self::ERROR;
        }

        if ($target->status === 'starting' && $pid) {
            return self::STARTING;
        }

        if ($pid) {
            return self::LIVE;
        }

        return self::IDLE;
    }

    public function label(string $effective): string
    {
        return match ($effective) {
            self::LIVE => 'En vivo en YouTube',
            self::STARTING => 'Iniciando',
            self::IDLE => 'Inactivo',
            self::ERROR => 'Error',
            self::STALE => 'Sin señal',
            self::YT_COMPLETE => 'YouTube dice "completado"',
            self::YT_NO_DATA => 'Daemon activo, YouTube sin datos',
            self::YT_REVOKED => 'YouTube dice "revocado"',
            self::YT_TEST_STARTING => 'YouTube iniciando prueba',
            default => 'Desconocido',
        };
    }

    public function badgeClass(string $effective): string
    {
        return match ($effective) {
            self::LIVE => 'bg-emerald-100 text-emerald-800',
            self::STARTING => 'bg-amber-100 text-amber-800',
            self::ERROR => 'bg-red-100 text-red-800',
            self::STALE => 'bg-amber-100 text-amber-800',
            self::YT_COMPLETE => 'bg-slate-200 text-slate-800',
            self::YT_NO_DATA => 'bg-amber-100 text-amber-800',
            self::YT_REVOKED => 'bg-red-100 text-red-800',
            self::YT_TEST_STARTING => 'bg-amber-100 text-amber-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    public function dotClass(string $effective): string
    {
        return match ($effective) {
            self::LIVE => 'bg-emerald-500 animate-pulse',
            self::STARTING => 'bg-amber-500 animate-pulse',
            self::ERROR => 'bg-red-500',
            self::STALE => 'bg-amber-500',
            self::YT_COMPLETE => 'bg-slate-400',
            self::YT_NO_DATA => 'bg-amber-500',
            self::YT_REVOKED => 'bg-red-500',
            self::YT_TEST_STARTING => 'bg-amber-500',
            default => 'bg-gray-400',
        };
    }
}