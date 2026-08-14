<?php

use App\Database\Migrations\Concerns\WithDataSafetySnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use WithDataSafetySnapshot;

    protected array $criticalTables = ['schedule_block_items', 'schedule_blocks'];

    protected array $criticalColumns = [
        'schedule_blocks' => ['id', 'template_id', 'day_of_month', 'playlist_id', 'start_time', 'end_time', 'kind', 'weekday_mask', 'priority', 'created_at', 'updated_at'],
        'schedule_block_items' => ['id', 'block_id', 'media_item_id', 'position', 'created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->snapshotBefore();

        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->dropForeign('schedule_blocks_playlist_id_foreign');
        });

        if (Schema::hasTable('schedule_block_items')) {
            Schema::dropIfExists('schedule_block_items');
        }

        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'kind', 'weekday_mask', 'priority']);
        });

        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->foreign('playlist_id')->references('id')->on('playlists')->onDelete('set null');
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS schedule_blocks_template_day_unique ON schedule_blocks(template_id, day_of_month)');

        DB::statement('DROP INDEX IF EXISTS ix_schedule_blocks_day');
    }

    public function down(): void
    {
        Schema::create('schedule_block_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('block_id');
            $table->uuid('media_item_id');
            $table->smallInteger('position');
            $table->timestamps();
            $table->foreign('block_id')->references('id')->on('schedule_blocks')->onDelete('cascade');
            $table->foreign('media_item_id')->references('id')->on('media_items')->onDelete('cascade');
        });

        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->time('start_time');
            $table->time('end_time');
            $table->string('kind', 20)->default('program');
            $table->smallInteger('weekday_mask')->nullable();
            $table->smallInteger('priority')->default(0);
        });

        DB::statement('DROP INDEX IF EXISTS schedule_blocks_template_day_unique');

        $this->restoreSnapshot();
    }
};
