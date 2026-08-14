<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->bigInteger('storage_limit_bytes')->nullable()->after('public_hls_url');
            $table->bigInteger('used_bytes')->default(0)->after('storage_limit_bytes');
        });

        DB::statement('
            UPDATE channels c
            SET storage_limit_bytes = u.storage_limit_bytes
            FROM users u
            WHERE u.id = c.owner_id AND u.storage_limit_bytes IS NOT NULL
        ');

        DB::statement('
            UPDATE channels c
            SET used_bytes = COALESCE((
                SELECT SUM(m.size_bytes)
                FROM media_items m
                WHERE m.channel_id = c.id AND m.size_bytes IS NOT NULL
            ), 0)
        ');
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['storage_limit_bytes', 'used_bytes']);
        });
    }
};
