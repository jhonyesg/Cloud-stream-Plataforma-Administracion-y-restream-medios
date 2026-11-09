<?php

use App\Database\Migrations\Concerns\WithDataSafetySnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use WithDataSafetySnapshot;

    protected array $criticalTables = ['restream_targets'];

    protected array $criticalColumns = [
        'restream_targets' => ['id', 'user_id', 'channel_id', 'platform', 'name', 'enabled', 'status', 'scheduled_start_at', 'platform_account_id', 'deleted_at'],
    ];

    public function up(): void
    {
        $this->snapshotBefore();

        // 1. Normalize legacy status values (model enum: idle|starting|live|error).
        // The column is a PG enum (idle|starting|live|error) so invalid values are
        // impossible by construction; this is a defensive no-op audit pass.
        $normalized = DB::table('restream_targets')
            ->whereRaw('status::text NOT IN (?, ?, ?, ?)', ['idle', 'starting', 'live', 'error'])
            ->update(['status' => DB::raw("'idle'")]);
        if ($normalized > 0) {
            Log::info('RestreamScheduleMigration::normalize_status', ['rows' => $normalized]);
        }

        // 2. Nullify platform_account_id when the account's platform mismatches the target's.
        $nulled = DB::table('restream_targets as t')
            ->join('restream_platform_accounts as a', 'a.id', '=', 't.platform_account_id')
            ->whereRaw('t.platform::text != a.platform::text')
            ->update(['t.platform_account_id' => null]);
        if ($nulled > 0) {
            Log::warning('RestreamScheduleMigration::nullify_mismatched_accounts', ['rows' => $nulled]);
        }

        // 3. Verify no duplicate triplets among LIVE rows before swapping the constraint.
        $dupes = DB::table('restream_targets')
            ->whereNull('deleted_at')
            ->selectRaw('user_id, channel_id, platform, count(*) as c')
            ->groupBy('user_id', 'channel_id', 'platform')
            ->havingRaw('count(*) > 1')
            ->get();
        if ($dupes->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot make restream_targets unique constraint partial: live duplicate triplets exist: '
                . $dupes->toJson()
            );
        }

        // 4. Swap rigid unique for a partial one so soft-deleted rows release the triplet.
        DB::statement('ALTER TABLE restream_targets DROP CONSTRAINT IF EXISTS restream_targets_user_channel_platform_unique');
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS restream_targets_user_channel_platform_unique '
            .'ON restream_targets (user_id, channel_id, platform) WHERE deleted_at IS NULL'
        );

        // 5. Scheduled stop + scheduler indexes.
        Schema::table('restream_targets', function ($table) {
            $table->timestampTz('scheduled_stop_at')->nullable()->after('scheduled_start_at');
        });
        DB::statement('CREATE INDEX IF NOT EXISTS restream_targets_scheduled_start_idx ON restream_targets (scheduled_start_at) WHERE enabled = false');
        DB::statement('CREATE INDEX IF NOT EXISTS restream_targets_scheduled_stop_idx ON restream_targets (scheduled_stop_at) WHERE scheduled_stop_at IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS restream_targets_scheduled_stop_idx');
        DB::statement('DROP INDEX IF EXISTS restream_targets_scheduled_start_idx');

        Schema::table('restream_targets', function ($table) {
            $table->dropColumn('scheduled_stop_at');
        });

        // Restore the rigid constraint. Soft-deleted duplicates may block it;
        // purge the conflicting soft-deleted rows first (they are recoverable
        // from the per-migration snapshot).
        DB::statement('ALTER TABLE restream_targets DROP CONSTRAINT IF EXISTS restream_targets_user_channel_platform_unique');
        DB::statement('DROP INDEX IF EXISTS restream_targets_user_channel_platform_unique');
        DB::statement('DELETE FROM restream_targets a USING restream_targets b '
            .'WHERE a.deleted_at IS NOT NULL AND b.deleted_at IS NULL '
            .'AND a.user_id = b.user_id AND a.channel_id = b.channel_id AND a.platform = b.platform');
        DB::statement(
            'ALTER TABLE restream_targets ADD CONSTRAINT restream_targets_user_channel_platform_unique '
            .'UNIQUE (user_id, channel_id, platform)'
        );

        $this->restoreSnapshot();
    }
};