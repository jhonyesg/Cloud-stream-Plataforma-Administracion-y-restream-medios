<?php

namespace Tests\Feature\Commands;

use App\Providers\AppServiceProvider;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Integration tests for the pre-migrate safety hook in AppServiceProvider.
 *
 * The hook reads from `database/migrations/` by default. To avoid
 * polluting the production tree with test fixtures, these tests use a
 * tmp directory and a TmpAppServiceProvider subclass that overrides
 * callLintCommand() to point the lint at the tmp dir.
 *
 * If any test fails before tearDown runs (assertion error, OOM, signal),
 * the tmp dir is auto-cleaned by PHPUnit's process isolation OR by
 * `/tmp` reaping. There is NEVER a fixture left in `database/migrations/`.
 */
class PreMigrateHookTest extends TestCase
{
    private string $tmpMigrationsDir;

    private array $createdFixtures = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpMigrationsDir = sys_get_temp_dir() . '/cloudstream-mighook-' . uniqid('', true);
        mkdir($this->tmpMigrationsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFixtures as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpMigrationsDir);
        parent::tearDown();
    }

    private function writeFixture(string $name, string $body): string
    {
        $path = $this->tmpMigrationsDir . '/' . $name;
        file_put_contents($path, $this->buildMigrationContents($body));
        $this->createdFixtures[] = $path;

        return $path;
    }

    private function buildMigrationContents(string $body): string
    {
        return "<?php\n\n"
            ."use Illuminate\\Database\\Migrations\\Migration;\n"
            ."use Illuminate\\Database\\Migrations\\WithDataSafetySnapshot;\n"
            ."use Illuminate\\Support\\Facades\\DB;\n\n"
            ."return new class extends Migration\n"
            ."{\n"
            ."    public function up(): void { {$body} }\n"
            ."    public function down(): void {}\n"
            ."};\n";
    }

    private function makeEvent(string $command, array $argv = []): CommandStarting
    {
        $input = new ArgvInput(array_merge([$command], $argv));
        $output = new BufferedOutput();

        return new CommandStarting($command, $input, $output);
    }

    /**
     * Return an AppServiceProvider instance that points the lint at the
     * test's tmp directory. This is the only way to drive the hook
     * without writing to database/migrations/.
     */
    private function appProviderForTmpDir(): AppServiceProvider
    {
        return new TmpAppServiceProvider($this->app, $this->tmpMigrationsDir);
    }

    #[Test]
    public function migrate_aborts_when_destructive_migration_is_on_disk(): void
    {
        $this->writeFixture(
            '2026_10_17_120001_test_hook_block.php',
            "DB::table('users')->truncate();"
        );

        $thrown = null;
        try {
            $this->appProviderForTmpDir()->handleMigrateSafetyHook($this->makeEvent('migrate'));
        } catch (RuntimeException $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'expected RuntimeException when destructive migration is on disk');
        $this->assertStringContainsString('[db-safety] `php artisan migrate` aborted', $thrown->getMessage());
        $this->assertStringContainsString('--allow-quarantined', $thrown->getMessage());
    }

    #[Test]
    public function migrate_with_allow_quarantined_proceeds(): void
    {
        $this->writeFixture(
            '2026_10_17_120002_test_hook_bypass.php',
            "DB::table('users')->truncate();"
        );

        // Should NOT throw
        $this->appProviderForTmpDir()->handleMigrateSafetyHook(
            $this->makeEvent('migrate', ['--allow-quarantined'])
        );
        $this->assertTrue(true, 'migrate --allow-quarantined should not throw');
    }

    #[Test]
    public function migrate_fresh_aborts_when_destructive_migration_is_on_disk(): void
    {
        $this->writeFixture(
            '2026_10_17_120003_test_hook_fresh.php',
            "DB::table('users')->truncate();"
        );

        $thrown = null;
        try {
            $this->appProviderForTmpDir()->handleMigrateSafetyHook($this->makeEvent('migrate:fresh'));
        } catch (RuntimeException $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'expected abort on migrate:fresh');
        $this->assertStringContainsString('migrate:fresh', $thrown->getMessage());
    }

    #[Test]
    public function migrate_rollback_aborts_when_destructive_migration_is_on_disk(): void
    {
        $this->writeFixture(
            '2026_10_17_120004_test_hook_rollback.php',
            "DB::table('users')->truncate();"
        );

        $thrown = null;
        try {
            $this->appProviderForTmpDir()->handleMigrateSafetyHook($this->makeEvent('migrate:rollback'));
        } catch (RuntimeException $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'expected abort on migrate:rollback');
        $this->assertStringContainsString('migrate:rollback', $thrown->getMessage());
    }

    #[Test]
    public function migrate_safe_aborts_when_destructive_migration_is_on_disk(): void
    {
        $this->writeFixture(
            '2026_10_17_120005_test_hook_safe.php',
            "DB::table('users')->truncate();"
        );

        $thrown = null;
        try {
            $this->appProviderForTmpDir()->handleMigrateSafetyHook($this->makeEvent('migrate:safe'));
        } catch (RuntimeException $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'expected abort on migrate:safe');
        $this->assertStringContainsString('migrate:safe', $thrown->getMessage());
    }

    #[Test]
    public function non_migrate_commands_are_unaffected(): void
    {
        // The hook should be a no-op for non-migrate commands, regardless
        // of any fixture in the tmp dir. (No fixture needed.)
        $this->appProviderForTmpDir()->handleMigrateSafetyHook($this->makeEvent('schedule:run'));
        $this->appProviderForTmpDir()->handleMigrateSafetyHook($this->makeEvent('db:backup'));
        $this->appProviderForTmpDir()->handleMigrateSafetyHook($this->makeEvent('queue:work'));

        $this->assertTrue(true, 'non-migrate commands should be no-ops');
    }

    #[Test]
    public function blocked_attempt_is_logged_at_warning_level(): void
    {
        $this->writeFixture(
            '2026_10_17_120006_test_hook_log.php',
            "DB::table('users')->truncate();"
        );

        Log::spy();

        try {
            try {
                $this->appProviderForTmpDir()->handleMigrateSafetyHook($this->makeEvent('migrate'));
                $this->fail('expected RuntimeException');
            } catch (RuntimeException $e) {
                // expected
            }

            Log::shouldHaveReceived('warning')
                ->once()
                ->withArgs(function (string $message, array $context) {
                    return $message === '[db-safety] blocked migrate'
                        && ($context['command'] ?? null) === 'migrate'
                        && is_string($context['file'] ?? null)
                        && str_ends_with($context['file'], '2026_10_17_120006_test_hook_log.php')
                        && is_int($context['line'] ?? null);
                });
        } finally {
            // No DB cleanup needed — fixtures live in /tmp.
        }
    }

    #[Test]
    public function bypass_via_allow_quarantined_does_not_log(): void
    {
        $this->writeFixture(
            '2026_10_17_120007_test_hook_log_bypass.php',
            "DB::table('users')->truncate();"
        );

        Log::spy();

        $this->appProviderForTmpDir()->handleMigrateSafetyHook(
            $this->makeEvent('migrate', ['--allow-quarantined'])
        );

        Log::shouldNotHaveReceived('warning');
    }
}

/**
 * AppServiceProvider subclass that points the destructive-migrations
 * lint at a configurable path (used in tests to avoid touching the
 * project's real `database/migrations/`).
 */
class TmpAppServiceProvider extends AppServiceProvider
{
    public function __construct($app, private readonly string $migrationsPath)
    {
        parent::__construct($app);
    }

    protected function callLintCommand(?string $migrationsPath = null): int
    {
        return parent::callLintCommand($this->migrationsPath);
    }
}
