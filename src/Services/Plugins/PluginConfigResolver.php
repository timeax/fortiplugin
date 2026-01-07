<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Services\Plugins;

use RuntimeException;
use Timeax\FortiPlugin\Models\Plugin;
use function Illuminate\Filesystem\join_paths;

final class PluginConfigResolver
{
    /**
     * @return class-string
     */
    public function resolveConfigClass(Plugin $plugin, string $installedRoot): string
    {
        $meta = $plugin->meta ?? [];
        if (!is_array($meta)) {
            $meta = (array) $meta;
        }

        $psr4Root = (string) ($meta['psr4_root'] ?? config('fortiplugin.psr4_root', 'Plugins'));
        $psr4Root = rtrim(trim($psr4Root), "\\ \t\n\r\0\x0B");

        $nsSegment = (string) ($meta['placeholder_name'] ?? $plugin->name);

        if ($nsSegment === '') {
            throw new RuntimeException(
                "Plugin #{$plugin->id} has no namespace segment (meta.placeholder_name / name)"
            );
        }

        $fqcn = "$psr4Root\\$nsSegment\\Config";

        if (class_exists($fqcn)) {
            return $fqcn;
        }

        $cfgPath = join_paths($installedRoot, '.internal', 'Config.php');

        if (is_file($cfgPath)) {
            require_once $cfgPath;
        }

        if (!class_exists($fqcn)) {
            throw new RuntimeException(
                "Plugin #{$plugin->id} Config class not found: $fqcn (autoload + fallback include attempted)"
            );
        }

        return $fqcn;
    }
}