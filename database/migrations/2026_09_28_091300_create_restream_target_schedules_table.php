<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restream_target_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('target_id');
            $table->string('name', 120)->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->text('notes')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('stopped_by_user')->default(false);
            $table->timestampsTz();

            $table->foreign('target_id')
                ->references('id')->on('restream_targets')
                ->cascadeOnDelete();

            $table->index(['target_id', 'starts_at']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restream_target_schedules');
    }
};
