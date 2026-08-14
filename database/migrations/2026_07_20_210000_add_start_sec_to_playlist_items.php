<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playlist_items', function (Blueprint $table) {
            $table->integer('start_sec')->nullable()->after('position');
            $table->uuid('split_from_id')->nullable()->after('start_sec');
        });

        Schema::table('playlist_items', function (Blueprint $table) {
            $table->foreign('split_from_id')
                ->references('id')->on('playlist_items')
                ->onDelete('set null');
            $table->index(['playlist_id', 'start_sec'], 'ix_playlist_items_schedule');
        });
    }

    public function down(): void
    {
        Schema::table('playlist_items', function (Blueprint $table) {
            $table->dropForeign(['split_from_id']);
            $table->dropIndex('ix_playlist_items_schedule');
            $table->dropColumn(['start_sec', 'split_from_id']);
        });
    }
};