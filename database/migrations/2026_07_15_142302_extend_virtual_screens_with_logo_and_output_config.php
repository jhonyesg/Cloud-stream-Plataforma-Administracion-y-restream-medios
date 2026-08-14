<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('virtual_screens', function (Blueprint $table) {
            $table->string('output_protocol', 8)->nullable()->after('theme');
            $table->string('output_url', 500)->nullable()->after('output_protocol');
            $table->smallInteger('fps')->nullable()->default(30)->after('output_url');
            $table->integer('video_bitrate_kbps')->nullable()->default(2500)->after('fps');
            $table->integer('audio_bitrate_kbps')->nullable()->default(128)->after('video_bitrate_kbps');
            $table->uuid('logo_media_item_id')->nullable()->after('audio_bitrate_kbps');
            $table->integer('logo_x')->nullable()->default(40)->after('logo_media_item_id');
            $table->integer('logo_y')->nullable()->default(40)->after('logo_x');
            $table->integer('logo_w')->nullable()->default(200)->after('logo_y');
            $table->integer('logo_h')->nullable()->default(80)->after('logo_w');
            $table->decimal('logo_opacity', 3, 2)->nullable()->default(0.85)->after('logo_h');

            $table->foreign('logo_media_item_id')->references('id')->on('media_items')->nullOnDelete();
            $table->index('logo_media_item_id', 'ix_virtual_screens_logo_media');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_screens', function (Blueprint $table) {
            $table->dropIndex('ix_virtual_screens_logo_media');
            $table->dropForeign(['logo_media_item_id']);
            $table->dropColumn([
                'output_protocol',
                'output_url',
                'fps',
                'video_bitrate_kbps',
                'audio_bitrate_kbps',
                'logo_media_item_id',
                'logo_x',
                'logo_y',
                'logo_w',
                'logo_h',
                'logo_opacity',
            ]);
        });
    }
};