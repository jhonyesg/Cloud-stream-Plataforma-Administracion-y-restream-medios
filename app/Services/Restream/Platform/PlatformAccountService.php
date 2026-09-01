<?php

namespace App\Services\Restream\Platform;

use App\Models\RestreamPlatformAccount;
use Illuminate\Support\Facades\Http;

/**
 * Keeps a connected platform account's access token usable: exchanges the
 * short-lived token Socialite hands back at connect time for a longer-lived
 * one, and refreshes it later. YouTube (Google) and Facebook have genuinely
 * different token lifecycles, so each platform is handled on its own terms
 * rather than forced through one generic "refresh_token" flow.
 */
class PlatformAccountService
{
    public function ensureFreshToken(RestreamPlatformAccount $account): void
    {
        if (! $account->isTokenExpired()) {
            return;
        }

        match ($account->platform) {
            RestreamPlatformAccount::PLATFORM_YOUTUBE => $this->refreshYoutubeToken($account),
            RestreamPlatformAccount::PLATFORM_FACEBOOK => throw RestreamPlatformAccountException::reconnectRequired($account),
            default => throw RestreamPlatformAccountException::reconnectRequired($account),
        };
    }

    protected function refreshYoutubeToken(RestreamPlatformAccount $account): void
    {
        if (empty($account->refresh_token)) {
            throw RestreamPlatformAccountException::reconnectRequired($account);
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.youtube.client_id'),
            'client_secret' => config('services.youtube.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw RestreamPlatformAccountException::reconnectRequired($account);
        }

        $data = $response->json();

        $account->access_token = $data['access_token'];
        $account->token_expires_at = now()->addSeconds((int) ($data['expires_in'] ?? 3600));
        $account->save();
    }

    /**
     * Google only returns a refresh_token once, on the very first consent for
     * a given user+app (unless the user has already revoked access). Facebook
     * has no server-side refresh at all — its long-lived user token is
     * exchanged once at connect time and simply expires after ~60 days,
     * requiring the user to reconnect through the consent screen again.
     */
    public function exchangeFacebookLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if (! $response->successful()) {
            throw new RestreamPlatformAccountException('No se pudo intercambiar el token de Facebook por uno de larga duración.');
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'expires_in' => (int) ($data['expires_in'] ?? 5184000), // ~60 days default
        ];
    }
}
