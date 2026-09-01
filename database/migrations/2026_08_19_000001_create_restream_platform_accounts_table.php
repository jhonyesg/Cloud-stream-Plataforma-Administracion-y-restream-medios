<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE restream_account_platform AS ENUM ('youtube', 'facebook')");

        DB::statement(<<<'SQL'
            CREATE TABLE restream_platform_accounts (
                id uuid PRIMARY KEY,
                user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                platform restream_account_platform NOT NULL,
                platform_account_id varchar(255) NOT NULL,
                display_name varchar(255) NULL,
                access_token text NOT NULL,
                refresh_token text NULL,
                token_expires_at timestamptz NULL,
                scopes text NULL,
                created_at timestamptz NULL,
                updated_at timestamptz NULL,
                CONSTRAINT restream_platform_accounts_user_platform_unique UNIQUE (user_id, platform)
            )
        SQL);

        DB::statement('CREATE INDEX ix_restream_platform_accounts_user_id ON restream_platform_accounts(user_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('restream_platform_accounts');
        DB::statement('DROP TYPE IF EXISTS restream_account_platform');
    }
};
