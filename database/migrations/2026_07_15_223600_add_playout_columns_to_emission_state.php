<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE emission_stop_reason AS ENUM ('manual', 'error', 'crash', 'completed')");

        Schema::table('emission_state', function (Blueprint $table) {
            $table->integer('ffmpeg_pid')->nullable()->after('error_message');
            $table->text('ffmpeg_cmd')->nullable()->after('ffmpeg_pid');
        });

        DB::statement('ALTER TABLE emission_state ADD COLUMN stop_reason emission_stop_reason NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE emission_state DROP COLUMN IF EXISTS stop_reason');

        Schema::table('emission_state', function (Blueprint $table) {
            $table->dropColumn(['ffmpeg_pid', 'ffmpeg_cmd']);
        });

        DB::statement('DROP TYPE IF EXISTS emission_stop_reason');
    }
};
