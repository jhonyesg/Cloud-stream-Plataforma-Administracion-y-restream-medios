<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\RestreamTarget;
use App\Models\RestreamTargetEvent;
use App\Models\RestreamTargetSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestreamTargetScheduleController extends Controller
{
    public function index(Request $request, RestreamTarget $target): View
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($target->channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id, 403, 'Este destino no te pertenece.');

        $schedules = RestreamTargetSchedule::forTarget($target->id)
            ->orderBy('starts_at', 'desc')
            ->limit(500)
            ->get();

        return view('client.restream.target.schedules.index', [
            'target' => $target,
            'channel' => $target->channel,
            'schedules' => $schedules,
        ]);
    }

    public function events(Request $request, RestreamTarget $target): View
    {
        $user = $request->user();
        abort_unless($user->canAccessChannel($target->channel), 403, 'No tienes acceso a este canal.');
        abort_unless($target->user_id === $user->id, 403, 'Este destino no te pertenece.');

        $events = RestreamTargetEvent::forTarget($target->id)
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();

        return view('client.restream.target.events.index', [
            'target' => $target,
            'channel' => $target->channel,
            'events' => $events,
        ]);
    }
}
