<?php

namespace Tests\Feature;

use App\Services\MigrationSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationDataSafetyTest extends TestCase
{
    protected MigrationSnapshot $snapshot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshot = app(MigrationSnapshot::class);

        Schema::dropIfExists('_snapshot_test');
        Schema::create('_snapshot_test', function ($t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });

        DB::table('_snapshot_test')->insert([
            ['name' => 'a', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'b', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'c', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('_snapshot_test');
        parent::tearDown();
    }

    public function test_capture_writes_gzip_json_file(): void
    {
        $name = '_test_capture';
        $result = $this->snapshot->capture($name, ['_snapshot_test']);

        $this->assertArrayHasKey('_snapshot_test', $result['files']);
        $this->assertFileExists($result['files']['_snapshot_test']);

        $raw = file_get_contents('compress.zlib://'.$result['files']['_snapshot_test']);
        $data = json_decode($raw, true);

        $this->assertCount(3, $data);
        $this->assertEquals(['a', 'b', 'c'], array_column($data, 'name'));
    }

    public function test_round_trip_capture_drop_restore(): void
    {
        $name = '_test_roundtrip';

        // 1. Capture
        $captured = $this->snapshot->capture($name, ['_snapshot_test']);
        $this->assertEquals(3, $captured['manifest']['tables']['_snapshot_test']['rows']);

        // 2. Drop and verify empty
        Schema::drop('_snapshot_test');
        Schema::create('_snapshot_test', function ($t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        $this->assertEquals(0, DB::table('_snapshot_test')->count());

        // 3. Restore
        $restored = $this->snapshot->restore($name);
        $this->assertArrayHasKey('_snapshot_test', $restored);
        $this->assertEquals(3, $restored['_snapshot_test']);

        // 4. Verify rows
        $names = DB::table('_snapshot_test')->orderBy('name')->pluck('name')->all();
        $this->assertEquals(['a', 'b', 'c'], $names);
    }

    public function test_restore_is_wrapped_in_transaction(): void
    {
        $name = '_test_tx';
        $this->snapshot->capture($name, ['_snapshot_test']);

        Schema::drop('_snapshot_test');
        Schema::create('_snapshot_test', function ($t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });

        $file = $this->snapshot->basePath()."/{$name}/_snapshot_test.json.gz";
        $payload = [
            ['name' => 'x', 'created_at' => 'not-a-real-timestamp-that-pgsql-will-accept-xxxxxxxxx'],
            ['name' => null],
        ];
        file_put_contents('compress.zlib://'.$file, json_encode($payload));

        try {
            $restored = $this->snapshot->restore($name);
        } catch (QueryException $e) {
            $restored = [];
        }

        $this->assertArrayNotHasKey('_snapshot_test', $restored);
        $this->assertEquals(0, DB::table('_snapshot_test')->count());
    }

    public function test_list_available_includes_recent_snapshot(): void
    {
        $name = '_test_list';
        $this->snapshot->capture($name, ['_snapshot_test']);

        $list = $this->snapshot->listAvailable();
        $found = collect($list)->firstWhere('migration', $name);

        $this->assertNotNull($found);
        $this->assertEquals(1, $found['tables']);
        $this->assertEquals(3, $found['total_rows']);
    }

    public function test_release_snapshot_round_trip(): void
    {
        if (! $this->snapshot->releaseSnapshotExists()) {
            $this->markTestSkipped('No release snapshot available in this environment.');
        }

        if (! DB::getSchemaBuilder()->hasTable('channels')) {
            $this->markTestSkipped('Test DB does not have migrations applied (no channels table).');
        }

        $before = DB::table('channels')->count();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ProductionDataSeeder'])
            ->assertExitCode(0);
        $after = DB::table('channels')->count();

        $this->assertGreaterThan(0, $after);
        $this->assertEquals($before, $after);
    }

    public function test_ci_check_passes_with_no_violations(): void
    {
        $this->artisan('check:destructive-migrations')
            ->assertExitCode(0);
    }
}
