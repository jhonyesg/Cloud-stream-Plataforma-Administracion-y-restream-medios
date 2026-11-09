<?php

use App\Database\Migrations\Concerns\WithDataSafetySnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Destructive: converts the inline fields `restream_targets.scheduled_start_at`
 * and `restream_targets.scheduled_stop_at` (created by the
 * `restream-scheduled-lifecycle` migration) into rows of
 * `restream_target_schedules`, then drops the columns.
 *
 * Snapshot covers `restream_targets` so `down()` restores from
 * `database/snapshots/<this-migration>/`.
 */
return new class extends Migration {
    use WithDataSafetySnapshot;

    /**
     * @var array<int, string>
     */
    protected array $criticalTables = ['restream_targets'];

    public function up(): void
    {
        // 1. Snapshot BEFORE we touch the columns so the down() migration can
        // restore the exact rows we are about to migrate out of the columns.
        $this->snapshotBefore();

        // 2. Insert-SELECT from the legacy columns into the new schedule
        // rows. Postgres generates the UUID via gen_random_uuid().
        DB::statement(<<<'SQL'
            INSERT INTO restream_target_schedules
                (id, target_id, name, starts_at, ends_at, timezone, enabled, created_at, updated_at)
            SELECT
                gen_random_uuid(),
                rt.id,
                NULL,
                rt.scheduled_start_at,
                rt.scheduled_stop_at,
                'UTC',
                TRUE,
                NOW(),
                NOW()
            FROM restream_targets rt
            WHERE rt.deleted_at IS NULL
              AND (rt.scheduled_start_at IS NOT NULL OR rt.scheduled_stop_at IS NOT NULL)
        SQL);

        // 3. Drop the legacy columns. The remaining restream_targets rows
        // without schedules lose nothing — those targets were never programmed.
        Schema::table('restream_targets', function ($table) {
            $table->dropColumn(['scheduled_start_at', 'scheduled_stop_at']);
        });
    }

    public function down(): void
    {
        // Re-add the columns (nullable) BEFORE restoring from the snapshot,
        // so the INSERT statements from the snapshot find the columns.
        Schema::table('restream_targets', function ($table) {
            $table->timestampTz('scheduled_start_at')->nullable()->after('thumbnail_path');
            $table->timestampTz('scheduled_stop_at')->nullable()->after('scheduled_start_at');
        });

        // The MigrationSnapshot service restores all rows of `restream_targets`
        // captured pre-migration. Because it pulls from the gzipped JSON
        // snapshot (which still contains the legacy `scheduled_start_at` and
        // `scheduled_stop_at` columns), the rows go back into the original
        // shape.
        $this->restoreSnapshot();
    }
};
