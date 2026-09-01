<?php

use App\Database\Migrations\Concerns\WithDataSafetySnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    use WithDataSafetySnapshot;

    protected array $criticalTables = ['restream_quotas', 'restream_targets'];

    protected array $criticalColumns = [
        'restream_quotas' => ['id', 'user_id', 'enabled', 'max_outputs', 'granted_by', 'granted_at', 'notes'],
        'restream_targets' => ['id', 'user_id', 'channel_id', 'platform', 'name', 'destination_url', 'stream_key', 'source_url', 'pipeline_pid', 'enabled', 'status', 'last_error', 'last_started_at', 'last_stopped_at', 'last_failed_at', 'created_by'],
    ];

    public function up(): void
    {
        $this->snapshotBefore();

        DB::statement('ALTER TABLE restream_quotas ADD COLUMN channel_id uuid NULL');

        $quotas = DB::table('restream_quotas')->whereNull('channel_id')->get(['id', 'user_id']);
        foreach ($quotas as $q) {
            $bestChannel = DB::table('restream_targets')
                ->where('user_id', $q->user_id)
                ->groupBy('channel_id')
                ->orderByRaw('COUNT(*) DESC')
                ->value('channel_id');

            if (! $bestChannel) {
                $bestChannel = DB::table('channels')
                    ->where('owner_id', $q->user_id)
                    ->orWhereIn('id', function ($sub) use ($q) {
                        $sub->select('channel_id')->from('channel_user')->where('user_id', $q->user_id);
                    })
                    ->orderBy('created_at')
                    ->value('id');
            }

            if ($bestChannel) {
                DB::table('restream_quotas')->where('id', $q->id)->update(['channel_id' => $bestChannel]);
            } else {
                Log::warning("restream-quota-per-channel: deleting quota {$q->id} for user {$q->user_id} — no channels available.");
                DB::table('restream_quotas')->where('id', $q->id)->delete();
            }
        }

        DB::statement('ALTER TABLE restream_quotas ADD CONSTRAINT restream_quotas_channel_id_fkey FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE restream_quotas ALTER COLUMN channel_id SET NOT NULL');
        DB::statement('ALTER TABLE restream_quotas DROP CONSTRAINT restream_quotas_user_unique');
        DB::statement('ALTER TABLE restream_quotas ADD CONSTRAINT restream_quotas_user_channel_unique UNIQUE (user_id, channel_id)');
        DB::statement('CREATE INDEX ix_restream_quotas_channel_id ON restream_quotas(channel_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ix_restream_quotas_channel_id');
        DB::statement('ALTER TABLE restream_quotas DROP CONSTRAINT IF EXISTS restream_quotas_user_channel_unique');

        $survivors = DB::table('restream_quotas')
            ->select('user_id')
            ->selectRaw('max(max_outputs) as max_outputs')
            ->selectRaw('max(granted_at) as granted_at')
            ->groupBy('user_id')
            ->get();

        DB::statement('CREATE TEMPORARY TABLE _restream_quotas_keep AS SELECT DISTINCT ON (user_id) id FROM restream_quotas ORDER BY user_id, max_outputs DESC, granted_at DESC NULLS LAST');
        DB::statement('DELETE FROM restream_quotas WHERE id NOT IN (SELECT id FROM _restream_quotas_keep)');
        DB::statement('DROP TABLE _restream_quotas_keep');

        DB::statement('ALTER TABLE restream_quotas DROP CONSTRAINT IF EXISTS restream_quotas_channel_id_fkey');
        DB::statement('ALTER TABLE restream_quotas DROP COLUMN IF EXISTS channel_id');
        DB::statement('ALTER TABLE restream_quotas ADD CONSTRAINT restream_quotas_user_unique UNIQUE (user_id)');

        $this->restoreSnapshot();
    }
};
