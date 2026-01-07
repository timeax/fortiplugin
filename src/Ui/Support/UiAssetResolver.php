<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Ui\Support;

use RuntimeException;
use Timeax\FortiPlugin\Autoload\Psr4RegistryStore;

final readonly class UiAssetResolver
{
    public function __construct(
        private Psr4RegistryStore $psr4Store
    ) {}

    /**
     * Resolves the public base path for a plugin (e.g., /vendor/fortiplugin/my-plugin/build).
     *
     * @param string $alias
     * @return string
     * @throws RuntimeException if plugin does not exist in the registry
     */
    public function resolvePublicPath(string $alias): string
    {
        $registry = $this->psr4Store->read();
        $plugins = $registry['plugins'] ?? [];

        if (!isset($plugins[$alias])) {
            throw new RuntimeException("Plugin '{$alias}' not found in registry.");
        }

        $baseTpl = (string)config('fortiplugin.ui.embed.public_base');
        $baseTpl = trim($baseTpl) !== '' ? $baseTpl : '/vendor/fortiplugin/{alias}';

        $path = str_replace(['{alias}', '{slug}', '{plugin}'], $alias, $baseTpl);
        $path = '/' . ltrim($path, '/');
        $path = rtrim($path, '/');

        // Build assets are located in the 'build' subdirectory of the published public folder.
        return $path . '/build';
    }

    /**
     * Resolves a full URL for a plugin asset.
     *
     * @param string $alias
     * @param string $assetPath Relative path to the asset within the build folder (e.g. assets/main.js)
     * @return string
     */
    public function resolveAssetUrl(string $alias, string $assetPath): string
    {
        $publicPath = $this->resolvePublicPath($alias);
        $fullPath = $publicPath . '/' . ltrim($assetPath, '/');

        $explicitOrigin = config('fortiplugin.ui.embed.asset_origin');
        if (is_string($explicitOrigin) && trim($explicitOrigin) !== '') {
            return rtrim($explicitOrigin, '/') . '/' . ltrim($fullPath, '/');
        }

        return asset($fullPath);
    }
}
