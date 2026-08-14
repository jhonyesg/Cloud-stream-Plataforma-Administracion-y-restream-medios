<?php

namespace Database\Seeders;

use App\Services\MigrationSnapshot;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $hasReleaseSnapshot = app(MigrationSnapshot::class)->releaseSnapshotExists();

        $this->call([
            UserSeeder::class,
            ProductionDataSeeder::class,
        ]);

        if (! $hasReleaseSnapshot) {
            $this->call(CloudstreamSeeder::class);
        }
    }
}
