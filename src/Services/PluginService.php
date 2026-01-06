<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Services;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Runtime\InstalledPlugin;
use function Illuminate\Filesystem\join_paths;

// 1. Removed 'readonly' from class so we can mutate the cache property
final class PluginService
{
    // 2. Add a local cache array
    private array $cache = [];

    public function __construct(
        // 3. Moved 'readonly' to specific properties
        private readonly AtomicFilesystem $afs,
        private readonly PluginSettingsWriter $settingsWriter,
        private readonly PermissionServiceInterface $permissionService,
    ) {}

    public function getPlugin(int $pluginId): Plugin
    {
        // 4. Return immediately if we already have it
        if (isset($this->cache[$pluginId])) {
            return $this->cache[$pluginId];
        }

        $plugin = Plugin::query()->find($pluginId);

        if (!$plugin) {
            throw new RuntimeException("Plugin #{$pluginId} not found");
        }

        // 5. Store result in cache before returning
        return $this->cache[$pluginId] = $plugin;
    }

    public function installedRoot(int $pluginId): string
    {
        // This now hits the cache instead of the DB
        $plugin = $this->getPlugin($pluginId);

        $root = trim((string) ($plugin->plugin_path ?? ''));
        if ($root === '') {
            throw new RuntimeException("Plugin #{$pluginId} has no plugin_path");
        }

        return rtrim($root, "\\/");
    }

    /**
     * Load the plugin's runtime Config class file and return its FQCN.
     * @return class-string
     */
    public function loadConfigClass(int $pluginId): string
    {
        // Hits cache
        $plugin = $this->getPlugin($pluginId);

        $meta = $plugin->meta ?? [];
        if (!is_array($meta)) {
            $meta = (array) $meta;
        }

        $psr4Root = (string)($meta['psr4_root'] ?? config('fortiplugin.psr4_root', 'Plugins'));
        $psr4Root = rtrim(trim($psr4Root), "\\ \t\n\r\0\x0B");

        $nsSegment = (string)($meta['placeholder_name'] ?? $plugin->name);

        if ($nsSegment === '') {
            throw new RuntimeException(
                "Plugin #{$pluginId} has no namespace segment (meta.placeholder_name / name)"
            );
        }

        $fqcn = "{$psr4Root}\\{$nsSegment}\\Config";

        if (class_exists($fqcn)) {
            return $fqcn;
        }

        // This calls installedRoot -> getPlugin, which now also hits cache
        $root = $this->installedRoot($pluginId);
        $cfgPath = join_paths($root, '.internal', 'Config.php');

        if (is_file($cfgPath)) {
            require_once $cfgPath;
        }

        if (!class_exists($fqcn)) {
            throw new RuntimeException(
                "Plugin #{$pluginId} Config class not found: {$fqcn} (autoload + fallback include attempted)"
            );
        }

        return $fqcn;
    }

    public function load(int $pluginId): InstalledPlugin
    {
        // All these calls now share the single DB result fetched by the first one
        $plugin = $this->getPlugin($pluginId);
        $root = $this->installedRoot($pluginId);
        $configClass = $this->loadConfigClass($pluginId);

        return new InstalledPlugin(
            $plugin,
            $root,
            $configClass,
            $this->settingsWriter,
            $this->permissionService
        );
    }
}