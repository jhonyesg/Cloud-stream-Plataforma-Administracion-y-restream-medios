<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emission_state', function (Blueprint $table) {
            $table->index('status', 'ix_emission_state_status');
            $table->index('last_heartbeat_at', 'ix_emission_state_last_heartbeat');
            $table->index('current_block_id', 'ix_emission_state_current_block');
            $table->index('current_playlist_id', 'ix_emission_state_current_playlist');
            $table->index('current_item_id', 'ix_emission_state_current_item');
        });

        Schema::table('media_items', function (Blueprint $table) {
            $table->index('sha256', 'ix_media_items_sha256');
            $table->index('mime_type', 'ix_media_items_mime_type');
        });

        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->index('playlist_id', 'ix_schedule_blocks_playlist');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('action', 'ix_audit_logs_action');
            $table->index('at', 'ix_audit_logs_at');
        });
    }

    public function down(): void
    {
        Schema::table('emission_state', function (Blueprint $table) {
            $table->dropIndex('ix_emission_state_status');
            $table->dropIndex('ix_emission_state_last_heartbeat');
            $table->dropIndex('ix_emission_state_current_block');
            $table->dropIndex('ix_emission_state_current_playlist');
            $table->dropIndex('ix_emission_state_current_item');
        });

        Schema::table('media_items', function (Blueprint $table) {
            $table->dropIndex('ix_media_items_sha256');
            $table->dropIndex('ix_media_items_mime_type');
        });

        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->dropIndex('ix_schedule_blocks_playlist');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('ix_audit_logs_action');
            $table->dropIndex('ix_audit_logs_at');
        });
    }
};
