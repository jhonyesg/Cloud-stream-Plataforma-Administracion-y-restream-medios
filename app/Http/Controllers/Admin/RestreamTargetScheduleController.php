<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestreamTarget;
use App\Models\RestreamTargetEvent;
use App\Models\RestreamTargetSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestreamTargetScheduleController extends Controller
{
    public function index(Request $request, RestreamTarget $target): View
    {
        $schedules = RestreamTargetSchedule::forTarget($target->id)
            ->orderBy('starts_at', 'desc')
            ->limit(500)
            ->get();

        return view('admin.restream.target.schedules.index', [
            'target' => $target,
            'channel' => $target->channel,
            'schedules' => $schedules,
        ]);
    }

    public function events(Request $request, RestreamTarget $target): View
    {
        $events = RestreamTargetEvent::forTarget($target->id)
            ->orderByDesc('occurred_at')
            ->limit(500)
            ->get();

        return view('admin.restream.target.events.index', [
            'target' => $target,
            'channel' => $target->channel,
            'events' => $events,
        ]);
    }
}
