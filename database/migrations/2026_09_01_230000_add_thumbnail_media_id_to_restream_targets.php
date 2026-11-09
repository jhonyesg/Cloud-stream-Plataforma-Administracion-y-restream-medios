<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restream_targets', function (Blueprint $table) {
            $table->uuid('thumbnail_media_id')->nullable()->after('thumbnail_path');
            $table->foreign('thumbnail_media_id')
                ->references('id')->on('media_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restream_targets', function (Blueprint $table) {
            $table->dropForeign(['thumbnail_media_id']);
            $table->dropColumn('thumbnail_media_id');
        });
    }
};