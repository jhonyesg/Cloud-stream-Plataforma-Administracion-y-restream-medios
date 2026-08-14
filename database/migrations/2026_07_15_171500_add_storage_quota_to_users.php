<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ADD COLUMN storage_limit_bytes BIGINT NULL');
        DB::statement('ALTER TABLE users ADD COLUMN used_bytes BIGINT NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS used_bytes');
        DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS storage_limit_bytes');
    }
};