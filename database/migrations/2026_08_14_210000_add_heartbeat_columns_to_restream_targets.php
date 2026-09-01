<?php

use App\Database\Migrations\Concerns\WithDataSafetySnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use WithDataSafetySnapshot;

    protected array $criticalTables = ['restream_targets'];

    protected array $criticalColumns = [
        'restream_targets' => ['id', 'user_id', 'channel_id', 'platform', 'name', 'destination_url', 'stream_key', 'source_url', 'pipeline_pid', 'enabled', 'status', 'last_error', 'last_started_at', 'last_stopped_at', 'last_failed_at', 'created_by'],
    ];

    public function up(): void
    {
        $this->snapshotBefore();

        Schema::table('restream_targets', function ($table) {
            $table->timestampTz('last_heartbeat_at')->nullable()->after('last_failed_at');
            $table->integer('loops_completed')->default(0)->after('last_heartbeat_at');
        });

        // Status enum: active -> live (daemon standard), plus new 'starting' state.
        DB::statement("ALTER TYPE restream_target_status RENAME VALUE 'active' TO 'live'");
        DB::statement("ALTER TYPE restream_target_status ADD VALUE IF NOT EXISTS 'starting'");
    }

    public function down(): void
    {
        Schema::table('restream_targets', function ($table) {
            $table->dropColumn(['last_heartbeat_at', 'loops_completed']);
        });

        // Revert enum: live -> active, drop starting.
        DB::statement("ALTER TYPE restream_target_status RENAME VALUE 'live' TO 'active'");

        $this->restoreSnapshot();
    }
};
