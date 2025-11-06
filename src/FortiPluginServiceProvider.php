<?php /** @noinspection PhpUnusedParameterInspection */

namespace Timeax\FortiPlugin;

use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\Filesystem as LaravelFs;
use Timeax\FortiPlugin\Console\Commands\ChangeHostCommand;
use Timeax\FortiPlugin\Console\Commands\CreateAuthorCommand;
use Timeax\FortiPlugin\Console\Commands\GenerateHostKeyCommand;
use Timeax\FortiPlugin\Console\Commands\ListHostsCommand;
use Timeax\FortiPlugin\Console\Commands\LoginCommand;
use Timeax\FortiPlugin\Console\Commands\LogoutCommand;
use Timeax\FortiPlugin\Console\Commands\MakePlugin;
use Timeax\FortiPlugin\Console\Commands\PackPlugin;
use Timeax\FortiPlugin\Console\Commands\ValidatePlugin;
use Timeax\FortiPlugin\Core\Install\JsonRouteCompiler;
use Timeax\FortiPlugin\Core\Install\RouteWriter;

use Timeax\FortiPlugin\Installations\Activation\Activator;
use Timeax\FortiPlugin\Installations\Activation\Writers\ProvidersRegistryWriter;
use Timeax\FortiPlugin\Installations\Activation\Writers\RoutesRegistryWriter;
use Timeax\FortiPlugin\Installations\Activation\Writers\UiRegistryWriter;
use Timeax\FortiPlugin\Installations\Contracts\ActorResolver;
use Timeax\FortiPlugin\Installations\Infra\EloquentPluginRepository;
use Timeax\FortiPlugin\Installations\Infra\EloquentZipRepository;
use Timeax\FortiPlugin\Installations\Infra\InMemoryFilesystem;
use Timeax\FortiPlugin\Installations\Infra\InMemoryPluginRepository;
use Timeax\FortiPlugin\Installations\Infra\InMemoryZipRepository;
use Timeax\FortiPlugin\Installations\Infra\LocalFilesystem;
use Timeax\FortiPlugin\Installations\Sections\ZipValidationGate;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\RouteMaterializer;
use Timeax\FortiPlugin\Installations\Support\RouteRegistryStore;
use Timeax\FortiPlugin\Permissions\Bootstrap\FortiPermissions;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\PermissionService;
use Timeax\FortiPlugin\Services\HostKeyService;

// crypto service

use Timeax\FortiPlugin\Installations\Installer;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\Psr4Checker;
use Timeax\FortiPlugin\Installations\Support\RouteUiBridge;
use Timeax\FortiPlugin\Installations\Support\ValidatorBridge;
use Timeax\FortiPlugin\Installations\Support\BackgroundScanDispatcher;

use Timeax\FortiPlugin\Installations\Contracts\Filesystem as FsContract;
use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Installations\Contracts\PluginRepository;
use Timeax\FortiPlugin\Installations\Contracts\HostKeyService as TokenContract;


use Timeax\FortiPlugin\Installations\Sections\VerificationSection;
use Timeax\FortiPlugin\Installations\Sections\FileScanSection;
use Timeax\FortiPlugin\Installations\Sections\ProviderValidationSection;
use Timeax\FortiPlugin\Installations\Sections\ComposerPlanSection;
use Timeax\FortiPlugin\Installations\Sections\VendorPolicySection;
use Timeax\FortiPlugin\Installations\Sections\RouteWriteSection;
use Timeax\FortiPlugin\Installations\Sections\DbPersistSection;
use Timeax\FortiPlugin\Installations\Sections\InstallFilesSection;
use Timeax\FortiPlugin\Installations\Sections\UiConfigValidationSection;

use Timeax\FortiPlugin\Installations\Support\ComposerInspector;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;

// Optional host-provided actor resolver abstraction
use Timeax\FortiPlugin\Installations\Support\DefaultActorResolver;
use Timeax\FortiPlugin\Services\PolicyService;
use Timeax\FortiPlugin\Services\ValidatorService;
use Timeax\FortiPlugin\Support\FortiGateRegistrar;

class FortiPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fortiplugin.php', 'fortiplugin');
        FortiPermissions::register($this->app);
        $this->registerSecurityServices();
        $this->registerInstallationModules();
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->registerRoutes();
        FortiGateRegistrar::register();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ChangeHostCommand::class,
                ListHostsCommand::class,
                LoginCommand::class,
                LogoutCommand::class,
                MakePlugin::class,
                PackPlugin::class,
                ValidatePlugin::class,
                CreateAuthorCommand::class,
                GenerateHostKeyCommand::class,
            ]);
        }
    }

    private function registerRoutes(): void
    {
        if (!$this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/fortiplugin.php' => config_path('fortiplugin.php'),
            __DIR__ . '/../config/fortipluginui.php' => config_path('fortipluginui.php'),
            __DIR__ . '/../config/fortiplugin-maps.php' => config_path('fortiplugin-maps.php'),
        ], 'fortiplugin-config');
    }

    private function publishMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }


    private function registerSecurityServices(): void
    {
        $this->app->singleton(PermissionServiceInterface::class, function ($app) {
            return $app->make(PermissionService::class);
        });

        $this->app->singleton(ValidatorService::class, function ($app) {
            $policySvc = $app->make(PolicyService::class);
            $policy = $policySvc->makePolicy();
            $config = (array)config('fortiplugin.validator', []);
            return new ValidatorService($policy, $config);
        });
    }

    private function registerInstallationModules(): void
    {
        // ── Policy ─────────────────────────────────────────────────────────────
        $this->app->singleton(
            InstallerPolicy::class,
            fn() => InstallerPolicy::fromArray((array)config('fortiplugin.installations.policy', []))
        );

        // ── Repositories (switchable: eloquent|inmemory) ───────────────────────
        $this->app->singleton(ZipRepository::class, function ($app) {
            $driver = (string)(config('fortiplugin.installations.repositories.zip') ?? 'inmemory');
            return $driver === 'eloquent'
                ? $app->make(EloquentZipRepository::class)
                : new InMemoryZipRepository();
        });

        $this->app->singleton(PluginRepository::class, function ($app) {
            $driver = (string)(config('fortiplugin.installations.repositories.plugin') ?? 'inmemory');
            return $driver === 'eloquent'
                ? $app->make(EloquentPluginRepository::class)
                : new InMemoryPluginRepository();
        });

        // ── Crypto-backed token manager (contracts → implementation) ───────────
        $this->app->singleton(TokenContract::class, fn($app) => new InstallerTokenManager($app->make(HostKeyService::class))
        );

        // ── Actor resolver (host overridable) ──────────────────────────────────
        $this->app->singleton(ActorResolver::class, fn($app) => $app->make(DefaultActorResolver::class)
        );

        // ── Filesystem (switchable: filesystem|inmemory) + Atomic FS wrapper ───
        $this->app->singleton(FsContract::class, function ($app) {
            $driver = (string)(config('fortiplugin.installations.files') ?? 'inmemory');
            return $driver === 'filesystem'
                ? $app->make(LocalFilesystem::class)
                : new InMemoryFilesystem();
        });

        $this->app->singleton(AtomicFilesystem::class, fn($app) => new AtomicFilesystem($app->make(FsContract::class))
        );

        // ── Helpers / Tools ────────────────────────────────────────────────────
        $this->app->singleton(Psr4Checker::class, fn($app) => new Psr4Checker($app->make(AtomicFilesystem::class))
        );

        $this->app->singleton(ComposerInspector::class, fn($app) => new ComposerInspector($app->make(AtomicFilesystem::class))
        );

        $this->app->singleton(JsonRouteCompiler::class, fn() => new JsonRouteCompiler());

        $this->app->singleton(RouteWriter::class, fn($app) => new RouteWriter($app->make(LaravelFs::class)) // output root is decided by RouteWriteSection
        );

        // Background scan dispatcher; hosts can replace via config binding
        $this->app->singleton(BackgroundScanDispatcher::class, function ($app) {
            // If you have multiple implementations, pick via config here.
            // Default: resolve the class directly from the container.
            return $app->make(BackgroundScanDispatcher::class);
        });

        // ── Sections ───────────────────────────────────────────────────────────
        $this->app->singleton(VerificationSection::class, fn($app) => new VerificationSection(
            $app->make(InstallerPolicy::class),
            $app->make(AtomicFilesystem::class),
            $app->make(Psr4Checker::class),
        ));

        $this->app->singleton(FileScanSection::class, fn($app) => new FileScanSection(
            $app->make(InstallerPolicy::class),
            $app->make(AtomicFilesystem::class),
            $app->make(TokenContract::class),
            null // default installer-level emitter (can be passed at runtime)
        ));

        $this->app->singleton(ProviderValidationSection::class, fn($app) => new ProviderValidationSection(
            $app->make(InstallerPolicy::class),
            $app->make(AtomicFilesystem::class),
            $app->make(Psr4Checker::class),
        ));

        $this->app->singleton(ComposerPlanSection::class, fn($app) => new ComposerPlanSection(
            $app->make(ComposerInspector::class),
            $app->make(AtomicFilesystem::class),
        ));

        $this->app->singleton(VendorPolicySection::class, fn($app) => new VendorPolicySection(
            $app->make(InstallerPolicy::class),
            $app->make(AtomicFilesystem::class),
            $app->make(ComposerInspector::class),
            $app->make(InstallationLogStore::class),
        ));

        $this->app->singleton(DbPersistSection::class, fn($app) => new DbPersistSection(
            $app->make(PluginRepository::class),
            $app->make(AtomicFilesystem::class),
        ));

        $this->app->singleton(InstallFilesSection::class, fn($app) => new InstallFilesSection(
            $app->make(InstallerPolicy::class),
            $app->make(InstallationLogStore::class),
            $app->make(AtomicFilesystem::class),
        ));

        $this->app->singleton(UiConfigValidationSection::class, fn($app) => new UiConfigValidationSection(
            $app->make(InstallationLogStore::class),
            $app->make(AtomicFilesystem::class),
        ));

        $this->app->singleton(RouteWriteSection::class, fn($app) => new RouteWriteSection(
            $app->make(InstallationLogStore::class),
            $app->make(AtomicFilesystem::class),
            $app->make(RouteRegistryStore::class),
            $app->make(RouteMaterializer::class),
        ));
        // ── Route registry + materializer ──────────────────────────────────────────────
        $this->app->singleton(RouteRegistryStore::class, fn($app) => new RouteRegistryStore(
            $app->make(AtomicFilesystem::class),
        ));

        $this->app->singleton(RouteMaterializer::class, fn($app) => new RouteMaterializer(
            $app->make(AtomicFilesystem::class),
        ));

        // ── Bridges ───────────────────────────────────────────────────────────────────
        $this->app->singleton(RouteUiBridge::class, fn($app) => new RouteUiBridge(
            $app->make(AtomicFilesystem::class),
            $app->make(JsonRouteCompiler::class),
            $app->make(RouteRegistryStore::class),
        ));

        $this->app->singleton(ValidatorBridge::class, fn($app) => new ValidatorBridge(
            $app->make(VerificationSection::class),
            $app->make(FileScanSection::class),
            $app->make(InstallerPolicy::class),
        ));

        // ── Installer (top-level orchestrator) ─────────────────────────────────
        $this->app->singleton(Installer::class, fn($app) => new Installer(
            policy: $app->make(InstallerPolicy::class),
            afs: $app->make(AtomicFilesystem::class),
            validatorBridge: $app->make(ValidatorBridge::class),

            // kept for DI completeness (ValidatorBridge uses some internally)
            verification: $app->make(VerificationSection::class),
            providerValidation: $app->make(ProviderValidationSection::class),

            composerPlan: $app->make(ComposerPlanSection::class),
            vendorPolicy: $app->make(VendorPolicySection::class),
            dbPersist: $app->make(DbPersistSection::class),
            routeUiBridge: $app->make(RouteUiBridge::class),
            routeWriterSection: $app->make(RouteWriteSection::class),
            installFiles: $app->make(InstallFilesSection::class),
            uiConfigValidation: $app->make(UiConfigValidationSection::class),
            tokens: $app->make(InstallerTokenManager::class),
            logStore: $app->make(InstallationLogStore::class),
            zipGate: $app->make(ZipValidationGate::class))
        );


        $this->app->singleton(RoutesRegistryWriter::class);
        $this->app->singleton(ProvidersRegistryWriter::class);
        $this->app->singleton(UiRegistryWriter::class);
        $this->app->singleton(Activator::class);
    }
}
