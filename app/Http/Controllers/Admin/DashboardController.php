<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChannelRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateChannelRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Mail\PasswordChangedNotification;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\MediaItem;
use App\Models\Playlist;
use App\Models\User;
use App\Models\VirtualScreen;
use App\Services\MediaserverMetricsAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DashboardController extends Controller
{
    public function index(Request $request, MediaserverMetricsAggregator $metrics)
    {
        $totalBytes = (int) MediaItem::sum('size_bytes');
        $stats = [
            'users' => User::count(),
            'channels' => Channel::count(),
            'channels_active' => Channel::where('status', 'active')->count(),
            'media_items' => MediaItem::count(),
            'media_items_ready' => MediaItem::where('status', 'ready')->count(),
            'ads' => MediaItem::where('kind', 'ad')->count(),
            'playlists' => Playlist::count(),
            'total_storage_bytes' => $totalBytes,
            'total_storage_human' => User::humanBytes($totalBytes),
        ];

        $recentChannels = Channel::with('owner')->latest()->limit(10)->get();
        $recentUsers = User::latest()->limit(10)->get();

        $mediaserver = $this->mediaserverMetrics($metrics, (string) $request->query('stream', ''));

        return view('admin.dashboard', compact('stats', 'recentChannels', 'recentUsers', 'mediaserver'));
    }

    private function mediaserverMetrics(MediaserverMetricsAggregator $metrics, string $selectedStream): array
    {
        try {
            if (! $metrics->available()) {
                return ['available' => false, 'streams' => [], 'rules' => [], 'selected' => null];
            }
            $data = $metrics->forAdmin($selectedStream);
            return [
                'available' => true,
                'streams' => $data['streams'],
                'rules' => $data['rules'],
                'selected' => $data['selected'],
            ];
        } catch (Throwable $e) {
            report($e);
            return ['available' => false, 'streams' => [], 'rules' => [], 'selected' => null];
        }
    }

    public function users()
    {
        $users = User::with('owner')->orderBy('created_at', 'desc')->paginate(25);
        return view('admin.users.index', compact('users'));
    }

    public function channels()
    {
        $channels = Channel::with(['owner', 'assignedUsers', 'virtualScreen'])->orderBy('created_at', 'desc')->paginate(25);
        return view('admin.channels.index', compact('channels'));
    }

    // ----- Users CRUD -----

    public function userShow(User $user): JsonResponse
    {
        return response()->json(['user' => $user]);
    }

public function userStore(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create($data);

        return response()->json([
            'message' => 'Usuario creado.',
            'user' => $user,
        ], 201);
    }

    public function userUpdate(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $passwordChanged = ! empty($data['password']);

        if (! $passwordChanged) {
            unset($data['password']);
        }

        $admin = $request->user();

        DB::transaction(function () use ($user, $data, $passwordChanged, $admin, $request) {
            $user->update($data);

            if ($passwordChanged) {
                AuditLog::record(
                    'update.user.password',
                    'user',
                    $user->id,
                    null,
                    ['changed_by' => 'admin', 'ip' => $request->ip()],
                );

                Mail::to($user)->queue(new PasswordChangedNotification(
                    user: $user,
                    changedBy: 'admin',
                    actor: $admin,
                    ip: $request->ip(),
                ));

                DB::table('sessions')->where('user_id', $user->id)->delete();

                $user->setRememberToken(Str::random(60));
                $user->save();
            }
        });

        return response()->json([
            'message' => 'Usuario actualizado.',
            'user' => $user->fresh(),
        ]);
    }

    protected function resolveChannelStorageLimit(\Illuminate\Http\Request $request): ?int
    {
        $raw = $request->input('storage_limit_gb');
        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }
        return (int) round(((float) $raw) * 1024 * 1024 * 1024);
    }

    public function userDestroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'self' => ['No puedes eliminar tu propia cuenta.'],
            ])->status(422);
        }

        $activeOwned = Channel::where('owner_id', $user->id)
            ->where('status', '!=', 'archived')
            ->get(['id', 'display_name']);

        if ($activeOwned->isNotEmpty()) {
            throw ValidationException::withMessages([
                'owner' => [
                    'No se puede eliminar: el usuario es owner de '.$activeOwned->count().' canal(es) activo(s). Reasigna primero la propiedad.',
                ],
                'channels' => $activeOwned->pluck('display_name')->all(),
            ])->status(422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado.',
        ]);
    }

    // ----- Channels CRUD -----

    public function channelShow(Channel $channel): JsonResponse
    {
        $channel->load('assignedUsers:id,display_name,username,email');
        return response()->json(['channel' => $channel]);
    }

    public function channelStore(StoreChannelRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['storage_limit_bytes'] = $this->resolveChannelStorageLimit($request);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['display_name']);
            $base = $data['slug'];
            $i = 1;
            while (Channel::where('slug', $data['slug'])->withTrashed()->exists()) {
                if ($i > 100) {
                    $data['slug'] = $base . '-' . Str::random(8);
                    break;
                }
                $data['slug'] = $base.'-'.$i++;
            }
        }

        $assignedIds = $data['assigned_user_ids'] ?? [];
        unset($data['assigned_user_ids']);

        $channel = Channel::create($data);

        if (! empty($assignedIds)) {
            $channel->assignedUsers()->sync($assignedIds);
        }

        VirtualScreen::create([
            'channel_id' => $channel->id,
            'name' => 'Pantalla ' . $channel->display_name,
            'width' => 1280,
            'height' => 720,
        ]);

        return response()->json([
            'message' => 'Canal creado.',
            'channel' => $channel->fresh()->load('assignedUsers:id,display_name,username,email'),
        ], 201);
    }

    public function channelUpdate(UpdateChannelRequest $request, Channel $channel): JsonResponse
    {
        $data = $request->validated();
        $assignedIds = $data['assigned_user_ids'] ?? null;
        unset($data['assigned_user_ids']);

        $data['storage_limit_bytes'] = $this->resolveChannelStorageLimit($request);

        $channel->update($data);

        if ($assignedIds !== null) {
            $channel->assignedUsers()->sync($assignedIds);
        }

        return response()->json([
            'message' => 'Canal actualizado.',
            'channel' => $channel->fresh()->load('assignedUsers:id,display_name,username,email'),
        ]);
    }

    public function channelArchive(Channel $channel): JsonResponse
    {
        $channel->status = 'archived';
        $channel->save();

        return response()->json([
            'message' => 'Canal archivado.',
            'channel' => $channel,
        ]);
    }

    public function channelDestroy(Channel $channel): JsonResponse
    {
        $channel->delete();

        return response()->json([
            'message' => 'Canal eliminado.',
        ]);
    }
}