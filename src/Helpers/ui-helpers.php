<?php

use Timeax\FortiPlugin\Support\PluginContext;

if (!function_exists('embed')) {
    /*
     * Function to embed a UI component.
     */
    function embed(string $name, array $props = [], string $key = 'default'): array
    {
        $Config = PluginContext::getCurrentConfigClass();
        if (!$Config) {
            throw new RuntimeException("Embed function can only be used within a plugin context");
        }

        $file = $Config::getViteEmbededAsset($name);
        return ['src' => $file, 'props' => $props, 'exportKey' => $key, "asset" => asset($file)];
    }
}


if (!function_exists('asset')) {
    function asset(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return $name;
        }

        // Already absolute: http://, https://, or //example.com
        if (preg_match('~^(https?:)?//~i', $name) === 1) {
            return $name;
        }

        $path = '/' . ltrim($name, '/');

        $assetUrl = $_ENV['ASSET_URL']
            ?? $_SERVER['ASSET_URL']
            ?? getenv('ASSET_URL')
            ?: '';

        $assetUrl = trim((string)$assetUrl);

        if ($assetUrl === '') {
            return $path;
        }

        // Absolute ASSET_URL
        if (preg_match('~^(https?:)?//~i', $assetUrl) === 1) {
            return rtrim($assetUrl, '/') . $path;
        }

        // Relative ASSET_URL like /public
        return '/' . trim($assetUrl, '/') . $path;
    }
}

if (!function_exists('page')) {
    /*
     * Function to reference a plugin override page bundle (SPA lane).
     *
     * Examples:
     * - page('app.tsx')
     * - page('Admin/Dashboard.tsx')
     * - page('pages/app.tsx')   (also allowed)
     * - page('Pages/app.tsx')   (also allowed)
     */
    function page(string $name, array $props = [], string $key = 'default'): array
    {
        $Config = PluginContext::getCurrentConfigClass();
        if (!$Config) {
            throw new RuntimeException("page() can only be used within a plugin context");
        }

        $file = $Config::getVitePageAsset($name);

        return ['src' => $file, 'props' => $props, 'exportKey' => $key];
    }
}