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
use Timeax\FortiPlugin\Permissions\Registry\PermissionRegistry;

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
        $app->bind(PermissionServiceInterface::class,    \Timeax\FortiPlugin\Permissions\Evaluation\PermissionService::class);
        $app->bind(CapabilityCacheInterface::class,      \Timeax\FortiPlugin\Permissions\Cache\CapabilityCache::class);
        $app->bind(AuditEmitterInterface::class,         \Timeax\FortiPlugin\Permissions\Audit\AuditEmitter::class);
        $app->bind(CatalogProviderInterface::class,      \Timeax\FortiPlugin\Permissions\Catalog\HostCatalogProvider::class);
        $app->bind(ConditionsEvaluatorInterface::class,  \Timeax\FortiPlugin\Permissions\Policy\ConditionsEvaluator::class);
        $app->bind(PermissionRepositoryInterface::class, \Timeax\FortiPlugin\Permissions\Repositories\EloquentPermissionRepository::class);
    }
}