<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE restream_platform AS ENUM ('facebook', 'tiktok', 'youtube', 'custom')");
        DB::statement("CREATE TYPE restream_target_status AS ENUM ('idle', 'active', 'error')");

        DB::statement(<<<'SQL'
            CREATE TABLE restream_quotas (
                id uuid PRIMARY KEY,
                user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                enabled boolean NOT NULL DEFAULT false,
                max_outputs smallint NOT NULL DEFAULT 1,
                granted_by uuid NULL REFERENCES users(id) ON DELETE SET NULL,
                granted_at timestamptz NULL,
                notes text NULL,
                created_at timestamptz NULL,
                updated_at timestamptz NULL,
                CONSTRAINT restream_quotas_max_outputs_check CHECK (max_outputs IN (1, 2, 3, 4)),
                CONSTRAINT restream_quotas_user_unique UNIQUE (user_id)
            )
        SQL);

        DB::statement('CREATE INDEX ix_restream_quotas_user_id ON restream_quotas(user_id)');

        DB::statement(<<<'SQL'
            CREATE TABLE restream_targets (
                id uuid PRIMARY KEY,
                user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                channel_id uuid NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
                platform restream_platform NOT NULL,
                name varchar(80) NOT NULL,
                destination_url text NOT NULL,
                stream_key text NOT NULL,
                enabled boolean NOT NULL DEFAULT false,
                status restream_target_status NOT NULL DEFAULT 'idle',
                last_error text NULL,
                last_started_at timestamptz NULL,
                last_stopped_at timestamptz NULL,
                created_by uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                created_at timestamptz NULL,
                updated_at timestamptz NULL,
                CONSTRAINT restream_targets_user_channel_platform_unique UNIQUE (user_id, channel_id, platform)
            )
        SQL);

        DB::statement('CREATE INDEX ix_restream_targets_user_id ON restream_targets(user_id)');
        DB::statement('CREATE INDEX ix_restream_targets_channel_id ON restream_targets(channel_id)');
        DB::statement('CREATE INDEX ix_restream_targets_enabled ON restream_targets(enabled)');
    }

    public function down(): void
    {
        Schema::dropIfExists('restream_targets');
        Schema::dropIfExists('restream_quotas');
        DB::statement('DROP TYPE IF EXISTS restream_target_status');
        DB::statement('DROP TYPE IF EXISTS restream_platform');
    }
};