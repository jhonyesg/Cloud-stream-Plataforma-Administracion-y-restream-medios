<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('virtual_screens', function (Blueprint $table) {
            $table->string('codec_video', 30)->nullable()->default('libx264')->after('audio_bitrate_kbps');
            $table->string('codec_audio', 30)->nullable()->default('aac')->after('codec_video');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_screens', function (Blueprint $table) {
            $table->dropColumn(['codec_video', 'codec_audio']);
        });
    }
};