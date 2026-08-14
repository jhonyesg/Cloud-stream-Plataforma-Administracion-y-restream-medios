<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\MediaItem;
use App\Services\Media\MediaFileClassifier;
use App\Services\Media\MediaHygieneReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaHygieneCommand extends Command
{
    protected $signature = 'media:hygiene
        {--channel= : Process only this channel (slug or uuid)}
        {--apply : Execute UPDATEs (default is dry-run)}
        {--json : Emit JSON report and exit (cannot combine with --apply)}
        {--rollback-since= : Restore media_items modified by media.hygiene.reclassify on or after this ISO8601 timestamp}';

    protected $description = 'Detect media_items whose files are missing on disk, misclassified non-media files, and unindexed files in channel root_paths. Dry-run by default; use --apply to mutate.';

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('json')) {
            $this->error('--apply and --json are mutually exclusive.');
            return self::INVALID;
        }

        if ($this->rollbackSince = $this->option('rollback-since')) {
            return $this->rollback($this->rollbackSince, (bool) $this->option('apply'));
        }

        $report = $this->scan((string) $this->option('channel'));

        if ($this->option('json')) {
            $this->line($report->toJson());
            return self::SUCCESS;
        }

        $this->printHumanReport($report);

        if ($this->option('apply')) {
            if (! $this->verifyRecentBackup()) {
                return self::FAILURE;
            }
            return $this->applyReport($report);
        }

        $this->newLine();
        $this->info('Dry-run only. No database changes. Re-run with --apply to mutate.');
        return self::SUCCESS;
    }

    protected function scan(string $channelFilter): MediaHygieneReport
    {
        $report = new MediaHygieneReport();

        $query = Channel::query()
            ->whereNotNull('root_path')
            ->where('root_path', '!=', '')
            ->whereNull('deleted_at');

        if ($channelFilter !== '') {
            if (Str::isUuid($channelFilter)) {
                $query->where('id', $channelFilter);
            } else {
                $query->where('slug', $channelFilter);
            }
        }

        $channels = $query->get();
        $report->channelsScanned = $channels->count();

        foreach ($channels as $channel) {
            $rootPath = rtrim($channel->root_path, '/');
            if (! is_dir($rootPath)) {
                continue;
            }

            $items = MediaItem::query()
                ->where('channel_id', $channel->id)
                ->get();

            foreach ($items as $item) {
                $full = $rootPath . '/' . $item->filename;
                if (! is_file($full)) {
                    $report->addFileMissing([
                        'id' => $item->id,
                        'channel_id' => $channel->id,
                        'channel_name' => $channel->display_name,
                        'filename' => $item->filename,
                        'kind' => $item->kind,
                        'size_bytes' => $item->size_bytes,
                    ]);
                    continue;
                }

                if ($item->status === 'ready' && MediaFileClassifier::isDenied($item->filename)) {
                    $report->addMisclassified([
                        'id' => $item->id,
                        'channel_id' => $channel->id,
                        'channel_name' => $channel->display_name,
                        'filename' => $item->filename,
                        'current_kind' => $item->kind,
                        'extension' => MediaFileClassifier::extension($item->filename),
                    ]);
                }
            }

            $registered = $items->pluck('filename')->all();
            $registeredMap = array_flip($registered);

            $entries = @scandir($rootPath);
            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $full = $rootPath . '/' . $entry;
                if (! is_file($full)) continue;

                if (isset($registeredMap[$entry])) continue;

                $size = @filesize($full) ?: null;
                if (MediaFileClassifier::isUploadTmp($entry)) {
                    $report->addUnindexedUpload([
                        'channel_id' => $channel->id,
                        'channel_name' => $channel->display_name,
                        'filename' => $entry,
                        'size_bytes' => $size,
                    ]);
                    continue;
                }

                $report->addUnindexedDiskFile([
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->display_name,
                    'filename' => $entry,
                    'extension' => MediaFileClassifier::extension($entry),
                    'size_bytes' => $size,
                ]);
            }
        }

        return $report;
    }

    protected function applyReport(MediaHygieneReport $report): int
    {
        $affected = 0;
        $skipped = 0;

        DB::transaction(function () use ($report, &$affected, &$skipped) {
            foreach ($report->fileMissing as $row) {
                $item = MediaItem::find($row['id']);
                if (! $item) { $skipped++; continue; }
                if ($item->status === 'failed' && $item->status_reason === 'file_missing') {
                    $skipped++;
                    continue;
                }

                $before = $item->only(['status', 'status_reason', 'kind']);
                $item->status = 'failed';
                $item->status_reason = 'file_missing';
                $item->save();

                AuditLog::record(
                    action: 'media.hygiene.reclassify',
                    entityType: 'MediaItem',
                    entityId: $item->id,
                    before: $before,
                    after: $item->only(['status', 'status_reason', 'kind']),
                    channelId: $item->channel_id,
                );
                $affected++;
            }

            foreach ($report->misclassified as $row) {
                $item = MediaItem::find($row['id']);
                if (! $item) { $skipped++; continue; }
                if ($item->status === 'failed' && $item->status_reason === 'non_media_file' && $item->kind === MediaFileClassifier::KIND_OTHER) {
                    $skipped++;
                    continue;
                }

                $before = $item->only(['status', 'status_reason', 'kind']);
                $item->status = 'failed';
                $item->status_reason = 'non_media_file';
                $item->kind = MediaFileClassifier::KIND_OTHER;
                $item->save();

                AuditLog::record(
                    action: 'media.hygiene.reclassify',
                    entityType: 'MediaItem',
                    entityId: $item->id,
                    before: $before,
                    after: $item->only(['status', 'status_reason', 'kind']),
                    channelId: $item->channel_id,
                );
                $affected++;
            }
        });

        $this->newLine();
        $this->info("Applied. Reclassified: {$affected}, Skipped (not found): {$skipped}");
        $this->line('Use `php artisan media:hygiene --rollback-since=' . now()->toIso8601String() . '` to undo.');

        return self::SUCCESS;
    }

    protected function rollback(string $isoSince, bool $apply): int
    {
        $since = Carbon::parse($isoSince);
        $entries = AuditLog::query()
            ->where('action', 'media.hygiene.reclassify')
            ->where('at', '>=', $since)
            ->where('entity_type', 'MediaItem')
            ->get();

        if ($entries->isEmpty()) {
            $this->info('No reclassify entries since ' . $isoSince . '. Nothing to roll back.');
            return self::SUCCESS;
        }

        $this->line(sprintf('Found %d reclassify entries since %s.', $entries->count(), $isoSince));

        if (! $apply) {
            $this->info('Dry-run. Use --apply to execute the rollback.');
            return self::SUCCESS;
        }

        if (! $this->verifyRecentBackup()) {
            return self::FAILURE;
        }

        $restored = 0;
        DB::transaction(function () use ($entries, &$restored) {
            foreach ($entries as $entry) {
                $item = MediaItem::find($entry->entity_id);
                if (! $item) continue;

                $before = $item->only(['status', 'status_reason', 'kind']);
                $snapshot = is_array($entry->before) ? $entry->before : [];
                if (isset($snapshot['status'])) $item->status = $snapshot['status'];
                if (array_key_exists('status_reason', $snapshot)) $item->status_reason = $snapshot['status_reason'];
                if (isset($snapshot['kind'])) $item->kind = $snapshot['kind'];
                $item->save();

                AuditLog::record(
                    action: 'media.hygiene.rollback',
                    entityType: 'MediaItem',
                    entityId: $item->id,
                    before: $before,
                    after: $item->only(['status', 'status_reason', 'kind']),
                    channelId: $item->channel_id,
                );
                $restored++;
            }
        });

        $this->info("Rolled back {$restored} media_items.");
        return self::SUCCESS;
    }

    protected function verifyRecentBackup(): bool
    {
        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            $this->error("Backup directory not found: {$backupDir}");
            return false;
        }
        $files = glob($backupDir . '/*.sql') ?: [];
        if (empty($files)) {
            $this->error("No backups found in {$backupDir}. Run `php artisan db:backup` first.");
            return false;
        }
        $latestMtime = 0;
        $latestName = '';
        foreach ($files as $f) {
            $mt = filemtime($f);
            if ($mt > $latestMtime) {
                $latestMtime = $mt;
                $latestName = basename($f);
            }
        }
        $age = time() - $latestMtime;
        if ($age > 86400) {
            $this->error(sprintf(
                "Latest backup %s is %d hours old. Run `php artisan db:backup` first.",
                $latestName,
                (int) ($age / 3600)
            ));
            return false;
        }
        $this->info(sprintf('Recent backup verified: %s (%d minutes old)', $latestName, (int) ($age / 60)));
        return true;
    }

    protected function printHumanReport(MediaHygieneReport $report): void
    {
        $this->info('Cloudstream media hygiene report');
        $this->line('Channels scanned: ' . $report->channelsScanned);
        $this->newLine();

        $this->section('File missing (media_items whose file no longer exists on disk)', $report->fileMissing, function ($r) {
            return sprintf("  [%s] %s/%s (%s, %s B)",
                $r['channel_name'], $r['channel_name'], $r['filename'], $r['kind'] ?? '?', $r['size_bytes'] ?? '?');
        });

        $this->section('Misclassified (non-media files marked ready)', $report->misclassified, function ($r) {
            return sprintf("  [%s] %s/%s — kind=%s ext=.%s",
                $r['channel_name'], $r['channel_name'], $r['filename'], $r['current_kind'], $r['extension']);
        });

        $this->section('Unindexed uploads (*.upload.tmp on disk)', $report->unindexedUploads, function ($r) {
            return sprintf("  [%s] %s (%s B)", $r['channel_name'], $r['filename'], $r['size_bytes'] ?? '?');
        });

        $this->section('Unindexed disk files (real files not yet in DB)', $report->unindexedDiskFiles, function ($r) {
            return sprintf("  [%s] %s (.%s, %s B)", $r['channel_name'], $r['filename'], $r['extension'], $r['size_bytes'] ?? '?');
        });

        $this->newLine();
        $t = $report->totals();
        $this->line(sprintf('Totals: file_missing=%d misclassified=%d unindexed_uploads=%d unindexed_disk_files=%d',
            $t['file_missing'], $t['misclassified'], $t['unindexed_uploads'], $t['unindexed_disk_files']));
    }

    protected function section(string $title, array $rows, callable $formatter): void
    {
        $this->info($title);
        if (empty($rows)) {
            $this->line('  (none)');
            $this->newLine();
            return;
        }
        foreach ($rows as $r) {
            $this->line($formatter($r));
        }
        $this->newLine();
    }

    protected ?string $rollbackSince = null;
}