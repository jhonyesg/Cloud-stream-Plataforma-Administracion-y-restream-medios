<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS idx_users_email_lower ON users (LOWER(email))');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_users_username_lower ON users (LOWER(username))');
    }

    public function down(): void
    {
        Schema::table('users', function ($table) {
            $table->dropIndex('idx_users_email_lower');
            $table->dropIndex('idx_users_username_lower');
        });
    }
};
