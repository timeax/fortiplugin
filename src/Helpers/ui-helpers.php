<?php

use Timeax\FortiPlugin\Support\PluginContext;

if (!function_exists('embed')) {
    /*
     * Function to embed a UI component.
     */
    function embed(string $name, array $props = []): array
    {
        $Config = PluginContext::getCurrentConfigClass();
        if (!$Config) {
            throw new RuntimeException("Embed function can only be used within a plugin context");
        }

        $file = $Config::getViteEmbededAsset($name);
        return ['src' => $file, 'props' => $props];
    }

}