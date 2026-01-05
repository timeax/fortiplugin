<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Services;

use RuntimeException;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Models\PluginVersion;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
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
     * Resolve installed root path for a plugin.
     *
     * Strategy:
     * 1) Prefer active version's archive_url (your pipeline uses it like an install root).
     * 2) Fallback to plugin.meta.install_meta.paths.install
     */
    public function installedRoot(int $pluginId): string
    {
        $plugin = $this->getPlugin($pluginId);

        // 1) Prefer active version's archive_url
        $activeVersionId = $plugin->active_version_id ?? 0;
        if ($activeVersionId > 0) {
            $ver = PluginVersion::query()->find($activeVersionId);
            $root = trim((string)($ver?->archive_url ?? ''));

            if ($root !== '') {
                return rtrim($root, "\\/");
            }
        }

        // 2) Fallback to plugin meta (install snapshot)
        $meta = (array)($plugin->meta ?? []);
        $installMeta = (array)($meta['install_meta'] ?? []);
        $paths = (array)($installMeta['paths'] ?? []);
        $root = trim((string)($paths['install'] ?? ''));

        if ($root === '') {
            throw new RuntimeException("Plugin #{$pluginId} has no installed root (active_version.archive_url empty and meta.install_meta.paths.install missing)");
        }

        return rtrim($root, "\\/");
    }

    public function loadConfig(int $pluginId): object
    {
        $root = $this->installedRoot($pluginId);
        $cfgPath = join_paths($root, '.internal', 'Config.php');

        if (!is_file($cfgPath)) {
            throw new RuntimeException(
                "Plugin #{$pluginId} Config.php not found: {$cfgPath}"
            );
        }

        $config = require $cfgPath;

        if (!is_object($config)) {
            throw new RuntimeException(
                "Plugin #{$pluginId} Config.php must return an object"
            );
        }

        return $config;
    }


}
