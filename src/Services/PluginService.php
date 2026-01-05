<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Services;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;
use function Illuminate\Filesystem\join_paths;

final readonly class PluginService
{
    public function __construct(private AtomicFilesystem $afs) {}

    /**
     * Fetch the Plugin row by id.
     */
    public function getPlugin(int $pluginId): Plugin
    {
        $plugin = Plugin::query()->find($pluginId);

        if (!$plugin) {
            throw new RuntimeException("Plugin #{$pluginId} not found");
        }

        return $plugin;
    }

    /**
     * Get an installed root path for a plugin (DB truth only).
     *
     * Contract:
     * - plugin.plugin_path is the installed plugin root directory.
     *
     * No filesystem validation here.
     */
    public function installedRoot(int $pluginId): string
    {
        $plugin = $this->getPlugin($pluginId);

        $root = trim((string)($plugin->plugin_path ?? ''));
        if ($root === '') {
            throw new RuntimeException("Plugin #{$pluginId} has no plugin_path");
        }

        return rtrim($root, "\\/");
    }

    /**
     * Load the generated runtime config object from:
     *   <installedRoot>/.internal/Config.php
     *
     * Note: The system generates this file during installation.
     *
     * This method DOES filesystem validation because it must.
     */
    public function loadConfig(int $pluginId): object
    {
        $fs = $this->afs->fs();

        $root = $this->installedRoot($pluginId);
        $cfgPath = join_paths($root, '.internal', 'Config.php');

        if (!$fs->exists($cfgPath) || !$fs->isFile($cfgPath)) {
            throw new RuntimeException("Plugin #{$pluginId} Config.php not found: {$cfgPath}");
        }

        $config = require $cfgPath;

        if (!is_object($config)) {
            throw new RuntimeException("Plugin #{$pluginId} Config.php must return an object");
        }

        return $config;
    }

    /**
     * Get a list of plugins that have a plugin_path set (frontend-friendly).
     *
     * No filesystem validation here.
     */
    public function list(): array
    {
        return Plugin::query()
            ->whereNotNull('plugin_path')
            ->orderByDesc('id')
            ->get()
            ->map(function (Plugin $plugin) {
                return [
                    'id' => $plugin->id,
                    'name' => $plugin->name,
                    'status' => $plugin->status,
                    'plugin_path' => $plugin->plugin_path,
                    'active_version_id' => $plugin->active_version_id,
                    'activated_at' => $plugin->activated_at,
                    'updated_at' => $plugin->updated_at,
                ];
            })
            ->values()
            ->all();
    }
}
