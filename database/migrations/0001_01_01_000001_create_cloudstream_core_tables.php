<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE channel_status AS ENUM ('draft', 'active', 'suspended', 'archived')");
        DB::statement("CREATE TYPE provider_type AS ENUM ('local', 's3', 'ftp', 'http')");
        DB::statement("CREATE TYPE media_kind AS ENUM ('video', 'ad', 'image', 'audio', 'slate', 'other')");
        DB::statement("CREATE TYPE media_status AS ENUM ('pending', 'probing', 'ready', 'failed')");
        DB::statement("CREATE TYPE transition_in AS ENUM ('cut', 'fade', 'slide_left', 'slide_right', 'dissolve')");
        DB::statement("CREATE TYPE block_kind AS ENUM ('program', 'ad_break')");
        DB::statement("CREATE TYPE template_status AS ENUM ('draft', 'active', 'archived')");
        DB::statement("CREATE TYPE emission_status AS ENUM ('offline', 'starting', 'live', 'paused', 'error')");

        Schema::create('channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_id');
            $table->string('slug', 50)->unique();
            $table->string('display_name', 120);
            $table->text('description')->nullable();
            $table->string('logo_path', 512)->nullable();
            $table->smallInteger('default_width')->default(1280);
            $table->smallInteger('default_height')->default(720);
            $table->timestampTz('last_emitted_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE channels ADD COLUMN status channel_status NOT NULL DEFAULT \'draft\'');
        DB::statement('CREATE INDEX ix_channels_owner_id ON channels(owner_id)');
        DB::statement('CREATE INDEX ix_channels_status ON channels(status)');
        DB::statement('CREATE INDEX ix_channels_deleted_at ON channels(deleted_at)');

        Schema::create('media_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('base_path', 1024);
            $table->string('type', 20);
            $table->jsonb('config')->default('{}');
            $table->uuid('created_by');
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });

        DB::statement("ALTER TABLE media_providers ALTER COLUMN type TYPE provider_type USING type::provider_type");

        Schema::create('media_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->uuid('parent_id')->nullable();
            $table->uuid('channel_id')->nullable();
            $table->string('name', 255);
            $table->string('rel_path', 1024);
            $table->string('media_kind_scope', 20)->nullable();
            $table->timestamps();
            $table->foreign('provider_id')->references('id')->on('media_providers')->onDelete('cascade');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('set null');
            $table->unique(['provider_id', 'rel_path'], 'uq_media_folders_provider_path');
            $table->index('channel_id', 'ix_media_folders_channel_id');
            $table->index('parent_id', 'ix_media_folders_parent_id');
        });

        DB::statement('ALTER TABLE media_folders ADD CONSTRAINT media_folders_parent_id_fk FOREIGN KEY (parent_id) REFERENCES media_folders(id) ON DELETE CASCADE');

        Schema::create('media_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->uuid('folder_id')->nullable();
            $table->uuid('channel_id')->nullable();
            $table->string('filename', 512);
            $table->string('rel_path', 1024);
            $table->char('sha256', 64)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->double('duration_sec')->nullable();
            $table->smallInteger('width')->nullable();
            $table->smallInteger('height')->nullable();
            $table->string('codec_video', 60)->nullable();
            $table->string('codec_audio', 60)->nullable();
            $table->integer('bitrate_kbps')->nullable();
            $table->string('thumb_path', 1024)->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->string('status_reason', 255)->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestamps();
            $table->foreign('provider_id')->references('id')->on('media_providers')->onDelete('cascade');
            $table->foreign('folder_id')->references('id')->on('media_folders')->onDelete('set null');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('set null');
        });

        DB::statement("ALTER TABLE media_items ADD COLUMN kind media_kind NOT NULL DEFAULT 'video'");
        DB::statement("ALTER TABLE media_items ADD COLUMN status media_status NOT NULL DEFAULT 'pending'");
        DB::statement('CREATE INDEX ix_media_items_provider_id ON media_items(provider_id)');
        DB::statement('CREATE INDEX ix_media_items_folder_id ON media_items(folder_id)');
        DB::statement('CREATE INDEX ix_media_items_channel_id ON media_items(channel_id)');
        DB::statement('CREATE INDEX ix_media_items_kind ON media_items(kind)');
        DB::statement('CREATE INDEX ix_media_items_status ON media_items(status)');
        DB::statement('CREATE INDEX ix_media_items_provider_sha ON media_items(provider_id, sha256)');
        DB::statement('CREATE INDEX ix_media_items_deleted_at ON media_items(deleted_at)');

        Schema::create('playlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('channel_id');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('loop')->default(true);
            $table->boolean('is_default')->default(false);
            $table->double('total_duration_sec')->default(0);
            $table->timestamps();
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->index('channel_id', 'ix_playlists_channel_id');
        });

        DB::statement('CREATE UNIQUE INDEX uq_playlists_default ON playlists(channel_id) WHERE is_default = true');

        Schema::create('playlist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('playlist_id');
            $table->uuid('media_item_id');
            $table->integer('position');
            $table->double('cue_in_sec')->nullable();
            $table->double('cue_out_sec')->nullable();
            $table->string('transition_in', 20)->nullable();
            $table->timestamps();
            $table->foreign('playlist_id')->references('id')->on('playlists')->onDelete('cascade');
            $table->foreign('media_item_id')->references('id')->on('media_items')->onDelete('restrict');
            $table->unique(['playlist_id', 'position'], 'uq_playlist_position');
            $table->index('playlist_id', 'ix_playlist_items_playlist_id');
        });

        DB::statement("ALTER TABLE playlist_items ALTER COLUMN transition_in TYPE transition_in USING transition_in::transition_in");
        DB::statement("ALTER TABLE playlist_items ADD CONSTRAINT ck_playlist_items_cue_order CHECK (cue_in_sec IS NULL OR cue_out_sec IS NULL OR cue_in_sec < cue_out_sec)");

        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('channel_id');
            $table->string('name', 120);
            $table->smallInteger('year');
            $table->smallInteger('month');
            $table->boolean('is_replicated')->default(false);
            $table->uuid('cloned_from_id')->nullable();
            $table->timestamps();
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->index('channel_id', 'ix_schedule_tmpl_channel');
        });

        DB::statement("ALTER TABLE schedule_templates ADD COLUMN status template_status NOT NULL DEFAULT 'draft'");
        DB::statement('CREATE UNIQUE INDEX uq_schedule_tmpl_active ON schedule_templates(channel_id, year, month, status)');
        DB::statement('ALTER TABLE schedule_templates ADD CONSTRAINT schedule_tmpl_cloned_from_fk FOREIGN KEY (cloned_from_id) REFERENCES schedule_templates(id) ON DELETE SET NULL');

        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->smallInteger('day_of_month')->nullable();
            $table->smallInteger('weekday_mask')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('kind', 20);
            $table->uuid('playlist_id')->nullable();
            $table->smallInteger('priority')->default(0);
            $table->timestamps();
            $table->foreign('template_id')->references('id')->on('schedule_templates')->onDelete('cascade');
            $table->foreign('playlist_id')->references('id')->on('playlists')->onDelete('restrict');
            $table->index('template_id', 'ix_schedule_blocks_template');
            $table->index('day_of_month', 'ix_schedule_blocks_day');
        });

        DB::statement("ALTER TABLE schedule_blocks ALTER COLUMN kind TYPE block_kind USING kind::block_kind");
        DB::statement("ALTER TABLE schedule_blocks ADD CONSTRAINT ck_schedule_blocks_coherent CHECK ((kind = 'program' AND playlist_id IS NOT NULL) OR (kind = 'ad_break' AND playlist_id IS NULL))");

        Schema::create('schedule_block_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('block_id');
            $table->uuid('media_item_id');
            $table->smallInteger('position');
            $table->timestamps();
            $table->foreign('block_id')->references('id')->on('schedule_blocks')->onDelete('cascade');
            $table->foreign('media_item_id')->references('id')->on('media_items')->onDelete('restrict');
            $table->unique(['block_id', 'position'], 'uq_schedule_block_items_position');
            $table->index('block_id', 'ix_schedule_block_items_block');
        });

        Schema::create('virtual_screens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('channel_id')->unique();
            $table->string('name', 120);
            $table->smallInteger('width')->default(1280);
            $table->smallInteger('height')->default(720);
            $table->jsonb('layout')->default('{}');
            $table->string('theme', 60)->nullable();
            $table->timestamps();
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });

        Schema::create('emission_state', function (Blueprint $table) {
            $table->uuid('channel_id')->primary();
            $table->string('status', 20);
            $table->uuid('current_block_id')->nullable();
            $table->uuid('current_playlist_id')->nullable();
            $table->uuid('current_item_id')->nullable();
            $table->double('position_in_item_sec')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('current_block_id')->references('id')->on('schedule_blocks')->onDelete('set null');
            $table->foreign('current_playlist_id')->references('id')->on('playlists')->onDelete('set null');
            $table->foreign('current_item_id')->references('id')->on('media_items')->onDelete('set null');
        });

        DB::statement("ALTER TABLE emission_state ALTER COLUMN status TYPE emission_status USING status::emission_status");
        DB::statement("ALTER TABLE emission_state ALTER COLUMN updated_at SET DEFAULT now()");

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('channel_id')->nullable();
            $table->string('action', 80);
            $table->string('entity_type', 80);
            $table->uuid('entity_id')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('set null');
            $table->index(['user_id', 'at'], 'ix_audit_user_at');
            $table->index(['entity_type', 'entity_id'], 'ix_audit_entity');
            $table->index(['channel_id', 'at'], 'ix_audit_channel_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('emission_state');
        Schema::dropIfExists('virtual_screens');
        Schema::dropIfExists('schedule_block_items');
        Schema::dropIfExists('schedule_blocks');
        Schema::dropIfExists('schedule_templates');
        Schema::dropIfExists('playlist_items');
        Schema::dropIfExists('playlists');
        Schema::dropIfExists('media_items');
        Schema::dropIfExists('media_folders');
        Schema::dropIfExists('media_providers');
        Schema::dropIfExists('channels');

        DB::statement('DROP TYPE IF EXISTS emission_status');
        DB::statement('DROP TYPE IF EXISTS template_status');
        DB::statement('DROP TYPE IF EXISTS block_kind');
        DB::statement('DROP TYPE IF EXISTS transition_in');
        DB::statement('DROP TYPE IF EXISTS media_status');
        DB::statement('DROP TYPE IF EXISTS media_kind');
        DB::statement('DROP TYPE IF EXISTS provider_type');
        DB::statement('DROP TYPE IF EXISTS channel_status');
    }
};
