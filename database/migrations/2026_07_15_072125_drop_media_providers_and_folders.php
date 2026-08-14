<?php

use App\Database\Migrations\Concerns\WithDataSafetySnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use WithDataSafetySnapshot;

    protected array $criticalTables = [
        'media_providers',
        'media_folders',
        'media_items',
    ];

    protected array $criticalColumns = [
        'media_items' => ['id', 'channel_id', 'filename', 'provider_id', 'folder_id', 'rel_path', 'sha256', 'size_bytes', 'mime_type', 'kind', 'status', 'status_reason', 'duration_sec', 'width', 'height', 'codec_video', 'codec_audio', 'bitrate_kbps', 'metadata', 'created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->snapshotBefore();

        if (Schema::hasColumn('media_items', 'provider_id')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->dropForeign(['provider_id']);
                $table->dropColumn('provider_id');
            });
        }

        if (Schema::hasColumn('media_items', 'folder_id')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->dropForeign(['folder_id']);
                $table->dropColumn('folder_id');
            });
        }

        if (Schema::hasColumn('media_items', 'rel_path')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->dropColumn('rel_path');
            });
        }

        if (Schema::hasIndex('media_items', 'ix_media_items_provider_id')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->dropIndex('ix_media_items_provider_id');
            });
        }

        if (Schema::hasIndex('media_items', 'ix_media_items_provider_sha')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->dropIndex('ix_media_items_provider_sha');
            });
        }

        if (Schema::hasIndex('media_items', 'ix_media_items_folder_id')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->dropIndex('ix_media_items_folder_id');
            });
        }

        Schema::dropIfExists('media_folders');
        Schema::dropIfExists('media_providers');

        DB::statement('DROP TYPE IF EXISTS provider_type');
    }

    public function down(): void
    {
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

        DB::statement('ALTER TABLE media_providers ALTER COLUMN type TYPE provider_type USING type::provider_type');

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

        Schema::table('media_items', function (Blueprint $table) {
            $table->uuid('provider_id')->nullable();
            $table->uuid('folder_id')->nullable();
            $table->string('rel_path', 1024)->nullable();
            $table->foreign('provider_id')->references('id')->on('media_providers')->onDelete('cascade');
            $table->foreign('folder_id')->references('id')->on('media_folders')->onDelete('set null');
            $table->index('provider_id', 'ix_media_items_provider_id');
            $table->index('folder_id', 'ix_media_items_folder_id');
        });

        $this->restoreSnapshot();
    }
};
