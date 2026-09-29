<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restream_targets', function (Blueprint $table) {
            $table->string('platform_privacy', 20)->nullable()->after('platform_broadcast_id');
        });
    }

    public function down(): void
    {
        Schema::table('restream_targets', function (Blueprint $table) {
            $table->dropColumn('platform_privacy');
        });
    }
};