<?php

use App\Database\Migrations\Concerns\WithDataSafetySnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use WithDataSafetySnapshot;

    protected array $criticalTables = ['emission_state'];

    protected array $criticalColumns = [
        'emission_state' => [
            'channel_id', 'status', 'stop_reason', 'current_block_id', 'current_playlist_id',
            'current_item_id', 'position_in_item_sec', 'started_at', 'last_heartbeat_at',
            'error_message', 'ffmpeg_pid', 'ffmpeg_cmd', 'loops_completed', 'updated_at',
        ],
    ];

    public function up(): void
    {
        $this->snapshotBefore();

        DB::statement("CREATE TYPE emission_playhead_mode AS ENUM ('content', 'interrupt', 'fallback')");

        Schema::table('emission_state', function (Blueprint $table) {
            $table->uuid('current_timeline_item_id')->nullable()->after('current_item_id');
            $table->uuid('active_timeline_item_id')->nullable()->after('current_timeline_item_id');
            $table->uuid('content_timeline_item_id')->nullable()->after('active_timeline_item_id');
            $table->double('content_position_sec')->default(0)->after('position_in_item_sec');
            $table->uuid('interrupt_timeline_item_id')->nullable()->after('content_position_sec');
            $table->double('interrupt_position_sec')->default(0)->after('interrupt_timeline_item_id');
            $table->double('broadcast_clock_sec')->default(0)->after('interrupt_position_sec');
            $table->double('overflow_sec')->default(0)->after('broadcast_clock_sec');
            $table->integer('timeline_version')->default(0)->after('overflow_sec');
            $table->integer('pipeline_pid')->nullable()->after('ffmpeg_pid');
        });

        DB::statement('ALTER TABLE emission_state ADD COLUMN mode emission_playhead_mode NOT NULL DEFAULT \'content\'');

        DB::statement('ALTER TABLE emission_state ADD CONSTRAINT emission_state_current_timeline_item_id_fk FOREIGN KEY (current_timeline_item_id) REFERENCES program_timeline_items(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE emission_state ADD CONSTRAINT emission_state_active_timeline_item_id_fk FOREIGN KEY (active_timeline_item_id) REFERENCES program_timeline_items(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE emission_state ADD CONSTRAINT emission_state_content_timeline_item_id_fk FOREIGN KEY (content_timeline_item_id) REFERENCES program_timeline_items(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE emission_state ADD CONSTRAINT emission_state_interrupt_timeline_item_id_fk FOREIGN KEY (interrupt_timeline_item_id) REFERENCES program_timeline_items(id) ON DELETE SET NULL');

        DB::statement('CREATE INDEX ix_emission_state_current_timeline_item ON emission_state(current_timeline_item_id)');
        DB::statement('CREATE INDEX ix_emission_state_mode ON emission_state(mode)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE emission_state DROP CONSTRAINT IF EXISTS emission_state_interrupt_timeline_item_id_fk');
        DB::statement('ALTER TABLE emission_state DROP CONSTRAINT IF EXISTS emission_state_content_timeline_item_id_fk');
        DB::statement('ALTER TABLE emission_state DROP CONSTRAINT IF EXISTS emission_state_active_timeline_item_id_fk');
        DB::statement('ALTER TABLE emission_state DROP CONSTRAINT IF EXISTS emission_state_current_timeline_item_id_fk');

        DB::statement('DROP INDEX IF EXISTS ix_emission_state_mode');
        DB::statement('DROP INDEX IF EXISTS ix_emission_state_current_timeline_item');

        Schema::table('emission_state', function (Blueprint $table) {
            $table->dropColumn([
                'mode',
                'pipeline_pid',
                'timeline_version',
                'overflow_sec',
                'broadcast_clock_sec',
                'interrupt_position_sec',
                'interrupt_timeline_item_id',
                'content_position_sec',
                'content_timeline_item_id',
                'active_timeline_item_id',
                'current_timeline_item_id',
            ]);
        });

        DB::statement('DROP TYPE IF EXISTS emission_playhead_mode');

        $this->restoreSnapshot();
    }
};
