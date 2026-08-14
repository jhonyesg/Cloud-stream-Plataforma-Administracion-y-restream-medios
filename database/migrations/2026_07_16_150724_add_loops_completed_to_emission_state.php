<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emission_state', function (Blueprint $table) {
            $table->smallInteger('loops_completed')->default(0)->notNull();
        });
    }

    public function down(): void
    {
        Schema::table('emission_state', function (Blueprint $table) {
            $table->dropColumn('loops_completed');
        });
    }
};
