<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE virtual_screen_fallback_type AS ENUM ('black', 'test_pattern', 'image_loop', 'media')");

        Schema::table('virtual_screens', function (Blueprint $table) {
            $table->uuid('fallback_media_item_id')->nullable()->after('logo_opacity');
        });

        DB::statement("ALTER TABLE virtual_screens ADD COLUMN fallback_type virtual_screen_fallback_type NOT NULL DEFAULT 'black'");

        DB::statement('ALTER TABLE virtual_screens ADD CONSTRAINT virtual_screens_fallback_media_item_id_fk FOREIGN KEY (fallback_media_item_id) REFERENCES media_items(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE virtual_screens DROP CONSTRAINT IF EXISTS virtual_screens_fallback_media_item_id_fk');
        DB::statement('ALTER TABLE virtual_screens DROP COLUMN IF EXISTS fallback_type');
        Schema::table('virtual_screens', function (Blueprint $table) {
            $table->dropColumn('fallback_media_item_id');
        });
        DB::statement('DROP TYPE IF EXISTS virtual_screen_fallback_type');
    }
};
