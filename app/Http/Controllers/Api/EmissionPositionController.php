<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ProgramTimelineItem;
use App\Models\ScheduleBlock;
use App\Models\ScheduleTemplate;
use App\Services\ScheduledPositionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmissionPositionController extends Controller
{
    public function __construct(
        private readonly ScheduledPositionCalculator $calculator,
    ) {}

    /**
     * GET /api/internal/channels/{channel}/emission/position
     *
     * Internal endpoint used by the emission daemon to calculate
     * exact playhead position after a crash.
     *
     * IP-gated: only localhost may access without auth.
     */
    public function position(Request $request, Channel $channel): JsonResponse
    {
        if (! $this->isLocalhost($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $broadcastClockSec = (int) $request->input('broadcast_clock_sec');
        if ($broadcastClockSec < 0 || $broadcastClockSec > 86_400) {
            return response()->json(['message' => 'broadcast_clock_sec fuera de rango'], 422);
        }

        $now = Carbon::now();
        $template = ScheduleTemplate::where('channel_id', $channel->id)
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->where('status', 'active')
            ->first();

        if (! $template) {
            return response()->json(['message' => 'Sin template activo'], 404);
        }

        $block = ScheduleBlock::where('template_id', $template->id)
            ->where('day_of_month', $now->day)
            ->first();

        if (! $block || ! $block->playlist_id) {
            return response()->json(['message' => 'Sin playlist asignada hoy'], 404);
        }

        $timeline = ProgramTimelineItem::forDay($template->id, $now->day)
            ->with('mediaItem')
            ->get();

        if ($timeline->isEmpty()) {
            return response()->json(['message' => 'Timeline vacío'], 404);
        }

        // Find the item that contains broadcast_clock_sec
        $targetItem = null;
        $offsetInItem = 0.0;
        $mode = 'content';

        foreach ($timeline as $item) {
            $starts = (int) $item->starts_at_sec;
            $ends = (int) $item->ends_at_sec;

            if ($broadcastClockSec >= $starts && $broadcastClockSec < $ends) {
                $targetItem = $item;
                $offsetInItem = (float) ($broadcastClockSec - $starts);

                if ($item->cue_in_sec !== null) {
                    $offsetInItem += (float) $item->cue_in_sec;
                }

                $mode = $item->isCue() ? 'interrupt' : 'content';
                break;
            }
        }

        // If past the last item, return the last item at its end
        if (! $targetItem) {
            $targetItem = $timeline->last();
            $offsetInItem = (float) ($targetItem->effective_duration_sec ?? 0);
            $mode = $targetItem->isCue() ? 'interrupt' : 'content';
        }

        return response()->json([
            'current_timeline_item_id' => $targetItem->id,
            'offset_in_item_sec' => max(0.0, $offsetInItem),
            'mode' => $mode,
            'broadcast_clock_sec' => $broadcastClockSec,
            'starts_at_sec' => (int) $targetItem->starts_at_sec,
            'ends_at_sec' => (int) $targetItem->ends_at_sec,
            'media_item_id' => $targetItem->media_item_id,
            'filename' => $targetItem->mediaItem?->filename,
        ]);
    }

    /**
     * POST /api/internal/channels/{channel}/emission/heartbeat
     *
     * Receive heartbeat from emission daemon.
     */
    public function heartbeat(Request $request, Channel $channel): JsonResponse
    {
        if (! $this->isLocalhost($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:live,paused,error,offline,starting'],
            'broadcast_clock_sec' => ['required', 'numeric', 'min:0'],
            'current_timeline_item_id' => ['nullable', 'uuid'],
            'content_position_sec' => ['nullable', 'numeric', 'min:0'],
            'mode' => ['nullable', 'in:content,interrupt,fallback'],
            'pipeline_pid' => ['nullable', 'integer'],
            'timeline_version' => ['nullable', 'integer'],
            'loops_completed' => ['nullable', 'integer'],
            'error_message' => ['nullable', 'string'],
        ]);

        $state = \App\Models\EmissionState::firstOrNew(['channel_id' => $channel->id]);
        $state->fill(array_merge($data, [
            'last_heartbeat_at' => now(),
            'updated_at' => now(),
        ]));
        $state->save();

        return response()->json(['received' => true]);
    }

    /**
     * GET /api/internal/channels/{channel}/emission/timeline
     *
     * Return today's timeline items for the daemon to consume.
     */
    public function timeline(Request $request, Channel $channel): JsonResponse
    {
        if (! $this->isLocalhost($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $now = Carbon::now();
        $template = ScheduleTemplate::where('channel_id', $channel->id)
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->where('status', 'active')
            ->first();

        if (! $template) {
            return response()->json(['message' => 'Sin template activo'], 404);
        }

        $items = ProgramTimelineItem::forDay($template->id, $now->day)
            ->with('mediaItem')
            ->get()
            ->map(fn ($it) => [
                'id' => $it->id,
                'kind' => $it->kind,
                'status' => $it->status,
                'starts_at_sec' => (int) $it->starts_at_sec,
                'ends_at_sec' => (int) $it->ends_at_sec,
                'effective_duration_sec' => (float) $it->effective_duration_sec,
                'media_item_id' => $it->media_item_id,
                'filename' => $it->mediaItem?->filename,
                'cue_in_sec' => $it->cue_in_sec !== null ? (float) $it->cue_in_sec : null,
                'cue_out_sec' => $it->cue_out_sec !== null ? (float) $it->cue_out_sec : null,
                'resume_offset_sec' => (float) $it->resume_offset_sec,
                'parent_content_id' => $it->parent_content_id,
                'is_interruptible' => (bool) $it->is_interruptible,
                'timeline_version' => (int) $it->timeline_version,
            ])
            ->values();

        $firstItem = $items->first();

        return response()->json([
            'template_id' => $template->id,
            'day_of_month' => $now->day,
            'items' => $items,
            'timeline_version' => $firstItem ? ($firstItem['timeline_version'] ?? 0) : 0,
        ]);
    }

    /**
     * GET /api/internal/channels/{channel}/emission/virtual-screen
     *
     * Return VirtualScreen config for the daemon.
     */
    public function virtualScreen(Request $request, Channel $channel): JsonResponse
    {
        if (! $this->isLocalhost($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $screen = \App\Models\VirtualScreen::where('channel_id', $channel->id)->first();

        if (! $screen) {
            return response()->json(['message' => 'Pantalla virtual no configurada'], 404);
        }

        return response()->json($screen->toArray());
    }

    /**
     * GET /api/internal/channels/{channel}/emission/channel
     *
     * Return basic channel info including root_path.
     */
    public function channelInfo(Request $request, Channel $channel): JsonResponse
    {
        if (! $this->isLocalhost($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'id' => $channel->id,
            'slug' => $channel->slug,
            'display_name' => $channel->display_name,
            'root_path' => $channel->root_path,
            'status' => $channel->status,
        ]);
    }

    private function isLocalhost(Request $request): bool
    {
        $ip = $request->ip();

        // Direct localhost
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        // Check X-Forwarded-For for localhost (when behind nginx proxy)
        $forwarded = $request->header('X-Forwarded-For');
        if ($forwarded) {
            $forwardedIps = array_map('trim', explode(',', $forwarded));
            foreach ($forwardedIps as $fwdIp) {
                if (in_array($fwdIp, ['127.0.0.1', '::1', 'localhost'], true)) {
                    return true;
                }
            }
        }

        // Allow server own IP (daemon running on same machine)
        $serverIp = gethostbyname(gethostname());
        if ($ip === $serverIp) {
            return true;
        }

        // Allow private network ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        return false;
    }
}
