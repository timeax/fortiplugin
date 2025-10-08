<?php /** @noinspection PhpUnusedParameterInspection */

namespace Timeax\FortiPlugin;

use Illuminate\Support\ServiceProvider;
use Timeax\FortiPlugin\Permissions\Bootstrap\FortiPermissions;
use Timeax\FortiPlugin\Support\FortiGates;
use Timeax\FortiPlugin\Support\FortiGateRegistrar;
use Timeax\FortiPlugin\Support\PublishConfig;

class FortiPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fortiplugin.php', 'fortiplugin');
        FortiPermissions::register($this->app);
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        FortiGateRegistrar::register();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/fortiplugin.php' => config_path('fortiplugin.php'),
        ], 'fortiplugin-config');
    }

    private function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'fortiplugin-migrations');
    }

}
