<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RestreamPlatformAccount;
use App\Models\RestreamTarget;
use App\Models\User;
use App\Services\Restream\Platform\PlatformAccountService;
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
        $accounts = RestreamPlatformAccount::query()
            ->with(['user:id,display_name,username,email'])
            ->orderBy('user_id')
            ->orderBy('platform')
            ->get()
            ->map(fn (RestreamPlatformAccount $a) => [
                'id' => $a->id,
                'platform' => $a->platform,
                'platform_label' => $a->platformLabel(),
                'platform_account_id' => $a->platform_account_id,
                'display_name' => $a->display_name,
                'owner' => [
                    'id' => optional($a->user)->id,
                    'display_name' => optional($a->user)->display_name
                        ?? optional($a->user)->username
                        ?? optional($a->user)->email,
                ],
                'connected_at' => $a->created_at,
                'token_expires_at' => $a->token_expires_at,
                'needs_reconnect' => $a->needsReconnect(),
            ])
            ->values();

        return response()->json([
            'accounts' => $accounts,
            'total' => $accounts->count(),
        ]);
    }

    public function connect(Request $request, User $user, string $platform): RedirectResponse
    {
        $this->abortUnlessSupported($platform);

        if ($user->id === $request->user()->id) {
            return redirect()->route('client.restream.accounts.connect', ['platform' => $platform]);
        }

        $request->session()->put('restream_oauth_for_user', $user->id);
        $request->session()->put('restream_oauth_actor', 'admin');

        return $this->driverFor($platform)->redirect();
    }

    public function callback(Request $request, string $platform): RedirectResponse
    {
        $this->abortUnlessSupported($platform);

        $targetUserId = $request->session()->pull('restream_oauth_for_user');
        $request->session()->forget('restream_oauth_actor');

        if (! $targetUserId) {
            return redirect()->route('admin.restream.index')
                ->withErrors(['restream' => 'La sesión OAuth expiró o no se inició desde el panel. Inténtalo de nuevo.']);
        }

        $target = User::find($targetUserId);
        if (! $target) {
            return redirect()->route('admin.restream.index')
                ->withErrors(['restream' => 'El cliente objetivo ya no existe.']);
        }

        try {
            $socialiteUser = $this->driverFor($platform)->user();
        } catch (\Throwable $e) {
            return redirect()->route('admin.restream.index')
                ->withErrors(['restream' => 'No se pudo completar la conexión con ' . ucfirst($platform) . ' para ' . $target->display_name . '.']);
        }

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

        $account = RestreamPlatformAccount::updateOrCreate(
            ['user_id' => $target->id, 'platform' => $platform],
            $attributes,
        );

        AuditLog::record(
            action: 'connect.restream_platform_account',
            entityType: 'restream_platform_account',
            entityId: $account->id,
            before: null,
            after: [
                'platform' => $platform,
                'display_name' => $attributes['display_name'],
                'on_behalf_of_user_id' => $target->id,
            ],
        );

        return redirect()->route('admin.restream.index')
            ->with('status', ucfirst($platform) . ' conectado para ' . $target->display_name . '.');
    }

    public function destroy(Request $request, User $user, string $platform): JsonResponse|RedirectResponse
    {
        $this->abortUnlessSupported($platform);

        $account = RestreamPlatformAccount::where('user_id', $user->id)->where('platform', $platform)->first();

        if (! $account) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'No hay una cuenta conectada para esta plataforma.'], 404);
            }
            return redirect()->route('admin.restream.index')
                ->withErrors(['restream' => 'No hay una cuenta conectada para esta plataforma.']);
        }

        RestreamTarget::where('user_id', $user->id)
            ->where('platform_account_id', $account->id)
            ->update(['platform_account_id' => null]);

        $before = ['platform' => $platform, 'display_name' => $account->display_name];

        $accountId = $account->id;
        $account->delete();

        AuditLog::record(
            action: 'disconnect.restream_platform_account',
            entityType: 'restream_platform_account',
            entityId: $accountId,
            before: $before + ['on_behalf_of_user_id' => $user->id],
            after: null,
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => ucfirst($platform) . ' desconectado para ' . $user->display_name . '.']);
        }
        return redirect()->route('admin.restream.index')
            ->with('status', ucfirst($platform) . ' desconectado para ' . $user->display_name . '.');
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
            'redirect' => config('services.youtube.redirect_admin'),
        ]);

        return $driver
            ->scopes([
                'https://www.googleapis.com/auth/youtube',
                'https://www.googleapis.com/auth/youtube.force-ssl',
            ])
            ->with(['access_type' => 'offline', 'prompt' => 'consent']);
    }
}
