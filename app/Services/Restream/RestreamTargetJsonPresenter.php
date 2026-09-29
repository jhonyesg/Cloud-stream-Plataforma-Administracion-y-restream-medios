<?php

namespace App\Services\Restream;

use App\Models\RestreamTarget;
use App\Services\Restream\RestreamStatusResolver;

class RestreamTargetJsonPresenter
{
    public function __construct(private readonly RestreamStatusResolver $resolver) {}

    /**
     * Build the canonical JSON shape returned by both
     * Admin\RestreamTargetController::index and Client\RestreamTargetController::index.
     * Any new field that the UI needs MUST be added here to keep both surfaces in sync.
     */
    public function present(RestreamTarget $t, bool $withChannel = false, bool $withUser = false): array
    {
        $effective = $this->resolver->resolve($t);
        $nextSchedule = $t->schedules()
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->orderByDesc('starts_at')
            ->first();
        $nextEndsAt = $nextSchedule?->ends_at
            ? $nextSchedule->ends_at->format('Y-m-d\TH:i:s')
            : null;

        $row = [
            'id' => $t->id,
            'channel_id' => $t->channel_id,
            'platform' => $t->platform,
            'name' => $t->name,
            'destination_url' => $t->destination_url,
            'source_url' => $t->source_url,
            'enabled' => (bool) $t->enabled,
            'status' => $t->status,
            'effective_status' => $effective,
            'effective_status_label' => $this->resolver->label($effective),
            'pipeline_pid' => $t->pipeline_pid,
            'last_started_at' => $t->last_started_at ? $t->last_started_at->format('Y-m-d H:i:s') : null,
            'last_stopped_at' => $t->last_stopped_at ? $t->last_stopped_at->format('Y-m-d H:i:s') : null,
            'last_heartbeat_at' => $t->last_heartbeat_at ? $t->last_heartbeat_at->format('Y-m-d H:i:s') : null,
            'last_error' => $t->last_error,
            'platform_broadcast_id' => $t->platform_broadcast_id,
            'platform_broadcast_lifecycle' => $t->platform_broadcast_lifecycle,
            'platform_broadcast_lifecycle_at' => $t->platform_broadcast_lifecycle_at
                ? $t->platform_broadcast_lifecycle_at->format('Y-m-d H:i:s') : null,
            'platform_broadcast_lifecycle_error' => $t->platform_broadcast_lifecycle_error,
            'last_youtube_poll_at' => $t->last_youtube_poll_at
                ? $t->last_youtube_poll_at->format('Y-m-d H:i:s') : null,
            'share_url' => $t->platform === 'youtube' && $t->platform_broadcast_id
                ? 'https://www.youtube.com/watch?v=' . $t->platform_broadcast_id
                : null,
            'scheduled_starts_at' => $t->schedules()
                ->where('starts_at', '>', now())
                ->orderBy('starts_at')
                ->first()?->local_starts_at?->format('Y-m-d\TH:i'),
            'next_ends_at' => $nextEndsAt,
        ];

        if ($withChannel) {
            $row['channel'] = ['display_name' => optional($t->channel)->display_name];
        }
        if ($withUser) {
            $row['user'] = [
                'id' => optional($t->user)->id,
                'display_name' => optional($t->user)->name,
            ];
            $row['user_id'] = $t->user_id;
        }

        return $row;
    }
}