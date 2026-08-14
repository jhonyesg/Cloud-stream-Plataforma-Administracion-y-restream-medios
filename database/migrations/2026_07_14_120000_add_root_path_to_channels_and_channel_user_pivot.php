<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('root_path', 1024)->nullable()->after('logo_path');
        });

        Schema::create('channel_user', function (Blueprint $table) {
            $table->uuid('channel_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->primary(['channel_id', 'user_id']);
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id', 'idx_channel_user_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_user');

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('root_path');
        });
    }
};