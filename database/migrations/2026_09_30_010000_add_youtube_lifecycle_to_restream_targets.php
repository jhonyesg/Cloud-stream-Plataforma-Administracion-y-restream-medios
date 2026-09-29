<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restream_targets', function (Blueprint $t) {
            if (! Schema::hasColumn('restream_targets', 'platform_broadcast_lifecycle')) {
                $t->string('platform_broadcast_lifecycle', 32)->nullable()->after('platform_broadcast_id');
            }
            if (! Schema::hasColumn('restream_targets', 'platform_broadcast_lifecycle_at')) {
                $t->timestamp('platform_broadcast_lifecycle_at')->nullable()->after('platform_broadcast_lifecycle');
            }
            if (! Schema::hasColumn('restream_targets', 'platform_broadcast_lifecycle_error')) {
                $t->text('platform_broadcast_lifecycle_error')->nullable()->after('platform_broadcast_lifecycle_at');
            }
            if (! Schema::hasColumn('restream_targets', 'last_youtube_poll_at')) {
                $t->timestamp('last_youtube_poll_at')->nullable()->after('platform_broadcast_lifecycle_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restream_targets', function (Blueprint $t) {
            if (Schema::hasColumn('restream_targets', 'last_youtube_poll_at')) {
                $t->dropColumn('last_youtube_poll_at');
            }
            if (Schema::hasColumn('restream_targets', 'platform_broadcast_lifecycle_error')) {
                $t->dropColumn('platform_broadcast_lifecycle_error');
            }
            if (Schema::hasColumn('restream_targets', 'platform_broadcast_lifecycle_at')) {
                $t->dropColumn('platform_broadcast_lifecycle_at');
            }
            if (Schema::hasColumn('restream_targets', 'platform_broadcast_lifecycle')) {
                $t->dropColumn('platform_broadcast_lifecycle');
            }
        });
    }
};