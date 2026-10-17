<?php

namespace Tests\Feature\Commands;

use App\Providers\AppServiceProvider;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class PreMigrateHookTest extends TestCase
{
    private string $tmpMigrationsDir;

    private array $createdFixtures = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpMigrationsDir = sys_get_temp_dir() . '/cloudstream-mighook-' . uniqid();
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

    /**
     * Write a fixture migration into the project's database/migrations dir
     * (we use the project dir directly because the lint is hardcoded to
     * read from there; the AppServiceProvider cannot be reconfigured at
     * runtime without making it env-driven, which is over-engineering for
     * a defensive check). The fixture is deleted in tearDown.
     */
    private function writeFixture(string $name, string $body): string
    {
        $path = database_path('migrations/' . $name);
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

    /**
     * Build a CommandStarting event with the given command + options. The
     * ArgvInput uses the real command name as argv[0] (Symfony convention)
     * and accepts --allow-quarantined / --pretend / etc. just like the CLI.
     */
    private function makeEvent(string $command, array $argv = []): CommandStarting
    {
        $input = new ArgvInput(array_merge([$command], $argv));
        $output = new BufferedOutput();

        return new CommandStarting($command, $input, $output);
    }

    private function appProvider(): AppServiceProvider
    {
        // ServiceProvider::__construct takes the container as a required
        // argument, but Laravel's auto-wiring only kicks in for classes
        // registered via $this->app->singleton(). For tests, the simplest
        // path is to manually instantiate the provider with the test
        // container and call its public method directly. We do NOT call
        // boot() because that would register event listeners on the
        // test container, leaking into other tests.
        return new AppServiceProvider($this->app);
    }

    #[Test]
    public function migrate_aborts_when_destructive_migration_is_on_disk(): void
    {
        $this->writeFixture(
            '2026_10_17_120001_test_hook_block.php',
            "DB::table('users')->truncate();"
        );

        try {
            $thrown = null;
            try {
                $this->appProvider()->handleMigrateSafetyHook($this->makeEvent('migrate'));
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
            $this->assertNotNull($thrown, 'expected RuntimeException when destructive migration is on disk');
            $this->assertStringContainsString('[db-safety] `php artisan migrate` aborted', $thrown->getMessage());
            $this->assertStringContainsString('--allow-quarantined', $thrown->getMessage());
        } finally {
            @unlink($this->createdFixtures[count($this->createdFixtures) - 1] ?? '');
        }
    }

    #[Test]
    public function migrate_with_allow_quarantined_proceeds(): void
    {
        $this->writeFixture(
            '2026_10_17_120002_test_hook_bypass.php',
            "DB::table('users')->truncate();"
        );

        try {
            // Should NOT throw
            $this->appProvider()->handleMigrateSafetyHook(
                $this->makeEvent('migrate', ['--allow-quarantined'])
            );
            $this->assertTrue(true, 'migrate --allow-quarantined should not throw');
        } finally {
            @unlink($this->createdFixtures[count($this->createdFixtures) - 1] ?? '');
        }
    }

    #[Test]
    public function migrate_fresh_aborts_when_destructive_migration_is_on_disk(): void
    {
        $this->writeFixture(
            '2026_10_17_120003_test_hook_fresh.php',
            "DB::table('users')->truncate();"
        );

        try {
            $thrown = null;
            try {
                $this->appProvider()->handleMigrateSafetyHook($this->makeEvent('migrate:fresh'));
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
            $this->assertNotNull($thrown, 'expected abort on migrate:fresh');
            $this->assertStringContainsString('migrate:fresh', $thrown->getMessage());
        } finally {
            @unlink($this->createdFixtures[count($this->createdFixtures) - 1] ?? '');
        }
    }

    #[Test]
    public function migrate_rollback_aborts_when_destructive_migration_is_on_disk(): void
    {
        $this->writeFixture(
            '2026_10_17_120004_test_hook_rollback.php',
            "DB::table('users')->truncate();"
        );

        try {
            $thrown = null;
            try {
                $this->appProvider()->handleMigrateSafetyHook($this->makeEvent('migrate:rollback'));
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
            $this->assertNotNull($thrown, 'expected abort on migrate:rollback');
            $this->assertStringContainsString('migrate:rollback', $thrown->getMessage());
        } finally {
            @unlink($this->createdFixtures[count($this->createdFixtures) - 1] ?? '');
        }
    }

    #[Test]
    public function migrate_safe_aborts_when_destructive_migration_is_on_disk(): void
    {
        $this->writeFixture(
            '2026_10_17_120005_test_hook_safe.php',
            "DB::table('users')->truncate();"
        );

        try {
            $thrown = null;
            try {
                $this->appProvider()->handleMigrateSafetyHook($this->makeEvent('migrate:safe'));
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
            $this->assertNotNull($thrown, 'expected abort on migrate:safe');
            $this->assertStringContainsString('migrate:safe', $thrown->getMessage());
        } finally {
            @unlink($this->createdFixtures[count($this->createdFixtures) - 1] ?? '');
        }
    }

    #[Test]
    public function non_migrate_commands_are_unaffected(): void
    {
        // No fixture needed: even if there were a destructive migration,
        // schedule:run must not be guarded. The hook should be a no-op.
        $this->appProvider()->handleMigrateSafetyHook($this->makeEvent('schedule:run'));
        $this->appProvider()->handleMigrateSafetyHook($this->makeEvent('db:backup'));
        $this->appProvider()->handleMigrateSafetyHook($this->makeEvent('queue:work'));

        $this->assertTrue(true, 'non-migrate commands should be no-ops');
    }

    #[Test]
    public function blocked_attempt_is_logged_at_warning_level(): void
    {
        $fixture = $this->writeFixture(
            '2026_10_17_120006_test_hook_log.php',
            "DB::table('users')->truncate();"
        );

        // Use Log::spy() to capture without polluting test output. The
        // hook emits Log::warning('[db-safety] blocked migrate', [...]).
        Log::spy();

        try {
            try {
                $this->appProvider()->handleMigrateSafetyHook($this->makeEvent('migrate'));
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
            @unlink($fixture);
        }
    }

    #[Test]
    public function bypass_via_allow_quarantined_does_not_log(): void
    {
        $fixture = $this->writeFixture(
            '2026_10_17_120007_test_hook_log_bypass.php',
            "DB::table('users')->truncate();"
        );

        Log::spy();

        try {
            $this->appProvider()->handleMigrateSafetyHook(
                $this->makeEvent('migrate', ['--allow-quarantined'])
            );

            Log::shouldNotHaveReceived('warning');
        } finally {
            @unlink($fixture);
        }
    }
}
