<?php

namespace App\Http\Middleware;

use App\Models\RestreamQuota;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestreamEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $channel = $request->route('channel');

        if ($channel) {
            $enabled = RestreamQuota::where('user_id', $user->id)
                ->where('channel_id', $channel->id)
                ->where('enabled', true)
                ->exists();

            if (! $enabled) {
                return redirect()
                    ->back()
                    ->withErrors(['restream' => 'El módulo Restream no está habilitado para este canal. Pide al administrador que lo habilite.']);
            }
        } else {
            if (! method_exists($user, 'hasRestreamEnabled') || ! $user->hasRestreamEnabled()) {
                return redirect()
                    ->back()
                    ->withErrors(['restream' => 'No tienes ningún canal con Restream habilitado. Pide al administrador que habilite al menos uno.']);
            }
        }

        return $next($request);
    }
}
