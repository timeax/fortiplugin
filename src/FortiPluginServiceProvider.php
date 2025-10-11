<?php /** @noinspection PhpUnusedParameterInspection */

namespace Timeax\FortiPlugin;

use Illuminate\Support\ServiceProvider;
use Timeax\FortiPlugin\Permissions\Bootstrap\FortiPermissions;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\PermissionService;
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

    private function registerFacades()
    {

        $this->app->singleton(PermissionServiceInterface::class, function ($app) {
            // Let Laravel autowire the PermissionService dependencies (repo, cache, registry, etc.)
            return $app->make(PermissionService::class);
        });

        // If you have your PermissionRegistry::register($app) static bootstrapping, call it here too.
        // \Timeax\FortiPlugin\Permissions\Registry\PermissionRegistryBootstrap::register($this->app);
    }
}
