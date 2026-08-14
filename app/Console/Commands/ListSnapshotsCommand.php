<?php

namespace App\Console\Commands;

use App\Services\MigrationSnapshot;
use Illuminate\Console\Command;

class ListSnapshotsCommand extends Command
{
    protected $signature = 'db:list-snapshots';

    protected $description = 'List all available migration data snapshots on disk.';

    public function handle(): int
    {
        /** @var MigrationSnapshot $snapshot */
        $snapshot = app(MigrationSnapshot::class);
        $list = $snapshot->listAvailable();

        if (empty($list)) {
            $this->info('No snapshots found at '.$snapshot->basePath());

            return self::SUCCESS;
        }

        $rows = array_map(static function (array $s): array {
            return [
                $s['migration'],
                $s['captured_at'] ?? '—',
                (string) $s['tables'],
                number_format($s['total_rows']),
                self::humanBytes($s['size_bytes']),
                $s['path'],
            ];
        }, $list);

        $this->table(['Migration', 'Captured at', 'Tables', 'Rows', 'Size', 'Path'], $rows);

        return self::SUCCESS;
    }

    protected static function humanBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
