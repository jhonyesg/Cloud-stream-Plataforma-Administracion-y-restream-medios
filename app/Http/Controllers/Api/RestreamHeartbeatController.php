<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestreamTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestreamHeartbeatController extends Controller
{
    /**
     * POST /api/internal/restream/{target}/heartbeat
     *
     * Internal endpoint used by the restream Python daemon to report state.
     * IP-gated: only localhost may access without auth.
     */
    public function heartbeat(Request $request, RestreamTarget $target): JsonResponse
    {
        if (! $this->isLocalhost($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:starting,live,error,offline'],
            'pipeline_pid' => ['nullable', 'integer'],
            'error_message' => ['nullable', 'string'],
            'timestamp' => ['nullable', 'string'],
        ]);

        $status = $data['status'];

        if ($status === 'offline') {
            $target->update([
                'status' => RestreamTarget::STATUS_IDLE,
                'pipeline_pid' => null,
                'last_heartbeat_at' => now(),
                'last_error' => null,
            ]);
        } else {
            // Ignore stray heartbeats from a daemon process that predates the
            // most recent explicit stop (e.g. an in-flight heartbeat sent right
            // as SIGTERM was delivered). Bounded to a short window so a start
            // attempt that fails before refreshing last_started_at can never
            // permanently freeze the target's heartbeat (self-heals within 15s).
            $recentlyStoppedAfterStart = $target->last_stopped_at
                && $target->last_stopped_at->gt(now()->subSeconds(15))
                && (! $target->last_started_at || $target->last_stopped_at->gt($target->last_started_at));

            if ($recentlyStoppedAfterStart) {
                return response()->json(['received' => true, 'ignored' => 'stopped']);
            }

            // Note: intentionally NOT writing $data['pipeline_pid'] here. The
            // daemon reports its *ffmpeg child's* OS pid in heartbeats, which
            // changes every time the daemon auto-restarts ffmpeg internally —
            // but `pipeline_pid` on this model must stay the daemon PROCESS's
            // own pid (set once at spawn time in RestreamOrchestrator), since
            // that's the pid `stop()` signals. Letting heartbeats overwrite it
            // meant "Detener" would kill the ffmpeg child instead of the
            // daemon, which then just restarted it via its own watchdog.
            $target->update([
                'status' => $status,
                'last_heartbeat_at' => now(),
                'last_error' => $data['error_message'] ?? $target->last_error,
            ]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * GET /api/internal/restream/{target}/log?lines=N
     *
     * Return the last N lines of the per-target daemon log, parsed into
     * structured entries ([STATS], [DAEMON], [WATCHDOG], other).
     */
    public function log(Request $request, RestreamTarget $target): JsonResponse
    {
        if (! $this->isLocalhost($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $lines = (int) $request->input('lines', 100);
        $lines = max(1, min($lines, 500));

        $logPath = storage_path('logs/restream/' . $target->id . '.log');

        if (! file_exists($logPath)) {
            return response()->json([
                'lines' => [],
                'total_lines' => 0,
                'target_id' => $target->id,
            ]);
        }

        $content = file_get_contents($logPath);
        $allLines = explode("\n", $content);
        $allLines = array_filter($allLines, fn ($l) => trim($l) !== '');
        $tail = array_slice($allLines, -$lines);

        $parsed = [];
        foreach ($tail as $line) {
            $entry = ['raw' => $line];

            if (str_contains($line, '[STATS]')) {
                preg_match_all('/(\w+)=([^\s]+)/', $line, $matches, PREG_SET_ORDER);
                foreach ($matches as $m) {
                    $val = $m[2];
                    $numStr = preg_replace('/^(\d+(?:\.\d+)?)(?:kbps|fps|s|m|frames)$/i', '$1', $val);
                    if (is_numeric($numStr)) {
                        $entry[$m[1]] = str_contains($numStr, '.') ? (float) $numStr : (int) $numStr;
                    } else {
                        $entry[$m[1]] = $val;
                    }
                }
                $entry['type'] = 'stats';
            } elseif (str_contains($line, '[WATCHDOG]')) {
                $entry['type'] = 'watchdog';
            } elseif (str_contains($line, '[DAEMON]')) {
                $entry['type'] = 'daemon';
            } else {
                $entry['type'] = 'other';
            }

            $parsed[] = $entry;
        }

        return response()->json([
            'lines' => $parsed,
            'total_lines' => count($allLines),
            'target_id' => $target->id,
        ]);
    }

    private function isLocalhost(Request $request): bool
    {
        $ip = $request->ip();

        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        $forwarded = $request->header('X-Forwarded-For');
        if ($forwarded) {
            $forwardedIps = array_map('trim', explode(',', $forwarded));
            foreach ($forwardedIps as $fwdIp) {
                if (in_array($fwdIp, ['127.0.0.1', '::1', 'localhost'], true)) {
                    return true;
                }
            }
        }

        $serverIp = gethostbyname(gethostname());
        if ($ip === $serverIp) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        return false;
    }
}
