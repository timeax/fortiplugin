<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;

trait BootsFortiTestbench
{
    protected function ensureSqliteFile(): void
    {
        $db = config('database.connections.sqlite.database');
        if ($db && $db !== ':memory:') {
            File::ensureDirectoryExists(dirname($db));
            if (!File::exists($db)) {
                File::put($db, '');
            }
        }
    }

    protected function loadFortiMigrations(): void
    {
        // Adjust if your migrations folder lives elsewhere
        $this->loadMigrationsFrom(base_path('database/migrations'));
    }

    protected function seedTestCatalogs(): void
    {
        // Minimal host catalogs for validator/normalizer & checkers
        config()->set('fortiplugin-maps.modules', [
            'analytics' => ['map' => 'Acme\\Analytics', 'docs' => null],
            'billing'   => ['map' => 'Acme\\Billing',   'docs' => null],
        ]);

        config()->set('fortiplugin-maps.notifications-channels', ['email', 'sms', 'push']);

        // If you reference models by alias in manifests
        config()->set('fortiplugin-maps.models', [
            'user' => [
                'map' => 'App\\Models\\User',
                'columns' => [
                    'all' => ['id','name','email'],
                    'writable' => ['name','email'],
                ],
            ],
        ]);
    }
}