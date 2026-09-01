<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RestreamPlatformAccount;
use App\Services\Restream\Platform\PlatformAccountService;
use App\Services\Restream\Platform\RestreamPlatformAccountException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class RestreamPlatformAccountController extends Controller
{
    public function __construct(private readonly PlatformAccountService $accounts)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $accounts = RestreamPlatformAccount::where('user_id', $request->user()->id)->get();

        return response()->json([
            'accounts' => RestreamPlatformAccount::PLATFORMS,
            'connected' => $accounts->map(fn (RestreamPlatformAccount $a) => [
                'platform' => $a->platform,
                'display_name' => $a->display_name,
                'needs_reconnect' => $a->needsReconnect(),
                'connected_at' => $a->created_at,
            ]),
        ]);
    }

    public function connect(Request $request, string $platform): RedirectResponse
    {
        $this->abortUnlessSupported($platform);

        return $this->driverFor($platform)->redirect();
    }

    public function callback(Request $request, string $platform): RedirectResponse
    {
        $this->abortUnlessSupported($platform);

        try {
            $socialiteUser = $this->driverFor($platform)->user();
        } catch (\Throwable $e) {
            return redirect()->route('client.restream.index')
                ->withErrors(['restream' => 'No se pudo completar la conexión con ' . ucfirst($platform) . '.']);
        }

        $user = $request->user();

        $attributes = [
            'platform_account_id' => $socialiteUser->getId(),
            'display_name' => $socialiteUser->getName() ?: $socialiteUser->getNickname() ?: $socialiteUser->getEmail(),
        ];

        if ($platform === RestreamPlatformAccount::PLATFORM_FACEBOOK) {
            $exchanged = $this->accounts->exchangeFacebookLongLivedToken($socialiteUser->token);
            $attributes['access_token'] = $exchanged['access_token'];
            $attributes['refresh_token'] = null;
            $attributes['token_expires_at'] = now()->addSeconds($exchanged['expires_in']);
        } else {
            $attributes['access_token'] = $socialiteUser->token;
            $attributes['refresh_token'] = $socialiteUser->refreshToken ?: null;
            $attributes['token_expires_at'] = $socialiteUser->expiresIn
                ? now()->addSeconds($socialiteUser->expiresIn)
                : null;
        }

        RestreamPlatformAccount::updateOrCreate(
            ['user_id' => $user->id, 'platform' => $platform],
            $attributes,
        );

        AuditLog::record(
            action: 'connect.restream_platform_account',
            entityType: 'restream_platform_account',
            entityId: $platform,
            before: null,
            after: ['platform' => $platform, 'display_name' => $attributes['display_name']],
        );

        return redirect()->route('client.restream.index')
            ->with('status', ucfirst($platform) . ' conectado correctamente.');
    }

    public function destroy(Request $request, string $platform): JsonResponse
    {
        $this->abortUnlessSupported($platform);

        $user = $request->user();
        $account = RestreamPlatformAccount::where('user_id', $user->id)->where('platform', $platform)->first();

        if (! $account) {
            return response()->json(['message' => 'No hay una cuenta conectada para esta plataforma.'], 404);
        }

        \App\Models\RestreamTarget::where('user_id', $user->id)
            ->where('platform_account_id', $account->id)
            ->update(['platform_account_id' => null]);

        $account->delete();

        AuditLog::record(
            action: 'disconnect.restream_platform_account',
            entityType: 'restream_platform_account',
            entityId: $platform,
            before: ['platform' => $platform],
            after: null,
        );

        return response()->json(['message' => ucfirst($platform) . ' desconectado.']);
    }

    protected function abortUnlessSupported(string $platform): void
    {
        abort_unless(in_array($platform, RestreamPlatformAccount::PLATFORMS, true), 404);
    }

    protected function driverFor(string $platform)
    {
        if ($platform === RestreamPlatformAccount::PLATFORM_FACEBOOK) {
            return Socialite::driver('facebook')->scopes([
                'pages_show_list',
                'pages_read_engagement',
                'publish_video',
            ]);
        }

        /** @var GoogleProvider $driver */
        $driver = Socialite::buildProvider(GoogleProvider::class, [
            'client_id' => config('services.youtube.client_id'),
            'client_secret' => config('services.youtube.client_secret'),
            'redirect' => config('services.youtube.redirect'),
        ]);

        return $driver
            ->scopes([
                'https://www.googleapis.com/auth/youtube',
                'https://www.googleapis.com/auth/youtube.force-ssl',
            ])
            ->with(['access_type' => 'offline', 'prompt' => 'consent']);
    }
}
