<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class EmissionLogRotator
{
    public function __construct(
        private readonly int $maxBytes,
        private readonly int $retentionDays,
    ) {}

    /**
     * Sanitize the emission log path before proc_open so a prior root-owned
     * file (or one created by a different user) does not block the spawn.
     *
     * - If the file does not exist: no-op.
     * - If the file exists and is writable by the current process: no-op.
     * - If the file exists but is not writable: unlink it (next proc_open
     *   creates a fresh file owned by the spawner).
     * - Independently, if the file's size exceeds the rotation threshold,
     *   rename it to a timestamped sibling before any new daemon writes.
     */
    public function prepare(string $logPath): void
    {
        if (! file_exists($logPath)) {
            return;
        }

        if (is_writable($logPath)) {
            if (($this->ensureRotatedBySize($logPath)) === false) {
                Log::warning('EmissionLogRotator: rotation by size failed', [
                    'log_path' => $logPath,
                ]);
            }
            return;
        }

        if (! @unlink($logPath)) {
            Log::warning('EmissionLogRotator: could not unlink unwritable log', [
                'log_path' => $logPath,
                'owner' => @fileowner($logPath),
                'group' => @filegroup($logPath),
                'perms' => substr(sprintf('%o', @fileperms($logPath)), -4),
            ]);
        }
    }

    /**
     * Delete rotated log siblings older than the retention window.
     *
     * Returns the number of files deleted.
     */
    public function gc(string $logPath, ?int $retentionDays = null): int
    {
        $retention = $retentionDays ?? $this->retentionDays;
        if ($retention < 0) {
            return 0;
        }

        $dir = dirname($logPath);
        $basename = basename($logPath);
        $pattern = $dir . DIRECTORY_SEPARATOR . $basename . '.*';

        $files = glob($pattern) ?: [];
        $cutoff = time() - ($retention * 86400);
        $deleted = 0;

        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }
            if (@unlink($file)) {
                $deleted++;
            } else {
                Log::warning('EmissionLogRotator: could not delete rotated log', [
                    'path' => $file,
                ]);
            }
        }

        return $deleted;
    }

    /**
     * If the log exceeds the size threshold, rename it to a timestamped
     * sibling. Returns true if no rotation was needed or rotation succeeded;
     * false on failure.
     */
    private function ensureRotatedBySize(string $logPath): bool
    {
        $size = @filesize($logPath);
        if ($size === false || $size <= $this->maxBytes) {
            return true;
        }

        $suffix = date('Ymd-His');
        $target = $logPath . '.' . $suffix;

        if (file_exists($target)) {
            $target = $logPath . '.' . $suffix . '-' . bin2hex(random_bytes(2));
        }

        return @rename($logPath, $target);
    }
}
