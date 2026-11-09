<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restream_target_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('target_id');
            $table->string('kind', 64);
            $table->text('message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');

            $table->foreign('target_id')
                ->references('id')->on('restream_targets')
                ->cascadeOnDelete();

            // Append-only: index that supports "recent events of a target" first.
            $table->index(['target_id', 'occurred_at']);
            $table->index('occurred_at');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restream_target_events');
    }
};
