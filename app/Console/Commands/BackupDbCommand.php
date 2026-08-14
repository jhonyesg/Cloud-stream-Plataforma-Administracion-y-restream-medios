<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BackupDbCommand extends Command
{
    protected $signature = 'db:backup';
    protected $description = 'Create a PostgreSQL database backup before running migrations or risky operations.';

    public function handle(): int
    {
        $pgDump = '/www/server/pgsql/bin/pg_dump';
        $dbName = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbHost = config('database.connections.pgsql.host');
        $dbPort = config('database.connections.pgsql.port');
        $dbPass = config('database.connections.pgsql.password');

        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = now()->format('Ymd_His');
        $backupFile = $backupDir . "/cloudstream_backup_{$timestamp}.sql";

        $this->info("Creating database backup...");

        $env = array_merge(getenv(), ['PGPASSWORD' => $dbPass]);
        $process = new Process([
            $pgDump,
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            '--username=' . $dbUser,
            '--dbname=' . $dbName,
            '--no-owner',
            '--no-privileges',
            '--file=' . $backupFile,
        ], null, $env, null, 120);

        $process->run();

        if (! $process->isSuccessful() || ! file_exists($backupFile)) {
            $this->error('Backup failed: ' . $process->getErrorOutput());
            return self::FAILURE;
        }

        $size = $this->formatBytes(filesize($backupFile));
        $this->info("Backup complete: {$backupFile} ({$size})");

        $backups = glob($backupDir . '/cloudstream_backup_*.sql');
        rsort($backups);
        $toDelete = array_slice($backups, 10);
        foreach ($toDelete as $old) {
            @unlink($old);
        }

        if (count($toDelete) > 0) {
            $this->info('Cleaned ' . count($toDelete) . ' old backup(s), keeping last 10.');
        }

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}