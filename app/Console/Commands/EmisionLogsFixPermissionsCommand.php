<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EmisionLogsFixPermissionsCommand extends Command
{
    protected $signature = 'emision:logs:fix-permissions';

    protected $description = 'Fix ownership and mode of every emission log file under storage/logs/. Run as root if files are owned by a different user.';

    public function handle(): int
    {
        $dir = storage_path('logs');
        $pattern = $dir . DIRECTORY_SEPARATOR . 'emission-*.log';
        $files = glob($pattern) ?: [];

        if (empty($files)) {
            $this->warn("No emission log files found at {$pattern}");

            return self::SUCCESS;
        }

        $fixed = 0;
        $skipped = 0;
        $conflicts = [];
        $webUser = function_exists('posix_getpwnam') ? posix_getpwnam('www') : false;
        $webUid = $webUser['uid'] ?? null;
        $webGid = $webUser['gid'] ?? null;
        $isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;

        foreach ($files as $file) {
            $perms = @fileperms($file);
            $mode = $perms === false ? null : substr(sprintf('%o', $perms), -4);
            $owner = @fileowner($file);
            $group = @filegroup($file);

            if ($isRoot && $webUid !== null && ($owner !== $webUid || $group !== $webGid)) {
                if (@chown($file, $webUid) && @chgrp($file, $webGid)) {
                    $this->line("  ✓ {$file}: owner {$owner}:{$group} → www:www");
                    $fixed++;
                } else {
                    $conflicts[] = ['path' => $file, 'owner' => $owner, 'group' => $group, 'mode' => $mode];
                    continue;
                }
            }

            if (is_writable($file)) {
                if ($mode !== '0664' && @chmod($file, 0664)) {
                    $this->line("  ✓ {$file}: mode {$mode} → 0664");
                    $fixed++;
                } else {
                    $this->line("  · {$file}: already writable (mode {$mode})");
                    $skipped++;
                }
                continue;
            }

            $ownerName = $owner === false ? '?' : posix_getpwuid($owner)['name'] ?? (string) $owner;
            $groupName = $group === false ? '?' : posix_getgrgid($group)['name'] ?? (string) $group;
            $conflicts[] = [
                'path' => $file,
                'owner' => $ownerName,
                'group' => $groupName,
                'mode' => $mode ?? '????',
            ];
        }

        foreach ($conflicts as $c) {
            $this->error(sprintf(
                '  ✗ %s owned by %s:%s mode %s — run this command as root (or as the file owner) to fix.',
                $c['path'],
                $c['owner'],
                $c['group'],
                $c['mode'],
            ));
        }

        $this->info(sprintf('Summary: %d fixed, %d already correct, %d need root.',
            $fixed,
            $skipped,
            count($conflicts),
        ));

        return count($conflicts) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
