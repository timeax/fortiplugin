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
        return ['src' => $file, 'props' => $props, 'exportKey' => $key];
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