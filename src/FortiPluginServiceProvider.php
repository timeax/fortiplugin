<?php /** @noinspection PhpUnusedParameterInspection */

namespace Timeax\FortiPlugin;

use Illuminate\Support\ServiceProvider;
use Timeax\FortiPlugin\Console\Commands\ValidatePlugin;
use Timeax\FortiPlugin\Installations\Contracts\ActorResolver;
use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Installations\Contracts\PluginRepository;
use Timeax\FortiPlugin\Installations\Infra\EloquentZipRepository;
use Timeax\FortiPlugin\Installations\Infra\InMemoryZipRepository;
use Timeax\FortiPlugin\Installations\Infra\InMemoryPluginRepository;
use Timeax\FortiPlugin\Installations\Support\DefaultActorResolver;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Permissions\Bootstrap\FortiPermissions;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\PermissionService;
use Timeax\FortiPlugin\Services\PolicyService;
use Timeax\FortiPlugin\Services\ValidatorService;
use Timeax\FortiPlugin\Support\FortiGates;
use Timeax\FortiPlugin\Support\FortiGateRegistrar;
use Timeax\FortiPlugin\Support\PublishConfig;

class FortiPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fortiplugin.php', 'fortiplugin');
        FortiPermissions::register($this->app);

        // Bind ValidatorService for facade access
        $this->app->singleton(ValidatorService::class, function ($app) {
            $policySvc = $app->make(PolicyService::class);
            $policy = $policySvc->makePolicy();
            $config = (array)config('fortiplugin.validator', []);
            return new ValidatorService($policy, $config);
        });

        // Default bindings for Installations module (overridable by host app)
        $this->app->singleton(ZipRepository::class, function ($app) {
            $driver = (string)(config('fortiplugin.installations.repositories.zip') ?? 'inmemory');
            if ($driver === 'eloquent') {
                return $app->make(EloquentZipRepository::class);
            }
            return new InMemoryZipRepository();
        });
        // Bind PluginRepository for Installations Phase 7
        $this->app->singleton(PluginRepository::class, function ($app) {
            $driver = (string)(config('fortiplugin.installations.repositories.plugin') ?? 'inmemory');
            if ($driver === 'eloquent') {
                return $app->make(\Timeax\FortiPlugin\Installations\Infra\EloquentPluginRepository::class);
            }
            return new InMemoryPluginRepository();
        });
        $this->app->singleton(InstallerTokenManager::class, function ($app) {
            return new InstallerTokenManager();
        });
        $this->app->singleton(ActorResolver::class, function ($app) {
            return new DefaultActorResolver();
        });
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        FortiGateRegistrar::register();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ValidatePlugin::class,
            ]);
        }
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
