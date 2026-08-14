<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("DROP TYPE IF EXISTS user_status");
        DB::statement("DROP TYPE IF EXISTS user_role");
        DB::statement("CREATE TYPE user_role AS ENUM ('admin', 'client')");
        DB::statement("CREATE TYPE user_status AS ENUM ('active', 'suspended')");

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username', 80)->unique();
            $table->string('email', 254)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('display_name', 120)->nullable();
            $table->string('avatar_path', 512)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('last_login_at');
        });

        DB::statement('ALTER TABLE users ADD COLUMN role user_role NOT NULL');
        DB::statement('ALTER TABLE users ADD COLUMN status user_status NOT NULL DEFAULT \'active\'');
        DB::statement('ALTER TABLE users ADD COLUMN owner_id uuid NULL REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('CREATE INDEX ix_users_owner_id ON users(owner_id)');
        DB::statement('CREATE INDEX ix_users_status ON users(status)');
        DB::statement('CREATE INDEX ix_users_role ON users(role)');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        DB::statement('DROP TYPE IF EXISTS user_status');
        DB::statement('DROP TYPE IF EXISTS user_role');
    }
};
