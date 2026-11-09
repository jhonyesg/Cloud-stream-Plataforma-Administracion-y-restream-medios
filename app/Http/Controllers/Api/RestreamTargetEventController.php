<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestreamTarget;
use App\Models\RestreamTargetEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestreamTargetEventController extends Controller
{
    private function authorize(Request $request, RestreamTarget $target): void
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(
            $user->isAdmin() || $target->user_id === $user->id,
            403,
            'No tienes acceso a este destino restream.'
        );
    }

    public function index(Request $request, RestreamTarget $target): JsonResponse
    {
        $this->authorize($request, $target);

        $perPage = max(1, min(200, (int) $request->query('limit', 50)));
        $page = max(1, (int) $request->query('page', 1));

        $query = RestreamTargetEvent::forTarget($target->id)
            ->orderByDesc('occurred_at');

        if ($kind = $request->query('kind')) {
            $query->forKind($kind);
        }
        if ($from = $request->query('from')) {
            $query->since(\Carbon\Carbon::parse($from));
        }

        $total = $query->count();
        $rows = $query->forPage($page, $perPage)->get();

        return response()->json([
            'data' => $rows->map(fn ($e) => $this->serialize($e))->all(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(Request $request, RestreamTarget $target, string $event): JsonResponse
    {
        $this->authorize($request, $target);
        $row = RestreamTargetEvent::forTarget($target->id)->where('id', $event)->firstOrFail();

        return response()->json(['data' => $this->serialize($row)]);
    }

    private function serialize(RestreamTargetEvent $e): array
    {
        return [
            'id' => $e->id,
            'target_id' => $e->target_id,
            'kind' => $e->kind,
            'message' => $e->message,
            'metadata' => $e->metadata,
            'occurred_at' => optional($e->occurred_at)->toIso8601String(),
            'human_reason' => $e->human_reason,
            'presentation' => $e->presentation,
        ];
    }
}
