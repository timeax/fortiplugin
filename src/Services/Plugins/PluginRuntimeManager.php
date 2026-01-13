<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Services\Plugins;

use Timeax\FortiPlugin\Installations\Lifecycle\Uninstallation\Uninstaller;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Runtime\InstalledPlugin;
use Timeax\FortiPlugin\Services\PluginSettingsWriter;

final readonly class PluginRuntimeManager
{
    public function __construct(
        private PluginInstallLocator $locator,
        private PluginConfigResolver $configResolver,
        private PluginSettingsWriter $settingsWriter,
        private PermissionServiceInterface $permissionService,
        private Uninstaller $uninstaller
    ) {}

    public function installedRoot(Plugin $plugin): string
    {
        return $this->locator->installedRoot($plugin);
    }

    /**
     * @return class-string
     */
    public function configClass(Plugin $plugin, string $installedRoot): string
    {
        return $this->configResolver->resolveConfigClass($plugin, $installedRoot);
    }

    public function load(Plugin $plugin): InstalledPlugin
    {
        $root = $this->installedRoot($plugin);
        $configClass = $this->configClass($plugin, $root);

        return new InstalledPlugin(
            $plugin,
            $root,
            $configClass,
            $this->settingsWriter,
            $this->permissionService,
            $this->uninstaller
        );
    }
}