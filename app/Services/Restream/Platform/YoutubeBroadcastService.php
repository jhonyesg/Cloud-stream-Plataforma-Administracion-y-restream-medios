<?php

namespace App\Services\Restream\Platform;

use App\Models\RestreamTarget;
use Illuminate\Support\Facades\Http;

class YoutubeBroadcastService
{
    public function __construct(private readonly PlatformAccountService $accounts)
    {
    }

    public function create(RestreamTarget $target): array
    {
        $account = $target->platformAccount;
        $this->accounts->ensureFreshToken($account);

        $broadcastId = null;

        try {
            $broadcast = $this->api($account)->post(
                'https://www.googleapis.com/youtube/v3/liveBroadcasts?part=snippet,status,contentDetails',
                [
                    'snippet' => array_filter([
                        'title' => $target->title ?: $target->name,
                        'description' => $target->description,
                        'scheduledStartTime' => $target->scheduled_start_at?->toAtomString() ?? now()->toAtomString(),
                    ]),
                    'status' => ['privacyStatus' => 'unlisted'],
                    'contentDetails' => ['enableAutoStart' => true, 'enableAutoStop' => true],
                ],
            );

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

            if ($target->thumbnail_path && $path = $this->resolveThumbnailPath($target->thumbnail_path)) {
                $this->uploadThumbnail($account, $broadcastId, $path);
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

        $account = $target->platformAccount;
        $this->accounts->ensureFreshToken($account);

        $this->api($account)->put(
            'https://www.googleapis.com/youtube/v3/liveBroadcasts?part=snippet',
            [
                'id' => $target->platform_broadcast_id,
                'snippet' => array_filter([
                    'title' => $target->title ?: $target->name,
                    'description' => $target->description,
                    'scheduledStartTime' => $target->scheduled_start_at?->toAtomString(),
                ]),
            ],
        );
    }

    public function delete(\App\Models\RestreamPlatformAccount $account, string $broadcastId): void
    {
        $this->api($account)->delete("https://www.googleapis.com/youtube/v3/liveBroadcasts?id={$broadcastId}");
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

    protected function api(\App\Models\RestreamPlatformAccount $account)
    {
        return Http::withToken($account->access_token)->asJson();
    }
}
