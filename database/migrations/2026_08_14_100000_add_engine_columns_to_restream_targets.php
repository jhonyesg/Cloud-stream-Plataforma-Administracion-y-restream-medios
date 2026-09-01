<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restream_targets', function ($table) {
            $table->text('source_url')->nullable()->after('stream_key');
            $table->integer('pipeline_pid')->nullable()->after('source_url');
            $table->timestampTz('last_failed_at')->nullable()->after('last_stopped_at');
        });
    }

    public function down(): void
    {
        Schema::table('restream_targets', function ($table) {
            $table->dropColumn(['source_url', 'pipeline_pid', 'last_failed_at']);
        });
    }
};