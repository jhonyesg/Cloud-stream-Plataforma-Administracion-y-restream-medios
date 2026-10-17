# Design: integration test + log on block

## Test strategy

The pre-migrate hook lives in `AppServiceProvider::registerMigrateSafetyHook()` and runs `Artisan::call('check:destructive-migrations', ['--strict-data' => true])` for every `migrate*` command. To test it end-to-end we need:

1. A controlled set of fixture migration files in a tmp directory.
2. A way to point the lint at that directory, instead of the project's `database/migrations/`.
3. The hook in `AppServiceProvider` must use that same directory.

The lint already accepts `--path=<absolute>` (added in `quarantine-destructive-migrations` to support the test suite). For the hook to use the same path, the test will **rebind** `AppServiceProvider` in the container or use a stub. After analysis, the cleanest approach is:

- **Option A**: rebind `CheckDestructiveMigrationsCommand` to a subclass that uses the test's tmp path. Complex.
- **Option B**: directly call the `CommandStarting` listener closure in a test, with a mocked `CommandStarting` event. Bypasses Laravel's `migrate*` plumbing but exercises the exact closure that the hook registers.
- **Option C**: invoke the lint directly in the test (skipping the listener), assert the lint output, AND separately assert that the hook's `RuntimeException` is thrown by calling the closure.

**Choose B**: it tests the actual closure registered in production, not a wrapper. The closure is a `callable` we can extract by registering a second listener that captures it via reflection, OR by extracting the closure into a named method on `AppServiceProvider` and unit-testing that method.

**Final approach**: extract the per-command hook logic into a public/protected method `handleMigrateSafetyHook(Rest... $event)` on `AppServiceProvider`. The `CommandStarting` listener becomes a 2-line wrapper that calls it. Tests instantiate `AppServiceProvider` and call the method directly with synthetic events. This is the cleanest separation and keeps the production code identical to the listener closure.

## Logging

```php
Log::warning('[db-safety] blocked migrate', [
    'command' => $command,
    'file' => $violation['file'] ?? null,
    'line' => $violation['line'] ?? null,
]);
```

The `Log::warning` call runs **before** the `throw new RuntimeException(...)`. The exception message stays the same so end-user UX is identical. The log provides post-mortem signal for operators grepping `Log::warning [db-safety]`.

We use `Log::warning` (not `Log::error`) because:
- It's a **defensive** block, not a system failure.
- `Log::error` would trigger Sentry/alert routing in many setups, creating noise for what is the expected behaviour.

## Test plan

```php
class PreMigrateHookTest extends TestCase
{
    private string $tmpMigrationsDir;
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpMigrationsDir = sys_get_temp_dir() . '/cloudstream-mighook-' . uniqid();
        mkdir($this->tmpMigrationsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $f) { @unlink($f); }
        @rmdir($this->tmpMigrationsDir);
        parent::tearDown();
    }

    private function writeMigration(string $name, string $body, ?string $withTrait = null): string
    {
        $traitUse = $withTrait
            ? "use {$withTrait};\n    protected array \$criticalTables = ['users'];\n    "
            : '';
        $path = $this->tmpMigrationsDir . '/' . $name;
        file_put_contents($path, "<?php\n\nuse Illuminate\Database\Migrations\Migration;\n{$traitUse}use Illuminate\\Support\\Facades\\DB;\n\nreturn new class extends Migration\n{\n    public function up(): void { {$body} }\n    public function down(): void {}\n};\n");
        $this->createdFiles[] = $path;
        return $path;
    }

    private function makeEvent(string $command, array $options = []): CommandStarting
    {
        $argv = ['artisan', $command, ...$this->argvFromOptions($options)];
        $input = new ArgvInput(array_merge([$command], $argv));
        $output = new BufferedOutput();
        return new CommandStarting($command, $input, $output);
    }

    public function test_migrate_aborts_when_destructive_migration_present(): void { ... }
    public function test_migrate_with_allow_quarantined_proceeds(): void { ... }
    public function test_migrate_fresh_aborts_when_destructive_migration_present(): void { ... }
    public function test_migrate_rollback_aborts_when_destructive_migration_present(): void { ... }
    public function test_migrate_safe_aborts_when_destructive_migration_present(): void { ... }
    public function test_non_migrate_command_is_unaffected(): void { ... }
    public function test_blocked_attempt_is_logged_at_warning_level(): void { ... }
}
```

7 tests total. Each uses a fixture migration in a tmp dir to drive the lint through the real `AppServiceProvider::handleMigrateSafetyHook` method.

The `setUp` uses `Log::shouldReceive('warning')` via Mockery to capture the warning without polluting test output.
