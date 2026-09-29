<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restream_targets', function (Blueprint $t) {
            if (! Schema::hasColumn('restream_targets', 'daemon_stalled_at')) {
                $t->timestamp('daemon_stalled_at')->nullable()->after('last_youtube_poll_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restream_targets', function (Blueprint $t) {
            if (Schema::hasColumn('restream_targets', 'daemon_stalled_at')) {
                $t->dropColumn('daemon_stalled_at');
            }
        });
    }
};