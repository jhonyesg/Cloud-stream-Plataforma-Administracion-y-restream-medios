<?php

namespace App\Services\Restream\Platform;

use App\Models\RestreamPlatformAccount;
use App\Models\RestreamTarget;
use Illuminate\Support\Facades\Http;

class FacebookBroadcastService
{
    public function __construct(private readonly PlatformAccountService $accounts)
    {
    }

    public function create(RestreamTarget $target): array
    {
        $account = $target->platformAccount;
        $this->accounts->ensureFreshToken($account);

        [$pageId, $pageToken] = $this->resolvePage($account);

        $payload = array_filter([
            'title' => $target->title ?: $target->name,
            'description' => $target->description,
            'status' => $target->scheduled_start_at ? 'SCHEDULED_UNPUBLISHED' : 'LIVE_NOW',
            'planned_start_time' => $target->scheduled_start_at?->timestamp,
            'access_token' => $pageToken,
        ]);

        $response = Http::asForm()->post("https://graph.facebook.com/v18.0/{$pageId}/live_videos", $payload);

        if (! $response->successful()) {
            throw RestreamPlatformAccountException::broadcastCreationFailed('facebook', $response->json('error.message', $response->body()));
        }

        $streamUrl = (string) $response->json('stream_url');
        [$destinationUrl, $streamKey] = $this->splitStreamUrl($streamUrl);

        return [
            'destination_url' => $destinationUrl,
            'stream_key' => $streamKey,
            'platform_broadcast_id' => (string) $response->json('id'),
        ];
    }

    public function update(RestreamTarget $target): void
    {
        if (! $target->platform_broadcast_id) {
            return;
        }

        $account = $target->platformAccount;
        $this->accounts->ensureFreshToken($account);

        [, $pageToken] = $this->resolvePage($account);

        Http::asForm()->post("https://graph.facebook.com/v18.0/{$target->platform_broadcast_id}", array_filter([
            'title' => $target->title ?: $target->name,
            'description' => $target->description,
            'access_token' => $pageToken,
        ]));
    }

    public function delete(RestreamPlatformAccount $account, string $liveVideoId): void
    {
        [, $pageToken] = $this->resolvePage($account);
        Http::delete("https://graph.facebook.com/v18.0/{$liveVideoId}", ['access_token' => $pageToken]);
    }

    /**
     * v1 simplification: uses the first Page the connected user manages.
     * Creating live videos on Facebook requires a Page access token, not the
     * user's own token — multi-page selection is left for a follow-up change.
     */
    protected function resolvePage(RestreamPlatformAccount $account): array
    {
        $response = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $account->access_token,
        ]);

        if (! $response->successful() || empty($response->json('data.0'))) {
            throw RestreamPlatformAccountException::broadcastCreationFailed('facebook', 'No se encontró ninguna página de Facebook administrada por esta cuenta.');
        }

        $page = $response->json('data.0');

        return [$page['id'], $page['access_token']];
    }

    protected function splitStreamUrl(string $streamUrl): array
    {
        $pos = strrpos($streamUrl, '/');
        if ($pos === false) {
            return [$streamUrl, ''];
        }

        return [substr($streamUrl, 0, $pos), substr($streamUrl, $pos + 1)];
    }
}
