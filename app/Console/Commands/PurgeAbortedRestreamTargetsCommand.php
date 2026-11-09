<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\RestreamTarget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeAbortedRestreamTargetsCommand extends Command
{
    protected $signature = 'restream:purge-aborted-targets
        {--channel= : Limit to one channel (uuid)}
        {--apply : Hard-delete the matching rows (default is dry-run)}
        {--json : Emit structured JSON for piping}';

    protected $description = 'Hard-delete restream_targets rows left orphaned by failed store() rollbacks (no stream_key, no platform_broadcast_id, never enabled, idle, soft-deleted). Dry-run by default.';

    public function handle(): int
    {
        $channelId = $this->option('channel');
        $apply = (bool) $this->option('apply');
        $json = (bool) $this->option('json');

        $candidates = $this->abortedQuery($channelId);

        if (! $apply) {
            return $this->renderDryRun($candidates, $channelId, $json);
        }

        return $this->apply($candidates, $channelId, $json);
    }

    /**
     * Mirrors the aborted-orphan signature enforced by
     * Client/Admin RestreamTargetController::isAbortedOrphan():
     *   trashed AND stream_key IS NULL AND platform_broadcast_id IS NULL
     *   AND status = 'idle'.
     *
     * `enabled` is intentionally NOT part of the signature: store() defaults it to
     * true, so aborted-create orphans typically carry enabled=true.
     *
     * See openspec/changes/restream-target-orphan-cleanup/design.md (Decisión 2).
     */
    private function abortedQuery(?string $channelId)
    {
        $query = RestreamTarget::onlyTrashed()
            ->whereNull('stream_key')
            ->whereNull('platform_broadcast_id')
            ->where('status', RestreamTarget::STATUS_IDLE)
            ->orderBy('deleted_at');

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        return $query->get(['id', 'user_id', 'channel_id', 'platform', 'created_at', 'deleted_at']);
    }

    private function renderDryRun($candidates, ?string $channelId, bool $json): int
    {
        if ($json) {
            $this->line(json_encode([
                'mode' => 'dry-run',
                'criteria' => [
                    'only_trashed' => true,
                    'stream_key_is_null' => true,
                    'platform_broadcast_id_is_null' => true,
                    'enabled' => 'any',
                    'status' => RestreamTarget::STATUS_IDLE,
                ],
                'channel_id' => $channelId,
                'candidates_count' => $candidates->count(),
                'candidates' => $candidates->map(fn ($r) => [
                    'id' => $r->id,
                    'user_id' => $r->user_id,
                    'channel_id' => $r->channel_id,
                    'platform' => $r->platform,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'deleted_at' => $r->deleted_at?->toIso8601String(),
                ])->values()->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($candidates->isEmpty()) {
            $this->info('No aborted restream_targets orphans found.'.($channelId ? " (channel={$channelId})" : ''));

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'user_id', 'channel_id', 'platform', 'created_at', 'deleted_at'],
            $candidates->map(fn ($r) => [
                $r->id,
                $r->user_id,
                $r->channel_id,
                $r->platform,
                $r->created_at?->toIso8601String(),
                $r->deleted_at?->toIso8601String(),
            ])->all()
        );
        $this->info("Dry-run: {$candidates->count()} candidate(s) match the aborted-orphan signature. Re-run with --apply to hard-delete them.");

        return self::SUCCESS;
    }

    private function apply($candidates, ?string $channelId, bool $json): int
    {
        if ($candidates->isEmpty()) {
            if ($json) {
                $this->line(json_encode([
                    'mode' => 'apply',
                    'purged_count' => 0,
                    'criteria' => [
                        'only_trashed' => true,
                        'stream_key_is_null' => true,
                        'platform_broadcast_id_is_null' => true,
                        'enabled' => 'any',
                        'status' => RestreamTarget::STATUS_IDLE,
                    ],
                    'channel_id' => $channelId,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->info('Nothing to purge.');
            }

            return self::SUCCESS;
        }

        $ids = $candidates->pluck('id')->all();
        $purgedIds = [];

        DB::transaction(function () use ($ids, &$purgedIds) {
            $locked = RestreamTarget::onlyTrashed()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            foreach ($locked as $row) {
                $row->forceDelete();
                $purgedIds[] = $row->id;
            }
        });

        $purgedCount = count($purgedIds);

        AuditLog::record(
            action: 'purge_aborted.restream_target',
            entityType: 'restream_target',
            entityId: null,
            before: null,
            after: [
                'purged_count' => $purgedCount,
                'criteria' => [
                    'only_trashed' => true,
                    'stream_key_is_null' => true,
                    'platform_broadcast_id_is_null' => true,
                    'enabled' => 'any',
                    'status' => RestreamTarget::STATUS_IDLE,
                ],
                'channel_id' => $channelId,
                'purged_ids' => $purgedIds,
            ],
            channelId: $channelId,
        );

        if ($json) {
            $this->line(json_encode([
                'mode' => 'apply',
                'purged_count' => $purgedCount,
                'criteria' => [
                    'only_trashed' => true,
                    'stream_key_is_null' => true,
                    'platform_broadcast_id_is_null' => true,
                    'enabled' => 'any',
                    'status' => RestreamTarget::STATUS_IDLE,
                ],
                'channel_id' => $channelId,
                'purged_ids' => $purgedIds,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info("Purged {$purgedCount} aborted restream_target orphan(s).");
        }

        return self::SUCCESS;
    }
}
