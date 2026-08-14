<?php

use App\Database\Migrations\Concerns\WithDataSafetySnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use WithDataSafetySnapshot;

    protected array $criticalTables = ['channels'];

    protected array $criticalColumns = [
        'channels' => ['id', 'owner_id', 'slug', 'display_name', 'description', 'status', 'default_width', 'default_height', 'root_path', 'created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->snapshotBefore();

        // Step 1: For channels without a virtual_screens row, create one using the channel's default_width/height
        $channelsWithoutScreen = DB::table('channels')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('virtual_screens')
                    ->whereColumn('virtual_screens.channel_id', 'channels.id');
            })
            ->get(['id', 'default_width', 'default_height', 'display_name']);

        foreach ($channelsWithoutScreen as $ch) {
            DB::table('virtual_screens')->insert([
                'id' => DB::raw('gen_random_uuid()'),
                'channel_id' => $ch->id,
                'name' => 'Pantalla '.($ch->display_name ?? 'Canal'),
                'width' => $ch->default_width ?? 1280,
                'height' => $ch->default_height ?? 720,
                'layout' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Step 2: Sync virtual_screens width/height from channels where VS still has defaults (1280x720)
        // and the channel has different values — avoids overwriting user-edited VS values
        DB::statement('
            UPDATE virtual_screens
            SET width = channels.default_width,
                height = channels.default_height,
                updated_at = NOW()
            FROM channels
            WHERE virtual_screens.channel_id = channels.id
              AND virtual_screens.width = 1280
              AND virtual_screens.height = 720
              AND (channels.default_width != 1280 OR channels.default_height != 720)
        ');

        // Step 3: Drop the columns from channels
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['default_width', 'default_height']);
        });
    }

    public function down(): void
    {
        // Re-add the columns
        Schema::table('channels', function (Blueprint $table) {
            $table->smallInteger('default_width')->default(1280)->after('root_path');
            $table->smallInteger('default_height')->default(720)->after('default_width');
        });

        // Copy back from virtual_screens
        DB::statement('
            UPDATE channels
            SET default_width = virtual_screens.width,
                default_height = virtual_screens.height
            FROM virtual_screens
            WHERE channels.id = virtual_screens.channel_id
        ');

        $this->restoreSnapshot();
    }
};
