<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_screens', function (Blueprint $table) {
            $table->string('video_preset', 30)->nullable()->default('veryfast')->after('codec_video');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_screens', function (Blueprint $table) {
            $table->dropColumn('video_preset');
        });
    }
};
