<?php

namespace App\Database\Migrations\Concerns;

use App\Services\MigrationSnapshot;

trait WithDataSafetySnapshot
{
    /**
     * Capture a snapshot of the declared critical tables. Call this
     * at the very top of `up()` on destructive migrations.
     *
     * Subclasses MUST declare `protected array $criticalTables = [...]`
     * and may declare `protected array $criticalColumns = [...]`.
     */
    protected function snapshotBefore(?string $migrationName = null): void
    {
        $tables = $this->criticalTables ?? [];
        if (empty($tables)) {
            return;
        }

        $name = $migrationName ?: $this->migrationName();
        app(MigrationSnapshot::class)->capture($name, $tables, $this->criticalColumns ?? []);
    }

    /**
     * Restore from the snapshot captured by the matching `up()`.
     * Call this at the end of `down()` after the schema has been recreated.
     *
     * @param  array<int, string>|null  $tables  restore only these tables (default: all)
     */
    protected function restoreSnapshot(?string $migrationName = null, ?array $tables = null): void
    {
        $default = $this->criticalTables ?? [];
        if (empty($default)) {
            return;
        }

        $name = $migrationName ?: $this->migrationName();
        $targets = $tables ?? $default;
        app(MigrationSnapshot::class)->restore($name, $targets);
    }

    protected function migrationName(): string
    {
        try {
            $file = (new \ReflectionClass(static::class))->getFileName();
        } catch (\ReflectionException $e) {
            $file = false;
        }

        if (is_string($file) && $file !== '') {
            $base = basename($file, '.php');
            if ($base !== '' && strpbrk($base, "\0") === false) {
                return $base;
            }
        }

        $class = static::class;
        $pos = strrpos($class, '\\');
        $name = $pos === false ? $class : substr($class, $pos + 1);

        if (strpbrk($name, "\0") !== false) {
            return 'migration_'.spl_object_id($this);
        }

        return $name;
    }
}
