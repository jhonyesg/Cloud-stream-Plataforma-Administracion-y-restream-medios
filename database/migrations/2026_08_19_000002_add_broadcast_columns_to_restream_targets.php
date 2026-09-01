<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restream_targets', function ($table) {
            $table->uuid('platform_account_id')->nullable()->after('channel_id');
            $table->foreign('platform_account_id')->references('id')->on('restream_platform_accounts')->nullOnDelete();
            $table->string('title')->nullable()->after('name');
            $table->text('description')->nullable()->after('title');
            $table->string('thumbnail_path')->nullable()->after('description');
            $table->timestampTz('scheduled_start_at')->nullable()->after('thumbnail_path');
            $table->string('platform_broadcast_id')->nullable()->after('scheduled_start_at');
        });

        // destination_url / stream_key are only required for manual (non-connected) targets.
        DB::statement('ALTER TABLE restream_targets ALTER COLUMN destination_url DROP NOT NULL');
        DB::statement('ALTER TABLE restream_targets ALTER COLUMN stream_key DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('restream_targets', function ($table) {
            $table->dropForeign(['platform_account_id']);
            $table->dropColumn([
                'platform_account_id',
                'title',
                'description',
                'thumbnail_path',
                'scheduled_start_at',
                'platform_broadcast_id',
            ]);
        });

        DB::statement('ALTER TABLE restream_targets ALTER COLUMN destination_url SET NOT NULL');
        DB::statement('ALTER TABLE restream_targets ALTER COLUMN stream_key SET NOT NULL');
    }
};
