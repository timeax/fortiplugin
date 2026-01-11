<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Throwable;
use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;

trait PermissionServiceActivationTrait
{
    abstract protected function repo(): PermissionRepositoryInterface;
    abstract protected function cache(): CapabilityCacheInterface;

    abstract public function invalidateCache(int $pluginId): void;
    abstract public function warmCache(int $pluginId): void;

    /**
     * Activates ONLY the plugin assignment row (PluginPermission.active = true).
     * No upsert, no constraints/audit/justification changes.
     *
     * Returns true only if it changed state.
     *
     * @throws Throwable
     */
    public function activatePermission(int $pluginId, PermissionType|string $type, int $permissionId): bool
    {
        $enum = is_string($type) ? PermissionType::from($type) : $type;

        $changed = $this->repo()->activatePluginPermission($pluginId, $enum, $permissionId);

        if ($changed) {
            $this->invalidateCache($pluginId);
            $this->warmCache($pluginId);
        }

        return $changed;
    }

    /**
     * Deactivates ONLY the plugin assignment row (PluginPermission.active = false).
     * Not a denial decision, just inactive.
     *
     * Returns true only if it changed state.
     *
     * @throws Throwable
     */
    public function deactivatePermission(int $pluginId, PermissionType|string $type, int $permissionId): bool
    {
        $enum = is_string($type) ? PermissionType::from($type) : $type;

        $changed = $this->repo()->deactivatePluginPermission($pluginId, $enum, $permissionId);

        if ($changed) {
            $this->invalidateCache($pluginId);
            $this->warmCache($pluginId);
        }

        return $changed;
    }
}