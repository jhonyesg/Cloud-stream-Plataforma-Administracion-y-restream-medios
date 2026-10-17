<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckDestructiveMigrationsStrictDataTest extends TestCase
{
    private string $tmpDir;

    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/cloudstream-dbmig-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    private function writeMigration(string $name, string $body): string
    {
        $path = $this->tmpDir . '/' . $name;
        $contents = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Support\\Facades\\DB;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        {$body}\n    }\n\n    public function down(): void\n    {\n    }\n};\n";
        file_put_contents($path, $contents);
        $this->createdFiles[] = $path;

        return $path;
    }

    #[Test]
    public function truncate_without_trait_is_flagged_in_strict_data_mode(): void
    {
        $this->writeMigration('2026_10_17_120000_destructive_truncate.php', "DB::table('users')->truncate();");

        $exit = Artisan::call('check:destructive-migrations', ['--path' => $this->tmpDir, '--strict-data' => true]);

        $this->assertSame(1, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('2026_10_17_120000_destructive_truncate.php', $output);
        $this->assertStringContainsString('truncate', $output);
    }

    #[Test]
    public function truncate_with_trait_and_critical_tables_is_accepted(): void
    {
        $body = "DB::table('users')->truncate();";
        $contents = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Migrations\\WithDataSafetySnapshot;\nuse Illuminate\\Support\\Facades\\DB;\n\nreturn new class extends Migration\n{\n    use WithDataSafetySnapshot;\n\n    protected array \$criticalTables = ['users'];\n\n    public function up(): void\n    {\n        {$body}\n    }\n\n    public function down(): void\n    {\n    }\n};\n";
        $path = $this->tmpDir . '/2026_10_17_120000_protected_truncate.php';
        file_put_contents($path, $contents);
        $this->createdFiles[] = $path;

        $exit = Artisan::call('check:destructive-migrations', ['--path' => $this->tmpDir, '--strict-data' => true]);

        $this->assertSame(0, $exit, 'protected migration should pass strict-data lint: ' . Artisan::output());
    }

    #[Test]
    public function raw_delete_from_is_flagged_in_strict_data_mode(): void
    {
        $this->writeMigration('2026_10_17_120000_raw_delete.php', "DB::statement('DELETE FROM users WHERE id < 100');");

        $exit = Artisan::call('check:destructive-migrations', ['--path' => $this->tmpDir, '--strict-data' => true]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('2026_10_17_120000_raw_delete.php', Artisan::output());
    }

    #[Test]
    public function delete_with_row_count_is_flagged_in_strict_data_mode(): void
    {
        $this->writeMigration('2026_10_17_120000_bounded_delete.php', "DB::table('users')->where('id', '<', 100)->delete(50);");

        $exit = Artisan::call('check:destructive-migrations', ['--path' => $this->tmpDir, '--strict-data' => true]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('2026_10_17_120000_bounded_delete.php', Artisan::output());
    }

    #[Test]
    public function destructive_data_ops_are_not_flagged_in_ddl_only_mode(): void
    {
        // The base lint (without --strict-data) only catches DDL. A migration
        // with only data-level destruction (no Schema::drop) should pass
        // when strict-data is NOT set — the goal is opt-in detection, not
        // a breaking change.
        $this->writeMigration('2026_10_17_120000_destructive_data_only.php', "DB::table('users')->truncate();");

        $exit = Artisan::call('check:destructive-migrations', ['--path' => $this->tmpDir]);

        $this->assertSame(0, $exit, 'data-level destruction should NOT be caught by DDL-only lint');
    }

    #[Test]
    public function empty_directory_returns_failure_with_message(): void
    {
        $exit = Artisan::call('check:destructive-migrations', ['--path' => $this->tmpDir, '--strict-data' => true]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('No migration files found', Artisan::output());
    }
}
