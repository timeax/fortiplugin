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
     * Resolves the public base path for a plugin (e.g. /vendor/fortiplugin/my-plugin/build).
     *
     * @param string $slug
     * @return string
     * @throws RuntimeException if plugin does not exist in registry
     */
    public function resolvePublicPath(string $slug): string
    {
        $registry = $this->psr4Store->read();
        $plugins = $registry['plugins'] ?? [];

        if (!isset($plugins[$slug])) {
            throw new RuntimeException("Plugin '{$slug}' not found in registry.");
        }

        $baseTpl = (string)(config('fortiplugin.ui.embed.public_base') ?? '/vendor/fortiplugin/{slug}/build');
        $baseTpl = trim($baseTpl) !== '' ? $baseTpl : '/vendor/fortiplugin/{slug}/build';

        $path = str_replace(['{slug}', '{plugin}'], $slug, $baseTpl);
        $path = '/' . ltrim($path, '/');
        
        return rtrim($path, '/');
    }

    /**
     * Resolves a full URL for a plugin asset.
     *
     * @param string $slug
     * @param string $assetPath Relative path to the asset within the build folder (e.g. assets/main.js)
     * @return string
     */
    public function resolveAssetUrl(string $slug, string $assetPath): string
    {
        $publicPath = $this->resolvePublicPath($slug);
        $fullPath = $publicPath . '/' . ltrim($assetPath, '/');

        $explicitOrigin = config('fortiplugin.ui.embed.asset_origin');
        if (is_string($explicitOrigin) && trim($explicitOrigin) !== '') {
            return rtrim($explicitOrigin, '/') . '/' . ltrim($fullPath, '/');
        }

        return asset($fullPath);
    }
}
