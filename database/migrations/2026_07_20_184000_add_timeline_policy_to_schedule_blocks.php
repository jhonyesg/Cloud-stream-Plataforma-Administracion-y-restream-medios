<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE timeline_policy AS ENUM ('shift', 'rigid')");

        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->boolean('is_interruptible')->default(true)->after('playlist_id');
        });

        DB::statement("ALTER TABLE schedule_blocks ADD COLUMN timeline_policy timeline_policy NOT NULL DEFAULT 'shift'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE schedule_blocks DROP COLUMN IF EXISTS timeline_policy');
        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->dropColumn('is_interruptible');
        });
        DB::statement('DROP TYPE IF EXISTS timeline_policy');
    }
};
