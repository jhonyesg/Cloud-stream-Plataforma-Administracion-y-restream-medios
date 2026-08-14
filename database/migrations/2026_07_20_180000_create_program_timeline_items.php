<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE timeline_item_kind AS ENUM ('content', 'cue', 'fallback')");
        DB::statement("CREATE TYPE timeline_item_status AS ENUM ('planned', 'queued', 'playing', 'paused', 'completed', 'skipped', 'failed')");

        Schema::create('program_timeline_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->uuid('block_id')->nullable();
            $table->smallInteger('day_of_month');
            $table->integer('starts_at_sec');
            $table->integer('ends_at_sec');
            $table->double('effective_duration_sec');
            $table->uuid('media_item_id')->nullable();
            $table->uuid('playlist_item_id')->nullable();
            $table->double('cue_in_sec')->nullable();
            $table->double('cue_out_sec')->nullable();
            $table->double('resume_offset_sec')->default(0);
            $table->uuid('parent_content_id')->nullable();
            $table->boolean('is_interruptible')->default(true);
            $table->integer('timeline_version')->default(0);
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('schedule_templates')->onDelete('cascade');
            $table->foreign('block_id')->references('id')->on('schedule_blocks')->onDelete('set null');
            $table->foreign('media_item_id')->references('id')->on('media_items')->onDelete('set null');
            $table->foreign('playlist_item_id')->references('id')->on('playlist_items')->onDelete('set null');
        });

        // Self-referencing FK must be added after the PK exists so PostgreSQL
        // can match the referenced column to a unique constraint.
        DB::statement('ALTER TABLE program_timeline_items ADD CONSTRAINT program_timeline_items_parent_content_id_foreign FOREIGN KEY (parent_content_id) REFERENCES program_timeline_items(id) ON DELETE SET NULL');

        DB::statement("ALTER TABLE program_timeline_items ADD COLUMN kind timeline_item_kind NOT NULL DEFAULT 'content'");
        DB::statement("ALTER TABLE program_timeline_items ADD COLUMN status timeline_item_status NOT NULL DEFAULT 'planned'");

        DB::statement('CREATE INDEX ix_timeline_day_start ON program_timeline_items(template_id, day_of_month, starts_at_sec)');
        DB::statement('CREATE INDEX ix_timeline_media ON program_timeline_items(media_item_id)');
        DB::statement('CREATE INDEX ix_timeline_block ON program_timeline_items(block_id)');
        DB::statement('CREATE INDEX ix_timeline_status ON program_timeline_items(template_id, day_of_month, status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('program_timeline_items');
        DB::statement('DROP TYPE IF EXISTS timeline_item_status');
        DB::statement('DROP TYPE IF EXISTS timeline_item_kind');
    }
};
