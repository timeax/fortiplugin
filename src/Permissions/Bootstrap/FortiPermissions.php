<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Bootstrap;

use Illuminate\Contracts\Container\Container;
use Timeax\FortiPlugin\Permissions\Contracts\{
    PermissionServiceInterface,
    CapabilityCacheInterface,
    AuditEmitterInterface,
    CatalogProviderInterface,
    ConditionsEvaluatorInterface,
    PermissionRepositoryInterface
};
use Timeax\FortiPlugin\Permissions\Audit\AuditEmitter;
use Timeax\FortiPlugin\Permissions\Cache\CapabilityCache;
use Timeax\FortiPlugin\Permissions\Catalog\HostCatalogProvider;
use Timeax\FortiPlugin\Permissions\Evaluation\PermissionService;
use Timeax\FortiPlugin\Permissions\Policy\ConditionsEvaluator;
use Timeax\FortiPlugin\Permissions\Registry\PermissionRegistry;
use Timeax\FortiPlugin\Permissions\Repositories\EloquentPermissionRepository;

final class FortiPermissions
{
    /**
     * Wire up registry + default bindings. Call from your FortiPluginServiceProvider:
     *
     *   \Timeax\FortiPlugin\Permissions\Bootstrap\FortiPermissions::register($this->app);
     */
    public static function register(Container $app): void
    {
        // Registry singleton (includes built-in defaults via its constructor)
        $app->singleton(PermissionRegistry::class, function (Container $app) {
            $registry  = new PermissionRegistry($app);

            // Optional overrides from config:
            // fortiplugin.permissions.checkers.{type}  => FQCN
            // fortiplugin.permissions.ingestors.{type} => FQCN
            $checkers  = (array) config('fortiplugin.permissions.checkers', []);
            $ingestors = (array) config('fortiplugin.permissions.ingestors', []);

            foreach ($checkers as $type => $fqcn) {
                $registry->registerChecker((string) $type, (string) $fqcn);
            }
            foreach ($ingestors as $type => $fqcn) {
                $registry->registerIngestor((string) $type, (string) $fqcn);
            }

            return $registry;
        });

        // Core contract bindings (swap these FQCNs with your concrete implementations as you add them)
        $app->bind(PermissionServiceInterface::class,    PermissionService::class);
        $app->bind(CapabilityCacheInterface::class,      CapabilityCache::class);
        $app->bind(AuditEmitterInterface::class,         AuditEmitter::class);
        $app->bind(CatalogProviderInterface::class,      HostCatalogProvider::class);
        $app->bind(ConditionsEvaluatorInterface::class,  ConditionsEvaluator::class);
        $app->bind(PermissionRepositoryInterface::class, EloquentPermissionRepository::class);
    }
}