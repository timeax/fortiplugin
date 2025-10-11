<?php

declare(strict_types=1);

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Tests\Concerns\BootsFortiTestbench;
use Timeax\FortiPlugin\FortiPluginServiceProvider;

abstract class PackageTestCase extends Orchestra
{
    use BootsFortiTestbench;

    protected function getPackageProviders($app): array
    {
        return [FortiPluginServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Respect testbench.yaml, but ensure file exists for sqlite
        $this->ensureSqliteFile();
        $this->seedTestCatalogs();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadFortiMigrations();
        $this->artisan('migrate', ['--force' => true]);
    }
}