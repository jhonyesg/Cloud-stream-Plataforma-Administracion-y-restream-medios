<?php

namespace App\Services\Restream\Platform;

use App\Models\RestreamTarget;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YoutubeBroadcastService
{
    public function __construct(private readonly PlatformAccountService $accounts)
    {
    }

    /**
     * Create a new YouTube live broadcast and bind it to a fresh liveStream.
     *
     * @param  array<string, mixed>  $context  Optional per-call overrides:
     *                                          - title (string)
     *                                          - description (string)
     *                                          - thumbnail_path (string)  absolute URL or local path
     *                                          - thumbnail_media_id (uuid)  reference to media_items row
     *                                        If a key is present it overrides the
     *                                        target's own field of the same name.
     */
    public function create(RestreamTarget $target, array $context = []): array
    {
        $account = $target->platformAccount;
        $this->accounts->ensureFreshToken($account);

        $broadcastId = null;
        $privacyStatus = $target->platform_privacy ?: 'public';

        try {
            $title = $context['title'] ?? ($target->title ?: $target->name);
            $description = $context['description'] ?? $target->description;
            $privacyStatus = $context['platform_privacy'] ?? $target->platform_privacy ?? 'public';
            $keepRecording = $context['keep_recording'] ?? $target->keep_recording;

            $snippet = array_filter([
                'title' => $title,
                'description' => $description,
                'scheduledStartTime' => now()->toAtomString(),
            ]);

            $broadcast = $this->api($account)->post(
                'https://www.googleapis.com/youtube/v3/liveBroadcasts?part=snippet,status,contentDetails',
                [
                    'snippet' => $snippet,
                    'status' => ['privacyStatus' => $privacyStatus, 'selfDeclaredMadeForKids' => false],
                    'contentDetails' => [
                        // enableAutoStart kept true: broadcast goes live the
                        // moment FFmpeg connects with the stream key.
                        // enableAutoStop intentionally FALSE: any RTMP
                        // micro-disconnect (network blip, conntrack timeout,
                        // VPS hiccup) would otherwise auto-transition the
                        // broadcast to `complete` and the bound stream key
                        // becomes dead. We transition manually from
                        // RestreamOrchestrator::stop() once the user (or
                        // scheduler) truly wants to end the broadcast.
                        'enableAutoStart' => true,
                        'enableAutoStop' => false,
                        'enableDvr' => true,
                        'recordFromStart' => true,
                    ],
                ],
            );

            if (! $broadcast->successful()) {
                // Degradation path: some API versions reject scheduledEndTime.
                if (isset($snippet['scheduledEndTime'])) {
                    Log::warning('[restream] youtube rejected scheduledEndTime, retrying without it', [
                        'target_id' => $target->id,
                        'error' => $broadcast->json('error.message', $broadcast->body()),
                    ]);
                    unset($snippet['scheduledEndTime']);

                    $broadcast = $this->api($account)->post(
                        'https://www.googleapis.com/youtube/v3/liveBroadcasts?part=snippet,status,contentDetails',
                        [
                            'snippet' => $snippet,
                            'status' => ['privacyStatus' => $privacyStatus, 'selfDeclaredMadeForKids' => false],
                            'contentDetails' => [
                                'enableAutoStart' => true,
                                'enableAutoStop' => true,
                                'enableDvr' => true,
                                'recordFromStart' => true,
                            ],
                        ],
                    );
                }
            }

            if (! $broadcast->successful()) {
                throw RestreamPlatformAccountException::broadcastCreationFailed('youtube', $broadcast->json('error.message', $broadcast->body()));
            }
            $broadcastId = $broadcast->json('id');

            $stream = $this->api($account)->post(
                'https://www.googleapis.com/youtube/v3/liveStreams?part=snippet,cdn',
                [
                    'snippet' => ['title' => $target->title ?: $target->name],
                    'cdn' => ['frameRate' => 'variable', 'ingestionType' => 'rtmp', 'resolution' => 'variable'],
                ],
            );

            if (! $stream->successful()) {
                throw RestreamPlatformAccountException::broadcastCreationFailed('youtube', $stream->json('error.message', $stream->body()));
            }

            $bind = $this->api($account)->post(
                "https://www.googleapis.com/youtube/v3/liveBroadcasts/bind?id={$broadcastId}&streamId={$stream->json('id')}&part=id,contentDetails",
            );

            if (! $bind->successful()) {
                throw RestreamPlatformAccountException::broadcastCreationFailed('youtube', $bind->json('error.message', $bind->body()));
            }

            // Resolve thumbnail: context override (per-schedule) > target's media > target's path.
            $thumbPath = null;
            $thumbMedia = null;
            if (! empty($context['thumbnail_media_id'])) {
                $thumbMedia = \App\Models\MediaItem::find($context['thumbnail_media_id']);
            } elseif (! empty($context['thumbnail_path'])) {
                $thumbPath = $this->resolveThumbnailPath($context['thumbnail_path']);
            }
            if ($thumbMedia === null && $context === []) {
                // No context override — fall back to target's own thumbnail.
                $thumbMedia = $target->thumbnailMedia;
                $thumbPath = $thumbMedia ? $this->resolveMediaItemPath($thumbMedia) : ($target->thumbnail_path ? $this->resolveThumbnailPath($target->thumbnail_path) : null);
            } elseif ($thumbMedia) {
                $thumbPath = $this->resolveMediaItemPath($thumbMedia);
            }

            if ($thumbPath) {
                $this->uploadThumbnail($account, $broadcastId, $thumbPath);
            }

            return [
                'destination_url' => rtrim((string) $stream->json('cdn.ingestionInfo.ingestionAddress'), '/'),
                'stream_key' => (string) $stream->json('cdn.ingestionInfo.streamName'),
                'platform_broadcast_id' => $broadcastId,
            ];
        } catch (\Throwable $e) {
            if ($broadcastId) {
                $this->delete($account, $broadcastId);
            }
            if ($e instanceof RestreamPlatformAccountException) {
                throw $e;
            }
            throw RestreamPlatformAccountException::broadcastCreationFailed('youtube', $e->getMessage());
        }
    }

    public function update(RestreamTarget $target): void
    {
        if (! $target->platform_broadcast_id) {
            return;
        }

        // A completed (or discarded) YouTube broadcast is single-use: it can never
        // be rescheduled. If the user edits and re-programs a target whose broadcast
        // already completed, transparently create a fresh one instead of pushing
        // an update into a dead broadcast (the stream would never reach the channel).
        if ($this->isBroadcastConsumed($target)) {
            Log::info('[restream] youtube broadcast already completed — recreating a fresh one', [
                'target_id' => $target->id,
                'old_broadcast' => $target->platform_broadcast_id,
            ]);

            $result = $this->create($target);
            $target->forceFill([
                'destination_url' => $result['destination_url'],
                'stream_key' => $result['stream_key'],
                'platform_broadcast_id' => $result['platform_broadcast_id'],
            ])->save();

            return;
        }

        $account = $target->platformAccount;
        $this->accounts->ensureFreshToken($account);

        $snippet = array_filter([
            'id' => $target->platform_broadcast_id,
            'title' => $target->title ?: $target->name,
            'description' => $target->description,
            'scheduledStartTime' => now()->toAtomString(),
        ]);

        $response = $this->api($account)->put(
            'https://www.googleapis.com/youtube/v3/liveBroadcasts?part=snippet,status',
            ['snippet' => $snippet, 'status' => array_filter([
                'id' => $target->platform_broadcast_id,
                'privacyStatus' => $target->platform_privacy ?: 'unlisted',
                'selfDeclaredMadeForKids' => false,
            ])],
        );

        if (! $response->successful() && isset($snippet['scheduledEndTime'])) {
            Log::warning('[restream] youtube rejected scheduledEndTime on update, retrying without it', [
                'target_id' => $target->id,
                'error' => $response->json('error.message', $response->body()),
            ]);
            unset($snippet['scheduledEndTime']);

            $this->api($account)->put(
                'https://www.googleapis.com/youtube/v3/liveBroadcasts?part=snippet,status',
                ['snippet' => $snippet, 'status' => array_filter([
                    'id' => $target->platform_broadcast_id,
                    'privacyStatus' => $target->platform_privacy ?: 'unlisted',
                    'selfDeclaredMadeForKids' => false,
                ])],
            );
        }
    }

    public function delete(\App\Models\RestreamPlatformAccount $account, string $broadcastId): void
    {
        $this->api($account)->delete("https://www.googleapis.com/youtube/v3/liveBroadcasts?id={$broadcastId}");
    }

    /**
     * Returns the YouTube-side `lifeCycleStatus` of the target's bound
     * broadcast (`complete`, `revoked`, `live`, `liveStarting`, `testing`,
     * ...) or null when the broadcast has been deleted / never existed /
     * the API could not be reached.
     *
     * Reused by the restream:reap-dead-broadcasts watchdog so it can record
     * the precise causal kind (revoked vs. completed).
     */
    public function broadcastLifecycle(RestreamTarget $target): ?string
    {
        if (! $target->platform_broadcast_id || ! $target->platformAccount) {
            return null;
        }

        try {
            $this->accounts->ensureFreshToken($target->platformAccount);

            $response = $this->api($target->platformAccount)->get(
                'https://www.googleapis.com/youtube/v3/liveBroadcasts',
                ['id' => $target->platform_broadcast_id, 'part' => 'status'],
            );

            $broadcast = $response->json('items.0');
            if (! $broadcast) {
                return null;
            }

            return $broadcast['status']['lifeCycleStatus'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('[restream] could not fetch broadcast lifecycle', [
                'target_id' => $target->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update an existing YouTube broadcast's metadata (title, description,
     * privacy). Used when a schedule re-uses a live broadcast but the user
     * changed those fields for the new emission.
     */
    public function updateMetadata(RestreamTarget $target, array $context): void
    {
        if (! $target->platform_broadcast_id || ! $target->platformAccount) {
            return;
        }

        $snippet = [];
        $status = [];
        if (! empty($context['title'])) {
            $snippet['title'] = $context['title'];
        }
        if (! empty($context['description'])) {
            $snippet['description'] = $context['description'];
        }
        // YouTube exige scheduledStartTime en cualquier update del snippet;
        // lo dejamos en el pasado reciente para no reprogramar la emisión.
        $snippet['scheduledStartTime'] = now()->subMinutes(1)->toAtomString();
        if (! empty($context['platform_privacy'])) {
            $status['privacyStatus'] = $context['platform_privacy'];
        }
        if (empty($snippet) && empty($status)) {
            return;
        }

        $body = ['id' => $target->platform_broadcast_id];
        if (! empty($snippet)) {
            $body['snippet'] = array_filter($snippet, fn ($v) => $v !== null && $v !== '');
        }
        if (! empty($status)) {
            $body['status'] = $status;
        }

        try {
            $this->accounts->ensureFreshToken($target->platformAccount);
            $r = $this->api($target->platformAccount)->put(
                "https://www.googleapis.com/youtube/v3/liveBroadcasts?part=snippet,status&id={$target->platform_broadcast_id}",
                $body,
            );
            if (! $r->successful()) {
                throw new \RuntimeException('YouTube PATCH failed: ' . ($r->json('error.message') ?? $r->body()));
            }
            Log::info('[restream] youtube broadcast metadata updated', [
                'target_id' => $target->id,
                'broadcast' => $target->platform_broadcast_id,
                'updated' => array_keys($body),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[restream] could not update youtube broadcast metadata', [
                'target_id' => $target->id,
                'broadcast' => $target->platform_broadcast_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * True when the target's broadcast no longer exists or already completed —
     * YouTube broadcasts are single-use and cannot be rescheduled once done.
     */
    public function isBroadcastConsumed(RestreamTarget $target): bool
    {
        if (! $target->platform_broadcast_id) {
            return false;
        }

        $account = $target->platformAccount;
        if (! $account) {
            return false;
        }

        try {
            $this->accounts->ensureFreshToken($account);

            $response = $this->api($account)->get(
                'https://www.googleapis.com/youtube/v3/liveBroadcasts',
                ['id' => $target->platform_broadcast_id, 'part' => 'status'],
            );

            $broadcast = $response->json('items.0');
            if (! $broadcast) {
                // Broadcast no longer exists (discarded or deleted).
                return true;
            }

            return in_array($broadcast['status']['lifeCycleStatus'] ?? null, ['complete', 'revoked'], true);
        } catch (\Throwable $e) {
            Log::warning('[restream] could not verify broadcast state, assuming reusable', [
                'target_id' => $target->id,
                'error' => $e->getMessage(),
            ]);

            // On API failure, don't silently duplicate broadcasts.
            return false;
        }
    }

    protected function uploadThumbnail(\App\Models\RestreamPlatformAccount $account, string $broadcastId, string $absolutePath): void
    {
        try {
            Http::withToken($account->access_token)
                ->attach('media', file_get_contents($absolutePath), basename($absolutePath))
                ->post("https://www.googleapis.com/upload/youtube/v3/thumbnails/set?videoId={$broadcastId}");
        } catch (\Throwable) {
            // Non-critical: the broadcast already exists without a custom thumbnail.
        }
    }

    protected function resolveThumbnailPath(string $storedPath): ?string
    {
        $full = storage_path('app/public/' . ltrim($storedPath, '/'));
        return file_exists($full) ? $full : null;
    }

    protected function resolveMediaItemPath(\App\Models\MediaItem $media): ?string
    {
        try {
            return app(\App\Services\MediaStorageService::class)->absolutePathFor($media);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function api(\App\Models\RestreamPlatformAccount $account)
    {
        return Http::withToken($account->access_token)->asJson();
    }

    /**
     * Transition the bound YouTube broadcast to `complete`. Used by
     * RestreamOrchestrator::stop() and by restream:reap-youtube-zombies to
     * close broadcasts the orchestrator forgot to end (the bug behind the
     * "EN DIRECTO para siempre" symptom when a scheduled window ends).
     *
     * Never throws — wraps every failure in a Log::warning and returns false
     * so callers can keep going (the daemon is already dead on the floor;
     * failing here must not roll the local target back to a non-idle state).
     *
     * @return bool true when YouTube confirms the transition (2xx).
     */
    public function transitionToComplete(RestreamTarget $target): bool
    {
        if (! $target->platform_broadcast_id) {
            return false;
        }

        $account = $target->platformAccount;
        if (! $account) {
            Log::warning('[youtube] transitionToComplete: target has no platformAccount', [
                'target_id' => $target->id,
                'broadcast_id' => $target->platform_broadcast_id,
            ]);
            return false;
        }

        try {
            $this->accounts->ensureFreshToken($account);
        } catch (\Throwable $e) {
            Log::warning('[youtube] transitionToComplete: token refresh failed', [
                'target_id' => $target->id,
                'broadcast_id' => $target->platform_broadcast_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        try {
            $response = $this->api($account)->post(
                'https://www.googleapis.com/youtube/v3/liveBroadcasts/transition'
                    . '?id=' . urlencode($target->platform_broadcast_id)
                    . '&broadcastStatus=complete'
                    . '&part=status'
            );

            if (! $response->successful()) {
                Log::warning('[youtube] transitionToComplete: non-2xx response', [
                    'target_id' => $target->id,
                    'broadcast_id' => $target->platform_broadcast_id,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('[youtube] transitionToComplete: request failed', [
                'target_id' => $target->id,
                'broadcast_id' => $target->platform_broadcast_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Force-cleanup used by the "Forzar cierre en YouTube" UI button and the
     * zombie reaper. Best-effort: tries to transition the broadcast to
     * `complete`, then deletes it and clears the local binding so the next
     * start() creates a fresh broadcast. Returns true when the local binding
     * is gone (operator-visible success), regardless of whether YouTube
     * already had it as complete.
     */
    public function forceCleanBroadcast(RestreamTarget $target): bool
    {
        if (! $target->platform_broadcast_id) {
            return true;
        }

        $account = $target->platformAccount;
        $broadcastId = $target->platform_broadcast_id;

        try {
            $this->accounts->ensureFreshToken($account);
        } catch (\Throwable $e) {
            Log::warning('[youtube] forceCleanBroadcast: token refresh failed', [
                'target_id' => $target->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($account) {
            try {
                $response = $this->api($account)->post(
                    'https://www.googleapis.com/youtube/v3/liveBroadcasts/transition'
                        . '?id=' . urlencode($broadcastId)
                        . '&broadcastStatus=complete'
                        . '&part=status'
                );
                if (! $response->successful()) {
                    Log::warning('[youtube] forceCleanBroadcast: transition non-2xx', [
                        'target_id' => $target->id,
                        'broadcast_id' => $broadcastId,
                        'status' => $response->status(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('[youtube] forceCleanBroadcast: transition request failed', [
                    'target_id' => $target->id,
                    'broadcast_id' => $broadcastId,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $this->api($account)->delete(
                    'https://www.googleapis.com/youtube/v3/liveBroadcasts?id=' . urlencode($broadcastId)
                );
            } catch (\Throwable $e) {
                Log::warning('[youtube] forceCleanBroadcast: delete broadcast failed', [
                    'target_id' => $target->id,
                    'broadcast_id' => $broadcastId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $target->forceFill([
            'platform_broadcast_id' => null,
            'destination_url' => null,
            'stream_key' => null,
            'platform_broadcast_lifecycle' => null,
            'platform_broadcast_lifecycle_at' => now(),
            'platform_broadcast_lifecycle_error' => null,
        ])->save();

        return true;
    }
}
