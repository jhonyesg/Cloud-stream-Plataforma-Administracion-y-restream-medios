<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restream_targets', function (Blueprint $table) {
            $table->boolean('keep_recording')->default(true)->after('platform_privacy');
        });
    }

    public function down(): void
    {
        Schema::table('restream_targets', function (Blueprint $table) {
            $table->dropColumn('keep_recording');
        });
    }
};