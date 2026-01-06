<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Services;

use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Runtime\InstalledPlugin;
use Timeax\FortiPlugin\Services\Plugins\PluginCatalog;
use Timeax\FortiPlugin\Services\Plugins\PluginConfigResolver;
use Timeax\FortiPlugin\Services\Plugins\PluginInstallLocator;
use Timeax\FortiPlugin\Services\Plugins\PluginRuntimeManager;

final class PluginService
{
    private PluginCatalog $catalog;
    private PluginRuntimeManager $runtime;

    public function __construct(
        private readonly AtomicFilesystem           $afs,
        private readonly PluginSettingsWriter       $settingsWriter,
        private readonly PermissionServiceInterface $permissionService,
    )
    {
        $this->catalog = new PluginCatalog();

        $locator = new PluginInstallLocator();
        $resolver = new PluginConfigResolver();

        $this->runtime = new PluginRuntimeManager(
            $locator,
            $resolver,
            $this->settingsWriter,
            $this->permissionService
        );
    }

    /**
     * @return PluginCatalog
     */
    public function getCatalog(): PluginCatalog
    {
        return $this->catalog;
    }

    public function getPlugin(int|string $idOrAlias): Plugin
    {
        return $this->catalog->get($idOrAlias);
    }

    /**
     * Facade advertises this; keep it here for compatibility.
     *
     * @return array<int, Plugin>
     */
    public function list(): array
    {
        return $this->catalog->list();
    }

    public function installedRoot(int $pluginId): string
    {
        return $this->runtime->installedRoot($this->getPlugin($pluginId));
    }

    /**
     * Load the plugin's runtime Config class file and return its FQCN.
     *
     * @return class-string
     */
    public function loadConfigClass(int $pluginId): string
    {
        $plugin = $this->getPlugin($pluginId);
        $root = $this->runtime->installedRoot($plugin);

        return $this->runtime->configClass($plugin, $root);
    }

    public function load(int|string $idOrAlias): InstalledPlugin
    {
        return $this->runtime->load($this->getPlugin($idOrAlias));
    }
}