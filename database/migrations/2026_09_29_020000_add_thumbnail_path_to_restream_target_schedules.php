<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restream_target_schedules', function (Blueprint $table) {
            $table->string('thumbnail_path', 512)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('restream_target_schedules', function (Blueprint $table) {
            $table->dropColumn('thumbnail_path');
        });
    }
};
