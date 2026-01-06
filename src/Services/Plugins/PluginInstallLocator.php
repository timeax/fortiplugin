<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Services\Plugins;

use RuntimeException;
use Timeax\FortiPlugin\Models\Plugin;

final class PluginInstallLocator
{
    public function installedRoot(Plugin $plugin): string
    {
        $root = trim((string) ($plugin->plugin_path ?? ''));

        if ($root === '') {
            throw new RuntimeException("Plugin #{$plugin->id} has no plugin_path");
        }

        return rtrim($root, "\\/");
    }
}