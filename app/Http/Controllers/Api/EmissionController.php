<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\EmissionState;
use App\Services\EmissionOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmissionController extends Controller
{
    public function __construct(
        private readonly EmissionOrchestrator $orchestrator,
    ) {}

    /**
     * POST /api/channels/{channel}/emission/start
     */
    public function start(Request $request, Channel $channel): JsonResponse
    {
        $this->authorizeAccess($request, $channel);

        $state = EmissionState::firstOrNew(['channel_id' => $channel->id]);

        if (in_array($state->status, ['starting', 'live'], true)) {
            return response()->json([
                'message' => 'Emisión ya en curso.',
                'status' => $state->status,
            ], 422);
        }

        try {
            $result = $this->orchestrator->start($channel);

            return response()->json([
                'status' => 'starting',
                'timeline_version' => $result['timeline_version'] ?? null,
                'items_count' => $result['items_count'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Emission start failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/channels/{channel}/emission/stop
     */
    public function stop(Request $request, Channel $channel): JsonResponse
    {
        $this->authorizeAccess($request, $channel);

        $state = EmissionState::where('channel_id', $channel->id)->first();

        if (! $state || ! in_array($state->status, ['starting', 'live', 'paused', 'error'], true)) {
            return response()->json([
                'status' => 'offline',
                'message' => 'No había emisión activa.',
            ]);
        }

        try {
            $this->orchestrator->stop($channel);

            return response()->json([
                'status' => 'offline',
                'stop_reason' => 'manual',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Emission stop failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            // Force offline even if signal failed
            $state->update([
                'status' => 'offline',
                'stop_reason' => 'manual',
                'pipeline_pid' => null,
            ]);

            return response()->json([
                'status' => 'offline',
                'stop_reason' => 'manual',
                'warning' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/channels/{channel}/emission/status
     */
    public function status(Request $request, Channel $channel): JsonResponse
    {
        $this->authorizeAccess($request, $channel);

        $state = EmissionState::where('channel_id', $channel->id)->first();

        if (! $state) {
            return response()->json([
                'status' => 'offline',
                'channel_id' => $channel->id,
            ]);
        }

        $data = $state->toArray();

        // Enrich with current item metadata
        if ($state->current_timeline_item_id) {
            $item = \App\Models\ProgramTimelineItem::with('mediaItem')
                ->where('id', $state->current_timeline_item_id)
                ->first();

            if ($item && $item->mediaItem) {
                $data['current_item'] = [
                    'id' => $item->mediaItem->id,
                    'filename' => $item->mediaItem->filename,
                    'thumb_path' => $item->mediaItem->thumb_path,
                    'kind' => $item->mediaItem->kind,
                    'duration_sec' => $item->mediaItem->duration_sec,
                ];
            }
        }

        // Stale heartbeat detection
        if ($state->last_heartbeat_at) {
            $data['seconds_since_last_heartbeat'] = (int) now()->diffInSeconds($state->last_heartbeat_at, false);
        } else {
            $data['seconds_since_last_heartbeat'] = null;
        }

        return response()->json($data);
    }

    /**
     * GET /api/channels/{channel}/emission/log
     *
     * Return last N lines of the daemon log file.
     */
    public function log(Request $request, Channel $channel): JsonResponse
    {
        $this->authorizeAccess($request, $channel);

        $lines = (int) $request->input('lines', 100);
        $lines = max(1, min($lines, 500));

        $logPath = storage_path("logs/emission-{$channel->id}.log");

        if (! file_exists($logPath)) {
            return response()->json([
                'lines' => [],
                'total_lines' => 0,
                'channel_id' => $channel->id,
            ]);
        }

        // Read file efficiently for last N lines
        $content = file_get_contents($logPath);
        $allLines = explode("\n", $content);
        $allLines = array_filter($allLines, fn ($l) => trim($l) !== '');
        $tail = array_slice($allLines, -$lines);

        // Parse stats lines into structured data
        $parsed = [];
        foreach ($tail as $line) {
            $entry = ['raw' => $line];

            // Parse [STATS] lines: [STATS] uptime=120s loops=3 bitrate=2500kbps fps=30.0 frames_sent=3600 item=Inicio.mp4 state=playing
            if (str_contains($line, '[STATS]')) {
                preg_match_all('/(\w+)=([^\s]+)/', $line, $matches, PREG_SET_ORDER);
                foreach ($matches as $m) {
                    $val = $m[2];
                    // Strip common units (kbps, fps, s, m) to keep only numeric
                    $numStr = preg_replace('/^(\d+(?:\.\d+)?)(?:kbps|fps|s|m|frames)$/i', '$1', $val);
                    if (is_numeric($numStr)) {
                        $entry[$m[1]] = str_contains($numStr, '.') ? (float) $numStr : (int) $numStr;
                    } else {
                        $entry[$m[1]] = $val;
                    }
                }
                $entry['type'] = 'stats';
            } elseif (str_contains($line, '[PIPELINE]')) {
                $entry['type'] = 'pipeline';
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
            'channel_id' => $channel->id,
        ]);
    }

    private function authorizeAccess(Request $request, Channel $channel): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if ($user->isAdmin()) {
            return;
        }
        if (! $user->canAccessChannel($channel)) {
            abort(403, 'No tiene acceso a este canal.');
        }
    }
}
