<?php

namespace App\Services\Restream;

use App\Models\RestreamTarget;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RestreamOrchestrator
{
    private const CONFIG_DIR = 'restream-daemons';

    private const HEALTH_CHECK_MS = 500;

    private const STOP_GRACE_SECONDS = 5;

    private const OFFLINE_WAIT_SECONDS = 10;

    private string $ffmpegBin;

    public function __construct(?string $ffmpegBin = null)
    {
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
     * @return array{pid: int, status: string}
     */
    public function start(RestreamTarget $target, bool $dryRun = false): array
    {
        $config = $this->buildConfig($target);

        if ($dryRun) {
            return ['pid' => 0, 'status' => RestreamTarget::STATUS_STARTING, 'config' => $config];
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
     * Signal the daemon to stop and wait for its offline heartbeat.
     */
    public function stop(RestreamTarget $target): void
    {
        $pid = $target->pipeline_pid;
        if (! $pid) {
            $target->update([
                'status' => RestreamTarget::STATUS_IDLE,
                'pipeline_pid' => null,
                'last_stopped_at' => now(),
            ]);
            return;
        }

        if (! function_exists('posix_kill')) {
            Log::warning('[restream] stop requested but posix_kill unavailable', ['target_id' => $target->id]);
            $target->update([
                'pipeline_pid' => null,
                'last_stopped_at' => now(),
                'status' => RestreamTarget::STATUS_IDLE,
            ]);
            return;
        }

        @posix_kill((int) $pid, SIGTERM);

        // Wait for the daemon's offline heartbeat (it clears pipeline_pid).
        $deadline = time() + self::OFFLINE_WAIT_SECONDS;
        while (time() < $deadline) {
            $fresh = RestreamTarget::find($target->id);
            if ($fresh && $fresh->pipeline_pid === null) {
                break;
            }
            usleep(500_000);
        }

        // Force kill if still alive after the grace period.
        $deadline = time() + self::STOP_GRACE_SECONDS;
        while (time() < $deadline) {
            if (! @posix_kill((int) $pid, 0)) {
                break;
            }
            usleep(500_000);
        }
        if (@posix_kill((int) $pid, 0)) {
            @posix_kill((int) $pid, SIGKILL);
            usleep(200_000);
        }

        $target->update([
            'pipeline_pid' => null,
            'last_stopped_at' => now(),
            'status' => RestreamTarget::STATUS_IDLE,
            'last_error' => null,
        ]);

        Log::info('[restream] stopped', [
            'target_id' => $target->id,
            'platform' => $target->platform,
            'previous_pid' => $pid,
        ]);
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
