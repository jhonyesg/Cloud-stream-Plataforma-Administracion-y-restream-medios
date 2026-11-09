<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restream_target_schedules', function (Blueprint $table) {
            $table->string('schedule_title', 150)->nullable()->after('name');
            $table->text('schedule_description')->nullable()->after('schedule_title');
            $table->string('platform_privacy', 16)->nullable()->after('schedule_description');
            $table->boolean('keep_recording')->nullable()->after('platform_privacy');
        });
    }

    public function down(): void
    {
        Schema::table('restream_target_schedules', function (Blueprint $table) {
            $table->dropColumn(['schedule_title', 'schedule_description', 'platform_privacy', 'keep_recording']);
        });
    }
};
