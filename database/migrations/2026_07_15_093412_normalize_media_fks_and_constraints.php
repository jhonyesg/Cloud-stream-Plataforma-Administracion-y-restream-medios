<?php

use App\Database\Migrations\Concerns\WithDataSafetySnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use WithDataSafetySnapshot;

    protected array $criticalTables = ['media_items'];

    protected array $criticalColumns = [
        'media_items' => ['id', 'channel_id', 'filename', 'sha256', 'size_bytes', 'mime_type', 'kind', 'status', 'status_reason', 'deleted_at', 'duration_sec', 'width', 'height', 'codec_video', 'codec_audio', 'bitrate_kbps', 'metadata', 'created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->snapshotBefore();

        // 1. media_items.channel_id: SET NULL → CASCADE
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropForeign('media_items_channel_id_foreign');
        });
        Schema::table('media_items', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });

        // 2. playlist_items.media_item_id: RESTRICT → CASCADE
        Schema::table('playlist_items', function (Blueprint $table) {
            $table->dropForeign('playlist_items_media_item_id_foreign');
        });
        Schema::table('playlist_items', function (Blueprint $table) {
            $table->foreign('media_item_id')->references('id')->on('media_items')->onDelete('cascade');
        });

        // 3. schedule_block_items.media_item_id: RESTRICT → CASCADE
        Schema::table('schedule_block_items', function (Blueprint $table) {
            $table->dropForeign('schedule_block_items_media_item_id_foreign');
        });
        Schema::table('schedule_block_items', function (Blueprint $table) {
            $table->foreign('media_item_id')->references('id')->on('media_items')->onDelete('cascade');
        });

        // 4. schedule_blocks.playlist_id: RESTRICT → SET NULL
        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->dropForeign('schedule_blocks_playlist_id_foreign');
        });
        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->foreign('playlist_id')->references('id')->on('playlists')->onDelete('set null');
        });

        // 5. UNIQUE(channel_id, filename) on media_items
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS media_items_channel_filename_unique ON media_items(channel_id, filename)');

        // 6. Drop deleted_at and its index from media_items
        DB::statement('DROP INDEX IF EXISTS ix_media_items_deleted_at');
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->timestampTz('deleted_at')->nullable()->after('status_reason');
        });
        DB::statement('CREATE INDEX IF NOT EXISTS ix_media_items_deleted_at ON media_items(deleted_at)');

        DB::statement('DROP INDEX IF EXISTS media_items_channel_filename_unique');

        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->dropForeign('schedule_blocks_playlist_id_foreign');
        });
        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->foreign('playlist_id')->references('id')->on('playlists')->onDelete('restrict');
        });

        Schema::table('schedule_block_items', function (Blueprint $table) {
            $table->dropForeign('schedule_block_items_media_item_id_foreign');
        });
        Schema::table('schedule_block_items', function (Blueprint $table) {
            $table->foreign('media_item_id')->references('id')->on('media_items')->onDelete('restrict');
        });

        Schema::table('playlist_items', function (Blueprint $table) {
            $table->dropForeign('playlist_items_media_item_id_foreign');
        });
        Schema::table('playlist_items', function (Blueprint $table) {
            $table->foreign('media_item_id')->references('id')->on('media_items')->onDelete('restrict');
        });

        Schema::table('media_items', function (Blueprint $table) {
            $table->dropForeign('media_items_channel_id_foreign');
        });
        Schema::table('media_items', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('set null');
        });

        $this->restoreSnapshot();
    }
};
