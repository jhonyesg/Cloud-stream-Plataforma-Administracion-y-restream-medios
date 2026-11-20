<?php

namespace App\Services\Restream;

use App\Models\RestreamTarget;
use App\Services\Restream\Platform\YoutubeBroadcastService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RestreamOrchestrator
{
    private const CONFIG_DIR = 'restream-daemons';

    private const HEALTH_CHECK_MS = 500;

    private const STOP_GRACE_SECONDS = 5;

    private const OFFLINE_WAIT_SECONDS = 10;

    private string $ffmpegBin;

    public function __construct(
        ?string $ffmpegBin = null,
        private readonly ?YoutubeBroadcastService $youtube = null,
        private readonly ?TargetSchedulerService $scheduler = null,
    ) {
        $bin = $ffmpegBin ?? (string) env('FFMPEG_BIN', 'ffmpeg');
        $resolved = $this->resolveBinary($bin);
        if ($resolved === null) {
            throw new RuntimeException("FFmpeg binary not found at: {$bin}. Set FFMPEG_BIN env var or install ffmpeg.");
        }
        $this->ffmpegBin = $resolved;
    }

    public function getBinary(): string
    {
        return $this->ffmpegBin;
    }

    /**
     * Build the JSON config consumed by the Python restream daemon.
     *
     * @return array<string, mixed>
     */
    public function buildConfig(RestreamTarget $target): array
    {
        $source = $target->effectiveSourceUrl();
        if (! $source) {
            throw new RuntimeException("Target {$target->id} has no source_url and channel has no RTMP output configured.");
        }

        $streamKey = Crypt::decryptString($target->getRawOriginal('stream_key'));

        return [
            'target_id' => $target->id,
            'channel_id' => $target->channel_id,
            'platform' => $target->platform,
            'source_url' => $source,
            'destination_url' => $target->destination_url,
            'stream_key' => $streamKey,
            'ffmpeg_bin' => $this->ffmpegBin,
        ];
    }

    /**
     * Render the exact ffmpeg command line the daemon will run for this
     * target, mirroring restream_daemon/daemon/target_daemon.py::_build_ffmpeg_args().
     * Returns null if any required piece (source, destination, stream key) is missing.
     */
    public function buildCommandPreview(RestreamTarget $target): ?string
    {
        $source = $target->effectiveSourceUrl();
        $streamKey = $target->revealStreamKey();
        if (! $source || ! $streamKey || ! $target->destination_url) {
            return null;
        }

        $destination = rtrim($target->destination_url, '/');

        $args = [
            $this->ffmpegBin,
            '-hide_banner', '-loglevel', 'info', '-re',
            '-i', $source,
            '-c:v', 'copy', '-c:a', 'aac', '-ar', '44100', '-ac', '2', '-b:a', '128k',
            '-f', 'flv', '-rtmp_live', 'live',
            "{$destination}/{$streamKey}",
        ];

        return implode(' ', array_map(
            fn ($a) => str_contains($a, ' ') ? escapeshellarg($a) : $a,
            $args
        ));
    }

    /**
     * Write the config file and spawn the Python daemon for a target.
     *
     * @param  array<string, mixed>  $context  Per-call overrides that take
     *                                            precedence over the target's own
     *                                            fields when creating platform
     *                                            broadcasts. Supported keys:
     *                                            title, description, thumbnail_path,
     *                                            thumbnail_media_id.
     * @return array{pid: int, status: string}
     */
    public function start(RestreamTarget $target, bool $dryRun = false, array $context = []): array
    {
        // YouTube broadcasts are single-use: once the broadcast transitions to
        // `complete` (which `enableAutoStop=true` does on the first RTMP
        // disconnect) the bound stream key is dead. FFmpeg can still hold the
        // TCP socket, but YouTube marks the stream `noData` and the broadcast
        // never shows up on the channel. Detect this BEFORE the idempotency
        // check so we stop the stale daemon, recreate the broadcast, and
        // spawn a fresh daemon pointing at the new stream key.
        $this->refreshDeadBroadcast($target, $context);

        // Brand-new target OR a target whose binding was cleared by a reap /
        // force-close: no `platform_broadcast_id`, so `refreshDeadBroadcast` is
        // a no-op and `buildConfig` would write a null destination URL.
        // Create the first broadcast here so the daemon has something real to
        // push to.
        if ($target->platform === 'youtube' && ! $target->platform_broadcast_id && $this->youtube) {
            try {
                $result = $this->youtube->create($target, $context);
                $target->forceFill([
                    'destination_url' => $result['destination_url'],
                    'stream_key' => $result['stream_key'],
                    'platform_broadcast_id' => $result['platform_broadcast_id'],
                ])->save();
                Log::info('[restream] created initial youtube broadcast for target', [
                    'target_id' => $target->id,
                    'broadcast_id' => $result['platform_broadcast_id'],
                ]);
            } catch (\Throwable $e) {
                Log::error('[restream] failed to create initial youtube broadcast', [
                    'target_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
                $target->update([
                    'status' => RestreamTarget::STATUS_ERROR,
                    'last_error' => 'YouTube broadcast creation failed: ' . $e->getMessage(),
                    'last_failed_at' => now(),
                ]);

                throw $e;
            }
        }

        $config = $this->buildConfig($target);

        if ($dryRun) {
            return ['pid' => 0, 'status' => RestreamTarget::STATUS_STARTING, 'config' => $config];
        }

        // Idempotency check: if a daemon for this target is already running
        // (verified via /proc cmdline to avoid PID-reuse false positives),
        // skip the spawn. Without this, multiple cron workers or restarts
        // can accumulate duplicate daemons pushing the same stream.
        if ($target->pipeline_pid && function_exists('posix_kill')) {
            $existingPid = (int) $target->pipeline_pid;
            if (@posix_kill($existingPid, 0)) {
                $cmdlineFile = "/proc/{$existingPid}/cmdline";
                $cmdline = @file_get_contents($cmdlineFile);
                if ($cmdline !== false) {
                    $cmdline = str_replace("\0", ' ', $cmdline);
                    $expected = "restream_daemon/main.py --target-id={$target->id}";
                    if (str_contains($cmdline, $expected)) {
                        Log::info('[restream] daemon already running, skipping spawn', [
                            'target_id' => $target->id,
                            'pid' => $existingPid,
                        ]);
                        return ['pid' => $existingPid, 'status' => RestreamTarget::STATUS_ACTIVE];
                    }
                }
            }
            // Stale PID (process gone or PID reused by another process): clear it
            // so spawnDaemon can claim a fresh slot.
            $target->update(['pipeline_pid' => null]);
        }

        $configPath = $this->writeConfig($target->id, $config);

        $target->update([
            'status' => RestreamTarget::STATUS_STARTING,
            'last_error' => null,
            'last_failed_at' => null,
            'last_started_at' => now(),
        ]);

        $pid = $this->spawnDaemon($target, $configPath);

        return ['pid' => $pid, 'status' => RestreamTarget::STATUS_STARTING];
    }

    /**
     * Signal the daemon to stop and WAIT until it is really gone.
     *
     * Returns the outcome instead of void so the caller can tell the operator
     * the truth:
     *   ['stopped' => bool, 'pid' => ?int, 'method' => string, 'reason' => ?string]
     *
     * Why the extra machinery: the daemon does not always run as the same OS
     * user as PHP-FPM. The scheduler (cron) runs as root and spawns the
     * daemons as root, while the panel runs as www, so `posix_kill()` fails
     * with EPERM. The old code ignored that failure and reported "stopped"
     * while the daemon kept pushing to YouTube — and the next "Detener"
     * found `pipeline_pid` already null and did nothing at all.
     *
     * Now:
     *   1. resolve the daemon PID by scanning /proc (the recorded
     *      `pipeline_pid` may be null or may even hold the ffmpeg child's
     *      PID from an older heartbeat),
     *   2. try SIGTERM and, on EPERM, fall back to the stop-flag file that
     *      the daemon polls every 5s,
     *   3. kill any orphan ffmpeg still pushing this target's destination,
     *   4. verify the process is really gone before marking the target idle.
     */
    public function stop(RestreamTarget $target): array
    {
        $pid = $this->resolveDaemonPid($target);

        if ($pid === null) {
            // Nothing running. Clean the row (a previous stop may have left
            // the PID behind, or the daemon died on its own).
            $this->killOrphanPipelines($target);
            $target->update([
                'status' => RestreamTarget::STATUS_IDLE,
                'pipeline_pid' => null,
                'last_stopped_at' => now(),
                'last_error' => null,
            ]);
            Log::info('[restream] stop: no daemon running', ['target_id' => $target->id]);

            return ['stopped' => true, 'pid' => null, 'method' => 'none', 'reason' => null];
        }

        $method = 'signal';
        $signalled = $this->signalDaemon($pid, SIGTERM);

        if (! $signalled) {
            // EPERM (or posix_kill unavailable): the daemon belongs to
            // another user. Ask it to stop through the flag file it polls.
            $method = 'flag';
            $this->requestStopFlag($target);
        }

        $deadline = time() + self::OFFLINE_WAIT_SECONDS;
        while (time() < $deadline && $this->processAlive($pid)) {
            usleep(500_000);
        }

        if ($this->processAlive($pid)) {
            $this->signalDaemon($pid, SIGKILL);
            $deadline = time() + self::STOP_GRACE_SECONDS;
            while (time() < $deadline && $this->processAlive($pid)) {
                usleep(500_000);
            }
        }

        $alive = $this->processAlive($pid);
        $orphans = $this->killOrphanPipelines($target);

        if ($alive) {
            $reason = 'El daemon no se pudo detener: el sistema rechazó las señales (permisos). '
                . 'Quedó emitido: se requiere detenerlo desde el servidor.';
            Log::error('[restream] stop failed: daemon still alive', [
                'target_id' => $target->id,
                'pid' => $pid,
                'method' => $method,
            ]);
            $target->update([
                'status' => RestreamTarget::STATUS_ERROR,
                'last_error' => $reason,
            ]);

            return ['stopped' => false, 'pid' => $pid, 'method' => $method, 'reason' => $reason];
        }

        // Transition the bound YouTube broadcast to `complete` before we clear
        // the local binding. Without this, broadcasts created with
        // `enableAutoStop=false` stay in `lifeCycleStatus=live` on YouTube's
        // side indefinitely (the "EN DIRECTO para siempre" symptom reported
        // when a scheduled window reaches its ends_at).
        $youtubeTransitionOk = null;
        if ($this->youtube && $target->platform === 'youtube' && $target->platform_broadcast_id) {
            try {
                $youtubeTransitionOk = $this->youtube->transitionToComplete($target);
            } catch (Throwable $e) {
                $youtubeTransitionOk = false;
                Log::warning('[restream] orchestrator stop: youtube transition threw', [
                    'target_id' => $target->id,
                    'broadcast_id' => $target->platform_broadcast_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $target->update([
            'pipeline_pid' => null,
            'last_stopped_at' => now(),
            'status' => RestreamTarget::STATUS_IDLE,
            'last_error' => null,
        ]);

        if ($target->platform === 'youtube' && $target->platform_broadcast_id && $this->scheduler) {
            try {
                $kind = $youtubeTransitionOk === true
                    ? \App\Enums\RestreamTargetEventKind::YOUTUBE_TRANSITION_SUCCEEDED
                    : \App\Enums\RestreamTargetEventKind::YOUTUBE_TRANSITION_FAILED;
                $this->scheduler->recordEvent($target, $kind, [
                    'broadcast_id' => $target->platform_broadcast_id,
                    'via' => 'orchestrator_stop',
                ]);
            } catch (Throwable $e) {
                Log::warning('[restream] orchestrator stop: failed to record youtube transition event', [
                    'target_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[restream] stopped', [
            'target_id' => $target->id,
            'platform' => $target->platform,
            'previous_pid' => $pid,
            'method' => $method,
            'orphan_pipelines_killed' => $orphans,
            'youtube_transition' => $youtubeTransitionOk,
        ]);

        return ['stopped' => true, 'pid' => $pid, 'method' => $method, 'reason' => null];
    }

    /**
     * PID of the daemon that serves this target, or null when none runs.
     */
    private function resolveDaemonPid(RestreamTarget $target): ?int
    {
        $fragment = $this->daemonCmdlineFragment($target);

        $recorded = (int) ($target->pipeline_pid ?? 0);
        if ($recorded > 0 && $this->cmdlineContains($recorded, $fragment)) {
            return $recorded;
        }

        foreach ($this->findPidsByCmdline($fragment) as $pid) {
            return $pid;
        }

        return null;
    }

    private function daemonCmdlineFragment(RestreamTarget $target): string
    {
        return 'restream_daemon/main.py --target-id=' . $target->id;
    }

    private function cmdlineContains(int $pid, string $needle): bool
    {
        $raw = @file_get_contents("/proc/{$pid}/cmdline");

        return $raw !== false && str_contains(str_replace("\0", ' ', $raw), $needle);
    }

    /**
     * @return array<int, int>
     */
    private function findPidsByCmdline(string $needle): array
    {
        $pids = [];
        $self = getmypid();

        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $file) {
            $pid = (int) basename(dirname($file));
            if ($pid === $self) {
                continue;
            }
            if ($this->cmdlineContains($pid, $needle)) {
                $pids[] = $pid;
            }
        }

        return $pids;
    }

    /**
     * Liveness check that works regardless of the process owner: /proc is
     * readable for every process. `posix_kill($pid, 0)` returns false with
     * EPERM when the process belongs to another user, which used to be
     * mistaken for "the process is gone".
     *
     * A zombie counts as dead: it no longer runs anything, it only waits to
     * be reaped by its parent.
     */
    private function processAlive(int $pid): bool
    {
        if ($pid <= 0 || ! @file_exists("/proc/{$pid}")) {
            return false;
        }

        $stat = @file_get_contents("/proc/{$pid}/stat");
        if ($stat === false) {
            return true;
        }

        $state = substr($stat, strrpos($stat, ')') + 2, 1);

        return $state !== 'Z';
    }

    private function signalDaemon(int $pid, int $signal): bool
    {
        if (! function_exists('posix_kill')) {
            Log::warning('[restream] posix_kill unavailable, cannot signal daemon', ['pid' => $pid]);

            return false;
        }

        $ok = @posix_kill($pid, $signal);
        if (! $ok) {
            Log::warning('[restream] could not signal daemon', [
                'pid' => $pid,
                'signal' => $signal,
                'errno' => function_exists('posix_get_last_error') ? posix_get_last_error() : null,
            ]);
        }

        return (bool) $ok;
    }

    /**
     * Ask the daemon to stop through the flag file it polls (used when the
     * signal cannot be delivered because of OS permissions).
     */
    private function requestStopFlag(RestreamTarget $target): void
    {
        $path = storage_path('app/' . self::CONFIG_DIR . '/' . $target->id . '.stop');

        try {
            if (! is_dir(dirname($path))) {
                @mkdir(dirname($path), 0775, true);
            }
            @file_put_contents($path, (string) now()->getTimestamp());
            Log::info('[restream] stop flag written', ['target_id' => $target->id, 'path' => $path]);
        } catch (Throwable $e) {
            Log::warning('[restream] could not write stop flag', [
                'target_id' => $target->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Kill any ffmpeg still pushing this target's destination. If the daemon
     * died but its child survived, that orphan keeps the emission alive on
     * YouTube long after "Detener".
     */
    private function killOrphanPipelines(RestreamTarget $target): int
    {
        $needle = $this->destinationNeedle($target);
        if ($needle === '') {
            return 0;
        }

        $killed = 0;
        foreach ($this->findPidsByCmdline($needle) as $pid) {
            if (@posix_kill($pid, SIGKILL)) {
                $killed++;
            }
        }

        if ($killed > 0) {
            Log::warning('[restream] killed orphan ffmpeg pipelines', [
                'target_id' => $target->id,
                'count' => $killed,
            ]);
        }

        return $killed;
    }

    /**
     * The daemon runs ffmpeg with "<destination_url>/<stream_key>" as a single
     * argument, so that exact string identifies the pipeline of this target.
     */
    private function destinationNeedle(RestreamTarget $target): string
    {
        $destination = trim((string) $target->destination_url);
        if ($destination === '') {
            return '';
        }

        try {
            $key = $target->revealStreamKey();
        } catch (Throwable) {
            $key = '';
        }

        if ($key === '') {
            return $destination;
        }

        return rtrim($destination, '/') . '/' . $key;
    }

    /**
     * YouTube broadcasts are single-use: once they transition to `complete`
     * (which `enableAutoStop=true` triggers on the first RTMP disconnect) the
     * bound stream key is dead. FFmpeg can still hold the TCP socket but
     * YouTube marks the stream `noData` and the broadcast never shows up.
     *
     * Detect that state before start(), stop the stale daemon pointing at
     * the dead broadcast, create a fresh broadcast, persist the new
     * `destination_url` / `stream_key` / `platform_broadcast_id`, and return
     * so `start()` proceeds normally with the new stream key.
     *
     * No-op for non-YouTube targets, targets without a bound broadcast, or
     * broadcasts that are still alive. Returns true if a recreation happened
     * (caller should not assume the previous daemon is still alive).
     */
    private function refreshDeadBroadcast(RestreamTarget $target, array $context = []): bool
    {
        if (! $this->youtube || $target->platform !== 'youtube') {
            return false;
        }

        if (! $target->platform_broadcast_id || ! $target->platformAccount) {
            return false;
        }

        try {
            $consumed = $this->youtube->isBroadcastConsumed($target);
        } catch (\Throwable $e) {
            Log::warning('[restream] could not check youtube broadcast state, skipping refresh', [
                'target_id' => $target->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $consumed) {
            // El broadcast sigue vivo. Si el cliente cambió título/privacidad/etc
            // para esta emisión, propagamos los cambios al broadcast existente
            // en vez de crear uno nuevo (que acumularía basura en YouTube Studio).
            $this->youtube->updateMetadata($target, $context);

            return false;
        }

        $oldBroadcast = $target->platform_broadcast_id;

        // Stop the daemon currently sending to the dead stream key. Without
        // this, the daemon and its FFmpeg child would keep writing to a
        // stream YouTube has already discarded.
        if ($target->pipeline_pid && function_exists('posix_kill') && @posix_kill((int) $target->pipeline_pid, 0)) {
            Log::info('[restream] stopping stale daemon before recreating youtube broadcast', [
                'target_id' => $target->id,
                'stale_pid' => $target->pipeline_pid,
                'dead_broadcast' => $oldBroadcast,
            ]);
            $this->stop($target);
        }

        // Limpia el broadcast anterior de YouTube para no acumular
        // "El Reporte" viejos en el Studio del cliente.
        if ($target->platformAccount) {
            try {
                $this->youtube->delete($target->platformAccount, $oldBroadcast);
                Log::info('[restream] deleted previous youtube broadcast', [
                    'target_id' => $target->id,
                    'deleted_broadcast' => $oldBroadcast,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[restream] could not delete previous youtube broadcast (continuing)', [
                    'target_id' => $target->id,
                    'broadcast' => $oldBroadcast,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $result = $this->youtube->create($target, $context);
        } catch (\Throwable $e) {
            Log::error('[restream] failed to recreate youtube broadcast', [
                'target_id' => $target->id,
                'old_broadcast' => $oldBroadcast,
                'error' => $e->getMessage(),
            ]);
            $target->update([
                'status' => RestreamTarget::STATUS_ERROR,
                'last_error' => 'YouTube broadcast terminado y no se pudo recrear: ' . $e->getMessage(),
                'last_failed_at' => now(),
            ]);

            return false;
        }

        $target->forceFill([
            'destination_url' => $result['destination_url'],
            'stream_key' => $result['stream_key'],
            'platform_broadcast_id' => $result['platform_broadcast_id'],
        ])->save();

        Log::info('[restream] recreated youtube broadcast', [
            'target_id' => $target->id,
            'old_broadcast' => $oldBroadcast,
            'new_broadcast' => $result['platform_broadcast_id'],
        ]);

        return true;
    }

    private function writeConfig(string $targetId, array $config): string
    {
        $configDir = storage_path('app/' . self::CONFIG_DIR);
        if (! is_dir($configDir)) {
            @mkdir($configDir, 0755, true);
        }

        $path = $configDir . '/' . $targetId . '-config.json';
        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT));
        @chmod($path, 0600);

        return $path;
    }

    private function spawnDaemon(RestreamTarget $target, string $configPath): int
    {
        $projectDir = base_path();
        $script = $projectDir . '/restream_daemon/main.py';

        if (! file_exists($script)) {
            throw new RuntimeException('Restream daemon script no encontrado: ' . $script);
        }

        $laravelApiUrl = 'http://127.0.0.1:9090';

        $env = [
            'PYTHONUNBUFFERED' => '1',
            'LARAVEL_API_URL' => $laravelApiUrl,
            'PATH' => getenv('PATH'),
            'HOME' => getenv('HOME') ?: '/tmp',
        ];

        $cmd = [
            'python3',
            $script,
            '--target-id=' . $target->id,
        ];

        $logDir = storage_path('logs/restream');
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logPath = $logDir . '/' . $target->id . '.log';

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', $logPath, 'a'],
        ];

        Log::info('[restream] spawning daemon via proc_open', [
            'target_id' => $target->id,
            'cmd' => implode(' ', array_map('escapeshellarg', $cmd)),
            'config_path' => $configPath,
        ]);

        $process = proc_open($cmd, $descriptors, $pipes, $projectDir, $env);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to spawn restream daemon via proc_open');
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        // Health check: give the daemon a moment to start and verify it is alive.
        usleep(self::HEALTH_CHECK_MS * 1000);

        $status = proc_get_status($process);
        if (! $status['running']) {
            $exitCode = $status['exitcode'];
            $cause = $this->readLogTail($target->id);
            $target->update([
                'status' => RestreamTarget::STATUS_ERROR,
                'last_error' => $cause ?: "Daemon exited immediately with code {$exitCode}.",
                'last_failed_at' => now(),
            ]);
            throw new RuntimeException($cause ?: "Daemon exited immediately with code {$exitCode}. Check storage/logs/restream/{$target->id}.log");
        }

        $pid = $status['pid'];

        $target->update([
            'pipeline_pid' => $pid,
            'last_started_at' => now(),
        ]);

        Log::info('[restream] daemon spawned', [
            'target_id' => $target->id,
            'pid' => $pid,
        ]);

        return $pid;
    }

    private function readLogTail(string $targetId, int $bytes = 65536): string
    {
        $logFile = storage_path('logs/restream/' . $targetId . '.log');
        if (! is_file($logFile)) {
            return '';
        }
        $size = filesize($logFile);
        if ($size === 0) {
            return '';
        }
        $fh = fopen($logFile, 'rb');
        if ($fh === false) {
            return '';
        }
        try {
            $offset = max(0, $size - $bytes);
            fseek($fh, $offset);
            $data = fread($fh, $bytes);
            return $data === false ? '' : trim($data);
        } finally {
            fclose($fh);
        }
    }

    private function resolveBinary(string $bin): ?string
    {
        if (is_file($bin) && is_executable($bin)) {
            return $bin;
        }
        if (str_contains($bin, '/')) {
            return null;
        }
        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
        foreach (explode(':', $path) as $dir) {
            $candidate = rtrim($dir, '/') . '/' . $bin;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}
