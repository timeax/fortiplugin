<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Models\PluginPlaceholder;

trait CreatesPlugin
{
    protected function createPlaceholder(array $over = []): PluginPlaceholder
    {
        $p = new PluginPlaceholder(array_merge([
            'slug'       => 'ph-' . Str::random(8),
            'name'       => 'Placeholder ' . Str::random(4),
            'unique_key' => Str::uuid()->toString(),
            'owner_ref'  => null,
            'meta'       => null,
        ], $over));
        $p->save();

        return $p;
    }

    protected function createPlugin(array $over = []): Plugin
    {
        $ph = $over['placeholder'] ?? $this->createPlaceholder();
        unset($over['placeholder']);

        $plugin = new Plugin(array_merge([
            'name'                  => 'Plugin ' . Str::random(5),
            'status'                => PluginStatus::active,
            'plugin_placeholder_id' => $ph->getKey(),
            'image'                 => null,
            'config'                => null,
            'meta'                  => null,
            'owner_ref'             => null,
        ], $over));
        $plugin->save();

        return $plugin;
    }
}