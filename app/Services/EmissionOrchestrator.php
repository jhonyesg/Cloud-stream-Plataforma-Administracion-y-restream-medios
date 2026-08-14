<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\EmissionState;
use App\Models\ProgramTimelineItem;
use App\Models\VirtualScreen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EmissionOrchestrator
{
    public function __construct(
        private readonly ScheduledPlaylistResolver $resolver,
        private readonly TimelineBuilder $builder,
        private readonly EmissionLogRotator $logRotator,
        private readonly ScheduledPositionCalculator $positionCalculator,
    ) {}

    /**
     * Start emission for a channel.
     * Returns timeline metadata or throws on failure.
     */
    public function start(Channel $channel): array
    {
        $now = CarbonImmutable::now();

        // Resolve today's template / block / playlist
        $resolved = $this->resolver->resolveForNow($channel);

        if (! $resolved['template']) {
            throw new RuntimeException('No hay template activo para este mes.');
        }

        if (! $resolved['block']) {
            throw new RuntimeException('No hay bloque asignado para hoy.');
        }

        if (! $resolved['playlist']) {
            throw new RuntimeException('No hay playlist asignada al bloque de hoy.');
        }

        $items = $resolved['items'] ?? collect();
        if ($items->isEmpty()) {
            throw new RuntimeException('La playlist no tiene items listos para emitir.');
        }

        // Repair legacy split rows before materializing today's roadmap so a
        // previously saved tail/cue order cannot be emitted backwards.
        \App\Models\Playlist::normalizeSplitOrdering($resolved['playlist']);
        $items = $resolved['playlist']->items()->with('mediaItem')->orderBy('position')->get();
        $resolved['items'] = $items;

        // Ensure timeline rows exist for today
        $timelineResult = $this->builder->buildForDay(
            $resolved['template'],
            $now->day
        );

        // Compute the start position so the daemon can resume at the
        // correct timeline item for the current broadcast clock.
        $start = $this->resolveStartPosition($channel, $resolved, $now);

        // Fetch VirtualScreen config for the daemon
        $virtualScreen = VirtualScreen::where('channel_id', $channel->id)->first();

        // Write startup config file for daemon to read
        $configPath = $this->writeDaemonConfig($channel, $resolved, $virtualScreen, $start);

        // Update emission_state
        $state = EmissionState::firstOrNew(['channel_id' => $channel->id]);
        $state->fill([
            'status' => 'starting',
            'current_block_id' => $resolved['block']->id,
            'current_playlist_id' => $resolved['playlist']->id,
            'current_timeline_item_id' => $start['timeline_item_id'] ?? null,
            'started_at' => now(),
            'last_heartbeat_at' => now(),
            'timeline_version' => $timelineResult['version'] ?? 0,
            'broadcast_clock_sec' => $start['broadcast_clock_sec'] ?? (int) $now->secondsSinceMidnight(),
            'content_position_sec' => $start['offset_sec'] ?? 0,
            'loops_completed' => 0,
            'error_message' => null,
            'updated_at' => now(),
        ]);
        $state->save();

        // Spawn daemon
        $this->spawnDaemon($channel, $configPath);

        return [
            'timeline_version' => $timelineResult['version'] ?? 0,
            'items_count' => $items->count(),
        ];
    }

    /**
     * Stop emission for a channel.
     */
    public function stop(Channel $channel): void
    {
        $state = EmissionState::where('channel_id', $channel->id)->first();
        if (! $state) {
            return;
        }

        // Signal daemon if we know its port
        $registration = $this->findDaemonRegistration($channel->id);
        if ($registration && isset($registration['port']) && $registration['port'] > 0) {
            try {
                $client = new \GuzzleHttp\Client([
                    'timeout' => 5,
                    'connect_timeout' => 2,
                ]);
                $client->post("http://127.0.0.1:{$registration['port']}/stop");
            } catch (\Throwable $e) {
                Log::warning('Daemon stop signal failed', [
                    'channel_id' => $channel->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Wait up to 10s for process to die
        $pid = $state->pipeline_pid ?? $state->ffmpeg_pid;
        if ($pid) {
            $this->waitForProcessExit((int) $pid, 10);
        }

        $state->update([
            'status' => 'offline',
            'stop_reason' => 'manual',
            'pipeline_pid' => null,
            'ffmpeg_pid' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Compute the start position (timeline item + offset) for the current
     * broadcast clock. Returns null when the calculator cannot determine a
     * position (the caller should fall back to "start at item 0").
     *
     * @return array{
     *   timeline_item_id: string|null,
     *   playlist_item_id: string|null,
     *   offset_sec: float,
     *   broadcast_clock_sec: int,
     *   file_seek_sec: float,
     * }|null
     */
    private function resolveStartPosition(Channel $channel, array $resolved, CarbonImmutable $now): ?array
    {
        try {
            $calc = $this->positionCalculator->calculate(
                $resolved['playlist'],
                $resolved['items'],
                $now,
            );
        } catch (\Throwable $e) {
            Log::warning('EmissionOrchestrator: position calculator failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        /** @var \App\Models\PlaylistItem $playlistItem */
        $playlistItem = $calc['item'];
        $offset = (float) $calc['offset_sec'];

        $timelineRow = ProgramTimelineItem::query()
            ->where('template_id', $resolved['template']->id)
            ->where('day_of_month', $now->day)
            ->where('playlist_item_id', $playlistItem->id)
            ->orderBy('starts_at_sec')
            ->first();

        $cueIn = (float) ($timelineRow->cue_in_sec ?? 0.0);

        return [
            'timeline_item_id' => $timelineRow?->id,
            'playlist_item_id' => $playlistItem->id,
            'offset_sec' => $offset,
            'broadcast_clock_sec' => (int) $now->secondsSinceMidnight(),
            'file_seek_sec' => $cueIn + $offset,
        ];
    }

    /**
     * Write a JSON config file that the daemon reads on startup.
     * This removes the need for the daemon to call Laravel API initially.
     */
    private function writeDaemonConfig(Channel $channel, array $resolved, ?VirtualScreen $virtualScreen, ?array $start = null): string
    {
        $now = CarbonImmutable::now();

        // Build timeline items with full file paths
        $timelineItems = \App\Models\ProgramTimelineItem::forDay($resolved['template']->id, $now->day)
            ->with('mediaItem')
            ->get()
            ->map(fn ($it) => [
                'id' => $it->id,
                'kind' => $it->kind,
                'starts_at_sec' => (int) $it->starts_at_sec,
                'ends_at_sec' => (int) $it->ends_at_sec,
                'effective_duration_sec' => (float) $it->effective_duration_sec,
                'media_item_id' => $it->media_item_id,
                'filename' => $it->mediaItem?->filename,
                'file_path' => $it->mediaItem
                    ? rtrim($channel->root_path, '/') . '/' . $it->mediaItem->filename
                    : null,
                'cue_in_sec' => $it->cue_in_sec,
                'cue_out_sec' => $it->cue_out_sec,
                'timeline_version' => (int) $it->timeline_version,
            ])
            ->values()
            ->toArray();

        $config = [
            'channel' => [
                'id' => $channel->id,
                'slug' => $channel->slug,
                'display_name' => $channel->display_name,
                'root_path' => $channel->root_path,
            ],
            'virtual_screen' => $virtualScreen ? [
                'id' => $virtualScreen->id,
                'channel_id' => $virtualScreen->channel_id,
                'name' => $virtualScreen->name,
                'width' => $virtualScreen->width,
                'height' => $virtualScreen->height,
                'output_protocol' => $virtualScreen->output_protocol,
                'output_url' => $virtualScreen->output_url,
                'fps' => $virtualScreen->fps,
                'video_bitrate_kbps' => $virtualScreen->video_bitrate_kbps,
                'audio_bitrate_kbps' => $virtualScreen->audio_bitrate_kbps,
                'codec_video' => $virtualScreen->codec_video,
                'codec_audio' => $virtualScreen->codec_audio,
                'video_preset' => $virtualScreen->video_preset,
                'logo_media_item_id' => $virtualScreen->logo_media_item_id,
                'logo_x' => $virtualScreen->logo_x,
                'logo_y' => $virtualScreen->logo_y,
                'logo_w' => $virtualScreen->logo_w,
                'logo_h' => $virtualScreen->logo_h,
                'logo_opacity' => $virtualScreen->logo_opacity,
                'fallback_type' => $virtualScreen->fallback_type,
                'fallback_media_item_id' => $virtualScreen->fallback_media_item_id,
            ] : null,
            'timeline' => [
                'template_id' => $resolved['template']->id,
                'day_of_month' => $now->day,
                'items' => $timelineItems,
                'timeline_version' => (int) ($timelineItems[0]['timeline_version'] ?? 0),
            ],
            'start' => $start ? [
                'timeline_item_id' => $start['timeline_item_id'],
                'playlist_item_id' => $start['playlist_item_id'],
                'offset_sec' => (float) $start['offset_sec'],
                'broadcast_clock_sec' => (int) $start['broadcast_clock_sec'],
                'file_seek_sec' => (float) $start['file_seek_sec'],
            ] : null,
            'started_at' => now()->toIso8601String(),
            'laravel_api_url' => config('app.url'), // Daemon uses this for heartbeat
        ];

        $configDir = storage_path('app/emission-daemons');
        if (! is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $path = $configDir . "/{$channel->id}-config.json";
        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT));

        return $path;
    }

    /**
     * Spawn the Python emission daemon for a channel.
     * Uses proc_open() instead of exec() so the process survives the HTTP request.
     */
    private function spawnDaemon(Channel $channel, string $configPath): void
    {
        $projectDir = base_path();
        $script = $projectDir . '/emisor_python/main.py';

        if (! file_exists($script)) {
            throw new RuntimeException('Daemon script no encontrado: ' . $script);
        }

        // Use the internal nginx server on port 9090 for daemon heartbeat.
        // The daemon reads config from file, so it doesn't need initial API calls.
        $laravelApiUrl = 'http://127.0.0.1:9090';

        // Build command with proper env vars
        $env = [
            'PYTHONUNBUFFERED' => '1',
            'LARAVEL_API_URL' => $laravelApiUrl,
            'DAEMON_CONFIG_PATH' => $configPath,
            'PATH' => getenv('PATH'),
            'HOME' => getenv('HOME') ?: '/tmp',
        ];

        $cmd = [
            'python3',
            $script,
            '--channel-id=' . $channel->id,
            '--port=0',
        ];

        $logPath = storage_path("logs/emission-{$channel->id}.log");

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['file', '/dev/null', 'w'], // stdout → /dev/null
            2 => ['file', $logPath, 'a'], // stderr → log file
        ];

        $cwd = $projectDir;

        $this->logRotator->prepare($logPath);

        Log::info('Spawning emission daemon via proc_open', [
            'channel_id' => $channel->id,
            'cmd' => implode(' ', array_map('escapeshellarg', $cmd)),
            'config_path' => $configPath,
        ]);

        $process = proc_open($cmd, $descriptors, $pipes, $cwd, $env);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to spawn emission daemon via proc_open');
        }

        // Close pipes immediately — we don't need to communicate with it
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        // Give it a moment to start and check if it's alive
        usleep(500000); // 500ms

        $status = proc_get_status($process);
        if (! $status['running']) {
            $exitCode = $status['exitcode'];
            throw new RuntimeException("Daemon exited immediately with code {$exitCode}. Check storage/logs/emission-{$channel->id}.log");
        }

        $this->logRotator->gc($logPath);

        // Detach the process so it survives this PHP request
        // We can't keep the proc resource, but the process is already running
        // Write a PID file so we can track it
        $pidFile = storage_path("app/emission-daemons/{$channel->id}-pid.txt");
        file_put_contents($pidFile, $status['pid']);

        Log::info('Emission daemon spawned successfully', [
            'channel_id' => $channel->id,
            'pid' => $status['pid'],
        ]);
    }

    /**
     * Read daemon registration file to discover port/PID.
     */
    private function findDaemonRegistration(string $channelId): ?array
    {
        $path = storage_path("app/emission-daemons/{$channelId}.json");
        if (! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if (! $content) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    private function waitForProcessExit(int $pid, int $maxSeconds): void
    {
        $elapsed = 0;
        while ($elapsed < $maxSeconds) {
            if (! posix_kill($pid, 0)) {
                return; // process no longer exists
            }
            sleep(1);
            $elapsed++;
        }

        // Force kill if still alive
        posix_kill($pid, SIGTERM);
        sleep(1);
        if (posix_kill($pid, 0)) {
            posix_kill($pid, SIGKILL);
        }
    }
}
